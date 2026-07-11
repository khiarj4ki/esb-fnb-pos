<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\EsoLogEvent;
use app\models\EsoPickupOrder;
use app\models\EsoProcessQueue;
use app\models\Queue;
use app\models\QueueSelfOrder;
use app\models\SalesHead;
use app\models\SalesPayment;
use app\models\SalesPaymentGateway;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\ShiftLogDetail;
use app\models\ShiftLogCash;
use app\models\ShiftLogMode;
use app\models\TableUsage;
use app\models\TempOrder;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\httpclient\Client;

/**
 * @property string $shiftInTotal
 * @property string $shiftOutTotal
 * @property string $shiftOutNotes
 * @property int $shiftID
 * @property int $shiftDetailID
 * 
 * PRIVATE
 * @property ShiftLog $shiftLogModel
 * @property ShiftLogDetail $shiftLogDetailModel
 * @property ShiftLogCash $shiftLogCashModel
 * @property ShiftLogCash $shiftLogModeModel
 */
class Shift extends Model {
    const SCENARIO_SHIFT_IN = 'shift in';
    const SCENARIO_SHIFT_CASH_IN = 'shift cash in';
    const SCENARIO_SHIFT_OUT = 'shift out';
    const SCENARIO_END_SHIFT = 'end shift';

    public $shiftInTotal;
    public $shiftOutTotal;
    public $shiftOutNotes;
    public $shiftID;
    public $shiftDetailID;
    public $shiftLogModel;
    public $shiftLogDetailModel;
    public $shiftLogCashModel;
    public $shiftLogModeModel;
    public $shiftBaOnline;
    public $labelPrinted;
    public $username;

    public $errMsg;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['shiftInTotal'], 'required', 'on' => self::SCENARIO_SHIFT_IN],
            [['shiftOutTotal'], 'required', 'on' => self::SCENARIO_SHIFT_OUT],
            [['shiftInTotal', 'shiftOutTotal'], 'number'],
            [['shiftBaOnline', 'username', 'errMsg'], 'safe'],
            [['shiftOutNotes'], 'string', 'max' => 200],
            [['shiftInTotal'], 'validateShiftIn'],
            [['shiftOutTotal'], 'validateShiftOut'],
        ];
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_SHIFT_IN] = ['shiftInTotal'];
        $scenarios[self::SCENARIO_SHIFT_OUT] = ['shiftOutTotal', 'shiftOutNotes'];
        $scenarios[self::SCENARIO_END_SHIFT] = [];
        $scenarios[self::SCENARIO_SHIFT_CASH_IN] = ['shiftInTotal', 'username'];

        return $scenarios;
    }

    public function validateShiftIn($attribute) {
        $shiftLogModel = ShiftLog::findActive();
        if ($shiftLogModel) {
            $this->addError($attribute, 'Shift has been opened');
        }
    }

    public function validateShiftOut($attribute) {
        $this->shiftLogModel = ShiftLog::findActive();
        if (!$this->shiftLogModel) {
            $this->addError($attribute, 'Cannot find open shift');
        }
    }

    public function save() {
        switch ($this->scenario) {
            case self::SCENARIO_SHIFT_IN:
                return $this->shiftIn();
            case self::SCENARIO_SHIFT_OUT:
                return $this->shiftOut();
            case self::SCENARIO_END_SHIFT:
                return $this->endShift();
            default:
                return false;
        }
    }

    private function shiftIn() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $branchID = Setting::getCurrentBranch();

            $this->shiftLogModel = new ShiftLog();
            $this->shiftLogModel->branchID = $branchID;
            $this->shiftLogModel->shiftInTime = $this->getShiftInTime();
            $this->shiftLogModel->shiftInTotal = $this->shiftInTotal;
            $this->shiftLogModel->shiftInUsername = Yii::$app->user->identity->username;
            if (!$this->shiftLogModel->save()) {
              
                throw new Exception('Failed to save shift log');
            }

            //@Notes: Shift Mode Checking
            $shiftMode = ShiftLogMode::getActiveShiftMode($this->shiftLogModel->shiftID);

            //@Notes: Insert Shift Log Mode
            $this->shiftLogModeModel = new ShiftLogMode();
            $this->shiftLogModeModel->shiftID = $this->shiftLogModel->shiftID;
            $this->shiftLogModeModel->shiftMode = $shiftMode;
            if (!$this->shiftLogModeModel->save()) {
                Yii::error($this->shiftLogModeModel->errors);
                throw new Exception('Failed to save shift log mode');
            }

            if ($shiftMode != "Regular" && $shiftMode == "Cash per Shift") {
                //@Notes: Insert Shift Log Cash
                $this->shiftLogCashModel = new ShiftLogCash();
                $this->shiftLogCashModel->shiftID = $this->shiftLogModel->shiftID;
                $this->shiftLogCashModel->shiftInTime = $this->shiftLogModel->shiftInTime;
                $this->shiftLogCashModel->startingCash = $this->shiftLogModel->shiftInTotal;
                $this->shiftLogCashModel->shiftInUsername = Yii::$app->user->identity->username;
                $this->shiftLogCashModel->shiftNumber = ShiftLogCash::getShiftNumber($this->shiftLogModel->shiftID);
                if (!$this->shiftLogCashModel->save()) {
                    Yii::error($this->shiftLogCashModel->errors);
                    throw new Exception('Failed to save shift log cash');
                }
            }

            Logging::save(strval($this->shiftLogModel->shiftID),
                Logging::SHIFT_IN, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('shiftInTotal', $ex->getMessage());
            return false;
        }
    }

    private function shiftOut() {
        if (!$this->validate()) {
            return false;
        }

        $salesModel = SalesHead::find()
            ->andWhere(['IS', 'salesDateOut', null])
            ->all();
        if ($salesModel) {
            $this->addError('shiftOutTotal',
                'Not all transactions are completed');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$this->shiftBaOnline) {
                if ($this->validateNeedEndShift($this->shiftLogModel) > 0) {
                    $this->endShift();
                }
            }

            $totalGrandTotal = SalesPayment::getTotalGrandTotal($this->shiftLogModel->shiftID,
                    $this->shiftLogModel);
            $totalNonCash = SalesPayment::getTotalNonCash($this->shiftLogModel->shiftID,
                    $this->shiftLogModel);
            $totalCash = $totalGrandTotal - $totalNonCash;

            $this->shiftLogModel->shiftOutTime = new Expression('NOW()');
            $this->shiftLogModel->systemCashReceivedTotal = $totalCash;
            $this->shiftLogModel->shiftOutTotal = $this->shiftOutTotal;
            $this->shiftLogModel->shiftOutNotes = $this->shiftOutNotes;
            $this->shiftLogModel->shiftOutUsername = Yii::$app->user->identity->username;
            if (!$this->shiftLogModel->save()) {
                
                throw new Exception('Failed to update shift log');
            }
            $this->shiftID = $this->shiftLogModel->shiftID;

            //@Notes: Shift Mode Checking
            $shiftMode = ShiftLogMode::getActiveShiftMode($this->shiftID);
            if ($shiftMode != "Regular" && $shiftMode == "Cash per Shift") {
                $this->shiftLogCashModel = ShiftLogCash::findByShift($this->shiftLogModel->shiftID);

                if(isset($this->shiftLogCashModel)){
                    $totalGrandTotalShift = SalesPayment::getTotalGrandTotalShift($this->shiftLogCashModel->ID,
                    $this->shiftLogModel);
                    $totalNonCashShift = SalesPayment::getTotalNonCashShift($this->shiftLogCashModel->ID,
                            $this->shiftLogModel);
                    $totalCashShift = $totalGrandTotalShift - $totalNonCashShift;

                    //@Notes: Insert Shift Log Cash
                    $this->shiftLogCashModel->shiftOutTime = $this->shiftLogModel->shiftOutTime;
                    $this->shiftLogCashModel->systemCashReceivedTotal = $totalCashShift;
                    $this->shiftLogCashModel->endingCash = $this->shiftLogModel->shiftOutTotal;
                    $this->shiftLogCashModel->shiftOutUsername = Yii::$app->user->identity->username;
                    $this->shiftLogCashModel->closingNotes = $this->shiftOutNotes;
                    if (!$this->shiftLogCashModel->save()) {
                        Yii::error($this->shiftLogCashModel->errors);
                        throw new Exception('Failed to save shift log cash');
                    }
                }
            }

            $this->labelPrinted = [];
            $labelSetting = Setting::find()
                ->select('key2')
                ->andWhere(['key1' => 'Local Setting'])
                ->andWhere(['value1' => '1'])
                ->all();

            foreach ($labelSetting as $label) {
                $this->labelPrinted[] = $label['key2'];
            }
            
            $pickupList = EsoPickupOrder::find()->all();
            $salesArray = [];
            if($pickupList) {
                foreach($pickupList as $sales) {
                    $salesArray[] = $sales->salesNum;
                }
                Logging::save('-', Logging::DELETE_PICKUP_LIST, $salesArray);
            }

            // @Notes: Clean up TableUsage table
            Yii::$app->db->createCommand()->delete(TableUsage::tableName())->execute();
            
            // @Notes: Clean up TempOrder table
            Yii::$app->db->createCommand()->truncateTable(TempOrder::tableName())->execute();

            // @Notes: Clean up Queue table
            Yii::$app->db->createCommand()->truncateTable(Queue::tableName())->execute();

            // @Notes: Clean up Self Order Queue table
            Yii::$app->db->createCommand()->truncateTable(QueueSelfOrder::tableName())->execute();

            // @Notes: Clean up Eso Pickup Order table
            Yii::$app->db->createCommand()->truncateTable(EsoPickupOrder::tableName())->execute();

            // @Notes: Clean up Eso Process Queue table
            Yii::$app->db->createCommand()->truncateTable(EsoProcessQueue::tableName())->execute();

            // @Notes: Clean up Sales Payment Gateway KIOSK table
            Yii::$app->db->createCommand()->truncateTable(SalesPaymentGateway::tableName())->execute();

            //@Notes: Clean up r_eso_log already synced to core
            Yii::$app->db->createCommand()
            ->delete(EsoLogEvent::tableName(), ['and', 'isSuccess = 1', 'syncDate IS NOT NULL'])
            ->execute();

            Logging::save(strval($this->shiftLogModel->shiftID),
                Logging::SHIFT_OUT, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('shiftOutTotal', $ex->getMessage());
            return false;
        }
    }

    private function endShift() {
        $this->shiftLogModel = ShiftLog::findActive();
        if (!$this->shiftLogModel) {
            Yii::error('Shift not found');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->shiftLogDetailModel = new ShiftLogDetail();
            $this->shiftLogDetailModel->shiftID = $this->shiftLogModel->shiftID;
            $this->shiftLogDetailModel->shiftTime = new Expression('NOW()');
            $this->shiftLogDetailModel->shiftUsername = Yii::$app->user->identity->username;
            if (!$this->shiftLogDetailModel->save()) {
               
                throw new Exception('Failed to save shift log detail');
            }
            $this->shiftDetailID = $this->shiftLogDetailModel->ID;

            Logging::save(strval($this->shiftLogDetailModel->shiftID),
                Logging::END_SHIFT, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    private function validateNeedEndShift($shiftLogModel) {
        $shiftTimes = ShiftLogDetail::find()
            ->select('shiftTime')
            ->andWhere(['shiftID' => $shiftLogModel->shiftID])
            ->column();
        array_unshift($shiftTimes, $shiftLogModel->shiftInTime);
        $startDate = $shiftTimes[0];
        $endDate = null;
        if (count($shiftTimes) > 1) {
            $endDate = $shiftTimes[count($shiftTimes) - 1];
        }

        return SalesHead::findFinished()
                ->andWhere(['>', 'salesDateOut', $startDate])
                ->andFilterWhere(['<', 'salesDateOut', $endDate])
                ->count();
    }

    private function getShiftInTime() {
        $shiftInTime = new Expression('NOW()');

        $shiftLogModel = ShiftLog::find()
                ->select([
                    'shiftInTime' => new Expression("MAX(tr_shiftlog.shiftInTime)"),
                    'shiftOutTime' => new Expression("MAX(tr_shiftlog.shiftOutTime)")
                ])
                ->where(['=', "DATE(shiftInTime)", date('Y-m-d')])
                ->one();

        $salesHeadModel = SalesHead::find()
                ->select([
                    'salesDateIn' => new Expression("MIN(DATE_SUB(tr_saleshead.salesDateIn, INTERVAL 1 SECOND))"),
                ])
                ->where(['=', "salesDate", date('Y-m-d')])
                ->one();

        // @notes: Cek jika ada sales tanpa shift
        if (isset($shiftLogModel->shiftInTime)) {
            $salesWithoutShift = SalesHead::find()
                ->select([
                    'salesDateIn' => new Expression("MIN(DATE_SUB(tr_saleshead.salesDateIn, INTERVAL 1 SECOND))"),
                ])
                ->where(['>', "salesDateIn", $shiftLogModel->shiftOutTime])
                ->one();

            if (isset($salesWithoutShift->salesDateIn)) {
                $shiftInTime = $salesWithoutShift->salesDateIn;
            }
        } else {
            if (isset($salesHeadModel->salesDateIn)) {
                $shiftInTime = $salesHeadModel->salesDateIn;
            }
        }

        return $shiftInTime;
    }

    public static function getServerClock() {
        try{
        
        // @refactor http_helper
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $httpService = new HttpHelperService();
        $url = $apiUrl . 'esb_apiv11/main/check-date';
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $datas = [
            'branchCode' => AppHelper::getBranchCode()
        ];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $datas, $options);

        $response = $result->getData();
      
        if ($result->getIsOk()) {
            $responseCode = $response['code'];
            if ($responseCode == 200) {
                return $response['data']['serverClock'];
                Yii::error ([$response['data']['serverClock']]);
            } else {
                return null;
            }
        } else {
            return null;
        }
      } catch (\Exception $ex) {
        Yii::error($ex->getMessage());
        if (strpos($ex->getMessage(), 'Curl error: #28 - Operation timed out') !== false) {
            return null;
      } 
     }
    }

}
