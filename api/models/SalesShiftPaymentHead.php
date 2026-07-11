<?php

namespace app\models;

use app\components\AppHelper;
use app\models\SalesShiftPaymentDenom;
use yii\db\ActiveRecord;
use yii\db\Exception;
use app\models\ShiftLogDetail;
use app\models\ShiftLog;
use app\models\SalesShiftPaymentDetail;
use app\models\PaymentMethod;
use app\models\CashMethod;
use app\models\forms\Logging;
use app\models\forms\Shift;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\httpclient\Client;
use yii\web\HttpException;

/**
 * This is the model class for table "tr_salespaymentgateway".
 *
 * @property string $salesPaymentGatewayNum
 * @property string $salesNum
 */
class SalesShiftPaymentHead extends ActiveRecord
{
    public $detail;
    public $startShiftTime;
    public $cashFraction;
    public $cashFractionStatus;
    public $isEndShift;
    public $currentSalesShiftPaymentHeadID;
    public $onLineStatus = true;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tr_salesshiftpaymenthead';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['shiftLogDetailID', 'actualTotalPaymentNonCash', 'expectedTotalPaymentNonCash', 'actualTotalPaymentCash', 'expectedTotalPaymentCash'], 'required'],
            [['shiftLogDetailID', 'shiftID', 'startShiftTime', 'startShiftDate', 'currentShiftDate', 'branchID', 'description', 'createdBy', 'submittedBy', 'isEndShift', 'currentSalesShiftPaymentHeadID',
                'onLineStatus'], 'safe'],
        ];
    }

    public function getShiftLogDetail()
    {
        return $this->hasOne(ShiftLogDetail::class, ['ID' => 'shiftLogDetailID']);
    }


    public function checkSalesShiftPayment()
    {
        try {
            $paymentMethodParentIDs = PaymentMethod::find()
                ->select('parentID')
                ->where(['>', 'parentID', 0])
                ->andWhere(["=", "flagActive", true])
                ->andWhere(["<>", "paymentMethodTypeID", 7])
                ->groupBy('parentID')
                ->column();

            $paymentMethodModel = PaymentMethod::find()
                ->where(['NOT IN', 'paymentMethodID', $paymentMethodParentIDs])
                ->andWhere(["=", "flagActive", true])
                ->andWhere(["<>", "paymentMethodTypeID", 7]);

            $salesShiftHeadModel = SalesShiftPaymentHead::find()
                ->where(['IS', SalesShiftPaymentHead::tableName() . '.submittedBy', NULL])
                ->innerJoinWith('shiftLogDetail')
                ->orderBy(SalesShiftPaymentHead::tableName() . '.salesShiftpaymentHeadID DESC')
                ->one();

            $result = [];
            if ($salesShiftHeadModel) {
                $result['status'] = false;
                $result['data']['salesShiftPaymentHeadID'] = $salesShiftHeadModel->salesShiftPaymentHeadID;
                $result['data']['shiftID'] = $salesShiftHeadModel->shiftID;
                $result['data']['shiftUsername'] = $salesShiftHeadModel->shiftLogDetail->shiftUsername;
                $result['data']['createdBy'] = $salesShiftHeadModel->createdBy;
                $result['data']['shiftTime'] = $salesShiftHeadModel->shiftLogDetail->shiftTime;
                $result['data']['actualTotalPaymentNonCash'] = (float) $salesShiftHeadModel->actualTotalPaymentNonCash;
                $result['data']['actualTotalPaymentCash'] = (float) $salesShiftHeadModel->actualTotalPaymentCash;
                $result['data']['expectedTotalPaymentNonCash'] = (float) $salesShiftHeadModel->expectedTotalPaymentNonCash;
                $result['data']['expectedTotalPaymentCash'] = (float) $salesShiftHeadModel->expectedTotalPaymentCash;
                $result['data']['description'] = $salesShiftHeadModel->description;

                $salesShiftDetailModel = SalesShiftPaymentDetail::find()
                    ->where(['salesShiftPaymentHeadID' => $salesShiftHeadModel->salesShiftPaymentHeadID])
                    ->all();

                $salesShiftDenomModel = SalesShiftPaymentDenom::find()
                    ->where(['salesShiftPaymentHeadID' => $salesShiftHeadModel->salesShiftPaymentHeadID])
                    ->all();

                $i = 0;
                foreach ($salesShiftDenomModel as $salesShiftDenom) {
                    $result['data']['salesCashFraction'][$i]['denomAmount'] = intval($salesShiftDenom->denomAmount);
                    $result['data']['salesCashFraction'][$i]['denomQty'] = $salesShiftDenom->denomQty;
                    $result['data']['salesCashFraction'][$i]['denomTotal'] = intval($salesShiftDenom->denomTotal);
                    $i++;
                }

                $details = [];
                $i = 0;
                foreach ($paymentMethodModel->all() as $paymentMethod) {
                    $details[$i]['paymentMethodName'] = $paymentMethod->paymentMethodName;
                    $details[$i]['paymentMethodID'] = $paymentMethod->paymentMethodID;
                    $details[$i]['paymentMethodTypeID'] = $paymentMethod->paymentMethodTypeID;
                    $details[$i]['actualPaymentAmount'] = (float) 0;
                    $details[$i]['expectedPaymentAmount'] = (float) 0;
                    foreach ($salesShiftDetailModel as $detail) {
                        if ($paymentMethod->paymentMethodID == $detail->paymentMethodID) {
                            $details[$i]['actualPaymentAmount'] = (float) $detail->actualPaymentAmount;
                            $details[$i]['expectedPaymentAmount'] = (float) $detail->expectedPaymentAmount;
                        }
                    }
                    $i++;
                }
                $result['data']['detail'] = $details;
                $result['status'] = true;
                return $result;
            }

            $currentShiftDate = date('Y-m-d H:i:s');
            $startShiftDate = date('Y-m-d H:i:s', strtotime($this->startShiftTime));
            if ($this->onLineStatus) {
                $apiVersion = 'esb_api';
                $branchID = Setting::getCurrentBranch();
                $apiKey = Setting::getApiKey();
                $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/sales/check-sales-shift-payment';
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl;
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $data = [
                    'startShiftDate' => $startShiftDate,
                    'currentShiftDate' => $currentShiftDate,
                    'branchID' => $branchID,
                    'shiftID' => $this->shiftID
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);

                if ($response->getIsOk()) {
                    $result = $response->getData();
                    if (!isset($result['status']) && !$result['status']) {
                        throw new Exception("Fail to get data sales shift payment");
                    }

                    $details = [];
                    $i = 0;
                    foreach ($paymentMethodModel->all() as $paymentMethod) {
                        $details[$i]['paymentMethodName'] = $paymentMethod['paymentMethodName'];
                        $details[$i]['paymentMethodID'] = $paymentMethod['paymentMethodID'];
                        $details[$i]['paymentMethodTypeID'] = $paymentMethod['paymentMethodTypeID'];
                        $details[$i]['actualPaymentAmount'] = (float) 0;
                        $details[$i]['expectedPaymentAmount'] = (float) 0;
                        foreach ($result['data']['detail'] as $detail) {
                            if (($paymentMethod['paymentMethodTypeID'] == $detail['paymentMethodTypeID']) && ($paymentMethod['paymentMethodID'] == $detail['paymentMethodID'])) {
                                $details[$i]['expectedPaymentAmount'] = (float) $detail['expectedPaymentAmount'];
                            }
                        }
                        $i++;
                    }
                    $result['data']['shiftUsername'] = Yii::$app->user->identity->username;
                    $result['data']['shiftTime'] = $startShiftDate;
                    $result['data']['detail'] = $details;
                    return $result;
                } else {
                    throw new Exception('Failed to fetch data');
                }
            } else {
                $details = [];
                $i = 0;
                foreach ($paymentMethodModel->all() as $paymentMethod) {
                    $details[$i]['paymentMethodName'] = $paymentMethod['paymentMethodName'];
                    $details[$i]['paymentMethodID'] = $paymentMethod['paymentMethodID'];
                    $details[$i]['paymentMethodTypeID'] = $paymentMethod['paymentMethodTypeID'];
                    $details[$i]['actualPaymentAmount'] = (float) 0;
                    $details[$i]['expectedPaymentAmount'] = (float) 0;
                    $i++;
                }
                $result['data']['expectedTotalPaymentCash'] = (float) 0;
                $result['data']['expectedTotalPaymentNonCash'] = (float) 0;
                $result['data']['shiftUsername'] = Yii::$app->user->identity->username;
                $result['data']['shiftTime'] = $startShiftDate;
                $result['data']['detail'] = $details;
                $result['status'] = true;
                return $result;
            }
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new Exception('Failed to fetch data');
        }
    }

    public function saveModel()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $shiftModel = new Shift();
            $shiftModel->scenario = Shift::SCENARIO_END_SHIFT;
            if (!$shiftModel->save()) {
                throw new HttpException(500, Yii::t('app', 'Failed to end shift'));
            }
            $this->shiftLogDetailID = $shiftModel->shiftDetailID;
            $this->createdBy = Yii::$app->user->identity->username;
            if (!$this->save()) {
                throw new Exception("Failed to save payment head");
            }
            $i = 0;
            $details = [];
            foreach ($this->detail as $val) {
                $modelDetail = new SalesShiftPaymentDetail();
                $modelDetail->salesShiftPaymentHeadID = intval($this->primaryKey);
                $modelDetail->paymentMethodID = intval($val['paymentMethodID']);
                $modelDetail->actualPaymentAmount = intval($val['actualPaymentAmount']);
                $modelDetail->expectedPaymentAmount = intval($val['expectedPaymentAmount']);

                if (!$modelDetail->save()) {
                    throw new Exception("Failed to save payment detail");
                }

                $details[$i]['salesShiftDetailID'] = $modelDetail->primaryKey;
                $details[$i]['salesShiftPaymentHeadID'] = $modelDetail->salesShiftPaymentHeadID;
                $details[$i]['paymentMethodID'] = $modelDetail->paymentMethodID;
                $details[$i]['actualPaymentAmount'] = $modelDetail->actualPaymentAmount;
                $details[$i]['expectedPaymentAmount'] = $modelDetail->expectedPaymentAmount;
                $i++;
            }
            $cashFraction = [];
            if ($this->cashFractionStatus) {
                $j = 0;
                foreach ($this->cashFraction as $val) {
                    $modelDenom = new SalesShiftPaymentDenom();
                    $modelDenom->salesShiftPaymentHeadID = $this->primaryKey;
                    $modelDenom->denomAmount = $val['cashDenom']['cashMethodAmount'];
                    $modelDenom->denomQty = $val['numberFraction'];
                    $modelDenom->denomTotal = $val['totalFraction'];
                    if (!$modelDenom->save()) {
                        throw new Exception("Failed to save payment denom");
                    }

                    $modelDenom->localID = $modelDenom->ID;
                    if (!$modelDenom->save()) {
                        throw new Exception("Failed to save local ID");
                    }
                    $cashFraction[$j]['ID'] = $modelDenom->ID;
                    $cashFraction[$j]['localID'] = $modelDenom->localID;
                    $cashFraction[$j]['salesShiftPaymentHeadID'] = $modelDenom->salesShiftPaymentHeadID;
                    $cashFraction[$j]['denomAmount'] = $modelDenom->denomAmount;
                    $cashFraction[$j]['denomQty'] = $modelDenom->denomQty;
                    $cashFraction[$j]['denomTotal'] = $modelDenom->denomTotal;
                    $j++;
                }
            }

            $shiftLogDetail = ShiftLogDetail::findOne($this->shiftLogDetailID);
            $shiftLogDetailIDs = ShiftLogDetail::find()
                ->select('ID')
                ->where(['shiftID' => $shiftLogDetail->shiftID])
                ->column();

            if (!$this->isEndShift) {
                SalesShiftPaymentHead::updateAll([
                    'shiftID' => $this->shiftID
                ], ['salesShiftPaymentHeadID' => $this->primaryKey]);
            }

            $salesShiftPaymentHead = SalesShiftPaymentHead::find()->where(['IN', 'shiftLogDetailID', $shiftLogDetailIDs]);
            $actualEndingCash = 0;
            foreach ($salesShiftPaymentHead->all() as $value) {
                $actualEndingCash += $value['actualTotalPaymentCash'];
            }
            $response = [
                'actualEndingCash' => $actualEndingCash,
                'description' => $this->description,
                'shiftLogDetailID' => $this->shiftLogDetailID,
                'salesShiftPaymentHeadID' => $this->primaryKey,
                'isEndShift' => $this->isEndShift
            ];
            $dataLog = $response;
            $startingCash = ShiftLog::find()
                ->select('shiftInTotal')
                ->where(['shiftID' => $shiftLogDetail->shiftID])
                ->scalar();
            $startingCash = $startingCash ? $startingCash : 0;
            $dataLog['actualEndingCash'] = intval($this->actualTotalPaymentCash) + intval($startingCash);
            Logging::save($this->primaryKey, Logging::ADD_BA_ONLINE, $dataLog);
            $transaction->commit();
            return $response;
        } catch (\Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex->getMessage());
            throw new Exception("Failed to save payment head");
        }
    }

    public function updateSubmittedBy()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {

            $salesShiftHeadModel = SalesShiftPaymentHead::find()->where(['salesShiftPaymentHeadID' => $this->currentSalesShiftPaymentHeadID])->one();
            if (!$salesShiftHeadModel) {
                throw new Exception("Sales shift head not found");
            }
            $salesShiftHeadModel->submittedBy = Yii::$app->user->identity->username;
            if (!$salesShiftHeadModel->submittedBy || $salesShiftHeadModel->submittedBy == "") {
                $salesShiftHeadModel->submittedBy = $salesShiftHeadModel->createdBy;
            }

            if (!$salesShiftHeadModel->save()) {
                throw new Exception("Failed to update submitted by");
            }
            $response = [
                'actualEndingCash' => $salesShiftHeadModel->actualTotalPaymentCash,
                'description' => $salesShiftHeadModel->description,
                'shiftLogDetailID' => $salesShiftHeadModel->shiftLogDetailID,
                'salesShiftPaymentHeadID' => $salesShiftHeadModel->salesShiftPaymentHeadID
            ];
            $transaction->commit();

            if (!$this->isEndShift) {
                $shiftLogModel = ShiftLog::find()->where(['shiftID' => $salesShiftHeadModel->shiftID])->one();
                if (!$shiftLogModel) {
                    throw new Exception("Shift log not found");
                }

                $attributes = [
                    'shiftOutTotal' => intval($response['actualEndingCash']) + intval($shiftLogModel->shiftInTotal),
                    'shiftOutNotes' => $response['description'],
                    'shiftBaOnline' => true
                ];
                $shiftModel = new Shift([
                    'attributes' => $attributes
                ]);
                $shiftModel->scenario = Shift::SCENARIO_SHIFT_OUT;
                if (!$shiftModel->save()) {
                    throw new Exception(json_encode($shiftModel->errors));
                }
                $response = [
                    'shiftID' => $shiftModel->shiftID
                ];
            }

            $dataLog = [
                'salesShiftPaymentHeadID' => $salesShiftHeadModel->salesShiftPaymentHeadID,
                'submittedBy' => $salesShiftHeadModel->submittedBy
            ];
            Logging::save($salesShiftHeadModel->salesShiftPaymentHeadID, Logging::UPDATE_SUBMITTED_BY_BA_ONLINE, $dataLog);

            return $response;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex->getMessage());
            throw new Exception("Failed to update submitted by");
        }
    }
}
