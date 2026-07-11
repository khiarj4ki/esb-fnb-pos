<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\CustomerTransaction;
use app\models\EsoPickupOrder;
use app\models\MapBranchVisitPurpose;
use app\models\MapSelfOrderPaymentMethod;
use app\models\Menu;
use app\models\MenuPackage;
use app\models\MenuTemplateHead;
use app\models\MenuTemplateDetail;
use app\models\MenuExtra;
use app\models\MenuGroup;
use app\models\PaymentMethod;
use app\models\PaymentOnlineTrackingLog;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesPayment;
use app\models\Setting;
use app\models\ShiftLog;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use Exception;

class SelfOrderTakeAway extends Model {
    public $subtotal;
    public $additionalTax;
    public $pb1;
    public $grandTotal;
    public $roundingTotal;
    public $salesMenu;
    public $orderID;
    public $paymentMethod;
    public $paymentTotal;
    public $fullName;
    public $customerNotes;
    public $email;
    public $phoneNumber;
    public $visitPurposeID;
    public $deliveryCost;
    public $promotionID;
    public $promotionDiscount;
    public $discountTotal;
    public $voucherDiscountTotal;
    public $orderPayment;
    public $orderVoucherUsage;
    public $externalApi;
    public $salesInfo;
    public $flagExternalMemberID;
    public $flagExternalCardID;
    public $flagExternalMemberPhone;
    public $flagExternalAPI;
    public $salesMode;
    public $transactionModeID;
    public $externalMembershipType;
    public $externalMemberName;
    public $paymentMethodCode;
    public $ezoServerID;
    public $orderFee;
    public $tableID;
    public $tableName;
    public $externalApiVisitPurpose;
    public $flagInclusive;
    public $errMsg;
    public $scanQrTakeAwayOff = false;
    public $kioskStationID;
    public $flagSavePaymentFs = false;
    public $flagEdcPayment = false;
    public $edcPayment;
    public $vouchers;
    public $flagVoucherPayment = false;

    public static function loadOrder($orderID, $ezoServerID = null) {

        if ($ezoServerID == 'qoqi') {
            $selfOrderApi = Setting::getQoQiApiUrl();
            if (!$selfOrderApi) {
                Yii::warning("Please set QoQi API Url");
                return null;
            }
        } else {
            $selfOrderApi = Setting::getEsoQsApiUrl();
        }

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
        $result = self::loadOrder($orderID, $this->ezoServerID);
        if ($result) {
            $this->setAttributes($result);
            $this->orderID = $orderID;
        } else {
            $this->addError("orderID", "Failed to connect to EZO");
            return false;
        }
    }

    public static function loadCheckOrder($orderID) {


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
        if ($response->statusCode == "200") {
            return json_decode($response->content, true);
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesMenu', 'orderID', 'paymentMethod', 'fullName'], 'required'],
            [['paymentTotal', 'visitPurposeID', 'orderPayment', 'orderVoucherUsage', 'salesInfo', 'customerNotes',
                'flagExternalAPI', 'flagExternalMemberID', 'flagExternalMemberPhone', 'flagExternalCardID', 'salesMode', 
                'transactionModeID', 'externalMembershipType', 'externalMemberName', 'paymentMethodCode', 'orderFee', 'tableName', 
                'flagInclusive', 'errMsg', 'tableID', 'fullName', 'phoneNumber', 'email', 'kioskStationID', 'flagEdcPayment', 'edcPayment','vouchers','flagVoucherPayment'], 'safe'],
            [['promotionID'], 'integer'],
            [['deliveryCost', 'promotionDiscount', 'discountTotal', 'voucherDiscountTotal'], 'number'],
            [['orderID'], 'validateOrderPayment'],
            [['paymentMethod'], 'validatePaymentMethod'],
            [['visitPurposeID'], 'validateVisitPurpose'],
            [['salesMenu'], 'validateSalesMenu']
        ];
    }

    public function validateOrderPayment($attribute) {

        $salesPayment = SalesPayment::findOne([
                'selfOrderID' => $this->orderID
        ]);
        if ($salesPayment) {
            $salesHead = SalesHead::findOne(['salesNum' => $salesPayment->salesNum]);
            $errMsg = Yii::t('app',
                                'Order already created before. Payment has been settled online using EZO. Your queue number is: {queueNum}',
                                ['queueNum' => $salesHead->queueNum]
                            );
            if ($this->externalApi == 1) {
                $errMsg = Yii::t('app',
                    'orderID: Order already created before, payment has been settled online using EZO. ');
            }
            $this->addError($attribute, $errMsg);
        }
    }

    public function validatePaymentMethod($attribute) {
        if ($this->paymentMethod == 'external' ) {
            foreach ($this->orderPayment as $payment) {
                $paymentMethod = PaymentMethod::findOne($payment['paymentMethodID']);
                if (!$paymentMethod) {
                    $this->addError($attribute,
                        Yii::t('app', 'paymentMethod: Payment method does not exist. '));
                }
            }
        } else if ($this->externalApi == 1) {

            if (!isset($this->paymentMethodCode)) {
                $paymentMethodID = MapSelfOrderPaymentMethod::find()
                    ->select("paymentMethodID")
                    ->where([
                        "selfOrderPaymentMethodID" => $this->paymentMethod,
                        "branchID" => Setting::getCurrentBranch()
                    ])
                    ->scalar();
                
                $paymentMethod = PaymentMethod::findOne($paymentMethodID);
            } else {
                $paymentMethod = PaymentMethod::findOne(['paymentMethodCode' => $this->paymentMethodCode]);
                if ($this->flagEdcPayment && isset($this->edcPayment['paymentMethodID'])) {
                    $paymentMethod = PaymentMethod::findOne(['paymentMethodID' => $this->edcPayment['paymentMethodID']]);
                }
            }

            if (!$paymentMethod) {
                $this->addError($attribute,
                    Yii::t('app', 'paymentMethod: Payment method does not exist. '));
            }

        }
    }

    public function validateVisitPurpose($attribute) {
        if ($this->externalApi == 1) {
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
    }

    public function validateSalesMenu($attribute) {
        if ($this->externalApi == 1) {
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
    }

    public function save() {
        if (!$this->validate()) {
            if ($this->externalApi == 1) {
                $this->getError();
            }
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction('Serializable');
        try {
            if (!$salesHead = $this->saveSales()) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }

            $printingSettings = Setting::getPrintingSettings();
            $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;
            $externalApi = isset($this->externalApi) ? $this->externalApi : 0;

            if (!$salesInfo = $this->saveSalesInfo($salesHead->salesNum)) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }
            
            if ($externalApi && !$printingAfterPayment) {
                $this->printOrder($salesHead->salesNum);
            }
            
            if (!$billNum = $this->savePayment($salesHead->salesNum)) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }

            if (!$this->saveCustomerTransaction($salesHead->salesNum, $this->fullName, $this->email, $this->phoneNumber)) {
                $this->addError('orderID', Yii::t('app', 'Failed to save data'));
                $transaction->rollback();
                return false;
            }

            if ($this->transactionModeID == 2){
                if (!$this->saveEsoPickupOrder($salesHead->salesNum)) {
                    throw new Exception($this->errMsg, 500);
                }
            }
    
            if ($externalApi && $printingAfterPayment) {
                $this->printOrder($salesHead->salesNum);
            }
            
            $ezoSettings = Setting::getEZOSetting();
            $printerStationID = isset($ezoSettings['Printer Station']) ? $ezoSettings['Printer Station'] : 0;
            
            if ($this->kioskStationID !== null) {
                $printerStationID = $this->kioskStationID;
            }

            if ($printerStationID > 0 && $externalApi) {
                $this->printPayment($salesHead->salesNum, $printerStationID);
            }

            if (!$externalApi && !isset($this->visitPurposeID)) {
                $apiUrl = Setting::getEsoQsApiUrl();
                $QoQiapiUrl = Setting::getQoQiApiUrl();
                if ($apiUrl || $QoQiapiUrl) {
                    if (!$this->notifSelfOrderApi($salesHead, $billNum)) {
                        throw new Exception('Failed updating sales information to ESB Order Server');
                    }
                }
            }

            $transaction->commit();
            
            $queueNum = intval($salesHead->queueNum);

            $salesDataPendingArray = [];
            if ($externalApi == 1) {
                $salesDataHeadQuery = (new Query)
                    ->select([
                        'tr_saleshead.salesNum',
                        'tr_saleshead.grandTotal',
                        'salesMenuID' => new Expression('tr_salesmenu.ID'),
                        'salesMenuRefID' => new Expression('tr_salesmenu.menuRefID'),
                        'salesMenuQty' => new Expression('tr_salesmenu.qty'),
                    ])
                    ->from(SalesHead::tableName())
                    ->leftJoin(SalesMenu::tableName(),
                        'tr_saleshead.salesNum = tr_salesmenu.salesNum AND (tr_salesmenu.menuRefID = 0 OR tr_salesmenu.ID = tr_salesmenu.menuRefID)')
                    ->where(['IN', 'tr_salesmenu.statusID', [13, 34]])
                    ->andWhere(['>=', 'tr_saleshead.salesDate', ShiftLog::getShiftInDate()])
                    ->orderBy([
                        SalesHead::tableName() . '.salesDate' => SORT_ASC,
                        SalesMenu::tableName() . '.batchID' => SORT_ASC
                    ])
                    ->all();

                $salesDataChildQuery = (new Query)
                    ->select([
                        'tr_saleshead.salesNum',
                        'tr_saleshead.grandTotal',
                        'salesMenuID' => new Expression('tr_salesmenu.ID'),
                        'salesMenuRefID' => new Expression('tr_salesmenu.menuRefID'),
                        'salesMenuQty' => new Expression('tr_salesmenu.qty'),
                    ])
                    ->from(SalesHead::tableName())
                    ->leftJoin(SalesMenu::tableName(),
                        'tr_saleshead.salesNum = tr_salesmenu.salesNum AND (tr_salesmenu.menuRefID <> 0 AND tr_salesmenu.ID <> tr_salesmenu.menuRefID)')
                    ->where(['IN', 'tr_salesmenu.statusID', [13, 34]])
                    ->andWhere(['>=', 'tr_saleshead.salesDate', ShiftLog::getShiftInDate()])
                    ->orderBy([
                        SalesHead::tableName() . '.salesDate' => SORT_ASC,
                        SalesMenu::tableName() . '.batchID' => SORT_ASC
                    ])
                    ->all();

                $salesNumList = array_unique(ArrayHelper::getColumn($salesDataHeadQuery, "salesNum"));

                $orderCompletion = SalesMenuCompletion::find()
                    ->select([
                        'salesNum',
                        'salesMenuID',
                        'qty'
                    ])
                    ->where(["IN", "salesNum", $salesNumList])
                    ->andWhere(['typeID' => 2])
                    ->asArray()
                    ->all();

                $totalQtyPerSalesNum = [];
                $totalQtyPerHead = [];
                if($salesDataHeadQuery) {
                    foreach($salesDataHeadQuery as $salesData){
                        if($salesData['salesMenuRefID'] != 0) {
                            $totalQtyPerHead[$salesData['salesMenuID']] = $salesData['salesMenuQty'];
                        }
                        foreach($orderCompletion as $completionPerID){
                            if($completionPerID['salesMenuID'] == $salesData['salesMenuID']){
                                $salesData['salesMenuQty'] -= $completionPerID['qty'];
                            }
                        }
                        $totalNow = isset($totalQtyPerSalesNum[$salesData['salesNum']]) ? $totalQtyPerSalesNum[$salesData['salesNum']] : 0 ;
                        $totalQtyPerSalesNum[$salesData['salesNum']] = $totalNow + $salesData['salesMenuQty'];
                    }
                }

                if($salesDataChildQuery) {
                    foreach($salesDataChildQuery as $salesData){
                        $qtyHead = $totalQtyPerHead[$salesData['salesMenuRefID']];
                        if($qtyHead) {
                            $totalNow = isset($totalQtyPerSalesNum[$salesData['salesNum']]) ? $totalQtyPerSalesNum[$salesData['salesNum']] : 0 ;
                            $totalQtyPerSalesNum[$salesData['salesNum']] = $totalNow + ($qtyHead * $salesData['salesMenuQty']);
                        }
                        foreach($orderCompletion as $completionPerID){
                            if($completionPerID['salesMenuID'] == $salesData['salesMenuID']){
                                $totalQtyPerSalesNum[$salesData['salesNum']] -= $completionPerID['qty'];
                            }
                        }
                    }
                }

                $salesHeadExist = [];
                foreach($salesDataHeadQuery as $salesData){
                    if (!in_array($salesData['salesNum'], $salesHeadExist)) {
                        $salesDataPendingArray[] = [
                            'salesNum' => $salesData['salesNum'],
                            'grandTotal' => floatval($salesData['grandTotal']),
                            'totalQty' => floatval($totalQtyPerSalesNum[$salesData['salesNum']])
                        ];
                        $salesHeadExist[] = $salesData['salesNum'];
                    }
                }
            }
            
            return [
                'salesNum' => $salesHead->salesNum,
                'billNum' => $billNum,
                'queueNum' => $queueNum,
                'orderInProgress' => $salesDataPendingArray
            ];
        } catch (Exception $ex) {
            Yii::warning($ex);
            $transaction->rollback();
            return false;
        }
    }

    public function saveSales() {
        $externalApi = isset($this->externalApi) ? $this->externalApi : 0;
        $currentSalesInfo = isset($this->salesInfo) ? $this->salesInfo : [];

        if (isset($this->visitPurposeID)) {
            $selfOrderVisitPurposeID = $this->visitPurposeID;
        } else {
            $selfOrderVisitPurposeID = $this->salesMode;
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
        $updateModel->scanQrTakeAwayOff = $this->scanQrTakeAwayOff;
        $updateModel->ezoQuickService = $externalApi == 1 ? false : true;
        $updateModel->validateStock = false;
        $updateModel->tableID = 0;
        $updateModel->additionalInfo = $externalApi == 0 ? $this->customerNotes : $this->fullName;
        $updateModel->batchID = 1;
        $updateModel->visitPurposeID = $selfOrderVisitPurposeID;
        $updateModel->paxTotal = 1;
        $updateModel->memberID = 0;
        $updateModel->promotionID = $this->promotionID;
        $updateModel->promotionDiscount = $this->promotionDiscount;
        $updateModel->discountTotal = $this->discountTotal;
        // @notes: set voucherDiscountTotal dari voucherTypeID = 1
        $updateModel->voucherDiscountTotal = $this->voucherDiscountTotal;
        $updateModel->deliveryCost = $this->deliveryCost;
        $updateModel->external = 1;
        $updateModel->externalApi = $externalApi;
        $updateModel->salesMenu = [];
        $updateModel->flagExternalMemberID = $this->flagExternalMemberID;
        $updateModel->flagExternalCardID = $this->flagExternalCardID;
        $updateModel->flagExternalMemberPhone = $this->flagExternalMemberPhone ? substr($this->flagExternalMemberPhone, 0, 20) : $this->flagExternalMemberPhone;
        $updateModel->flagExternalAPI = $this->flagExternalAPI;
        $updateModel->externalMemberName = $this->externalMemberName;
        $updateModel->transactionModeID = $this->transactionModeID;
        $updateModel->saveOrderKiosk = true;
        if ($this->orderFee && $this->orderFee > 0) {
            $updateModel->orderFee = $this->orderFee;
        }
       
        //$updateModel->externalMembershipType = $this->externalMembershipType;

        for ($i = 0; $i < count($this->salesMenu); $i++) {
            $salesMenu = $this->salesMenu[$i];
            if (isset($this->visitPurposeID)) {
                $menuPrice = floatval(isset($salesMenu['price']) ? $salesMenu['price'] : $this->calculateNetPrice($salesMenu));
                $menuTotal = floatval(isset($salesMenu['total']) ? $salesMenu['total'] : $this->calculateTotal($salesMenu));
            } else {
                $menuPrice = $salesMenu['price'];
                $menuTotal = $salesMenu['total'];
            }

            if(isset($salesMenu['vatValue'])){
                $menuVatValue = $salesMenu['vatValue'];
            }else{
                if($salesMenu['otherTaxOnVat']){
                    $menuVatValue = (($salesMenu['qty'] * $menuPrice) * ($salesMenu['vat'] / 100));
                    $menuOtherTaxOnVat = $menuVatValue * ($salesMenu['otherTax'] / 100);
                    $menuVatValue = $menuVatValue + $menuOtherTaxOnVat;
                }else{
                    $menuVatValue = (($salesMenu['qty'] * $menuPrice) * ($salesMenu['vat'] / 100));
                }
            }

            if (isset($salesMenu['otherVatValue'])){
                $menuOtherVatValue = $salesMenu['otherVatValue'];
            } else{
                if (isset($salesMenu['otherVat'])) {
                    if($salesMenu['otherTaxOnVat']){
                        $menuOtherVatValue = (($salesMenu['qty'] * $menuPrice) * ($salesMenu['otherVat'] / 100));
                        $menuOtherTaxOnOtherVat = $menuOtherVatValue * ($salesMenu['otherTax'] / 100);
                        $menuOtherVatValue = $menuOtherVatValue + $menuOtherTaxOnOtherVat;
                    }else{
                        $menuOtherVatValue = (($salesMenu['qty'] * $menuPrice) * ($salesMenu['otherVat'] / 100));
                    }
                } else {
                    $menuOtherVatValue = 0;
                }
                
            }

            $inclusivePrice = isset($salesMenu['sellPrice']) 
                ? $salesMenu['sellPrice'] 
                : (isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $menuTotal / $salesMenu['qty']);
            
            if ($inclusiveMenuTemplateID && $menuPrice == $inclusivePrice) {
                $menuPrice = floatval($this->calculateNetPrice($salesMenu));
            }
            $discount = isset($salesMenu['discount']) ? $salesMenu['discount'] : 0;
            $discountValue = isset($salesMenu['discountValue']) ? $salesMenu['discountValue'] : 0;
            $inclusiveDiscountValue = isset($salesMenu['inclusiveDiscountValue']) ? $salesMenu['inclusiveDiscountValue'] : 0;
            $updateModel->salesMenu[$i] = [
                'ID' => $i,
                'salesNum' => '',
                'menuID' => $salesMenu['menuID'],
                'mainMenuID' => isset($salesMenu['mainMenuID']) ? $salesMenu['mainMenuID'] : null,
                'menuName' => $menuModel[$salesMenu['menuID']]->menuName,
                'menuShortName' => $menuModel[$salesMenu['menuID']]->menuShortName,
                'menuFlagTax' => isset($salesMenu['menuFlagTax']) ? $salesMenu['menuFlagTax'] : 0,
                'qty' => $salesMenu['qty'],
                'originalPrice' => isset($this->flagInclusive) && $this->flagInclusive == 1 && isset($salesMenu['originalInclusivePrice']) ? 
                    $salesMenu['originalInclusivePrice'] : (isset($salesMenu['originalPrice']) ? $salesMenu['originalPrice'] : $menuPrice),
                'price' => $menuPrice,
                'inclusivePrice' => $inclusiveMenuTemplateID ? $inclusivePrice : 0,
                'displayPriceValue' => $inclusivePrice,
                'discount' => $discount,
                'discountValue' => $discountValue,
                'inclusiveDiscountValue' => $inclusiveDiscountValue,
                'discountTotal' => 0,
                'otherTax' => $salesMenu['otherTax'],
                'otherTaxValue' => isset($salesMenu['otherTaxValue']) ? $salesMenu['otherTaxValue'] : (($salesMenu['qty'] * $menuPrice) * ($salesMenu['otherTax'] / 100)),
                'vat' => $salesMenu['vat'],
                'vatValue' => $menuVatValue,
                'otherVat' => isset($salesMenu['otherVat']) ? $salesMenu['otherVat'] : 0,
                'otherVatValue' => $menuOtherVatValue,
                'otherTaxOnVat' => $salesMenu['otherTaxOnVat'],
                'total' => $menuTotal,
                'notes' => $this->removeEmoji($salesMenu['notes']),
                'salesType' => isset($salesMenu['salesType']) ? $salesMenu['salesType'] : 'POS',
                'statusID' => 1,
                'statusName' => 'New',
                'promotionDetailID' => isset($salesMenu['promotionDetailID']) ? $salesMenu['promotionDetailID'] : 0,
                'flagRecommendation' => isset($salesMenu['flagRecommendation']) ? $salesMenu['flagRecommendation'] : false,
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
                        ->groupBy(MenuPackage::tableName() . '.ID', MenuPackage::tableName() . '.menuGroupID')
                        ->one();
                    if ($menuGroupModel) {
                        $menuGroupID = $menuGroupModel->menuGroupID;
                    }
                }

                if(isset($salesPackage['vatValue'])){
                    $packageVatValue = $salesPackage['vatValue'];
                }else{
                    if($salesPackage['otherTaxOnVat']){
                        $packageVatValue = (($salesPackage['qty'] * $packagePrice) * ($salesPackage['vat'] / 100));
                        $packageOtherTaxOnVat = $packageVatValue * ($salesPackage['otherTax'] / 100);
                        $packageVatValue = $packageVatValue + $packageOtherTaxOnVat;
                    }else{
                        $packageVatValue = (($salesPackage['qty'] * $packagePrice) * ($salesPackage['vat'] / 100));
                    }
                }

                if(isset($salesPackage['otherVatValue'])){
                    $packageOtherVatValue = $salesPackage['otherVatValue'];
                }else{
                    if (isset($salesPackage['otherVat'])) {
                        if($salesPackage['otherTaxOnVat']){
                            $packageOtherVatValue = (($salesPackage['qty'] * $packagePrice) * ($salesPackage['otherVat'] / 100));
                            $packageOtherTaxOnOtherVat = $packageOtherVatValue * ($salesPackage['otherTax'] / 100);
                            $packageOtherVatValue = $packageOtherVatValue + $packageOtherTaxOnOtherVat;
                        }else{
                            $packageOtherVatValue = (($salesPackage['qty'] * $packagePrice) * ($salesPackage['otherVat'] / 100));
                        }
                    } else {
                        $packageOtherVatValue = 0;
                    }
                }

                $inclusivePrice = isset($salesPackage['inclusivePrice']) ? $salesPackage['inclusivePrice'] : $packageTotal / $salesPackage['qty'];
                if ($inclusiveMenuTemplateID && $packagePrice == $inclusivePrice) {
                    $packagePrice = floatval($this->calculateNetPrice($salesPackage));
                }

                $discountValue = isset($salesPackage['discountValue']) ? $salesPackage['discountValue'] : 0;
                $updateModel->salesMenu[$i]['packages'][$j] = [
                    'ID' => $j,
                    'salesNum' => '',
                    'menuGroupID' => $menuGroupID,
                    'menuID' => $salesPackage['menuID'],
                    'menuName' => $menuModel[$salesPackage['menuID']]->menuName,
                    'menuShortName' => $menuModel[$salesPackage['menuID']]->menuShortName,
                    'menuFlagTax' => isset($salesPackage['menuFlagTax']) ? $salesPackage['menuFlagTax'] : 0,
                    'qty' => $salesPackage['qty'],
                    'originalPrice' => $packagePrice,
                    'price' => $packagePrice,
                    'inclusivePrice' => $inclusiveMenuTemplateID ? $inclusivePrice : 0,
                    'displayPriceValue' => $inclusivePrice,
                    'discount' => 0,
                    'discountValue' => $discountValue,
                    'discountTotal' => 0,
                    'otherTax' => $salesPackage['otherTax'],
                    'otherTaxValue' => isset($salesPackage['otherTaxValue']) ? $salesPackage['otherTaxValue'] : (($salesPackage['qty'] * $packagePrice) * ($salesPackage['otherTax'] / 100)),
                    'vat' => $salesPackage['vat'],
                    'vatValue' => $packageVatValue,
                    'otherVat' => isset($salesPackage['otherVat']) ? $salesPackage['otherVat'] : 0,
                    'otherVatValue' => $packageOtherVatValue,
                    'otherTaxOnVat' => $salesPackage['otherTaxOnVat'],
                    'total' => $packageTotal,
                    'notes' => $this->removeEmoji($salesPackage['notes']),
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

                if(isset($salesExtra['vatValue'])){
                    $extraVatValue = $salesExtra['vatValue'];
                }else{
                    if($salesExtra['otherTaxOnVat']){
                        $extraVatValue = (($salesExtra['qty'] * $extraPrice) * ($salesExtra['vat'] / 100));
                        $extraOtherTaxOnVat = $extraVatValue * ($salesExtra['otherTax'] / 100);
                        $extraVatValue = $extraVatValue + $extraOtherTaxOnVat;
                    }else{
                        $extraVatValue = (($salesExtra['qty'] * $extraPrice) * ($salesExtra['vat'] / 100));
                    }
                }

                if(isset($salesExtra['otherVatValue'])){
                    $extraOtherVatValue = $salesExtra['otherVatValue'];
                }else{
                    if (isset($salesExtra['otherVat'])) {
                        if($salesExtra['otherTaxOnVat']){
                            $extraOtherVatValue = (($salesExtra['qty'] * $extraPrice) * ($salesExtra['otherVat'] / 100));
                            $extraOtherTaxOnOtherVat = $extraOtherVatValue * ($salesExtra['otherTax'] / 100);
                            $extraOtherVatValue = $extraOtherVatValue + $extraOtherTaxOnOtherVat;
                        }else{
                            $extraOtherVatValue = (($salesExtra['qty'] * $extraPrice) * ($salesExtra['otherVat'] / 100));
                        }
                    } else {
                        $extraOtherVatValue = 0;
                    }
                }

                $inclusivePrice = isset($salesExtra['inclusivePrice']) ? $salesExtra['inclusivePrice'] : $extraTotal / $salesExtra['qty'];
                if ($inclusiveMenuTemplateID && $extraPrice == $inclusivePrice) {
                    $extraPrice = floatval($this->calculateNetPrice($salesExtra));
                }

                $discountValue = isset($salesExtra['discountValue']) ? $salesExtra['discountValue'] : 0;
                $updateModel->salesMenu[$i]['extras'][$k] = [
                    'ID' => $k,
                    'salesNum' => '',
                    'menuExtraID' => $salesExtra['menuExtraID'],
                    'menuExtraName' => $menuExtraModel[$salesExtra['menuExtraID']]->menuExtraName,
                    'menuExtraShortName' => $menuExtraModel[$salesExtra['menuExtraID']]->menuExtraShortName,
                    'menuFlagTax' => isset($salesExtra['menuFlagTax']) ? $salesExtra['menuFlagTax'] : 0,
                    'qty' => $salesExtra['qty'],
                    'price' => $extraPrice,
                    'inclusivePrice' => $inclusiveMenuTemplateID ? $inclusivePrice : 0,
                    'displayPriceValue' => $inclusivePrice,
                    'discount' => 0,
                    'discountValue' => $discountValue,
                    'discountTotal' => 0,
                    'otherTax' => $salesExtra['otherTax'],
                    'otherTaxValue' => isset($salesExtra['otherTaxValue']) ? $salesExtra['otherTaxValue'] : (($salesExtra['qty'] * $extraPrice) * ($salesExtra['otherTax'] / 100)),
                    'vat' => $salesExtra['vat'],
                    'vatValue' => $extraVatValue,
                    'otherVat' => isset($salesExtra['otherVat']) ? $salesExtra['otherVat'] : 0,
                    'otherVatValue' => $extraOtherVatValue,
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
                $this->notifSelfOrderError($bookModel->getErrors());
                $transaction->rollBack();
                return false;
            }

            $updateModel->salesNum = $bookModel->salesNum;
            if (!$updateModel->save()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    Yii::warning($errMsg);
                    $this->notifSelfOrderError($errMsg);
                } else {
                    Yii::warning($updateModel->getErrors());
                    $this->notifSelfOrderError($updateModel->getErrors());
                }
                $transaction->rollBack();
                return false;
            }

            $transaction->commit();
            return $updateModel->salesModel;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::warning($ex);
            return false;
        }
    }

    public function preSave($salesNum, $paymentMethodFs = null, $paymentTotalFs = null) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($salesNum) {
                // Lock Sales Head row by Sales Number
                $lockSalesHeadQuery = SalesHead::find()
                    ->where(['salesNum' => $salesNum])
                    ->createCommand()
                    ->getRawSql();

                SalesHead::findBySql($lockSalesHeadQuery . ' FOR UPDATE')->one();
            }

            $result = $this->savePayment($salesNum, $paymentMethodFs, $paymentTotalFs);

            if ($transaction->isActive) {
                $transaction->commit();
            }
            
            return $result;
        } catch (Exception $ex) {
            if ($transaction->isActive) {
                try {
                    $transaction->rollBack();
                } catch (Exception $e_rollback) {
                    Yii::error($e_rollback, 'Rollback error');
                }
            }
            $this->addError('salesMenu', $ex->getMessage());
            return false;
        }
    }

    public function savePayment($salesNum, $paymentMethodFs=null, $paymentTotalFs=null) {
        $this->paymentMethod = $paymentMethodFs ? $paymentMethodFs : $this->paymentMethod;
        $this->paymentTotal = $paymentTotalFs ? $paymentTotalFs : $this->paymentTotal;
        $salesPaymentExternals = [];
        if ($this->paymentMethod == 'external') {
            $salesPaymentIndex = 1;
            foreach ($this->orderPayment as $payment) {
                $paymentMethod = PaymentMethod::find()->where(['=','paymentMethodID',$payment['paymentMethodID']])->one();
                $salesPaymentExternals[] = [
                    'ID' => $salesPaymentIndex,
                    'salesNum' => $salesNum,
                    'coaNo' => $paymentMethod->coaNo,
                    'paymentMethodID' => $paymentMethod->paymentMethodID,
                    'paymentMethodTypeID' => $paymentMethod->paymentMethodTypeID,
                    'paymentMethodName' => $paymentMethod->paymentMethodName,
                    'paymentMethodChild' => '',
                    'flagAuthorization' => 0,
                    'paymentAmount' => $payment['paymentAmount'],
                    'fullPaymentAmount' => $this->paymentTotal,
                    'notes' => '',
                    'selfOrderID' => $this->orderID
                ];
                $salesPaymentIndex++;
            }
        } else if (!isset($this->paymentMethodCode)) {
            $paymentMethodID = MapSelfOrderPaymentMethod::find()
                ->select("paymentMethodID")
                ->where([
                    "selfOrderPaymentMethodID" => $this->paymentMethod,
                    "branchID" => Setting::getCurrentBranch()
                ])
                ->scalar();
            
            $paymentMethod = PaymentMethod::findOne($paymentMethodID);
        } else {
            $paymentMethod = PaymentMethod::findOne(['paymentMethodCode' => $this->paymentMethodCode]);
            if ($this->flagEdcPayment && isset($this->edcPayment['paymentMethodID'])) {
                $paymentMethod = PaymentMethod::findOne(['paymentMethodID' => $this->edcPayment['paymentMethodID']]);
            }
        }
        
        $paymentModel = new SavePayment();
        $paymentModel->tableID = 0;
        $paymentModel->salesNum = $salesNum;
        $paymentModel->salesVoucher = null;
        $paymentModel->flagSavePaymentFs = $this->flagSavePaymentFs;
        if ($this->paymentMethod == 'external' ) {
            $paymentModel->salesPayment = $salesPaymentExternals;
        } else if($this->flagEdcPayment) {
            $paymentModel->salesPayment[] = [
                'ID' => 1,
                'accountName' => $this->edcPayment['accountName'],
                'bankName' => $this->edcPayment['bankName'],
                'cardNumber' => $this->edcPayment['cardNumber'],
                'cardNumberValidationTypeID' => $this->edcPayment['cardNumberValidationTypeID'],
                'edcPort' => $this->edcPayment['edcPort'],
                'edcTerminalID' => $this->edcPayment['edcTerminalID'],
                'edcWssUrl' => $this->edcPayment['edcWssUrl'],
                'fixedAmount' => $this->edcPayment['fixedAmount'],
                'flagEdcActive' => $this->edcPayment['flagEdcActive'],
                'flagMandatoryCardNumber' => $this->edcPayment['flagMandatoryCardNumber'],
                'flagMandatoryVerificationCode' => $this->edcPayment['flagMandatoryVerificationCode'],
                'paymentMethodCode' => $this->edcPayment['paymentMethodCode'],
                'posExternalPaymentID' => $this->edcPayment['posExternalPaymentID'],
                'traceNumber' => $this->edcPayment['traceNumber'],
                'verificationCode' => $this->edcPayment['verificationCode'],
                'salesNum' => $salesNum,
                'coaNo' => $paymentMethod->coaNo,
                'paymentMethodID' => $paymentMethod->paymentMethodID,
                'paymentMethodTypeID' => $paymentMethod->paymentMethodTypeID,
                'paymentMethodName' => $paymentMethod->paymentMethodName,
                'paymentMethodChild' => '',
                'flagAuthorization' => 0,
                'paymentAmount' => $this->paymentTotal,
                'fullPaymentAmount' => $this->paymentTotal,
                'notes' => '',
                'selfOrderID' => $this->orderID,
            ];
        } else {
            //@notes: not kiosk voucher online
            if(!$this->flagVoucherPayment) {
                $isExternalOrderPaymentMethod = MapSelfOrderPaymentMethod::find()
                    ->where([
                        "paymentMethodID" => $paymentMethod->paymentMethodID,
                        "branchID" => Setting::getCurrentBranch()
                    ])
                    ->one(); //external order payment method: grab, gofood, hubster

                $paymentModel->salesPayment[] = [
                    'ID' => 1,
                    'salesNum' => $salesNum,
                    'coaNo' => $paymentMethod->coaNo,
                    'paymentMethodID' => $paymentMethod->paymentMethodID,
                    'paymentMethodTypeID' => $paymentMethod->paymentMethodTypeID,
                    'paymentMethodName' => $paymentMethod->paymentMethodName,
                    'paymentMethodChild' => '',
                    'flagAuthorization' => 0,
                    'paymentAmount' => $this->paymentTotal,
                    'fullPaymentAmount' => $this->paymentTotal,
                    'notes' => '',
                    'selfOrderID' => $this->orderID,
                    'posExternalPaymentID' => $paymentMethod->posExternalPaymentID,
                    'isExternalOrderPaymentMethod' => $isExternalOrderPaymentMethod && in_array($isExternalOrderPaymentMethod->selfOrderPaymentMethodID, ["grab","gofood","hubster"]) ? true : false
                ];
            }
        }
        
        //untuk save voucher payment
        if($this->orderPayment && $this->paymentMethod != 'external') {
            foreach($this->orderPayment as $orderPayment) {
                $orderPayment = (object) $orderPayment;
                $paymentModel->salesPayment[] = [
                    'ID' => 1,
                    'salesNum' => $salesNum,
                    'coaNo' => $orderPayment->coaNo,
                    'paymentMethodID' => $orderPayment->paymentMethodID,
                    'voucherCode' => $orderPayment->voucherCode,
                    'paymentMethodTypeID' => '',
                    'paymentMethodName' => '',
                    'paymentMethodChild' => '',
                    'flagAuthorization' => 0,
                    'paymentAmount' => $orderPayment->paymentAmount,
                    'fullPaymentAmount' => $orderPayment->fullPaymentAmount,
                    'notes' => $orderPayment->notes,
                    'selfOrderID' => $this->orderID
                ];
            }
        }

        // @note: proses ini belum masuk ke table voucherusage, hanya masuk ke tr_salespayment
        if($this->orderVoucherUsage) {
            foreach($this->orderVoucherUsage as $orderVoucherUsage) {
                $orderVoucherUsage = (object) $orderVoucherUsage;
                $paymentModel->salesPayment[] = [
                    'ID' => 1,
                    'salesNum' => $salesNum,
                    'coaNo' => $orderVoucherUsage->coaNo,
                    'paymentMethodID' => $orderVoucherUsage->paymentMethodID,
                    'voucherCode' => $orderVoucherUsage->voucherCode,
                    'paymentMethodTypeID' => '',
                    'paymentMethodName' => '',
                    'paymentMethodChild' => '',
                    'flagAuthorization' => 0,
                    'paymentAmount' => $orderVoucherUsage->paymentAmount,
                    'fullPaymentAmount' => $orderVoucherUsage->fullPaymentAmount,
                    'notes' => $orderVoucherUsage->notes,
                    'selfOrderID' => $this->orderID
                ];
            }
        }

        //untuk save voucher payment at kiosk
        if(isset($this->vouchers)) {
            // @notes takes online voucher paling kecil
            $paymentMethodVoucherKiosk = PaymentMethod::checkPaymentMethodEsbVoucherKioskOnly();

            foreach($this->vouchers as $vouchers) {
                $vouchers = (object) $vouchers;
                $paymentModel->salesPayment[] = [
                    'ID' => 1,
                    'salesNum' => $salesNum,
                    'coaNo' => $paymentMethodVoucherKiosk->coaNo,
                    'paymentMethodID' => $paymentMethodVoucherKiosk->paymentMethodID,
                    'voucherCode' => $vouchers->voucherID,
                    'voucherCategoryID' => $paymentMethodVoucherKiosk->voucherCategoryID,
                    'paymentMethodTypeID' => '',
                    'paymentMethodName' => '',
                    'paymentMethodChild' => '',
                    'flagAuthorization' => 0,
                    'paymentAmount' => $vouchers->voucherAmount,
                    'fullPaymentAmount' => $vouchers->voucherAmount,
                    'notes' => $vouchers->notes,
                    'selfOrderID' => $this->orderID
                ];
            }
        }

        try {
            if (!$paymentModel->save()) {
                $this->getError('payment', $paymentModel->errors);
                foreach ($paymentModel->attributes() as $attribute) {
                    if (isset($paymentModel->errors[$attribute])) {
                        $this->notifSelfOrderError($paymentModel->errors[$attribute][0]);
                    }
                }
                return false;
            } else {
                $this->tableID = $paymentModel->salesModel->tableID;
                if($this->flagSavePaymentFs) {
                    Logging::save($paymentModel->salesNum, Logging::SAVE_PAYMENT_ESO, $paymentModel->getAttributes());
                }
                else {
                Logging::save($paymentModel->salesNum, Logging::SAVE_PAYMENT_KIOSK, $paymentModel->getAttributes());
                }
                return $paymentModel->salesModel->billNum;
            }
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
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
                        throw new Exception("Unable to save sales info");
                    }
                }
            }

            if ($this->tableName) {
                $salesInfoTableNameModel = new SalesInfo();
                $salesInfoTableNameModel->salesNum = $salesNum;
                $salesInfoTableNameModel->key = 'Table Name';
                $salesInfoTableNameModel->value = $this->tableName;
                if (!$salesInfoTableNameModel->save()) {
                    throw new Exception("Unable to save sales info");
                }
            }
            
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            $this->notifSelfOrderError($ex->getMessage());
            $transaction->rollBack();
            return false;
        }
    }

    public function saveCustomerTransaction($salesNum, $customerName, $customerEmail, $customerPhoneNumber) {
        try {
            if ($salesNum && $customerName && $customerEmail && $customerPhoneNumber) {
                $transaction = Yii::$app->db->beginTransaction();
                $customerTransaction = new CustomerTransaction();
                $customerTransaction->salesNum = $salesNum;
                $customerTransaction->fullName = $customerName;
                $customerTransaction->email = $customerEmail;
                $customerTransaction->phoneNumber = $customerPhoneNumber;

                if (!$customerTransaction->save()) {
                    throw new Exception("Unable to save customer transaction");
                }
                $transaction->commit();
            }
            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            $this->notifSelfOrderError($ex->getMessage());
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
            Yii::warning($ex);
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
            Yii::warning($ex);
        }

        return true;
    }

    public function notifSelfOrderError($errMsg) {
        if ($this->orderID && $this->externalApi == 0 && $errMsg) {
            if ($this->ezoServerID === 'qoqi') {
                $selfOrderApi = Setting::getQoQiApiUrl();
            } else {
                $selfOrderApi = Setting::getEsoQsApiUrl();
            }
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

    public function notifSelfOrderApi($salesHead, $billNum) {
        try {
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
                'paymentMethodName' => $salesPayment->paymentMethod->paymentMethodName,
                'paymentAmount' => (float) $salesPayment->paymentAmount
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
                Yii::warning($voidModel->errors);
                return false;
            }
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            return false;
        }

        return true;
    }

    private function calculateTotal($detail) {
        $subtotal = (float) $detail['price'] * $detail['qty'];
        $otherTaxTotal = (float) $subtotal * $detail['otherTax'] / 100;
        $vatTotal = (float) ($subtotal + ($detail['otherTaxOnVat'] ? $otherTaxTotal : 0)) * $detail['vat'] / 100;

        return ceil($subtotal + $otherTaxTotal + $vatTotal);
    }

    private function calculateNetPrice($detail) {
        $vatOrOtherVat = isset($detail['menuFlagTax']) && $detail['menuFlagTax'] === 2 ? $detail['otherVat'] : $detail['vat'];
        $netPrice = 0;
        if ($detail['otherTaxOnVat']) {
            $netPrice = ($detail['total'] * 100 / (100 + $vatOrOtherVat) * 100 / (100 + $detail['otherTax']) / $detail['qty']);
        } else {
            $netPrice = ($detail['total'] * 100 / (100 + $vatOrOtherVat + $detail['otherTax']) / $detail['qty']);
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

    private function removeEmoji($text) {
        return preg_replace('/[^ -\x{2122}]\s+|\s*[^ -\x{2122}]/u','', $text);
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

    public static function defineQueueNumKiosk($salesNum, $currentQueueNum) {
        $printingSettings = Setting::getPrintingSettings();
        $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;
        if ($printingAfterPayment && $printingAfterPayment == 1) {
            $salesModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
            if ($salesModel) {
                if ($salesModel->queueNum && $salesModel->queueNum > 0) {
                    return intval($salesModel->queueNum);
                } else {
                    $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                    $salesModel->queueNum = SalesHead::getQueueNumber($salesModel->salesNum, $salesModel->salesDate, $salesModel->branchID, true);
                    if (!$salesModel->save()) {
                        Yii::error($salesModel->errors);
                        return $currentQueueNum;
                    }

                    return intval($salesModel->queueNum);
                }
            }
        }

        return $currentQueueNum;

    }

}
