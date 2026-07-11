<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BrandSetting;
use app\models\CustomerTransaction;
use app\models\CustomNumber;
use app\models\EsoProcessQueue;
use app\models\EsoPickupOrder;
use app\models\MapBranchVisitPurpose;
use app\models\MenuExtra;
use app\models\MenuPackage;
use app\models\MenuTemplateDetail;
use app\models\MenuTemplateHead;
use app\models\PaymentMethod;
use app\models\QueueSelfOrder;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMenuRelated;
use app\models\SalesPayment;
use app\models\SalesPlatformFee;
use app\models\Setting;
use app\models\ShiftLog;
use Exception;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\httpclient\Client;

class EsbOrder extends Model
{
    // Sales Head
    public $salesNum;
    public $branchID;
    public $paxTotal;
    public $subtotal;
    public $discountTotal;
    public $menuDiscountTotal;
    public $promotionDiscount;
    public $otherTaxTotal;
    public $vatTotal;
    public $otherVatTotal;
    public $deliveryCost;
    public $orderFee;
    public $grandTotal;
    public $roundingTotal;
    public $paymentTotal;
    public $promotionID;
    public $promotionCode;
    public $promotionVoucherCode;
    public $flagInclusive;
    public $visitPurposeID;
    public $transactionModeID;
    public $deliveryTime;
    public $transactionModeName;
    public $fullName;
    public $email;
    public $phoneNumber;
    public $orderID;
    public $additionalInfo;
    public $statusID;
    public $memberID;
    public $flagExternalMemberID;
    public $flagExternalCardID;
    public $flagExternalMemberPhone;
    public $externalMemberName;
    public $flagExternalAPI;
    public $externalMembershipTypeID;
    public $tableID;
    public $remarks;
    public $voucherDiscountTotal;
    // Sales Menu
    public $salesMenu;
    // Sales Payment
    public $salesPayment;
    // Sales Info
    public $salesInfo;
    // Platform Fee
    public $platformFee;
    public $errMsg;
    public $errorInformation;


    public static function loadOrder($orderID)
    {
        try {
            $selfOrderApi = Setting::getEsoQsApiUrl();
            $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
            $companyCode = $branch->companyCode;
            $authKey = Setting::getApiKey();
            $client = new Client(['baseUrl' => $selfOrderApi]);
            $response = $client->createRequest()
                ->setUrl("pos-order")
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-branch' => $branch->branchCode,
                    'data-company' => $companyCode
                ])
                ->setData([
                    "orderID" => $orderID,
                    "branchID" => Setting::getCurrentBranch(),
                    "companyCode" => $companyCode
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            if ($response->getIsOk()) {
                return json_decode($response->content, true);
            } else {
                $responseData = $response->getData();
                $statusCode = isset($response->statusCode) ? $response->statusCode : 500;
                $errorMessage = $responseData && $responseData['message'] ? $responseData['message'] : "Server Unreachable";
                throw new Exception("$statusCode - $errorMessage");
            }
        } catch (\Exception $ex) {
            $attributes = [
                'orderID' => $orderID,
                'message' => "- Error when load data order: {$orderID}, Error: {$ex->getMessage()}"
            ];
            Logging::save($orderID, Logging::ESO_PROCESS_QUEUE, $attributes);
            return null;
        }
    }

    public function loadOrderId($orderID)
    {
        $result = self::loadOrder($orderID);
        if ($result) {
            $this->setAttributes($result);
            $this->orderID = $orderID;
        } else {
            $this->addError("orderID", "Failed to connect to EZO");
            return false;
        }
    }

    public static function loadCheckOrder($orderID)
    {
        $selfOrderApi = Setting::getEsoQsApiUrl();

        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;

        $authKey = Setting::getApiKey();
        $client = new Client(['baseUrl' => $selfOrderApi]);
        $response = $client->createRequest()
            ->setUrl("pos-view-order")
            ->setMethod('POST')
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                'data-branch' => $branch->branchCode,
                'data-company' => $companyCode
            ])
            ->setData([
                "orderID" => $orderID,
                "branchID" => Setting::getCurrentBranch(),
                "companyCode" => $companyCode
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if ($response->getIsOk()) {
            if ($response->statusCode == "200") {
                return json_decode($response->content, true);
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['branchID', 'orderID', 'salesMenu', 'salesPayment'], 'required'],
            [[
                'branchID', 'paxTotal', 'subtotal', 'discountTotal', 'menuDiscountTotal', 'promotionDiscount', 'otherTaxTotal', 'vatTotal', 'otherVatTotal', 'deliveryCost',
                'orderFee', 'grandTotal', 'roundingTotal', 'paymentTotal', 'promotionID', 'promotionCode', 'promotionVoucherCode', 'flagInclusive', 'visitPurposeID',
                'transactionModeID', 'transactionModeName', 'fullName', 'orderID', 'additionalInfo', 'statusID', 'memberID', 'flagExternalMemberID', 'flagExternalCardID', 'flagExternalMemberPhone',
                'externalMemberName', 'flagExternalAPI', 'externalMembershipTypeID', 'salesMenu', 'salesPayment', 'salesInfo', 'tableID', 'email', 'phoneNumber', 'deliveryTime', 'remarks',
                'voucherDiscountTotal', 'salesNum', 'platformFee'], 'safe'],
            [['branchID'], 'validateBranch'],
            [['orderID'], 'validateOrderPayment'],
            [['salesMenu'], 'validateSalesMenu'],
            [['salesPayment'], 'validateSalesPayment']
        ];
    }

    public function validateOrderPayment($attribute)
    {

        $salesPayment = SalesPayment::findOne([
            'selfOrderID' => $this->orderID
        ]);
        if ($salesPayment) {
            $salesHead = SalesHead::findOne(['salesNum' => $salesPayment->salesNum]);
            $errMsg = Yii::t(
                'app',
                'Order already created before. Payment has been settled online using EZO. Your queue number is: ' . $salesHead->queueNum,
                ['orderID' => $this->orderID]
            );
            $this->errorInformation = AppHelper::errorLogInformation();
            $this->addError($attribute, $errMsg);
        }
    }

    public function validateBranch($attribute)
    {
        $branchID = Setting::getCurrentBranch();
        if (!isset($this->branchID) || (isset($this->branchID) && $this->branchID != $branchID)) {
            $errMsg = Yii::t(
                'app',
                'Invalid branch. Your branch ID is: {branchID}',
                ['branchID' => $this->branchID]
            );
            $this->errorInformation = AppHelper::errorLogInformation();
            $this->addError($attribute, $errMsg);
        }
    }

    public function validateSalesMenu($attribute)
    {
        if (!isset($this->salesMenu) || (isset($this->salesMenu) && count($this->salesMenu) == 0)) {
            $errMsg = Yii::t(
                'app',
                'Sales Menu is empty!',
                ['orderID' => $this->orderID]
            );
            $this->addError($attribute, $errMsg);
            $this->errorInformation = AppHelper::errorLogInformation();
        } else {
            for ($i = 0; $i < count($this->salesMenu); $i++) {
                $salesMenu = $this->salesMenu[$i];
                $menuID = $salesMenu['menuID'];


                $query = (new Query())
                    ->select([
                        'c.menuID'
                    ])
                    ->from(MapBranchVisitPurpose::tableName() . ' a')
                    ->innerJoin(
                        MenuTemplateHead::tableName() . ' b',
                        'b.menuTemplateID = a.menuTemplateID'
                    )
                    ->innerJoin(
                        MenuTemplateDetail::tableName() . ' c',
                        'c.menuTemplateID = b.menuTemplateID'
                    )
                    ->andWhere([
                        'a.branchID' => Setting::getCurrentBranch(),
                        'a.visitPurposeID' => $this->visitPurposeID,
                        'c.menuID' => $menuID,
                        'c.flagActive' => 1
                    ])
                    ->one();

                if (!$query) {
                    $this->addError($attribute, Yii::t(
                        'app',
                        $menuID . "- Some menu does not exist."
                    ));
                    $this->errorInformation = AppHelper::errorLogInformation();
                    break;
                }

                for ($j = 0; $j < count($this->salesMenu[$i]['packages']); $j++) {
                    $salesPackage = $this->salesMenu[$i]['packages'][$j];
                    $packageMenuID = $salesPackage['menuID'];
                    $packageGroupID = isset($salesPackage['menuGroupID']) ? $salesPackage['menuGroupID'] : null;

                    $queryPackage = (new Query())
                        ->select([
                            'a.menuID'
                        ])
                        ->from(MenuPackage::tableName() . ' a')
                        ->where([
                            'a.menuID' => $packageMenuID,
                            'a.flagActive' => 1
                        ])
                        ->andFilterWhere(['a.menuGroupID' => $packageGroupID])
                        ->one();

                    if (!$queryPackage) {
                        $this->addError($attribute, Yii::t(
                            'app',
                            $menuID .  "Some menu does not exist."
                        ));
                        $this->errorInformation = AppHelper::errorLogInformation();
                        break;
                    }
                }

                for ($k = 0; $k < count($this->salesMenu[$i]['extras']); $k++) {
                    $salesExtra = $this->salesMenu[$i]['extras'][$k];
                    $menuExtraID = $salesExtra['menuExtraID'];

                    $queryExtra = (new Query())
                        ->select([
                            'a.menuExtraID'
                        ])
                        ->from(MenuExtra::tableName() . ' a')
                        ->andWhere([
                            'a.menuExtraID' => $menuExtraID,
                            'a.flagActive' => 1
                        ])
                        ->one();

                    if (!$queryExtra) {
                        $this->addError($attribute, Yii::t(
                            'app',
                            $menuID . "Some menu does not exist."
                        ));
                        $this->errorInformation = AppHelper::errorLogInformation();
                        break;
                    }
                }
            }
        }
    }

    public function validateSalesPayment($attribute)
    {
        if (!isset($this->salesPayment) || (isset($this->salesPayment) && count($this->salesPayment) == 0)) {
            $errMsg = Yii::t(
                'app',
                'Sales Payment is empty!',
                ['orderID' => $this->orderID]
            );
            $this->addError($attribute, $errMsg);
            $this->errorInformation = AppHelper::errorLogInformation();

        } else {
            foreach ($this->salesPayment as $salesPayment) {
                $paymentMethod = PaymentMethod::findOne($salesPayment['paymentMethodID']);
                if (!$paymentMethod) {
                    $errMsg = Yii::t(
                        'app',
                        $salesPayment['paymentMethodID'] . '- Some payment method is not found!',
                        ['orderID' => $this->orderID]
                    );
                    $this->addError($attribute, $errMsg);
                    $this->errorInformation = AppHelper::errorLogInformation();
                    break;
                }
            }
        }
    }

    public function save()
    {
        $transaction = Yii::$app->db->beginTransaction('Serializable');
        try {
            if (!$this->validate()) {
                $transaction->rollBack();
                $errors = $this->getErrors();
                $errKeys = array_keys($errors);
                $arrErr = [];
                foreach ($errKeys as $key) {
                    foreach ($errors[$key] as $errVal) {
                        $arrErr[] = $errVal;
                    }
                }
                $this->notifSelfOrderError(implode(', ', $arrErr));
                if (isset($this->orderID) && $this->orderID != null) {
                    // EsoProcessQueue::deleteAll(['orderID' => $this->orderID]);
                    $jsonErr = json_encode($arrErr);
                    $attributes = [
                        'orderID' => $this->orderID,
                        'message' => "- Error when validate data order: {$this->orderID}, Error: {$jsonErr}"
                    ];
                    Logging::save($this->orderID, Logging::ESO_PROCESS_QUEUE, $attributes);

                    $this->notifLogEso(implode(', ', $arrErr));

                }
                return false;
            }

            if (!$salesHead = $this->saveSales()) {
                throw new Exception($this->errMsg, 500);
            }

            if (!$billNum = $this->saveSalesPayment($salesHead->salesNum)) {
                throw new Exception($this->errMsg, 500);
            }

            if (!$this->saveSalesInfo($salesHead->salesNum)) {
                throw new Exception($this->errMsg, 500);
            }

            if (!$this->saveCustomerTransaction($salesHead->salesNum)) {
                throw new Exception($this->errMsg, 500);
            }

            if (!$this->savePlatformFee($salesHead->salesNum)) {
                throw new Exception($this->errMsg, 500);
            }

            if($this->transactionModeID == 2){
                if (!$this->saveEsoPickupOrder($salesHead->salesNum)) {
                    throw new Exception($this->errMsg, 500);
                }
            }

            $transaction->commit();

            $apiUrl = Setting::getEsoQsApiUrl();
            if ($apiUrl) {
                $this->addQueue($salesHead->salesNum, QueueSelfOrder::TYPE_SALES_FINISH);
            }

            return [
                'salesNum' => $salesHead->salesNum,
                'billNum' => $billNum,
                'queueNum' => intval($salesHead->queueNum),
                'statusID' => $salesHead->statusID,
                'additionalInfo' => $salesHead->additionalInfo,
                'orderInProgress' => []
            ];

        } catch (Exception $ex) {
            $this->notifSelfOrderError($ex->getMessage());
            $transaction->rollback();
            $attributes = [
                'orderID' => $this->orderID,
                'message' => "- Error when save data order: {$this->orderID}, Error: {$ex->getMessage()}"
            ];
            Logging::save($this->orderID, Logging::ESO_PROCESS_QUEUE, $attributes);

            $this->notifLogEso($ex->getMessage());
            
            return false;
        }
    }

    public function notifLogEso($errMsg) {

        $errAttr = AppHelper::convertErrorMessage($errMsg);
        $attributes = [
            'orderID' => $this->orderID,
            'message' => json_decode($errAttr),
            'information' => $this->errorInformation
        ];
        LoggingEso::save($this->orderID, LoggingEso::ESO_PROCESS_QUEUE, json_encode($attributes));
    }

    public function addQueue($salesNum, $type)
    {
        $currentQueueCount = QueueSelfOrder::find()->count();
        $queueModel = new QueueSelfOrder();
        $queueModel->salesNum = $salesNum;
        $queueModel->orderID = $this->orderID;
        if ($type == QueueSelfOrder::TYPE_SALES_FINISH) {
            $queueModel->type = QueueSelfOrder::TYPE_SALES_FINISH;
        } else {
            $queueModel->type = QueueSelfOrder::TYPE_SALES_VOID;
        }
        if (!$queueModel->save()) {
            Yii::warning($queueModel->errors());
        }

        $queueLogFileLocation = Yii::$app->basePath . '/' . Yii::$app->params['selfOrderQueueLogFile'];
        $fileValue = file_exists($queueLogFileLocation) ? file_get_contents($queueLogFileLocation) : 0;
        $lastQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);
        if ($currentQueueCount == 0 || (microtime(true) - $lastQueueRunTime > 60)) {
            $yiiLocation = Yii::$app->basePath . '/yii';
            $runQueueAction = 'self-order-queue/run';

            if (substr(php_uname(), 0, 3) == "Win") {
                pclose(popen("start /B php $yiiLocation $runQueueAction ", "r"));
            } else {
                shell_exec("php $yiiLocation $runQueueAction > /dev/null 2>/dev/null &");
            }
        }
    }

    private function saveCustomerTransaction($salesNum)
    {
        try {
            if ($salesNum && $this->fullName && $this->email && $this->phoneNumber) {
                $customerTransaction = new CustomerTransaction();
                $customerTransaction->salesNum = $salesNum;
                $customerTransaction->fullName = $this->fullName;
                $customerTransaction->email = $this->email;
                $customerTransaction->phoneNumber = $this->phoneNumber;

                if (!$customerTransaction->save()) {
                    throw new Exception(json_encode($customerTransaction->getErrors()), 500);
                }
            }
            return true;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function savePlatformFee($salesNum)
    {
        try {
            if ($this->platformFee) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($salesNum, $this->platformFee)) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }
            
            return true;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveSales()
    {
        try {
            // save sales head
            $salesHead = $this->setSalesHead();
            $salesHeadModel = new SalesHead([
                'attributes' => $salesHead
            ]);
            $salesHeadModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;

            if (!$salesHeadModel->save()) {
                throw new Exception(json_encode($salesHeadModel->getErrors()), 500);
            }

            if (!$this->saveSalesMenu($salesHeadModel->salesNum)) {
                throw new Exception($this->errMsg, 500);
            }
            $this->saveLog($salesHead);
            return $salesHeadModel;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveSalesInfo($salesNum)
    {
        if (!$this->salesInfo) {
            return true;
        }

        try {
            foreach ($this->salesInfo as $salesInfo) {
                if (isset($salesInfo['key'])) {
                    $salesInfoModel = new SalesInfo();
                    $salesInfoModel->salesNum = $salesNum;
                    $salesInfoModel->key = isset($salesInfo['key']) ? $salesInfo['key'] : '';
                    $salesInfoModel->value = isset($salesInfo['value']) ? $salesInfo['value'] : '';
                    if (!$salesInfoModel->save()) {
                        throw new Exception(json_encode($salesInfoModel->getErrors()), 500);
                    }
                }
            }
            return true;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveSalesPayment($salesNum)
    {
        try {
            $paymentData = $this->salesPayment;
            foreach ($paymentData as $salesPayment) {
                $salesPayment['salesNum'] = $salesNum;
                $paymentModel = new SalesPayment([
                    'attributes' => $salesPayment
                ]);
                if (!$paymentModel->save()) {
                    throw new Exception(json_encode($paymentModel->getErrors()), 500);
                }
            }

            $salesHeadModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
            if (!$salesHeadModel) {
                throw new Exception('Sales not found!', 500);
            }
            $salesHeadModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;

            $salesHeadModel->billNum = AppHelper::createNewTransactionNumber(
                'Bill',
                $salesHeadModel->salesDate,
                $salesHeadModel->branchID
            );
            $salesHeadModel->salesDateOut = new Expression('NOW()');
            if (!$salesHeadModel->save()) {
                throw new Exception(json_encode($salesHeadModel->getErrors()), 500);
            }
            $customNumberSetting = BrandSetting::getBrandPosSetting('Custom Number');
            if(isset($customNumberSetting['Custom Number']) && $customNumberSetting['Custom Number'] == 1) {
                CustomNumber::saveCustomNumber($salesHeadModel);
            }
            $this->saveLogPayment($paymentData, $salesHeadModel);
            return $salesHeadModel->billNum;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveSalesMenu($salesNum)
    {
        try {
            $errorStockMsg = '';
            $batchID = SalesMenu::getNewBatchID($salesNum);
            $shouldRunValidationStock = !in_array($this->transactionModeID, SalesHead::EXTERNAL_TRANSCATION_MODE_ID);
            foreach ($this->salesMenu as $key => $salesMenu) {
                $salesMenu['salesNum'] = $salesNum;
                $salesMenu['batchID'] = $batchID;
                $salesMenuModel = new SalesMenu([
                    'attributes' => $salesMenu
                ]);

                $salesMenu['transactionModeID'] = $this->transactionModeID;

                if ($shouldRunValidationStock) {
                    $validateStockModel = new ValidateStock([
                        'attributes' => $salesMenu
                    ]);
                    $menuName = $validateStockModel->validateStock();
                    if ($menuName) {
                        if (!$errorStockMsg) {
                            $errorStockMsg .= $menuName;
                        } else {
                            $errorStockMsg .= ", " . $menuName;
                        }
                    }
                }

                if (!$salesMenuModel->save()) {
                    throw new Exception(json_encode($salesMenuModel->getErrors()), 500);
                }
                $this->salesMenu[$key]['ID'] = $salesMenuModel->ID;

                // package is exists
                if (isset($salesMenu['packages']) && count($salesMenu['packages']) > 0) {
                    foreach ($salesMenu['packages'] as $packageKey => $package) {
                        $package['salesNum'] = $salesMenuModel->salesNum;
                        $package['batchID'] = $batchID;
                        $package['menuRefID'] = $salesMenuModel->localID;
                        $packageModel = new SalesMenu([
                            'attributes' => $package
                        ]);

                        if ($shouldRunValidationStock) {
                            $validatePckStockModel = new ValidateStock();
                            $validatePckStockModel->salesNum = $salesMenuModel->salesNum;
                            $validatePckStockModel->menuID = $package['menuID'];
                            $validatePckStockModel->qty = (float)$package['qty'] * (float)$salesMenu['qty'];
                            
                            $menuName = $validatePckStockModel->validateStock();
                            if ($menuName) {
                                if (!$errorStockMsg) {
                                    $errorStockMsg .= $menuName;
                                } else {
                                    $errorStockMsg .= ", " . $menuName;
                                }
                            }
                        }

                        if (!$packageModel->save()) {
                            throw new Exception(json_encode($packageModel->getErrors()), 500);
                        }
                        $this->salesMenu[$key]['packages'][$packageKey]['ID'] = $packageModel->ID;
                    }
                    SalesMenu::updateAll([
                        'menuRefID' => $salesMenuModel->localID
                    ], ['localID' => $salesMenuModel->localID]);
                }

                // extra is exists
                if (isset($salesMenu['extras']) && count($salesMenu['extras']) > 0) {
                    foreach ($salesMenu['extras'] as $extraKey => $extra) {
                        $extra['salesNum'] = $salesMenuModel->salesNum;
                        $extra['menuDetailID'] = $salesMenuModel->localID;
                        $extraModel = new SalesMenuExtra([
                            'attributes' => $extra
                        ]);
                        if (!$extraModel->save()) {
                            throw new Exception(json_encode($extraModel->getErrors()), 500);
                        }
                        $this->salesMenu[$key]['extras'][$extraKey]['ID'] = $extraModel->ID;
                    }
                }

                // save menu related
                if(isset($salesMenu['mainMenuID']) && $salesMenu['mainMenuID'] !== null) {
                    SalesMenuRelated::saveSalesMenuRelated($salesMenuModel->salesNum, $salesMenuModel->localID, $salesMenu, $this->salesMenu);
                }

                if ($errorStockMsg) {
                    $errMessage = 'Insufficient qty for: ' . $errorStockMsg;
                    $this->errMsg = $errMessage;
                    throw new Exception(json_encode($errMessage));
                }
            }
            return true;
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveLog($salesHead) {
        try {
            $modelSalesHead = SalesHead::findPromotionSalesHead($salesHead['salesNum']);
            
            $attributes = [
                'tableID' => $salesHead['tableID'],
                'salesNum' => $salesHead['salesNum'],
                'additionalInfo' => $salesHead['additionalInfo'],
                'batchID' => 1,
                'promotionID' => $salesHead['promotionID'],
                'salesHead' => $modelSalesHead,
                'salesMenu' => $this->salesMenu,
                'externalMemberName' => $salesHead['externalMemberName'],
                'externalMembershipTypeID' => $salesHead['externalMembershipTypeID'],
            ];
            Logging::save($salesHead['salesNum'], Logging::SAVE_ORDER_ESO_QS, $attributes);
            return true;
        }
        catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveLogPayment($salesPayment, $salesHead) {
        try {
            $paymentModel = new SavePayment();
            $paymentModel->tableID = 0;
            $paymentModel->salesNum = $salesHead['salesNum'];
            $paymentModel->salesPayment = $salesPayment;
            $paymentModel->salesModel = $salesHead;
            Logging::save($paymentModel->salesNum, Logging::SAVE_PAYMENT_ESO, $paymentModel->getAttributes());
            return true;
        }
        catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    private function saveLogFinishOrder($salesNum, $username) {
        try {
            $attributes = [
                'salesNum' => $salesNum,
                'orderID' => $this->orderID,
                'finishedBy' => $username
            ];
            Logging::save($salesNum, Logging::FINISH_PICKUP_ORDER, $attributes);
            return true;
        }
        catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return $this->errMsg;
        }
    }

    private function setSalesHead()
    {
        $salesDate = ShiftLog::getShiftInDate();
        $salesNum = AppHelper::createNewTransactionNumber('Sales', $salesDate, $this->branchID);
        $queueNum = SalesHead::getQueueNumber($salesNum, $salesDate, $this->branchID);

        return [
            'salesDate' => $salesDate,
            'salesNum' => $salesNum,
            'branchID' => $this->branchID,
            'paxTotal' => $this->paxTotal,
            'subtotal' => $this->subtotal,
            'discountTotal' => $this->discountTotal,
            'menuDiscountTotal' => $this->menuDiscountTotal,
            'promotionDiscount' => $this->promotionDiscount,
            'otherTaxTotal' => $this->otherTaxTotal,
            'vatTotal' => $this->vatTotal,
            'otherVatTotal' => $this->otherVatTotal,
            'deliveryCost' => $this->deliveryCost,
            'orderFee' => $this->orderFee,
            'grandTotal' => $this->grandTotal,
            'roundingTotal' => $this->roundingTotal,
            'paymentTotal' => $this->paymentTotal,
            'promotionID' => $this->promotionID,
            'promotionCode' => $this->promotionCode,
            'promotionVoucherCode' => $this->promotionVoucherCode,
            'visitPurposeID' => $this->visitPurposeID,
            'transactionModeID' => $this->transactionModeID,
            'deliveryTime' => $this->deliveryTime,
            'transactionModeName' => $this->transactionModeName,
            'additionalInfo' => $this->additionalInfo,
            'statusID' => $this->statusID,
            'memberID' => $this->memberID,
            'flagExternalMemberID' => $this->flagExternalMemberID,
            'flagExternalCardID' => $this->flagExternalCardID,
            'flagExternalMemberPhone' => $this->flagExternalMemberPhone,
            'externalMemberName' => $this->externalMemberName,
            'flagExternalAPI' => $this->flagExternalAPI,
            'externalMembershipTypeID' => $this->externalMembershipTypeID,
            'tableID' => $this->tableID,
            'employeeCode' => '',
            'salesDateIn' => new Expression('NOW()'),
            'salesDateOut' => null,
            'flagInclusive' => $this->flagInclusive,
            'queueNum' => $queueNum,
            'remarks' => $this->remarks,
            'voucherDiscountTotal' => $this->voucherDiscountTotal
        ];
    }

    private function notifSelfOrderApi($salesHead, $billNum)
    {
        try {
            $salesNum = $salesHead->salesNum;
            $queueNum = $salesHead->queueNum;
            $selfOrderApi = Setting::getEsoQsApiUrl();
            $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
            $companyCode = $branch->companyCode;
            $authKey = Setting::getApiKey();
            $client = new Client(['baseUrl' => $selfOrderApi]);
            $salesData = $this->getSalesDataForEmail($salesNum);

            $response = $client->createRequest()
                ->setUrl('pos-finish')
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-branch' => $branch->branchCode,
                    'data-company' => $companyCode
                ])
                ->setData([
                    'orderID' => $this->orderID,
                    'branchID' => Setting::getCurrentBranch(),
                    'companyCode' => $companyCode,
                    'salesNum' => $salesNum,
                    'billNum' => $billNum,
                    'salesDatas' => $salesData['salesDatas'],
                    'salesPayments' => $salesData['salesPayments'],
                    'queueNum' => $queueNum
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            if ($response->getIsOk()) {
                $content = json_decode($response->getContent(), true);
                if ($content && $content['status'] == '00') {
                    return true;
                } else {
                    throw new Exception(json_encode($content), 500);
                }
            } else {
                throw new Exception('Cannot connect to ESO server', 500);
            }
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
    }

    public function updateOrderStatus($username) {
        $selfOrderApi = Setting::getEsoQsApiUrl();

        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;

        $authKey = Setting::getApiKey();
        $client = new Client(['baseUrl' => $selfOrderApi]);
        $response = $client->createRequest()
            ->setUrl("pos-update-order-status")
            ->setMethod('POST')
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                'data-branch' => $branch->branchCode,
            ])
            ->setData([
                "orderID" => $this->orderID,
                "statusID" => $this->statusID
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        if ($response->getIsOk()) {
            $content = json_decode($response->getContent(), true);
            if ($content && $content['status'] == '00') {
                EsoPickupOrder::deleteAll(['orderID' => $this->orderID]);
                $this->saveLogFinishOrder($this->salesNum, $username);
                return true;
            } else {
                throw new Exception(json_encode($content), 500);
            }
        } else {
            throw new Exception('Cannot connect to ESO server', 500);
        }
    }

    private function getSalesDataForEmail($salesNum)
    {
        $orderPayment = SalesHead::findOrderPaymentAsArray(null, $salesNum);
        $billList = array_merge(
            [$orderPayment['order']],
            $orderPayment['salesLink']
        );

        $salesData = [];
        foreach ($billList as $bill) {
            $salesHeadData = [
                'salesNum' => $bill['salesNum'],
                'billNum' => $bill['billNum'],
                'salesDateOut' => $bill['salesDateOut'],
                'tableName' => $bill['tableName'],
                'flagInclusive' => $bill['flagInclusive'],
                'subtotal' => (float) $bill['subtotal'],
                'discountTotal' => (float) $bill['discountTotal'],
                'menuDiscountTotal' => (float) $bill['menuDiscountTotal'],
                'otherTaxTotal' => (float) $bill['otherTaxTotal'],
                'vatTotal' => (float) $bill['vatTotal'],
                'otherVatTotal' => (float) $bill['otherVatTotal'],
                'grandTotal' => (float) $bill['grandTotal'],
                'voucherTotal' => (float) $bill['voucherTotal'],
                'roundingTotal' => (float) $bill['roundingTotal']
            ];

            $salesMenuData = [];
            foreach ($bill['salesMenu'] as $salesMenu) {
                $packages = [];
                foreach ($salesMenu->childSalesMenus as $package) {
                    $packages[] = [
                        'menuName' => $package->menu->menuName,
                        'qty' => (int) $package->qty,
                        'price' => (float) $package->price,
                    ];
                }

                $extras = [];
                foreach ($salesMenu->salesExtras as $extra) {
                    $extras[] = [
                        'menuName' => $extra->menuExtra->menuExtraName,
                        'qty' => (int) $extra->qty,
                        'price' => (float) $extra->price,
                    ];
                }

                $salesMenuData[] = [
                    'menuName' => $salesMenu->menu->menuName,
                    'qty' => (int) $salesMenu->qty,
                    'price' => (float) $salesMenu->price,
                    'packages' => $packages,
                    'extras' => $extras,
                    'promotionDetailID' => (int) isset($salesMenu->promotionDetailID) ? $salesMenu->promotionDetailID : 0
                ];
            }

            $salesData[] = [
                'salesHead' => $salesHeadData,
                'salesMenu' => $salesMenuData
            ];
        }

        $salesPaymentData = [];
        foreach ($orderPayment['salesPayment'] as $salesPayment) {
            $salesPaymentData[] = [
                'paymentMethodName' => $salesPayment->paymentMethod->paymentMethodName,
                'paymentAmount' => (float) $salesPayment->paymentAmount
            ];
        }

        return [
            'salesDatas' => $salesData,
            'salesPayments' => $salesPaymentData
        ];
    }

    public function notifSelfOrderError($errMsg)
    {
        if ($this->orderID && $errMsg) {
            $errMsg = AppHelper::convertErrorMessage($errMsg);
            $selfOrderApi = Setting::getEsoQsApiUrl();
            $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
            $companyCode = $branch->companyCode;
            $authKey = Setting::getApiKey();
            $client = new Client(['baseUrl' => $selfOrderApi]);
            $response = $client->createRequest()
                ->setUrl('pos-error-log')
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-branch' => $branch->branchCode,
                    'data-company' => $companyCode
                ])
                ->setData([
                    'orderID' => $this->orderID,
                    'branchID' => $branch->branchID,
                    'companyCode' => $companyCode,
                    'errorMessage' => $errMsg,
                    'errorTime' => date('Y-m-d H:i:s')
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            try {
                $content = json_decode($response->getContent(), true);
                if ($content && $content['status'] == '00') {
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $ex) {
                Yii::warning($ex);
                return false;
            }
        }
    }

    public function saveEsoProcessQueue($orderID) {
        try {
            $checkSalesPayment = EsoProcessQueue::checkExistingOrderId($orderID);

            if ($checkSalesPayment) {
                $this->addQueue($checkSalesPayment->salesNum, QueueSelfOrder::TYPE_SALES_FINISH);
                return false;
            }

            $pendingDataByOrderId = EsoProcessQueue::findPendingDataByOrderId($orderID, EsoProcessQueue::TYPE_NEW);
            if ($pendingDataByOrderId && !$checkSalesPayment) return true;
            
            $model = new EsoProcessQueue();
            $model->orderID = $orderID;
            if (!$model->save()) {
                Yii::error($model->getErrors());
                return false;
            }

            return true;
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }


    public function saveVoidEsoProcessQueue($orderID, $salesNum, $voidNotes) {
        try {
            $checkSales = EsoProcessQueue::checkStatusSalesNum($salesNum);

            $voidCondition = $checkSales && $checkSales->statusID == 24;
            if ($voidCondition)
            {
                $this->addQueue($checkSales->salesNum, QueueSelfOrder::TYPE_SALES_VOID);
                return false;
            }

            $pendingDataByOrderId = EsoProcessQueue::findPendingDataByOrderId($orderID, EsoProcessQueue::TYPE_VOID);
            if ($pendingDataByOrderId && !$voidCondition) return true;

            $model = new EsoProcessQueue();
            $model->orderID = $orderID;
            $model->salesNum = $salesNum;
            $model->voidNotes = $voidNotes;
            $model->eventType = EsoProcessQueue::TYPE_VOID;
            if (!$model->save()) {
                Yii::error($model->getErrors());
                return false;
            }
            return true;
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public function saveEsoPickupOrder($salesNum) {
        try {
            $model = new EsoPickupOrder();
            $model->salesNum = $salesNum;
            $model->orderID = $this->orderID;
            if (!$model->save()) {
                Yii::error($model->getErrors());
                return false;
            }
            Logging::save($salesNum, Logging::ADD_PICKUP_ORDER, $model->getAttributes());
            return true;
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
}
