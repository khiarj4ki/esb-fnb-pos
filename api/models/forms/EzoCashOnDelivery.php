<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\MapBranchVisitPurpose;
use app\models\Menu;
use app\models\MenuPackage;
use app\models\MenuTemplateHead;
use app\models\MenuTemplateDetail;
use app\models\MenuExtra;
use app\models\MenuGroup;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesPayment;
use app\models\SalesPlatformFee;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\httpclient\Client;
use yii\httpclient\Exception;

class EzoCashOnDelivery extends Model {
    public $orderID;
    public $ezoDeliveryNum;
    public $salesPayment;
    public $order;
    public $salesMenu;
    public $visitPurposeID;
    public $externalApiVisitPurpose;
    public $ezoServerID;
    public $salesInfo;
    public $selfOrderPaymentMethodID = null;
    public $errMsg;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['orderID', 'salesPayment', 'order'], 'required'],
            [['orderID', 'ezoDeliveryNum', 'salesPayment', 'order', 'visitPurposeID', 'ezoServerID', 'salesInfo', 'selfOrderPaymentMethodID'], 'safe'],
            [['orderID'], 'validateOrderPayment'],
            [['order'], 'validateSalesNum'],
            [['salesMenu'], 'validateSalesMenu'],
            [['visitPurposeID'], 'validateVisitPurpose'],
        ];
    }

    public static function loadOrder($orderID) {

        $selfOrderApi = Setting::getEsoQsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();
        $client = new Client(['baseUrl' => $selfOrderApi]);
        $response = $client->createRequest()
            ->setUrl("pos-view")
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
        if ($response->statusCode == "200") {
            return json_decode($response->content, true);
        } else {
            return null;
        }
    }

    public function loadOrderId($orderID) {
        $result = self::loadOrder($orderID);
        if ($result) {
            $result['otherTaxTotal'] = $result['additionalTax'];
            $result['voucherTotal'] = 0;
            $result['menuDiscountTotal'] = 0;
            $i = 0;

            for ($i=0; $i < count($result['salesMenu']); $i++) { 
                if (count($result['salesMenu'][$i]['extras']) > 0) {
                    $j=0;
                    for ($j=0; $j < count($result['salesMenu'][$i]['extras']); $j++) { 
                        $menuExtraModel = MenuExtra::findOne($result['salesMenu'][$i]['extras'][$j]['menuExtraID']);
                        $result['salesMenu'][$i]['extras'][$j]['menuExtraShortName'] = $menuExtraModel->menuExtraShortName;
                        $j++;
                    }
                }
                $result['menuDiscountTotal'] += $result['salesMenu'][$i]['discount'];
                $i++;
            }

            if (isset($result['salesMode']) && $result['salesMode']) {
                $selfOrderVisitPurposeID = $this->order->salesMode;
            } else {
                $selfOrderVisitPurposeID = MapBranchVisitPurpose::findOne([
                        'flagSelfOrder' => 1,
                    ])->visitPurposeID;
            }
            $result['visitPurposeID'] = $selfOrderVisitPurposeID;
            return $result;
        } else {
            $this->addError("orderID", "Failed to connect to EZO");
            return false;
        }
    }

    public function validateSalesNum($attribute) {
        // remove fake salesNum
        if ($this->order['salesNum']) {
            $this->order['salesNum'] = null;
        }
    }

    public function validateVisitPurpose($attribute) {
        $visitPurposeID = MapBranchVisitPurpose::find()
                ->select("visitPurposeID")
                ->where([
                    "visitPurposeID" => $this->visitPurposeID,
                    "branchID" => Setting::getCurrentBranch()
                ])
                ->scalar();

        if (!$visitPurposeID) {
            $this->addError($attribute, Yii::t('app',
                'visitPurposeID: Visit Purpose does not exist. '));

            $this->externalApiVisitPurpose = false;
        } else {
            $this->externalApiVisitPurpose = true;
        }
    }

    public function validateOrderPayment($attribute) {

        $salesPayment = SalesPayment::findOne([
                'selfOrderID' => $this->orderID
        ]);
        if ($salesPayment) {
            $salesHead = SalesHead::findOne(['salesNum' => $salesPayment->salesNum]);
            $errMsg = Yii::t('app',
                    'Order already created before. Payment has been settled online using EZO. <br/> Your queue number is: <br/><strong>{queueNum}</strong>',
                    [
                        'queueNum' => $salesHead->queueNum
            ]);
            $this->addError($attribute, $errMsg);
        }
    }

    public function validateSalesMenu($attribute) {
        for ($i = 0; $i < count($this->salesMenu); $i++) {
            $salesMenu = $this->salesMenu[$i];
            $menuID = $salesMenu['menuID'];


            $query = (new Query())
                ->select([
                    'c.menuID'
                ])
                ->from(MapBranchVisitPurpose::tableName() . ' a')
                ->innerJoin(MenuTemplateHead::tableName() . ' b',
                    'b.menuTemplateID = a.menuTemplateID')
                ->innerJoin(MenuTemplateDetail::tableName() . ' c',
                    'c.menuTemplateID = b.menuTemplateID')
                ->andWhere([
                    'a.branchID' => Setting::getCurrentBranch(),
                    'a.visitPurposeID' => $this->visitPurposeID,
                    'c.menuID' => $menuID,
                    'c.flagActive' => 1
                ])
                ->one();

            if (!$query && $this->externalApiVisitPurpose) {
                $this->addError($attribute, Yii::t('app',
                    "menuID: Menu ID $menuID does not exist. "));
            }

            for ($j = 0; $j < count($this->salesMenu[$i]['packages']); $j++) {
                $salesPackage = $this->salesMenu[$i]['packages'][$j];
                $packageMenuID = $salesPackage['menuID'];

                $queryPackage = (new Query())
                    ->select([
                        'c.menuID'
                    ])
                    ->from(MapBranchVisitPurpose::tableName() . ' a')
                    ->innerJoin(MenuTemplateHead::tableName() . ' b',
                        'b.menuTemplateID = a.menuTemplateID')
                    ->innerJoin(MenuTemplateDetail::tableName() . ' c',
                        'c.menuTemplateID = b.menuTemplateID')
                    ->andWhere([
                        'a.branchID' => Setting::getCurrentBranch(),
                        'a.visitPurposeID' => $this->visitPurposeID,
                        'c.menuID' => $packageMenuID,
                        'c.flagActive' => 1
                    ])
                    ->one();

                if (!$queryPackage && $this->externalApiVisitPurpose) {
                    $this->addError($attribute, Yii::t('app',
                        "menuID: Menu ID $packageMenuID does not exist. "));
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

                if (!$queryExtra && $this->externalApiVisitPurpose) {
                    $this->addError($attribute, Yii::t('app',
                        "menuID: Menu ID $menuExtraID does not exist. "));
                }
            }
        }
    }

    public function save() {
        if (!$this->validate()) {
            $this->getError();
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {

            if (!$salesHead = $this->saveSales()) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }
            
            $printingSettings = Setting::getPrintingSettings();
            $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;

            if (!$printingAfterPayment) {
                $this->printOrder($salesHead->salesNum);
            }

            if (!$billNum = $this->savePayment($salesHead->salesNum)) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }
    
            if (!$salesInfo = $this->saveSalesInfo($salesHead->salesNum)) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }

            if (!$this->updateEzoStatus()) {
                $this->addError('orderID', Yii::t('app', 'Failed to update status ez order transaction'));
                $transaction->rollback();
                return false;
            }
            
            $ezoSettings = Setting::getEZOSetting();
            $printerStationID = isset($ezoSettings['Printer Station']) ? $ezoSettings['Printer Station'] : 0;
    
            $activateEzoTA = isset($ezoSettings['Activate EZO TA']) ? $ezoSettings['Activate EZO TA'] : 0;
            $activateQoQi = isset($ezoSettings['Activate QoQi']) ? $ezoSettings['Activate QoQi'] : 0;
            if ($activateEzoTA || $activateQoQi) {
                $apiUrl = Setting::getEsoQsApiUrl();
                $QoQiapiUrl = Setting::getQoQiApiUrl();
                if ($apiUrl || $QoQiapiUrl) {
                    if (!isset($this->visitPurposeID)) {
                        $this->notifSelfOrderApi($salesHead, $billNum);
                    }
                }
            }
            $queueNum = intval($salesHead->queueNum);

            $transaction->commit();

            if ($printerStationID > 0) {
                $this->printPayment($salesHead->salesNum, $printerStationID);
            }

            if ($printingAfterPayment) {
                $this->printOrder($salesHead->salesNum);
            }
            return [
                'salesNum' => $salesHead->salesNum,
                'billNum' => $billNum,
                'queueNum' => $queueNum
            ];
        } catch (Exception $ex) {
            $transaction->rollback();
            return false;
        }
    }

    public function saveSales() {

        if (isset($this->visitPurposeID)) {
            $selfOrderVisitPurposeID = $this->visitPurposeID;
        }  else if (isset($this->salesMode)) {
            $selfOrderVisitPurposeID = $this->order->salesMode;
        } else {
            if (isset($this->order['visitPurposeID'])) {
                $selfOrderVisitPurposeID = $this->order['visitPurposeID'];
            } else {
                $selfOrderVisitPurposeID = MapBranchVisitPurpose::findOne([
                    'flagSelfOrder' => 1,
                ])
                ->visitPurposeID;
            }
        }

        $menuModel = Menu::find()
            ->indexBy("menuID")
            ->all();

        $menuExtraModel = MenuExtra::find()
            ->indexBy("menuExtraID")
            ->all();

        $bookModel = new BookTable();
        $bookModel->tableID = 0;
        $bookModel->memberID = 0;
        $bookModel->visitPurposeID = $selfOrderVisitPurposeID;
        $bookModel->paxTotal = 1;
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($bookModel->visitPurposeID);

        $updateModel = new UpdateOrder();
        $updateModel->tableID = 0;
        $updateModel->additionalInfo = $this->order['fullName'];
        $updateModel->batchID = 1;
        $updateModel->visitPurposeID = $selfOrderVisitPurposeID;
        $updateModel->paxTotal = 1;
        $updateModel->memberID = 0;
        $updateModel->promotionID = isset($this->order['promotionID']) ? $this->order['promotionID'] : 0;
        $updateModel->promotionDiscount = isset($this->order['promotionDiscount']) ? $this->order['promotionDiscount'] : 0;
        $updateModel->discountTotal = $this->order['discountTotal'];
        // @notes: set voucherDiscountTotal dari voucherTypeID = 1
        $updateModel->voucherDiscountTotal = $this->order['voucherDiscountTotal'];
        $updateModel->deliveryCost = $this->order['deliveryCost'];
        $updateModel->external = 1;
        $updateModel->salesMenu = [];
        $updateModel->transactionModeID = isset($this->order['transactionModeID']) ? $this->order['transactionModeID'] : null;
        if ($this->order['memberID'] && !empty($this->order['memberID'])) {
            $orderData = self::loadOrder($this->orderID);
            if ($orderData['flagExternalMemberID']) {
                $updateModel->flagExternalMemberID = $orderData['flagExternalMemberID'];
                $updateModel->flagExternalCardID = $orderData['flagExternalCardID'];
                $updateModel->flagExternalMemberPhone =  $orderData['flagExternalMemberPhone'] ? substr( $orderData['flagExternalMemberPhone'], 0, 20) : $orderData['flagExternalMemberPhone'];
                $updateModel->flagExternalAPI = $orderData['flagExternalAPI'];
                $updateModel->externalMemberName = $orderData['externalMemberName'];
            }
        }
        if ($this->order['orderFee'] && $this->order['orderFee'] > 0) {
            $updateModel->orderFee = $this->order['orderFee'];
        }
        if (isset($this->order['selfOrderPaymentMethodID']) && $this->order['selfOrderPaymentMethodID']) {
            $this->selfOrderPaymentMethodID = $this->order['selfOrderPaymentMethodID'];
            $updateModel->selfOrderPaymentMethodID = $this->order['selfOrderPaymentMethodID'];
        }

        for ($i = 0; $i < count($this->salesMenu); $i++) {
            $salesMenu = $this->salesMenu[$i];
            if (isset($this->visitPurposeID)) {
                $menuPrice = floatval(isset($salesMenu['price']) ? $salesMenu['price'] : $this->calculateNetPrice($salesMenu));
                $menuTotal = floatval(isset($salesMenu['total']) ? $salesMenu['total'] : $this->calculateTotal($salesMenu));
            } else {
                $menuPrice = $salesMenu['price'];
                $menuTotal = $salesMenu['total'];
            }
            $inclusivePrice = isset($salesMenu['sellPrice']) 
                ? $salesMenu['sellPrice'] 
                : (isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $menuTotal / $salesMenu['qty']);
            $updateModel->salesMenu[$i] = [
                'ID' => $i,
                'salesNum' => '',
                'menuID' => $salesMenu['menuID'],
                'mainMenuID' => isset($salesMenu['mainMenuID']) ? $salesMenu['mainMenuID'] : null,
                'menuName' => $menuModel[$salesMenu['menuID']]->menuName,
                'menuShortName' => $menuModel[$salesMenu['menuID']]->menuShortName,
                'menuFlagTax' => (int) $salesMenu['menuFlagTax'],
                'qty' => $salesMenu['qty'],
                'originalPrice' => $menuPrice,
                'price' => $menuPrice,
                'inclusivePrice' => $inclusivePrice,
                'discount' => isset($salesMenu['discount']) ? $salesMenu['discount'] : 0,
                'discountTotal' => isset($salesMenu['discountTotal']) ? $salesMenu['discountTotal'] : 0,
                'displayPriceValue' => isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : 0,
                'otherTax' => $salesMenu['otherTax'],
                'otherTaxValue' => isset($salesMenu['otherTaxValue']) ? $salesMenu['otherTaxValue'] : 0,
                'vat' => intVal($salesMenu['menuFlagTax']) === 1 ? $salesMenu['vat'] : 0,
                'vatValue' => isset($salesMenu['vatValue']) ? $salesMenu['vatValue'] : 0,
                'otherVat' => intVal($salesMenu['menuFlagTax']) === 2 ? $salesMenu['otherVat'] : 0,
                'otherVatValue' => isset($salesMenu['otherVatValue']) ? $salesMenu['otherVatValue'] : 0,
                'otherTaxOnVat' => $salesMenu['otherTaxOnVat'],
                'total' => $menuTotal,
                'notes' => isset($salesMenu['notes']) ? $salesMenu['notes'] : '',
                'salesType' => isset($salesMenu['salesType']) ? $salesMenu['salesType'] : 'POS',
                'statusID' => 1,
                'statusName' => 'New',
                'promotionDetailID' => isset($salesMenu['promotionDetailID']) ? $salesMenu['promotionDetailID'] : 0,
                'packages' => [],
                'extras' => [],
            ];
            for ($j = 0; $j < count($this->salesMenu[$i]['packages']); $j++) {
                $salesPackage = $this->salesMenu[$i]['packages'][$j];
                if (isset($this->visitPurposeID)) {
                    $packagePrice = floatval(isset($salesPackage['price']) ? $salesPackage['price'] : $this->calculateNetPrice($salesPackage));
                    $packageTotal = floatval(isset($salesPackage['total']) ? $salesPackage['total'] : $this->calculateTotal($salesPackage));
                } else {
                    $packagePrice = $salesPackage['price'];
                    $packageTotal = $salesPackage['total'];
                }
                $menuGroupID = 1;
                if (isset($salesPackage['menuGroupID'])) {
                    $menuGroupID = $salesPackage['menuGroupID'];
                }else{
                    $menuGroupModel = MenuPackage::find()
                        ->innerJoinWith('menuGroup')
                        ->innerJoinWith('branchMenu')
                        ->where([
                            MenuPackage::tableName() . '.menuID' => $salesPackage['menuID'],
                            MenuGroup::tableName() . '.menuID' => $salesMenu['menuID']
                        ])
                        ->groupBy(MenuPackage::tableName() . '.menuGroupID')
                        ->one();
                    if ($menuGroupModel) {
                        $menuGroupID = $menuGroupModel->menuGroupID;
                    }
                }

                $inclusivePrice = isset($salesPackage['inclusivePrice']) ? $salesPackage['inclusivePrice'] : $packageTotal / $salesPackage['qty'];

                $updateModel->salesMenu[$i]['packages'][$j] = [
                    'ID' => $j,
                    'salesNum' => '',
                    'menuGroupID' => $menuGroupID,
                    'menuID' => $salesPackage['menuID'],
                    'menuName' => $menuModel[$salesPackage['menuID']]->menuName,
                    'menuShortName' => $menuModel[$salesPackage['menuID']]->menuShortName,
                    'menuFlagTax' => (int) $salesMenu['menuFlagTax'],
                    'qty' => $salesPackage['qty'],
                    'originalPrice' => $packagePrice,
                    'price' => $packagePrice,
                    'inclusivePrice' => $inclusiveMenuTemplateID ? $inclusivePrice : 0,
                    'discount' => isset($salesPackage['discount']) ? $salesPackage['discount'] : 0,
                    'discountTotal' => isset($salesPackage['discountTotal']) ? $salesPackage['discountTotal'] : 0,
                    'displayPriceValue' => isset($salesPackage['inclusivePrice']) ? $salesPackage['inclusivePrice'] : 0,
                    'otherTax' => $salesPackage['otherTax'],
                    'otherTaxValue' => isset($salesPackage['otherTaxValue']) ? $salesPackage['otherTaxValue'] : 0,
                    'vat' => intVal($salesPackage['menuFlagTax']) === 1 ? $salesPackage['vat'] : 0,
                    'vatValue' => isset($salesPackage['vatValue']) ? $salesPackage['vatValue'] : 0,
                    'otherVat' => intVal($salesPackage['menuFlagTax']) === 2 ? $salesPackage['otherVat'] : 0,
                    'otherVatValue' => isset($salesPackage['otherVatValue']) ? $salesPackage['otherVatValue'] : 0,
                    'otherTaxOnVat' => $salesPackage['otherTaxOnVat'],
                    'total' => $packageTotal,
                    'notes' => isset($salesPackage['notes']) ? $salesPackage['notes'] : '',
                    'salesType' => isset($salesMenu['salesType']) ? $salesMenu['salesType'] : 'POS',
                    'statusID' => 1,
                    'statusName' => 'New',
                    'promotionDetailID' => 0,
                    'packages' => [],
                    'extras' => [],
                ];
            }

            for ($k = 0; $k < count($this->salesMenu[$i]['extras']); $k++) {
                $salesExtra = $this->salesMenu[$i]['extras'][$k];
                if (isset($this->visitPurposeID)) {
                    $extraPrice = floatval(isset($salesExtra['price']) ? $salesExtra['price'] : $this->calculateNetPrice($salesExtra));
                    $extraTotal = floatval(isset($salesExtra['total']) ? $salesExtra['total'] : $this->calculateTotal($salesExtra));
                } else {
                    $extraPrice = $salesExtra['price'];
                    $extraTotal = $salesExtra['total'];
                }
                $inclusivePrice = isset($salesExtra['inclusivePrice']) ? $salesExtra['inclusivePrice'] : $extraTotal / $salesExtra['qty'];
                $updateModel->salesMenu[$i]['extras'][$k] = [
                    'ID' => $k,
                    'salesNum' => '',
                    'menuExtraID' => $salesExtra['menuExtraID'],
                    'menuExtraName' => $menuExtraModel[$salesExtra['menuExtraID']]->menuExtraName,
                    'menuExtraShortName' => $menuExtraModel[$salesExtra['menuExtraID']]->menuExtraShortName,
                    'menuFlagTax' => (int) $salesExtra['menuFlagTax'],
                    'qty' => $salesExtra['qty'],
                    'price' => $extraPrice,
                    'inclusivePrice' => $inclusiveMenuTemplateID ? $inclusivePrice : 0,
                    'discount' => isset($salesExtra['discount']) ? $salesExtra['discount'] : 0,
                    'discountTotal' => isset($salesExtra['discountTotal']) ? $salesExtra['discountTotal'] : 0,
                    'displayPriceValue' => isset($salesExtra['inclusivePrice']) ? $salesExtra['inclusivePrice'] : 0,
                    'otherTax' => $salesExtra['otherTax'],
                    'otherTaxValue' => isset($salesExtra['otherTaxValue']) ? $salesExtra['otherTaxValue'] : 0,
                    'vat' => intVal($salesExtra['menuFlagTax']) === 1 ? $salesExtra['vat'] : 0,
                    'vatValue' => isset($salesExtra['vatValue']) ? $salesExtra['vatValue'] : 0,
                    'otherVat' => intVal($salesExtra['menuFlagTax']) === 2 ? $salesExtra['otherVat'] : 0,
                    'otherVatValue' => isset($salesExtra['otherVatValue']) ? $salesExtra['otherVatValue'] : 0,
                    'otherTaxOnVat' => $salesExtra['otherTaxOnVat'],
                    'total' => $extraTotal,
                    'statusID' => 1,
                    'statusName' => 'New',
                ];
            }
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$bookModel->save()) {
                Yii::error($bookModel->getErrors());
                $this->notifSelfOrderError($bookModel->getErrors());
                $transaction->rollBack();
                return false;
            }
    
            if (!$this->savePlatformFee($bookModel->salesNum)) {
                Yii::error($bookModel->getErrors());
                $this->notifSelfOrderError($bookModel->getErrors());
                $transaction->rollBack();
                return false;
            }

            $updateModel->salesNum = $bookModel->salesNum;
            if (!$updateModel->save()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    Yii::error($errMsg);
                    $this->notifSelfOrderError($errMsg);
                } else {
                    Yii::error($updateModel->getErrors());
                    $this->notifSelfOrderError($updateModel->getErrors());
                }
                $transaction->rollBack();
                return false;
            }

            $transaction->commit();
            return $updateModel->salesModel;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    public function updateEzoStatus() {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        
        $client = new Client(['baseUrl' => $apiUrl]);
        $response = $client->createRequest()
                ->setUrl('esb_api/ezo-delivery/update-sync-status-cash-on-delivery')
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->setData([
                    'ezoDeliveryNum' => $this->ezoDeliveryNum,
                    'ezoOrderID' => $this->orderID,
                    'syncStatus' => 'SYNCED'
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->send();

        return $response->getIsOk() ? $response->getData() : false;
    }

    public function savePayment($salesNum) {
        
        $paymentModel = new SavePayment();
        $paymentModel->tableID = 0;
        $paymentModel->salesNum = $salesNum;
        $paymentModel->salesVoucher = null;
        $paymentModel->ezoCodPayment = true;
        $paymentModel->selfOrderPaymentMethodID = $this->selfOrderPaymentMethodID;
        
        $i = 0;
        foreach ($this->salesPayment as $salesPayment) {
            $paymentModel->salesPayment[] = [
                'ID' => $salesPayment['ID'],
                'salesNum' => $salesNum,
                'coaNo' => $salesPayment['coaNo'],
                'paymentMethodID' => $salesPayment['paymentMethodID'],
                'paymentMethodTypeID' => $salesPayment['paymentMethodTypeID'],
                'paymentMethodName' => $salesPayment['paymentMethodName'],
                'paymentMethodChild' => '',
                'flagAuthorization' => 0,
                'paymentAmount' => $salesPayment['paymentAmount'],
                'fullPaymentAmount' => $salesPayment['fullPaymentAmount'],
                'notes' => '',
                'selfOrderID' => $this->orderID,
                'voucherCode' => isset($salesPayment['voucherCode']) ? $salesPayment['voucherCode'] : null
            ];
            $i++;
        }

        try {
            if (!$paymentModel->save()) {
                Yii::error($paymentModel->errors);
                $this->notifSelfOrderError($paymentModel->errors);
                $this->getError('payment', $paymentModel->errors);
                foreach ($paymentModel->attributes() as $attribute) {
                    if (isset($paymentModel->errors[$attribute])) {
                        $this->notifSelfOrderError($paymentModel->errors[$attribute][0]);
                    }
                }
                return false;
            } else {
                return $paymentModel->salesModel->billNum;
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $this->notifSelfOrderError($ex->getMessage());
            return false;
        }
    }

    public function saveSalesInfo($salesNum) {
        if (!$this->salesInfo) {
            return true;
        }
        try {
            $transaction = Yii::$app->db->beginTransaction();
            
            foreach($this->salesInfo as $salesInfo) {
                if (isset($salesInfo['desc'])) {
                    $salesInfoModel = new SalesInfo();
                    $salesInfoModel->salesNum = $salesNum;
                    $salesInfoModel->key = $salesInfo['desc'];
                    $salesInfoModel->value = $salesInfo['value'];
                    if (!$salesInfoModel->save()) {
                        $this->notifSelfOrderError($salesInfoModel->errors);
                        throw new Exception("Unable to save sales info");
                    }
                }
            }
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $transaction->rollBack();
            return false;
        }
    }

    public function savePlatformFee($salesNum) {
        if (!isset($this->order['platformFee'])) {
            return true;
        }
        try {
            $transaction = Yii::$app->db->beginTransaction();
            
            // Insert Platform Fee Data - Start
            $salesPlatformFees = $this->order['platformFee'];

            if ($salesPlatformFees) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($salesNum, $salesPlatformFees)) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }
            // Insert Platform Fee Data - End

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $transaction->rollBack();
            return false;
        }
    }

    public function printOrder($salesNum) {
        try {
            $printingModel = new PrintOrder();
            $printingModel->tableID = 0;
            $printingModel->salesNum = $salesNum;
            $printingModel->batchID = 1;
            $printingModel->doPrint();
        } catch (Exception $ex) {
            Yii::error($ex);
        }

        return true;
    }

    public function printPayment($salesNum, $printerStationID) {
        try {
            $printingModel = new PrintPayment();
            $printingModel->salesNum = $salesNum;
            $printingModel->stationID = $printerStationID;

            if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0,
                    $salesNum)) {
                $printingModel->doPrint();
            }
        } catch (Exception $ex) {
            Yii::error($ex);
        }

        return true;
    }

    public function notifSelfOrderError($errMsg) {
        if ($this->orderID && $errMsg) {
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
                    Yii::error($ex);
                    return false;
                }
        }
    }

    public function notifSelfOrderApi($salesHead, $billNum) {
        $salesDate = $salesHead->salesDate;
        $salesNum = $salesHead->salesNum;
        $queueNum = $salesHead->queueNum;
        if ($this->ezoServerID === 'qoqi') {
            $selfOrderApi = Setting::getQoQiApiUrl();
        } else {
            $selfOrderApi = Setting::getEsoQsApiUrl();
        }
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
        try {
            $content = json_decode($response->getContent(), true);
            if ($content && $content['status'] == '00') {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    private function getSalesDataForEmail($salesNum) {
        $orderPayment = SalesHead::findOrderPaymentAsArray(null, $salesNum);
        $billList = array_merge([$orderPayment['order']],
            $orderPayment['salesLink']);

        $salesData = [];
        foreach ($billList as $bill) {
            $salesHeadData = [
                'salesNum' => $bill['salesNum'],
                'billNum' => $bill['billNum'],
                'salesDateOut' => $bill['salesDateOut'],
                'tableName' => $bill['tableName'],
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
                if(!empty($salesMenu['package'])){
                    foreach ($salesMenu['package'] as $package) {
                        $packages[] = [
                            'menuName' => $package['menuName'],
                            'qty' => (int) $package['qty'],
                            'price' => (float) $package['price'],
                        ];
                    }
                }

                $extras = [];
                if(!empty($salesMenu['extras'])){
                    foreach ($salesMenu['extras'] as $extra) {
                        $extras[] = [
                            'menuName' => $extra['menuExtraName'],
                            'qty' => (int) $extra['qty'],
                            'price' => (float) $extra['price'],
                        ];
                    }
                }

                $salesMenuData[] = [
                    'menuName' => $salesMenu['menuName'],
                    'qty' => (int) $salesMenu['qty'],
                    'price' => (float) $salesMenu['price'],
                    'packages' => $packages,
                    'extras' => $extras
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
                'paymentMethodName' => $salesPayment['paymentMethodName'],
                'paymentAmount' => (float) $salesPayment['paymentAmount']
            ];
        }

        return [
            'salesDatas' => $salesData,
            'salesPayments' => $salesPaymentData
        ];
    }

    public function cancelTransaction($salesNum) {
        $voidModel = new VoidSales();
        $voidModel->salesNum = $salesNum;
        $voidModel->voidNotes = 'Cancelled by Salf Order. Unable to connect to Self Order Web Service.';

        try {
            if (!$voidModel->save()) {
                Yii::error($voidModel->errors);
                return false;
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }

        return true;
    }

    private function calculateTotal($detail) {
        $subtotal = (float) $detail['price'] * $detail['qty'];
        $otherTaxTotal = (float) $subtotal * $detail['otherTax'] / 100;
        $vatTotal = (float) ($subtotal + ($detail['otherTaxOnVat'] ? $otherTaxTotal : 0)) * $detail['vat'] / 100;
        $otherVatTotal = (float) ($subtotal + ($detail['otherTaxOnVat'] ? $otherTaxTotal : 0)) * $detail['otherVat'] / 100;
        $vatTotalOrOtherVatTotal = $otherVatTotal > 0 ? $otherVatTotal : $vatTotal;

        return ceil($subtotal + $otherTaxTotal + $vatTotalOrOtherVatTotal);
    }

    private function calculateNetPrice($detail) {
        $netPrice = 0;
        $vatOrOtherVat = isset($detail['otherVat']) && $detail['otherVat'] > 0 ? $detail['otherVat'] : $detail['vat'];
        if ($detail['otherTaxOnVat']) {
            $netPrice = floor($detail['total'] * 100 / (100 + $vatOrOtherVat) * 100 / (100 + $detail['otherTax']) / $detail['qty']);
        } else {
            $netPrice = floor($detail['total'] * 100 / (100 + $vatOrOtherVat + $detail['otherTax']) / $detail['qty']);
        }

        return $netPrice;
    }

    private function getError($mode=null, $errorModel=null) {
        if (!$mode) {
            $errorModel = $this->errors;
        }
        if ($errorModel) {
            foreach ($errorModel as $errors) {
                foreach ($errors as $attribute => $error) {
                    $this->errMsg .= $error;
                }
            }
        }
    }

}
