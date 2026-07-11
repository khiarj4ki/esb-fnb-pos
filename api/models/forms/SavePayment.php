<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BranchEvent;
use app\models\BrandSetting;
use app\models\CustomNumber;
use app\models\HubHost;
use app\models\HubMenu;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\PaymentMethod;
use app\models\PromotionHead;
use app\models\ReceiptTextHead;
use app\models\SalesContactInfo;
use app\models\SalesDepositWithdrawal;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesPayment;
use app\models\SalesPaymentGateway;
use app\models\SalesPaymentStiReader;
use app\models\SalesVoucher;
use app\models\SalesVoucherOnline;
use app\models\SalesVoucherUsage;
use app\models\Setting;
use app\models\Station;
use app\models\Voucher;
use app\services\http_helper\HttpHelperService;
use DateTime;
use Error;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property string $billNum
 * @property array $salesVoucher
 * @property array $salesPayment
 * 
 * PRIVATE
 * @property boolean $updateMode
 * @property SalesHead $salesModel
 * @property string $totalDeposit
 * @property string $totalVoucher
 * @property string $totalPayment
 * @property int $otherCostCount
 */
class SavePayment extends Model {
    public $tableID;
    public $salesNum;
    public $billNum;
    public $salesVoucher;
    public $salesPayment;
    public $updateMode = false;
    public $salesModel;
    public $totalDeposit = 0;
    public $totalVoucher = 0;
    public $totalPayment = 0;
    public $otherCostCount;
    public $salesHeadArr = [];
    public $headArr = [];
    public $discountTotalHead;
    public $grandTotalHead;
    public $burnPoints = 0;
    public $externalTransaction = null;
    public $terminalID;
    public $allGrandTotal = 0;
    public $allOrderFee = 0;
    public $ezoCodPayment = false;
    public $voucherArrayData = [];
    public $flagSavePaymentFs = false;
    public $paymentMethodWithAuth;
    public $paymentMethodID;
    public $salesNumMenus = [];
    public $responseErrorMessage = null;
    public $remainingBalance;
    public $selfOrderPaymentMethodID = null;

    private $depositPaymentMethodID = null;
    private $depositSourceID = null;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID'], 'integer'],
            [['salesNum', 'billNum'], 'string', 'max' => 20],
            [['tableID'], 'validateTable'],
            [['salesVoucher', 'terminalID', 'paymentMethodWithAuth', 'paymentMethodID', 'salesNumMenus','remainingBalance', 'selfOrderPaymentMethodID'], 'safe'],
            [['salesVoucher'], 'validateVoucher'],
            [['salesPayment'], 'validatePayment', 'skipOnEmpty' => false]
        ];
    }

    public function validateTable($attribute) {
        if ($this->tableID != 0) {
            $salesHeadCheck = SalesHead::findOne($this->salesNum);
            if ($salesHeadCheck) {
                if ($salesHeadCheck->salesDateOut) {
                    $this->salesModel = SalesHead::findMainSales($this->tableID,
                            $this->salesNum);
                } else {
                    $this->salesModel = SalesHead::findMainSales($this->tableID,
                            $this->salesNum);
                }
            } else {
                $this->addError($attribute, 'Invalid sales number');
            }
        } else {
            $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        }
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }
        if ($this->salesModel->salesDateOut) {
            $this->updateMode = true;
        }

        $platformFeeList = SalesHead::getSalesPlatformFee($this->salesModel->salesNum);
        if ($platformFeeList) {
            $this->salesModel->platformFee = $platformFeeList;
        }
    }

    public function validateVoucher($attribute) {
        $settings = Setting::getPrintingSettings();
        $purchaseVoucher = 'offline';
        if (array_key_exists('Voucher Management', $settings)) {
            $purchaseVoucher = $settings['Voucher Management'];
        }

        foreach ($this->salesVoucher as $salesVoucher) {
            if ($purchaseVoucher == 'offline') {
                $voucherModel = Voucher::findNotActive()
                    ->andWhere(['voucherID' => $salesVoucher['voucherID']])
                    ->one();
                if (!$voucherModel) {
                    $this->addError($attribute,
                        'Invalid voucher ID ' . $salesVoucher['voucherID']);
                    break;
                }
            }
        }
    }

    public function validatePayment($attribute) {
        $paymentMethodName = "";
        $paymentMethodIDs = [];
        $valid = true;
        $isExternalOrderPaymentMethod = false;
        $vouchers = [];
        $otherVouchers = [];
        $duplicateVouchers = [];

        $depositPaymentMethodID = PaymentMethod::findActive()
            ->andWhere(['paymentMethodTypeID' => 6])
            ->column();

        if ($this->salesPayment) {
            foreach ($this->salesPayment as $salesPayment) {
                $this->totalPayment += $salesPayment['paymentAmount'];
                if (in_array($salesPayment['paymentMethodID'],
                        $depositPaymentMethodID)) {
                    $this->totalDeposit += $salesPayment['paymentAmount'];
                    $this->depositPaymentMethodID = $salesPayment['paymentMethodID']; 

                    /**
                     * @notes :
                     * depositSourceID = 1 : Flexible (has been converted to 2 or 3 by conditions on the frontend)
                     * depositSourceID = 2 : Internal
                     * depositSourceID = 3 : Loyalty
                     */
                    $this->depositSourceID = $salesPayment['depositSourceID'];
                }
                $paymentMethodIDs[] = $salesPayment['paymentMethodID'];
                $isExternalOrderPaymentMethod = isset($salesPayment['isExternalOrderPaymentMethod']) ? $salesPayment['isExternalOrderPaymentMethod'] : false;

                //@notes: Collect all voucher payment data in an array
                if (isset($salesPayment['paymentMethodTypeID']) && isset($salesPayment['voucherCode'])) {
                    $voucherCode = $salesPayment['voucherCode'];
                    $paymentMethodTypeID = $salesPayment['paymentMethodTypeID'];
                    if($paymentMethodTypeID == 4 && $salesPayment['paymentMethodID'] != '-1'){
                        if(in_array($voucherCode, $vouchers)){
                            $valid = false;
                            $duplicateVouchers[] = $voucherCode;
                        }
                        $vouchers[] = $voucherCode;
                    }else if($paymentMethodTypeID == 5){
                        if(in_array($voucherCode, $otherVouchers)){
                            $duplicateVouchers[] = $voucherCode;
                        }
                        $otherVouchers[] = $voucherCode;
                    }
                }
            }

            if(!empty($duplicateVouchers)){
                $valid = false;
                $this->addError($attribute,
                    'Duplicate Voucher Detected. ');
                $this->addError($attribute,
                    implode(", ", $duplicateVouchers) . ' was used more than once.');
            }
            $this->voucherArrayData = $vouchers;
        }

        if ($this->salesVoucher) {
            foreach ($this->salesVoucher as $salesVoucher) {
                $this->totalVoucher += $salesVoucher['voucherSalesPrice'];
            }
        }

        // @Notes: 7 = Other Cost. If exists cannot have more than 1 payment method
        $this->otherCostCount = PaymentMethod::findActive()
            ->andWhere(['IN', 'paymentMethodID', $paymentMethodIDs])
            ->andWhere(['paymentMethodTypeID' => 7])
            ->count();
        if (count($paymentMethodIDs) > 1 && $this->otherCostCount > 0) {
            $valid = false;
            $this->addError($attribute,
                'Invalid payment method. Other cost cannot be combined with other payment method');
        }

        if ($valid && !in_array($this->salesModel->externalMembershipTypeID, ['memberid', 'capillary', 'capillaryV2', 'uvloyalty'])) {
            if ($this->totalDeposit > 0 && !$this->updateMode) {
                if ($this->salesModel->externalMembershipTypeID === 'general' && $this->salesModel->flagExternalAPI === 1 && $this->depositSourceID == 3) {
                    $balance = ExternalMember::getBalance($this->salesModel->flagExternalMemberPhone, $this->salesModel->flagExternalCardID);
                    if($balance) {
                        $this->burnPoints = $this->totalDeposit / $balance['pointConversion'];
                        if($this->burnPoints > $balance['totalAvailablePoints']) {
                            $valid = false;
                            $this->addError($attribute,
                                'Invalid payment method. Deposit (Point) exceeds outstanding');
                        }
                    } else {
                        $valid = false;
                        $this->addError($attribute,
                            'External member not found');
                    }
                } else {
                    // @notes: Pengecekan hasaccess deposit receivable, skip pengecekan outstanding jika memiliki akses.
                    $userAccessA = array_filter(Yii::$app->user->identity->userAccess, function($userAccess) {
                        return $userAccess['posAccessID'] === 'A';
                    });
                    $hasAccessDepositReceivable = $userAccessA ? count(array_filter($userAccessA[0]['access'], function($access) {
                        return $access && $access['filterAccessID'] === 'A17' && $access['hasAccess'] === 1;
                    })) === 1 : false;

                    if (!$hasAccessDepositReceivable) {
                        $outstandingDeposit = MemberDeposit::getOutstandingDeposit($this->salesModel->memberCode);
                        
                        $memberMode = Setting::getMemberMode();
                        if ($memberMode && $memberMode == 'online') {
                            $memberDepositOnlineModel = new MemberDepositWithdrawalOnline();
                            $memberDepositOnlineModel->memberCode = $this->salesModel->memberCode;
                            $response = $memberDepositOnlineModel->getMember();
                            if ($response && isset($response['activeBalance'])) {
                                $outstandingDeposit = $response['activeBalance'];
                            }
                        }

                        if ($outstandingDeposit < $this->totalDeposit) {
                            $valid = false;
                            $this->addError($attribute,
                                'Invalid payment method. Deposit exceeds outstanding');
                        }
                    }
                }
                
            }
        }

        if ($this->salesModel)
        {
            $checkGrandTotal = $this->salesModel->grandTotal - $this->salesModel->roundingTotal;
            $checkValidate = $checkGrandTotal == 0 &&
            $this->salesModel->subtotal == 0 &&
            $this->salesModel->otherTaxTotal == 0 &&
            $this->salesModel->vatTotal == 0 &&
            $this->salesModel->otherVatTotal == 0;
            // force recalculate
            if ($checkValidate && $this->totalPayment > 0)
            {
                if (!$this->salesModel->save())
                {
                    $this->addError($attribute, 'Failed update sales when recalculate');
                }
            }
        }


        if ($valid && !$isExternalOrderPaymentMethod) {
            if ($this->otherCostCount > 0) {
                $grandTotal = SalesHead::getTotal($this->salesModel->salesNum,
                        'subtotal - discountTotal - menuDiscountTotal');
            } else {
                $grandTotal = SalesHead::getTotal($this->salesModel->salesNum,
                        'grandTotal-roundingTotal');
            }

            if ($this->totalPayment < $grandTotal) {
                $this->addError($attribute, 'Invalid payment amount');
            }
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction('Serializable');
        $dataExternalMemberLogging = null;
        $epassVoucherCodes = null;
        $giftVoucherCodes = null;
        $dataPluxeeLogging = null;
        $ultraVoucherLoggingData = null;
        try {
            SalesPayment::deleteAll(['salesNum' => $this->salesModel->salesNum]);
            
            $salesLinkModel = SalesHead::findLinkSalesHeads($this->salesModel->salesNum);
            if ($salesLinkModel) {
                foreach ($salesLinkModel as $linkSales) {
                    if ($linkSales->salesPayments) {
                        foreach ($linkSales->salesPayments as $linkPayment) {
                            if ($linkPayment->paymentMethod->voucherSourceID == 14) {
                                SalesPayment::deleteAll(['salesNum' => $linkSales->salesNum]);
                            }
                        }
                    }
                }
            }

            $settings = Setting::getPrintingSettings();
            $purchaseVoucher = 'offline';
            if (array_key_exists('Voucher Management', $settings)) {
                $purchaseVoucher = $settings['Voucher Management'];
            }

            $hubMenu = HubMenu::find()->all();
            if ($hubMenu) {
                $this->grandTotalHead = $this->savePosMultiplePt();
            }
            // Generate MemberCode bila Apply dari Payment
            $memberModel = Member::find()
                ->select(['ms_member.memberCode'])
                ->where(['=', 'ms_member.memberCode', $this->salesModel->memberCode])
                ->one();
            $this->salesModel->memberCode = $memberModel ? $memberModel->memberCode : '';
            // END
            // @Notes: if there is voucher purchase
            if ($this->salesVoucher && !$this->updateMode) {
                $vouchers = [];
                foreach ($this->salesVoucher as $salesVoucher) {
                    if ($purchaseVoucher == 'offline') {
                        $salesVoucherModel = new SalesVoucher([
                            'attributes' => $salesVoucher
                        ]);
                        $salesVoucherModel->salesNum = $this->salesModel->salesNum;
                        if (!$salesVoucherModel->save()) {
                            Yii::error($salesVoucherModel->errors);
                            throw new Exception('Failed to save voucher purchase');
                        }
    
                        $activate = Voucher::activate($salesVoucher['voucherID']);
                        if (!$activate['status']) {
                            throw new Exception($activate['message']);
                        }
                    } else if ($purchaseVoucher == 'online') {
                        $salesVoucherOnlineModel = new SalesVoucherOnline([
                            'attributes' => $salesVoucher
                        ]);
                        $salesVoucherOnlineModel->salesNum = $this->salesModel->salesNum;
                        if (!$salesVoucherOnlineModel->save()) {
                            Yii::error($salesVoucherOnlineModel->errors);
                            throw new Exception('Failed to save voucher purchase');
                        }

                        $group = [
                            'voucherID' => $salesVoucherOnlineModel->voucherID,
                            'voucherAmount' => $salesVoucherOnlineModel->voucherAmount,
                            'voucherStartDate' => date("Y-m-d"),
                            'flagJournal' => 1
                        ];
                        $vouchers[] = $group;
                    }
                }

                if ($purchaseVoucher == 'online') {
                    $apiUrl = Setting::getApiUrl();
                    $apiKey = Setting::getApiKey();
                    $branchID = Setting::getCurrentBranch();

                    $paymentMethodModel = PaymentMethod::find()
                        ->where(['paymentMethodID' => $this->salesPayment[0]['paymentMethodID']])
                        ->one();
    
                    $client = new Client(['baseUrl' => $apiUrl]);
                    $response = $client->createRequest()
                        ->setUrl("/esb_api/voucher/activate-online-vouchers")
                        ->setMethod('POST')
                        ->addHeaders([
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $apiKey
                        ])
                        ->setData([
                            'branchID' => $branchID,
                            "vouchers" => $vouchers,
                            'salesDate' => $this->salesModel->salesDate,
                            'paymentCoaNo' => $paymentMethodModel->coaNo
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->send();
    
                    if ($response->statusCode == "200") {
                        $decodedResponse = json_decode($response->content, true);
                        if (isset($decodedResponse['status']) && $decodedResponse['status'] == false) {
                            throw new Exception($decodedResponse['message']);
                        }
                    } else {
                        throw new Exception('Connection Error!');
                    }
                }
            }

            // @Notes: if payment using member deposit
            if ($this->totalDeposit > 0 && !$this->updateMode  && $this->depositSourceID == 2) {
                $memberMode = Setting::getMemberMode();
                if ($memberMode && $memberMode == 'online') {

                    $depositWithdrawalOnlineModel = new MemberDepositWithdrawalOnline();
                    $balanceDepositMember = $depositWithdrawalOnlineModel->checkBalanceDepositMember(
                        $this->salesModel->salesNum, $this->salesModel->memberCode
                    );
                    
                    if($balanceDepositMember['status']) {
                        $sendDeposit =  true;
                    } else {
                        $sendDeposit = $depositWithdrawalOnlineModel->sendSalesDeposit(
                            $this->salesModel->salesNum, $this->salesModel->memberCode, $this->totalDeposit
                        );
                    }

                    if (!$balanceDepositMember['status'] && $sendDeposit && isset($depositWithdrawalOnlineModel->responseData["withdrawalDetails"])) {
                        // @first create transaction
                        $depositSyncDate = date('Y-m-d H:i:s');
                        foreach ($depositWithdrawalOnlineModel->responseData["withdrawalDetails"] as $detail) {
                            $salesDepositModel = new SalesDepositWithdrawal();
                            $salesDepositModel->salesNum = $this->salesModel->salesNum;
                            $salesDepositModel->memberDepositNum = $detail['memberDepositNum'];
                            $salesDepositModel->paymentTotal = $detail['total'];
                            if (!$salesDepositModel->save()) {
                                Yii::error($salesDepositModel->errors);
                                throw new Exception('Failed to save sales deposit');
                            } else {
                                //update usedDepositTotal
                                $depositModel = MemberDeposit::findOne(['memberDepositNum' => $detail['memberDepositNum']]);
                                if ($depositModel) {
                                    $newUsedDepositTotal = $depositModel->usedDepositTotal + $detail['total'];
                                    $depositModel->usedDepositTotal = $newUsedDepositTotal;
                                    $depositModel->syncDate = $depositSyncDate;
                                    if (!$depositModel->save()) {
                                        Yii::error($depositModel->errors);
                                        throw new Exception('Failed to update member deposit usage');
                                    }
                                }
                            }
                        }
                    } elseif ($balanceDepositMember['status'] && $sendDeposit && isset($balanceDepositMember['data'])) {
                        // @burn deposit already created before for this second transaction
                            $depositSyncDate = date('Y-m-d H:i:s');
                            $salesDepositModel = new SalesDepositWithdrawal();
                            $salesDepositModel->salesNum = $this->salesModel->salesNum;
                            $salesDepositModel->memberDepositNum = $balanceDepositMember['data']['salesNum'];
                            $salesDepositModel->paymentTotal = $balanceDepositMember['data']['paymentTotal'];
                            if (!$salesDepositModel->save()) {
                                Yii::error($salesDepositModel->errors);
                                throw new Exception('Failed to save sales deposit');
                            } else {
                                //update usedDepositTotal
                                $depositModel = MemberDeposit::findOne(['memberDepositNum' => $balanceDepositMember['data']['salesNum']]);
                                if ($depositModel) {
                                    $newUsedDepositTotal = $depositModel->usedDepositTotal + $balanceDepositMember['data']['paymentTotal'];
                                    $depositModel->usedDepositTotal = $newUsedDepositTotal;
                                    $depositModel->syncDate = $depositSyncDate;
                                    if (!$depositModel->save()) {
                                        Yii::error($depositModel->errors);
                                        throw new Exception('Failed to update member deposit usage');
                                    }
                                }
                        }
                    } else {
                        $errorSendDeposit = "ERR_DEPOSIT:SERVER_ERROR";
                        if ($depositWithdrawalOnlineModel->responseData && isset($depositWithdrawalOnlineModel->responseData["message"])) {
                            switch ($depositWithdrawalOnlineModel->responseData["message"]) {
                                case '"Member not found"':
                                    $errorSendDeposit = "ERR_DEPOSIT:MEMBER_NOT_FOUND";
                                    break;
                                
                                case '"Available Deposit is not enough"':
                                    $errorSendDeposit = "ERR_DEPOSIT:INSUFFICIENT_BALANCE";
                                    break;
                                default:
                                    $errorSendDeposit = "ERR_DEPOSIT:" . $depositWithdrawalOnlineModel->responseData["message"];
                                    break;
                            }
                        }
                        throw new Exception($errorSendDeposit);
                    }
                } else {
                    $depositDetail = MemberDeposit::substractDeposit($this->salesModel->memberCode,
                            $this->totalDeposit, $this->depositPaymentMethodID);
                        
                    foreach ($depositDetail as $detail) {
                        $salesDepositModel = new SalesDepositWithdrawal();
                        $salesDepositModel->salesNum = $this->salesModel->salesNum;
                        $salesDepositModel->memberDepositNum = $detail['memberDepositNum'];
                        $salesDepositModel->paymentTotal = $detail['substractionAmount'];
                        if (!$salesDepositModel->save()) {
                            Yii::error($salesDepositModel->errors);
                            throw new Exception('Failed to save sales deposit');
                        }
                    }
                }
            }

            // @notes: Pluxee vouchers
            $epassVouchers = [];
            $giftVouchers = [];

            // @notes: array voucher online untuk nanti digunakan save & claim
            $internalOnlineVouchers = [];
            $internalOnlineVouchersFreeItem = [];

            // @notes: array external voucher untuk nanti digunakan save
            $internalVoucherArray = array();
            $externalVoucherArray = array();
            $externalVoucherMemberIDArray = array();
            $externalVoucherTadaArray = array();
            $externalVoucherLoyaltyArray = array();
            $externalVoucherGifteeArray = array();
            $externalVoucherCapillaryArray = array();
            $externalVoucherQwikCilverArray = array();
            $externalVoucherGlobalTixArray = array();
            $externalVoucherStampsArray = array();
            $externalVoucherCapillaryV2Array = array();
            $externalUltraVoucherArray = [];
            $salesLinkModel = null;
            if (!$this->salesPayment && ($this->salesModel->externalMembershipTypeID === 'memberid' || $this->salesModel->externalMembershipTypeID === 'esbloyalty' ||
                $this->salesModel->externalMembershipTypeID === 'looplite' || $this->salesModel->externalMembershipTypeID === 'stamps' || 
                $this->salesModel->externalMembershipTypeID === 'uvloyalty')) {
                // @Notes: Total bill = 0, submit dummy cash payment
                $cashPaymentMethodModel = PaymentMethod::find()
                    ->where(['flagActive' => 1])
                    ->andWhere(['paymentMethodTypeID' => 1])
                    ->orderBy('paymentMethodID')
                    ->one();

                if (!$cashPaymentMethodModel) {
                    throw new Exception("Payment method cash not found");
                }

                $this->salesPayment[0]['salesNum'] = $this->salesNum;
                $this->salesPayment[0]['paymentMethodID'] = $cashPaymentMethodModel->paymentMethodID;
                $this->salesPayment[0]['coaNo'] = $cashPaymentMethodModel->coaNo;
                $this->salesPayment[0]['paymentAmount'] = 0;
                $this->salesPayment[0]['fullPaymentAmount'] = 0;
            }
            // @Notes: save payment detail
            $summarySubtotal = $this->salesModel->subtotal;
            if ($this->salesPayment) {
                $totalVoucher = 0;
                foreach ($this->salesPayment as $salesPayment) {
                    $salesPaymentModel = new SalesPayment([
                        'attributes' => $salesPayment
                    ]);
                    if (!$salesPaymentModel->save()) {
                        throw new Exception('Failed to save payment');
                    }

                    if (!$this->updateMode) {
                        // @Notes: 4 = Voucher
                        $typeVoucher = $salesPaymentModel->paymentMethod->paymentMethodType->paymentMethodTypeID == 4 ? true : false;
                        $typeOtherVoucher = $salesPaymentModel->paymentMethod->paymentMethodType->paymentMethodTypeID == 5 ? true : false;
                        if ($typeVoucher) {
                            /**
                             * @notes :
                             * voucherSourceID = 2 : external Voucher MAP
                             * voucherSourceID = 3 : external Voucher Member.id
                             * voucherSourceID = 4 : external Voucher TADA
                             * voucherSourceID = 5 : external Voucher Loyalty
                             * voucherSourceID = 7 : external Voucher Giftee
                             * voucherSourceID = 8 : external Voucher Capillary
                             * voucherSourceID = 9 : external Voucher QwikCilver
                             * voucherSourceID = 10 : external Voucher GlobalTix
                             * voucherSourceID = 12 : external Voucher Capillary V2
                             * voucherSourceID = 13 : external Voucher Pluxee
                             */

                            $flagExternalVoucherAPI = in_array($salesPaymentModel->paymentMethod->voucherSourceID, [2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13, 14]) ? true : false;
                            if ($flagExternalVoucherAPI) {
                                if ($salesPaymentModel->paymentMethod->voucherSourceID == 2) {
                                    $externalVoucherArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'fullPaymentAmount' => $salesPayment['fullPaymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 3) {
                                    $externalVoucherMemberIDArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 4){
                                    $externalVoucherTadaArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 5){
                                    $externalVoucherLoyaltyArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 7){
                                    $externalVoucherGifteeArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 8){
                                    $capillaryGrandTotal = SalesHead::getTotal($this->salesModel->salesNum, 'grandTotal-roundingTotal');
                                    $externalVoucherCapillaryArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $capillaryGrandTotal,
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 9){
                                    $externalVoucherQwikCilverArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $salesPayment['paymentAmount'],
                                        'voucherCode' => $salesPayment['voucherCode'],
                                        'voucherPIN' => isset($salesPayment['voucherPIN']) ? $salesPayment['voucherPIN'] : null,
                                        'trackData' => isset($salesPayment['trackData']) ? $salesPayment['trackData'] : null,
                                        'billAmount' => (float)$this->salesModel->grandTotal
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 10) {
                                    $externalVoucherGlobalTixArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'voucherID' => $salesPayment['voucherID'],
                                        'voucherCode' => $salesPayment['voucherCode'],
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 11) {
                                    $externalVoucherStampsArray[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'voucherID' => $salesPayment['voucherID'],
                                        'voucherCode' => $salesPayment['voucherCode'],
                                        'salesPaymentID' => $salesPaymentModel->ID
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 12) {
                                    $capillaryV2GrandTotal = SalesHead::getTotal($this->salesModel->salesNum, 'grandTotal-roundingTotal');
                                    $externalVoucherCapillaryV2Array[] = (object) array(
                                        'ID' => $salesPaymentModel->ID,
                                        'paymentAmount' => $capillaryV2GrandTotal,
                                        'voucherCode' => $salesPayment['voucherCode']
                                    );
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 13) {
                                    $checkEpass = substr($salesPayment['voucherCode'], 0, 2);
                                    if ($checkEpass == 'SX') {
                                        $epassVouchers[] = $salesPayment['voucherCode'];
                                    } else {
                                        $giftVouchers[] = $salesPayment['voucherCode'];
                                    }
                                } else if ($salesPaymentModel->paymentMethod->voucherSourceID == 14) {
                                    $externalUltraVoucherArray[] = (object) array(
                                        'salesNum' => $salesPayment['salesNum'],
                                        'voucherCode' => $salesPayment['voucherCode'],
                                        'voucherType' => $salesPayment['notes'],
                                    );
                                }
                            } else {
                                if ($purchaseVoucher == 'offline') {
                                    // @ no need to claim gift voucher in POS
                                    if ($salesPayment['paymentMethodID'] != -1) {
                                        $claim = Voucher::claim($salesPaymentModel->voucherCode, $this->salesModel);
                                        if (!$claim['status']) {
                                            throw new Exception($claim['message']);
                                        }
                                    }
                                } else if ($purchaseVoucher == 'online') {
                                    $internalOnlineVouchers[] = $salesPayment['voucherCode'];
                                }
                            }
                            
                        }
                        if ($typeOtherVoucher) {
                            $paymentMethodModel = PaymentMethod::find()
                                ->where(['paymentMethodID' => $salesPaymentModel->paymentMethodID])
                                ->one();
                            
                            if($paymentMethodModel->voucherTypeID == 1) {
                                $totalVoucher += $salesPayment['paymentAmount'];
                                SalesHead::updateAll([
                                    'voucherDiscountTotal' => $totalVoucher
                                    ], ['salesNum' => $this->salesModel->salesNum]);
                                
                                $salesVoucherUsageModel = new SalesVoucherUsage();
                                $salesVoucherUsageModel->salesNum = $this->salesModel->salesNum;
                                $salesVoucherUsageModel->paymentMethodID = $salesPaymentModel->paymentMethodID;
                                $salesVoucherUsageModel->voucherCode = $salesPaymentModel->voucherCode;
                                $salesVoucherUsageModel->notes = $salesPaymentModel->notes;
                                $salesVoucherUsageModel->coaNo = $paymentMethodModel->coaNo;
                                $salesVoucherUsageModel->voucherAmount = $salesPayment['paymentAmount'];
                                $salesVoucherUsageModel->fullVoucherAmount = $salesPayment['fullPaymentAmount'];
                                if (!$salesVoucherUsageModel->save()) {
                                    Yii::error($salesVoucherUsageModel->errors);
                                    throw new Exception('Failed to save voucher purchase');
                                }
                            }

                            if ($paymentMethodModel->voucherTypeID == 2 && $paymentMethodModel->voucherSourceID == 1) {
                                $internalVoucherArray[] = (object) array(
                                    'paymentAmount' => $salesPayment['paymentAmount'],
                                    'voucherCode' => $salesPayment['voucherCode']
                                );
                            }
                        }
                    }

                    //@notes : insert sti reader
                    if(isset($salesPayment['posExternalPaymentID']) && $salesPayment['posExternalPaymentID'] === 'emoney') {
                        $branchID = Setting::getCurrentBranch();

                        $readerSetting = new SalesPaymentStiReader();
                        $readerSetting->TID = $salesPayment['edcTerminalID'];
                        $readerSetting->MID = Yii::$app->params['stiReaderMID'];
                        $readerSetting->salesNum = $salesPayment['salesNum'];
                        $readerSetting->branchID = $branchID;
                        $readerSetting->remainBalance = $this->remainingBalance;
                        $readerSetting->createdBy = Yii::$app->user->identity->username;
                        $readerSetting->createdDate = date('Y-m-d H:i:s');

                        if (!$readerSetting->save()) {
                            throw new Exception('Failed to save payment sti reader');
                        }
                    }
                }

                // @notes: proses check promotion voucher code
                $promotionVoucherCode = false;

                // @notes: check sales link
                $salesLinkModel = SalesHead::findLinkSalesHeads($this->salesModel->salesNum);
                $salesLinkPromotionVoucherCodes = [];
                $salesLinkPromotionGifteeVoucherCodes = [];
                if ($salesLinkModel) {    
                    foreach ($salesLinkModel as $salesLink) {
                        $isMemberID = false;
                        $isSameMember = false;
                        $summarySubtotal += $salesLink->subtotal;
                        // check member sales link is memberid / loyalty / looplite
                        if ($salesLink->flagExternalMemberID) {
                            if ($salesLink->flagExternalMemberID != '') {
                                $isMemberID = true;
                                // check member sales link is same to parent sales head
                                if ($isMemberID) {
                                    if ($this->salesModel->flagExternalMemberID) {
                                        if ($this->salesModel->flagExternalMemberID != '') {
                                            if ($salesLink->flagExternalMemberID == $this->salesModel->flagExternalMemberID) {
                                                $isSameMember = true;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // check sales link head
                        $useHeadPromotionVoucherCode = false;
                        if ($salesLink->promotionVoucherCode) {
                            if ($salesLink->promotionVoucherCode != '') {
                                $useHeadPromotionVoucherCode = true;
                            }
                        }
                        if ($isSameMember && $isMemberID && $useHeadPromotionVoucherCode) {
                            $promotionVoucherCode = true;
                            array_push($salesLinkPromotionVoucherCodes, $salesLink->promotionVoucherCode);
                        }
                        // check sales link detail
                        if ($salesLink->salesMenus) {
                            foreach ($salesLink->salesMenus as $salesLinkDetail) {
                                $useDetailPromotionVoucherCode = false;
                                if ($salesLinkDetail->promotionVoucherCode) {
                                    if ($salesLinkDetail->promotionVoucherCode != '') {
                                        $useDetailPromotionVoucherCode = true;
                                        $salesLinkPromotionGifteeVoucherCodes[] = $salesLinkDetail->promotionVoucherCode;
                                    }
                                }
                                if ($isSameMember && $isMemberID && $useDetailPromotionVoucherCode) {
                                    $promotionVoucherCode = true;
                                    array_push($salesLinkPromotionVoucherCodes, $salesLinkDetail->promotionVoucherCode);
                                }
                            }
                        }
                    }
                }

                // @notes: check level head
                if ($this->salesModel->promotionVoucherCode || $this->salesModel->promotionVoucherCode != '') {
                    $promotionVoucherCode = true;
                    if (count($salesLinkPromotionVoucherCodes) > 0 || count($salesLinkPromotionGifteeVoucherCodes) > 0) {
                        if (in_array($this->salesModel->promotionVoucherCode, $salesLinkPromotionVoucherCodes) || in_array($this->salesModel->promotionVoucherCode, $salesLinkPromotionGifteeVoucherCodes)) {
                            throw new Exception("Duplicate voucher detected");
                        }
                    }
                }

                // @notes: check level menu
                $internalOnlineVouchersFreeItemCheck = [];
                foreach ($this->salesModel->salesMenus as $salesMenu) {
                    if ($salesMenu->promotionVoucherCode || $salesMenu->promotionVoucherCode != '') {
                        $promotionVoucherCode = true;
                        
                        if ($salesMenu->promotionDetailID > 0 && $salesMenu->promotion->promotionTypeID == 4) {
                            // @notes: voucherSourceID | 1 = ESB, 7 = Giftee 
                            if ($salesMenu->promotion->voucherSourceID == 1) {
                                if ($internalOnlineVouchersFreeItemCheck && in_array($salesMenu->promotionVoucherCode, $internalOnlineVouchersFreeItemCheck)) {
                                    throw new Exception("VOUCHER_DUPLICATE");
                                }
                                $internalOnlineVouchersFreeItemCheck[] = $salesMenu->promotionVoucherCode;
                                $internalOnlineVouchersFreeItem[] = (object) array(
                                    "promotionVoucherCode" => $salesMenu->promotionVoucherCode,
                                    "promotionMasterCode" => $salesMenu->promotion->promotionMasterCode
                                );
                            } else if ($salesMenu->promotion->voucherSourceID == 7) {
                                if ($externalVoucherGifteeArray) {
                                    foreach ($externalVoucherGifteeArray as $voucher) {
                                        if ($salesMenu->promotionVoucherCode == $voucher->voucherCode) {
                                            throw new Exception("VOUCHER_DUPLICATE");
                                        }   
                                    }
                                }
                                $externalVoucherGifteeArray[] = (object) array(
                                    'ID' => $salesMenu->ID,
                                    'voucherCode' => $salesMenu->promotionVoucherCode
                                );
                            }
                        }

                        if (count($salesLinkPromotionVoucherCodes) > 0 || count($salesLinkPromotionGifteeVoucherCodes) > 0) {
                            if (in_array($salesMenu->promotionVoucherCode, $salesLinkPromotionVoucherCodes) || in_array($salesMenu->promotionVoucherCode, $salesLinkPromotionGifteeVoucherCodes)) {
                                throw new Exception("VOUCHER_DUPLICATE");
                            }
                        }
                    }
                }

                // @notes: check birthday benefit
                if ($this->salesModel->externalMembershipTypeID === 'esbloyalty') {
                    // claim birthday on sales detail
                    foreach ($this->salesModel->salesMenus as $salesMenu) {
                        if (strpos($salesMenu->promotionVoucherCode, '|') !== false) {
                            $responseClaimBirthday = ExternalVoucher::claimBirthdayOfLoyalty($this->salesModel->flagExternalMemberID, $salesMenu->menu->menuCode);
                            if ($responseClaimBirthday->status === false) {
                                throw new Exception("Cannot claim birthday benefit <br/>", $responseClaimBirthday, 2);
                            }
                        }
                    }
                    // claim birthday on sales link detail
                    if ($salesLinkModel) {
                        foreach ($salesLinkModel as $salesLink) {
                            if ($salesLink->externalMembershipTypeID === 'esbloyalty') {
                                foreach ($salesLink->salesMenus as $salesMenu) {
                                    if (strpos($salesMenu->promotionVoucherCode, '|') !== false) {
                                        $responseClaimBirthday = ExternalVoucher::claimBirthdayOfLoyalty($this->salesModel->flagExternalMemberID, $salesMenu->menu->menuCode);
                                        if ($responseClaimBirthday->status === false) {
                                            throw new Exception("Cannot claim birthday benefit <br/>", $responseClaimBirthday, 2);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && (count($externalVoucherMemberIDArray) > 0 || $promotionVoucherCode === true) 
                    && $this->salesModel->externalMembershipTypeID === 'memberid') {
                    // burn voucher applied on sales head & detail
                    $responseExternalVoucher = ExternalVoucher::saveVoucherMemberID($externalVoucherMemberIDArray, $this->salesModel);
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        $errorMemberID = null;
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                            $errorMemberID = $item->responseMessage == 'minimumSpending not satisfied' ? 'ERR_MEMBERID:' : 'ERR_MEMBERIDs:Voucher invalid ';
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception($errorMemberID . $voucherError, 2);
                    }
                    // burn voucher applied on sales link
                    if ($salesLinkModel) {
                        foreach ($salesLinkModel as $salesLink) {
                            if ($salesLink->externalMembershipTypeID === 'memberid') {
                                $responseExternalVoucher = ExternalVoucher::saveVoucherMemberID([], $salesLink);
                                if ($responseExternalVoucher->status === false) {
                                    $voucherError = "";
                                    $errorMemberID = null;
                                    foreach($responseExternalVoucher->items as $item) {
                                        $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                                        $errorMemberID = $item->responseMessage == 'minimumSpending not satisfied' ? 'ERR_MEMBERID:' : 'ERR_MEMBERIDs:Voucher invalid ';
                                    }
                                    if (isset($responseExternalVoucher->dataLogging)) {
                                        $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                                    }
                                    throw new Exception($errorMemberID . $voucherError, 2);
                                }
                            }
                        }
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && $promotionVoucherCode === true
                    && $this->salesModel->externalMembershipTypeID === 'looplite') {
                    // burn voucher applied on sales head & detail
                    $responseExternalVoucher = ExternalVoucher::saveVoucherESBLoopLite($this->salesModel);
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }

                    // burn benefit applied on sales head & detail
                    $responseExternalBenefit = ExternalVoucher::saveBenefitESBLoopLite($this->salesModel);
                    if ($responseExternalBenefit->status === false) {
                        $benefitError = "";
                        foreach($responseExternalBenefit->items as $item) {
                            $benefitError .= "$item->benefitCode ($item->responseMessage) <br/>";
                        }
                        throw new Exception("Benefit Invalid <br/> $benefitError", $responseExternalBenefit->items, 2);
                    }

                    if ($salesLinkModel) {
                        foreach ($salesLinkModel as $salesLink) {
                            // burn voucher applied on sales link
                            if ($salesLink->externalMembershipTypeID === 'looplite') {
                                $responseExternalVoucher = ExternalVoucher::saveVoucherESBLoopLite($salesLink);
                                if ($responseExternalVoucher->status === false) {
                                    $voucherError = "";
                                    foreach($responseExternalVoucher->items as $item) {
                                        $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                                    }
                                    throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                                }

                                // burn benefit applied on sales link
                                $responseExternalBenefit = ExternalVoucher::saveBenefitESBLoopLite($salesLink);
                                if ($responseExternalBenefit->status === false) {
                                    $benefitError = "";
                                    foreach($responseExternalBenefit->items as $item) {
                                        $benefitError .= "$item->benefitCode ($item->responseMessage) <br/>";
                                    }
                                    throw new Exception("Benefit Invalid <br/> $benefitError", $responseExternalBenefit->items, 2);
                                }
                            }
                        }
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && (count($externalVoucherLoyaltyArray) > 0 || $promotionVoucherCode === true)
                    && $this->salesModel->externalMembershipTypeID === 'esbloyalty') {
                    // burn voucher applied on sales head & detail
                    $responseExternalVoucher = ExternalVoucher::saveVoucherLoyalty($externalVoucherLoyaltyArray, $this->salesModel);
                    if ($responseExternalVoucher->status === false) {
                        throw new Exception("Voucher Invalid <br/>", $responseExternalVoucher->message, 2);
                    }
                     // burn voucher applied on sales link
                    if ($salesLinkModel) {
                        foreach ($salesLinkModel as $salesLink) {
                            if ($salesLink->externalMembershipTypeID === 'esbloyalty') {
                                $responseExternalVoucher = ExternalVoucher::saveVoucherLoyalty([], $salesLink);
                                if ($responseExternalVoucher->status === false) {
                                    throw new Exception("Voucher Invalid <br/>", $responseExternalVoucher->message, 2);
                                }
                            }
                        }
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && count($externalVoucherTadaArray) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherTada($externalVoucherTadaArray, $this->salesNum);
 
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && count($externalVoucherGifteeArray) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherGiftee($externalVoucherGifteeArray, $this->salesNum);
 
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                            if (strpos($item->responseMessage, 'exchanged') !== false) {
                                throw new Exception("VOUCHER_USED");
                            }
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && count($externalVoucherCapillaryArray) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherCapillary($externalVoucherCapillaryArray, $this->salesModel);
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage)<br/>";
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }
                
                }
                if (!$this->ezoCodPayment && !$this->updateMode && count($externalVoucherQwikCilverArray) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherQwikCilver($externalVoucherQwikCilverArray, $this->salesNum);
                    if (isset($responseExternalVoucher->dataLogging)) {
                        $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                    }

                    if ($responseExternalVoucher->status === false) {
                        if (isset($responseExternalVoucher->errorMessage)) {
                            throw new Exception("ERR_QWIKCILVER:" . $responseExternalVoucher->errorMessage, 2);
                        } else {
                            throw new Exception("ERR_QWIKCILVER:There is an issue with the Qwikcilver server", 2);
                        }
                    }
                }

                if (!$this->ezoCodPayment && !$this->flagSavePaymentFs && !$this->updateMode && (count($internalVoucherArray) > 0 || count($externalVoucherGlobalTixArray) > 0)) {
                    if (count($internalVoucherArray) > 0) {
                        $responseInternalVoucher = Voucher::claimEsbVoucher($internalVoucherArray, $this->salesModel->salesNum, $summarySubtotal);
                        if ($responseInternalVoucher->status === false) {
                            if ($responseInternalVoucher->error !== null && isset($responseInternalVoucher->error->errorCode)) {
                                throw new Exception("ERR_ESBVOUCHER:" . $responseInternalVoucher->error->errorCode, 2);
                            } else {
                                throw new Exception("ERR_ESBVOUCHER:" . $responseInternalVoucher->error, 2);
                            }
                        }
                    }
                    if (count($externalVoucherGlobalTixArray) > 0) {
                        $responseExternalVoucher = ExternalVoucher::saveVoucherGlobalTix($externalVoucherGlobalTixArray, $this->salesNum);
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
    
                        if (isset($responseExternalVoucher->responseCode)) {
                            $errorMessage = "(" . $responseExternalVoucher->message . ")";
                            throw new Exception("Voucher Invalid, $errorMessage");
                        }
    
                        if (isset($responseExternalVoucher->status) && $responseExternalVoucher->status === false) {
                            $voucherError = '';
                            foreach($responseExternalVoucher->items as $item) {
                                $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                            }
                            throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                        }
                    }
                }

                if (!$this->ezoCodPayment && !$this->updateMode && (count($externalVoucherStampsArray) > 0 || $promotionVoucherCode === true)
                    && $this->salesModel->externalMembershipTypeID === 'stamps') {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherStamps($externalVoucherStampsArray, $this->salesModel);
                    if (isset($responseExternalVoucher->dataLogging)) {
                        $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                    }

                    if ($responseExternalVoucher->status === false) {
                        if (isset($responseExternalVoucher->errorMessage)) {
                            throw new Exception("ERR_STAMPS:" . $responseExternalVoucher->errorMessage, 2);
                        } else {
                            throw new Exception("ERR_STAMPS:an error occurred in STAMPS Server", 2);
                        }
                    } else {
                        //update voucherCode in salespayment
                        foreach ($dataExternalMemberLogging['stampsVouchers'] as $stampsVoucher) {
                            if ($stampsVoucher->salesPaymentID != null) {
                                SalesPayment::updateAll(
                                    ['voucherCode' => $stampsVoucher->newVoucherCode],
                                    ['ID' => $stampsVoucher->salesPaymentID]
                                );
                            }
                        }

                        //update promotionVoucherCode
                        if ($promotionVoucherCode) {
                            if (strlen($this->salesModel->promotionVoucherCode) > 0) {
                                $this->salesModel->promotionVoucherCode = ExternalVoucher::findNewPromotionVoucherCodeStamps(
                                    $promotionVoucherCode, $dataExternalMemberLogging['stampsVouchers']
                                );
                            }

                            foreach($this->salesModel->salesMenus as $salesMenu) {
                                if (strlen($salesMenu->promotionVoucherCode) > 0) {
                                    $smNewPromotionVoucherCode = ExternalVoucher::findNewPromotionVoucherCodeStamps(
                                        $salesMenu->promotionVoucherCode, $dataExternalMemberLogging['stampsVouchers']
                                    );
                                    SalesMenu::updateAll(
                                        ['promotionVoucherCode' => $smNewPromotionVoucherCode],
                                        ['ID' => $salesMenu->ID]
                                    );
                                }
                            }
                        }
                    }
                }

                if(!$this->ezoCodPayment && !$this->updateMode && count($externalVoucherCapillaryV2Array) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherCapillaryV2($externalVoucherCapillaryV2Array, $this->salesModel);
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage)<br/>";
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }
                
                }

            } else {
                // @Notes: Total bill = 0, submit dummy cash payment
                $cashPaymentMethodModel = PaymentMethod::find()
                    ->where(['flagActive' => 1])
                    ->andWhere(['paymentMethodTypeID' => 1])
                    ->orderBy('paymentMethodID')
                    ->one();

                if (!$cashPaymentMethodModel) {
                    throw new Exception("Payment method cash not found");
                }

                $salesPaymentModel = new SalesPayment();
                $salesPaymentModel->salesNum = $this->salesNum;
                $salesPaymentModel->paymentMethodID = $cashPaymentMethodModel->paymentMethodID;
                $salesPaymentModel->coaNo = $cashPaymentMethodModel->coaNo;
                $salesPaymentModel->paymentAmount = 0;
                $salesPaymentModel->fullPaymentAmount = 0;
                if (!$salesPaymentModel->save()) {
                    Yii::error($salesPaymentModel->errors);
                    throw new Exception('Failed to save payment');
                }

                $salesLinkModel = SalesHead::findLinkSalesHeads($this->salesModel->salesNum);
                $salesLinkPromotionGifteeVoucherCodes = [];
                if ($salesLinkModel) {    
                    foreach ($salesLinkModel as $salesLink) {
                        // check sales link detail
                        if ($salesLink->salesMenus) {
                            foreach ($salesLink->salesMenus as $salesLinkDetail) {
                                if ($salesLinkDetail->promotionVoucherCode) {
                                    if ($salesLinkDetail->promotionVoucherCode != '') {
                                        $salesLinkPromotionGifteeVoucherCodes[] = $salesLinkDetail->promotionVoucherCode;
                                    }
                                }
                            }
                        }
                    }
                }

                $internalOnlineVouchersFreeItemCheck = [];
                foreach ($this->salesModel->salesMenus as $salesMenu) {
                    if ($salesMenu->promotionVoucherCode || $salesMenu->promotionVoucherCode != '') {
                        if ($salesMenu->promotionDetailID > 0 && $salesMenu->promotion->promotionTypeID == 4) {
                            // @notes: voucherSourceID | 1 = ESB, 7 = Giftee 
                            if ($salesMenu->promotion->voucherSourceID == 1) {
                                if ($internalOnlineVouchersFreeItemCheck && in_array($salesMenu->promotionVoucherCode, $internalOnlineVouchersFreeItemCheck)) {
                                    throw new Exception("VOUCHER_DUPLICATE");
                                }
                                $internalOnlineVouchersFreeItemCheck[] = $salesMenu->promotionVoucherCode;
                                $internalOnlineVouchersFreeItem[] = (object) array(
                                    "promotionVoucherCode" => $salesMenu->promotionVoucherCode,
                                    "promotionMasterCode" => $salesMenu->promotion->promotionMasterCode
                                );
                            } else if ($salesMenu->promotion->voucherSourceID == 7) {
                                if ($externalVoucherGifteeArray) {
                                    foreach ($externalVoucherGifteeArray as $voucher) {
                                        if ($salesMenu->promotionVoucherCode == $voucher->voucherCode) {
                                            throw new Exception("VOUCHER_DUPLICATE");
                                        }   
                                    }
                                }
                                $externalVoucherGifteeArray[] = (object) array(
                                    'ID' => $salesMenu->ID,
                                    'voucherCode' => $salesMenu->promotionVoucherCode
                                );
                            }
                        }

                        if (count($salesLinkPromotionGifteeVoucherCodes) > 0) {
                            if (in_array($salesMenu->promotionVoucherCode, $salesLinkPromotionGifteeVoucherCodes)) {
                                throw new Exception("VOUCHER_DUPLICATE");
                            }
                        }
                    }
                }

                if(!$this->updateMode && count($externalVoucherGifteeArray) > 0) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucherGiftee($externalVoucherGifteeArray, $this->salesNum);
 
                    if ($responseExternalVoucher->status === false) {
                        $voucherError = "";
                        foreach($responseExternalVoucher->items as $item) {
                            $voucherError .= "$item->voucherCode ($item->responseMessage) <br/>";
                            if (strpos($item->responseMessage, 'exchanged') !== false) {
                                throw new Exception("VOUCHER_USED");
                            }
                        }
                        if (isset($responseExternalVoucher->dataLogging)) {
                            $dataExternalMemberLogging = $responseExternalVoucher->dataLogging;
                        }
                        throw new Exception("Voucher Invalid <br/> $voucherError", $responseExternalVoucher->items, 2);
                    }
                }
            }



            // Burn ESB Online Vouchers
            if (!empty($internalOnlineVouchers) || !empty($internalOnlineVouchersFreeItem)) {
                Voucher::claimOnline($this->salesModel->salesNum, $summarySubtotal, $internalOnlineVouchers, $internalOnlineVouchersFreeItem);
            }

            // Burn Pluxee Vouchers
            if (!empty($epassVouchers)) {
                $epassVoucherCodes = $epassVouchers;
                $pluxeeEpassBurnResult = ExternalVoucher::burnVoucherPluxee($this->terminalID, 'ePass', $this->salesModel->salesNum, $epassVouchers);
                if (isset($pluxeeEpassBurnResult['dataLogging'])) {
                    $dataPluxeeLogging = $pluxeeEpassBurnResult['dataLogging'];
                    throw new Exception($pluxeeEpassBurnResult['message']);
                }
            }
            if (!empty($giftVouchers)) {
                $giftVoucherCodes = $giftVouchers;
                $giftVoucherCodes = array_filter($giftVoucherCodes, function($voucher) {
                    return strlen($voucher) >= 24;
                });
                $pluxeeGiftBurnResult = ExternalVoucher::burnVoucherPluxee($this->terminalID, 'gift_voucher', $this->salesModel->salesNum, $giftVoucherCodes);
                if (isset($pluxeeGiftBurnResult['dataLogging'])) {
                    $dataPluxeeLogging = $pluxeeGiftBurnResult['dataLogging'];
                    throw new Exception($pluxeeGiftBurnResult['message']);
                }
            }

            if (!empty($externalUltraVoucherArray)) {
                $ultraVoucherBurnResult = ExternalVoucher::burnUltraVoucher($externalUltraVoucherArray, $this->salesModel->salesNum, $this->terminalID);
                if (isset($ultraVoucherBurnResult['dataLogging'])) {
                    $ultraVoucherLoggingData = $ultraVoucherBurnResult['dataLogging'];
                    throw new Exception($ultraVoucherBurnResult['message']);
                }
            }

            // @Notes: if payment using other cost, substract all taxes and recalculate total
            $grandTotal = 0;
            if ($this->otherCostCount > 0) {
                SalesMenu::updateAll([
                    'otherTax' => 0,
                    'otherTaxValue' => 0,
                    'vat' => 0,
                    'vatValue' => 0,
                    'otherVat' => 0,
                    'otherVatValue' => 0,
                    'discount' => 0,
                    'discountValue' => 0,
                    'total' => new Expression('(qty * price) - (discount / 100 * (qty * price))'),
                    'promotionDetailID' => 0,
                    'syncDate' => null
                    ], ['salesNum' => $this->salesModel->salesNum]);

                SalesMenuExtra::updateAll([
                    'otherTax' => 0,
                    'otherTaxValue' => 0,
                    'vat' => 0,
                    'vatValue' => 0,
                    'otherVat' => 0,
                    'otherVatValue' => 0,
                    'discount' => 0,
                    'discountValue' => 0,
                    'total' => new Expression('(qty * price) - (discount / 100 * (qty * price))'),
                    'syncDate' => null
                    ], ['salesNum' => $this->salesModel->salesNum]);

                $this->salesModel->roundingTotal = 0;
                $this->salesModel->orderFee = 0;
                $this->salesModel->deliveryCost = 0;
                $this->salesModel->promotionID = 0;
                $this->salesModel->promotionDiscount = 0;
            } else {
                $this->salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
            }

            //@ Notes: if not edit payment mode, update salesDateOut
            if (!$this->updateMode) {
                // @Notes: 8 = Finished
                //disini generate billNum

                if ($this->otherCostCount == 0) {
                    $branchID = $this->salesModel->branchID;
                    $this->salesModel->billNum = AppHelper::createNewTransactionNumber('Bill',
                            $this->salesModel->salesDate, $branchID);
                }

                $this->salesModel->salesDateOut = new Expression('NOW()');
                $this->salesModel->voucherTotal = $this->totalVoucher;
                $this->salesModel->grandTotal = $this->salesModel->grandTotal + $this->totalVoucher;
                $this->salesModel->statusID = 8;

                $externalMemberSetting = BrandSetting::getExternalMemberSetting();
                $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
                if ($this->salesModel->flagExternalAPI === 1) {
                    SalesHead::updateAll([
                        'externalMembershipTypeID' => $membershipType
                        ], ['salesNum' => $this->salesModel->salesNum]);
                }else{
                    SalesHead::updateAll([
                        'externalMembershipTypeID' => NULL
                        ], ['salesNum' => $this->salesModel->salesNum]);
                }

                if ($this->salesModel->externalMembershipTypeID === 'looplite') {
                    $salesContactModel = SalesContactInfo::checkSalesPhoneNumber($this->salesNum);
                    if($salesContactModel) {
                        self::validateMember($salesContactModel, true);
                    }
                }

                // if ($this->salesModel->flagExternalAPI === 1 && $membershipType == 'memberid' && count($externalVoucherMemberIDArray) > 0) {
                //     //@notes: save external transaction (MemberID)
                //     $errMsg = '';
                //     $externalMemberModel = new ExternalMember();
                //     $externalMemberModel->salesModel = $this->salesModel;
                //     $externalMemberModel->salesPayments = $this->salesPayment;
                //     $externalMemberModel->burnPoints = $this->burnPoints;
                //     $externalMemberModel->saveTransactionMemberID($errMsg);
                //     if ($errMsg != '') {
                //         throw new Exception("Voucher Invalid <br/> $errMsg", [], 2);
                //     }
                // }

                // @notes: proses final save external voucher
                $isSelfOrderPaymentExists = SalesPayment::find()
                    ->where(['AND', ['salesNum' => $this->salesNum], ['IS NOT', 'selfOrderID', null]])
                    ->exists();

                if (
                    count($externalVoucherArray) > 0
                    && ($this->salesModel->transactionModeID === null || (in_array($this->salesModel->transactionModeID, [1, 2]) && !$isSelfOrderPaymentExists))
                    && !$this->flagSavePaymentFs
                ) {
                    $responseExternalVoucher = ExternalVoucher::saveVoucher($externalVoucherArray, $this->salesNum, $this->terminalID, true);
                    if($responseExternalVoucher->status) {
                        foreach($responseExternalVoucher->items as $item) {
                            $salesPaymentModel = SalesPayment::findOne(['ID' => $item->ID]);
                            $salesPaymentModel->flagExternalVoucherAPI = $item->flagExternalVoucherAPI;
                            $salesPaymentModel->verificationCode = $item->verificationCode;
                            $salesPaymentModel->externalVoucherCode = $item->externalVoucherCode;
                            $salesPaymentModel->externalTransactionId = $responseExternalVoucher->transactionId;
                            $salesPaymentModel->externalBatchNumber = $responseExternalVoucher->batchId;
                            $salesPaymentModel->save();
                        }
                    } else {
                        throw new Exception("Voucher Invalid <br/> $responseExternalVoucher->message", [], 2);
                    }
                }

                //@notes: save external transaction (MAP)
                if($this->salesModel->flagExternalAPI === 1 && $membershipType == 'general') {
                    $errMsg = '';
                    $externalMemberModel = new ExternalMember();
                    $externalMemberModel->salesModel = $this->salesModel;
                    $externalMemberModel->salesPayments = $this->salesPayment;
                    $externalMemberModel->burnPoints = $this->burnPoints;
                    if($externalTransaction = $externalMemberModel->saveTransaction($errMsg)) {
                        $this->salesModel->externalTransID = $externalTransaction['transactionId'];
                        $this->externalTransaction = $externalTransaction;
                    }
                    if ($errMsg != '') {
                        throw new Exception("Online Transaction Failed <br/> $errMsg", [], 2);
                    }
                }

                // @notes: proses final save external voucher memberID
                if (count($externalVoucherMemberIDArray) > 0) {                    
                    foreach($externalVoucherMemberIDArray as $item) {
                        $salesPaymentModel = SalesPayment::findOne(['ID' => $item->ID]);
                        $salesPaymentModel->flagExternalVoucherAPI = 1;
                        $salesPaymentModel->externalVoucherCode = $item->voucherCode;
                        $salesPaymentModel->save();
                    }
                }

                $customNumberSetting = BrandSetting::getBrandPosSetting('Custom Number');
                if(isset($customNumberSetting['Custom Number']) && $customNumberSetting['Custom Number'] == 1) {
                    CustomNumber::saveCustomNumber($this->salesModel);
                }
            }

            $printingSettings = Setting::getPrintingSettings();
            $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;
            if ($printingAfterPayment == 1 && $this->salesModel->tableID == 0) {
                $this->salesModel->queueNum = SalesHead::getQueueNumber($this->salesModel->salesNum, $this->salesModel->salesDate, $this->salesModel->branchID, true);
            }

            $this->salesModel->terminalID = $this->terminalID;
            $this->salesModel->paymentTotal = $this->totalPayment;

            if (!$this->salesModel->save()) {
                Yii::error($this->salesModel->errors);
                throw new Exception('Failed to update sales head');
            }

            $grandTotal += $this->salesModel->grandTotal;

            // @Notes: update all linked tables
            $linkedSalesModel = SalesHead::findLinkSalesHeads($this->salesModel->salesNum);

            $linkedSalesNum = [];
            if ($linkedSalesModel) {
                foreach ($linkedSalesModel as $salesModel) {
                    if ($this->otherCostCount > 0) {
                        SalesMenu::updateAll([
                            'otherTax' => 0,
                            'otherTaxValue' => 0,
                            'vat' => 0,
                            'vatValue' => 0,
                            'otherVat' => 0,
                            'otherVatValue' => 0,
                            'discount' => 0,
                            'discountValue' => 0,
                            'total' => new Expression('(qty * price) - (discount / 100 * (qty * price))'),
                            'promotionDetailID' => 0,
                            'syncDate' => null
                            ], ['salesNum' => $salesModel->salesNum]);
    
                        SalesMenuExtra::updateAll([
                            'otherTax' => 0,
                            'otherTaxValue' => 0,
                            'vat' => 0,
                            'vatValue' => 0,
                            'otherVat' => 0,
                            'otherVatValue' => 0,
                            'discount' => 0,
                            'discountValue' => 0,
                            'total' => new Expression('(qty * price) - (discount / 100 * (qty * price))'),
                            'syncDate' => null
                            ], ['salesNum' => $salesModel->salesNum]);
                        
                        $salesModel->promotionID = 0;
                        $salesModel->promotionDiscount = 0;
                    } else {
                        $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                    }
    
                    if (!$this->updateMode) {
                        $salesModel->salesDateOut = new Expression('NOW()');
                        $salesModel->statusID = 8;
                        $salesModel->terminalID = $this->terminalID;
                        if (!$salesModel->save()) {
                            Yii::error($salesModel->errors);
                            throw new Exception('Failed to update linked sales head');
                        }
    
                        $grandTotal += $salesModel->grandTotal;
                    }
                    $linkedSalesNum[] = $salesModel->salesNum;
                }
            }


            if ($hubMenu) {
                $this->updateBillNum($this->grandTotalHead);
            }

            if ($this->otherCostCount > 0) {
                $salesPaymentModel = SalesPayment::find()
                    ->joinWith('paymentMethod')
                    ->where(['salesNum' => $this->salesNum])
                    ->andWhere(['paymentMethodTypeID' => 7])
                    ->one();

                if ($salesPaymentModel) {
                    $salesPaymentModel->paymentAmount = $grandTotal;
                    $salesPaymentModel->fullPaymentAmount = $grandTotal;
                    if (!$salesPaymentModel->save()) {
                        Yii::error('Failed to save payment non sales');
                        throw new Exception('Failed to save payment non sales');
                    }
                }
            }

            if ($this->updateMode) {
                $paymentMethodTypeArray = [];
                $paymentMethodOtherVoucher = 5;
                $paymentMethodMemberDeposit = 6;
                $i = 0;
                $totalVoucher = 0;
                if (isset($this->salesPayment)) {
                    foreach ($this->salesPayment as $salesPayment) {
                        $paymentMethodModel = PaymentMethod::find()
                            ->where(['paymentMethodID' => $salesPayment['paymentMethodID']])
                            ->one();
                        $paymentMethodTypeArray[$i] = $salesPayment['paymentMethodTypeID'];
                        $i++;
                        if($salesPayment['paymentMethodTypeID'] === 5 && $paymentMethodModel->voucherTypeID === 1){
                            $totalVoucher += $salesPayment['paymentAmount'];
                        }
                    }    
                }
                
                if (!in_array($paymentMethodMemberDeposit, $paymentMethodTypeArray)) {
                    $salesWithdrawalModel = SalesDepositWithdrawal::find()
                        ->andWhere(['salesNum' => $this->salesModel->salesNum])
                        ->all();

                    foreach ($salesWithdrawalModel as $detail) {
                        MemberDeposit::updateAll([
                            'usedDepositTotal' => new Expression('usedDepositTotal - ' . $detail->paymentTotal),
                            'syncDate' => null
                            ], ['=', 'memberDepositNum', $detail->memberDepositNum]);
                    }
                }

                if(in_array($paymentMethodOtherVoucher, $paymentMethodTypeArray)) {
                        SalesHead::updateAll([
                            'voucherDiscountTotal' => $totalVoucher,
                            'syncDate' => null
                            ],['=', 'salesNum', $this->salesModel->salesNum]);
                } else {
                        SalesHead::updateAll([
                            'voucherDiscountTotal' => 0,
                            'syncDate' => null
                            ], ['=', 'salesNum', $this->salesModel->salesNum]);
                }
            }

            if ($this->updateMode) {
                $salesNumPayment = $this->salesModel->salesNum;
                $paymentHistory = BranchEvent::getPaymentHistory($salesNumPayment);
                
                Logging::save($salesNumPayment, Logging::EDIT_PAYMENT_BEFORE, $paymentHistory);
            }

            if (!empty($linkedSalesNum)) {
                SalesPaymentGateway::deleteAll(['IN', 'salesNum', $linkedSalesNum]);
            }
            
            SalesPaymentGateway::deleteAll(['salesNum' => $this->salesModel->salesNum]);

            Logging::save($this->salesModel->salesNum,
                $this->updateMode ? Logging::EDIT_PAYMENT : Logging::SAVE_PAYMENT,
                $this->getAttributes());

            if (isset($this->salesPayment)) {
                $paymentAuthModelsTemp = [];
                foreach($this->salesPayment as $salesPayment) {
                    if (isset($salesPayment['paymentMethodWithAuth']) && $salesPayment['paymentMethodWithAuth'] != null) {
                        array_push($paymentAuthModelsTemp, $salesPayment['paymentMethodWithAuth']);
                    }
                }
                
                $paymentAuthModels = array_unique($paymentAuthModelsTemp, SORT_REGULAR);
                foreach($paymentAuthModels as $paymentAuth) {
                    $dataLogging = [
                        "authUserName" => isset($paymentAuth['authUserName']) ? $paymentAuth['authUserName'] : null,
                        "branchID" => isset($this->salesModel->branchID) ? $this->salesModel->branchID : 0,
                        "paymentMethodID" => isset($paymentAuth['paymentMethodID']) ? $paymentAuth['paymentMethodID'] : 0,
                        "paymentMethodName" => isset($paymentAuth['paymentMethodName']) ? $paymentAuth['paymentMethodName'] : null,
                        "tableID" => isset($this->salesModel->tableID) ? $this->salesModel->tableID : 0
                    ];
    
                    Logging::save($salesPayment['salesNum'], Logging::APPLY_PAYMENT_WITH_PIN, $dataLogging);
                }
            }
                
            $transaction->commit();

            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            if ($dataExternalMemberLogging !== null) {
                Logging::save($this->salesModel->salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataExternalMemberLogging);
                
                if (isset($dataExternalMemberLogging['qwikcilverVouchers']) && count($dataExternalMemberLogging['qwikcilverVouchers']) > 0) {
                    ExternalVoucher::UnburnVoucherQwikCilver($dataExternalMemberLogging['qwikcilverVouchers'], $this->salesNum);
                }

                if (isset($dataExternalMemberLogging['stampsVouchers']) && count($dataExternalMemberLogging['stampsVouchers']) > 0) {
                    ExternalVoucher::UnburnVoucherStamps($dataExternalMemberLogging['stampsVouchers'], $this->salesNum);
                }
            }

            if ($epassVoucherCodes !== null) {
                Logging::save($this->salesModel->salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataPluxeeLogging);
                ExternalVoucher::unburnEpassVoucherPluxee('ePass', $this->salesNum);
            }
            if ($giftVoucherCodes !== null) {
                Logging::save($this->salesModel->salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataPluxeeLogging);
                ExternalVoucher::unburnGiftVoucherPluxee('gift_voucher', $this->salesNum);
            }

            if (!empty($externalUltraVoucherArray)) {
                Logging::save($this->salesModel->salesNum, Logging::BURN_ULTRA_VOUCHER, $ultraVoucherLoggingData);
            }
            
            $this->addError('salesPayment', $ex->getMessage());
            // throw $ex;
            return false;
        }
    }

    private function sendEmail($salesNum) {
        $selfOrderApi = '';
        $activateEZO = Setting::getEZOSetting();
        if($activateEZO['Activate EZO'] == 1){
            $selfOrderApi = Setting::getEsoFsApiUrl();
        }
        
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();
        $transId = AppHelper::encryptSalesNum($salesNum);

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $selfOrderApi . 'guest-check-send-email';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
            'data-company' => AppHelper::getCompanyCode(),
            'data-branch' => AppHelper::getBranchCode(),
            'data-transId' => $transId
        ];
        $datas = [];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $datas, $options);

        if ($result->getIsOk()) {
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
                            'menuName' => $extra->menu->menuName,
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

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $selfOrderApi . 'guest-send-email';
            $headers = [
                'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                'data-company' => AppHelper::getCompanyCode(),
                'data-branch' => AppHelper::getBranchCode(),
                'data-transId' => $transId
            ];
            $datas = [
                'salesDatas' => $salesData,
                'salesPayments' => $salesPaymentData
            ];
            $options = ['timeOut' => 300];
            $httpService->post($url, $headers, $datas, $options);
        }
    }

    public function savePosMultiplePt() {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $salesNums = [];
            $this->headArr = [];
            $this->salesHeadArr = [];
            $salesmenuPackageArr = [];
            $salesMenuModel = (new Query())
                ->select([
                    'b.hubID',
                    'salesmenuID' => new Expression('GROUP_CONCAT(a.ID)'),
                    'c.flagPrimary'
                ])
                ->from(SalesMenu::tableName() . ' a')
                ->innerJoin(HubMenu::tableName() . ' b', "a.menuID = b.menuID ")
                ->innerJoin(HubHost::tableName() . ' c', "c.hubID = b.hubID ")
                ->where(['salesNum' => $this->salesNum])
                ->groupBy(['b.hubID', 'c.flagPrimary'])
                ->orderBy('c.flagPrimary')
                ->all();

            $menuPackageHeadModel = (new Query())
                ->select([
                    'salesmenuID' => new Expression('GROUP_CONCAT(a.ID)'),
                ])
                ->from(SalesMenu::tableName() . ' a')
                ->where(['salesNum' => $this->salesNum])
                ->andWhere('menuRefID > 0')
                ->andWhere('menuGroupID = 0')
                ->all();

            foreach ($menuPackageHeadModel as $dataPackage) {
                if($dataPackage['salesmenuID'] !== null){
                    $salesmenuPackageArr = explode(',', $dataPackage['salesmenuID']);
                }
            }

            $salesHeadModel = SalesHead::findOne(['salesNum' => $this->salesNum]);
            $this->allGrandTotal = $salesHeadModel->grandTotal;
            $this->allOrderFee = $salesHeadModel->orderFee;
            $this->grandTotalHead = $salesHeadModel->grandTotal;
            if ($salesHeadModel->promotionID) {
                $this->discountTotalHead = $salesHeadModel->discountTotal;
            }
            $this->headArr[] = $this->salesNum;
            $newSalesNum = [];
            if (count($salesMenuModel) > 1) {
                $i = 0;
                foreach ($salesMenuModel as $data) {
                    if ($data['flagPrimary'] != 1) {
                        $salesmenuArr = explode(',', $data['salesmenuID']);

                        $branchID = Setting::getCurrentBranch();
                        $newSalesModel = new SalesHead([
                            'attributes' => $this->getAttributes()
                        ]);
                        $newSalesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                        $newSalesModel->attributes = $salesHeadModel->attributes;
                        $newSalesModel->flagInclusive = $salesHeadModel->flagInclusive;
                        $newSalesModel->salesNum = AppHelper::createNewTransactionNumber('Sales',
                                $newSalesModel->salesDate, $branchID);
                        $newSalesModel->statusID = 8;
                        $newSalesModel->orderFee = 0;
                        $newSalesNum[] = $newSalesModel->salesNum;
                        if (!$newSalesModel->save()) {
                            Yii::error($newSalesModel->errors);
                            throw new Exception('Failed to save sales head');
                        }


                        foreach ($salesmenuArr as $salesmenuID) {
                            $salesMenuData = SalesMenu::findOne(['ID' => $salesmenuID]);
                            if ($salesMenuData->menuGroupID == 0) {
                                $salesMenuData->salesNum = $newSalesModel->salesNum;
                                $salesMenuData->calculateTotal();
                                if (!$salesMenuData->save()) {
                                    Yii::error($salesMenuData->errors);
                                    throw new Exception('Failed to save sales menu');
                                }
                                $salesMenuExtraData = SalesMenuExtra::findOne(['salesNum' => $this->salesNum, 'menuDetailID' => $salesmenuID]);
                                if ($salesMenuExtraData) {
                                    $salesMenuExtraData->salesNum = $newSalesModel->salesNum;
                                    if (!$salesMenuExtraData->save()) {
                                        Yii::error($salesMenuExtraData->errors);
                                        throw new Exception('Failed to save sales menu extra');
                                    }
                                }
                            } else if ($salesMenuData->menuGroupID > 0) {
                                $salesMenuData->salesNum = $newSalesModel->salesNum;
                                if ($newSalesModel->flagInclusive) {
                                    $salesMenuData->calculateTotal($newSalesModel->flagInclusive, $salesMenuData->inclusivePrice);
                                } else {
                                    $salesMenuData->calculateTotal();
                                }
                                if (!$salesMenuData->save()) {
                                    Yii::error($salesMenuData->errors);
                                    throw new Exception('Failed to save sales menu');
                                }
                                $salesMenuExtraData = SalesMenuExtra::findOne(['salesNum' => $this->salesNum, 'menuDetailID' => $salesmenuID]);
                                if ($salesMenuExtraData) {
                                    $salesMenuExtraData->salesNum = $newSalesModel->salesNum;
                                    if (!$salesMenuExtraData->save()) {
                                        Yii::error($salesMenuExtraData->errors);
                                        throw new Exception('Failed to save sales menu extra');
                                    }
                                }
                            }
                        }

                        $linkModel = SalesLink::find()
                            ->andWhere(['salesNum' => $this->salesNum])
                            ->andWhere(['linkSalesNum' => $newSalesModel->salesNum])
                            ->one();
                        if ($linkModel) {
                            $salesNums[] = $linkModel->linkSalesNum;
                        } else {
                            $salesModel = SalesHead::find()
                                ->andWhere(['salesNum' => $this->salesNum])
                                ->andWhere(['tableID' => $this->tableID])
                                ->one();
                            if (!$salesModel) {
                                throw new Exception('Invalid link table ' . $this->tableID);
                            }

                            $newLinkModel = new SalesLink();
                            $newLinkModel->salesNum = $this->salesNum;
                            $newLinkModel->linkSalesNum = $newSalesModel->salesNum;
                            if (!$newLinkModel->save()) {
                                Yii::error($newLinkModel->errors);
                                throw new Exception('Failed to save link table');
                            }
                            $salesNums[] = $newLinkModel->linkSalesNum;
                        }

                        $this->salesHeadArr[] = $newSalesModel->salesNum;
                        $this->headArr[] = $newSalesModel->salesNum;
                        $newSalesHeadModel = SalesHead::findOne(['salesNum' => $newSalesModel->salesNum]);
                        $newSalesHeadModel->allGrandTotal = $this->allGrandTotal;
                        $newSalesHeadModel->allOrderFee = $this->allOrderFee;
                        if (!$newSalesHeadModel->save()) {
                            Yii::error($newSalesHeadModel->errors);
                            throw new Exception('Failed to update sales head');
                        }
                    } else {
                        $salesmenuArr = explode(',', $data['salesmenuID']);
                        foreach ($salesmenuArr as $salesmenuID) {
                            $salesMenuData = SalesMenu::findOne(['ID' => $salesmenuID]);
                            if ($salesMenuData->menuGroupID > 0) {
                                if ($salesHeadModel->flagInclusive) {
                                    $salesMenuData->calculateTotal($salesHeadModel->flagInclusive, $salesMenuData->inclusivePrice);
                                } else {
                                    $salesMenuData->calculateTotal();
                                }
                                if (!$salesMenuData->save()) {
                                    Yii::error($salesMenuData->errors);
                                    throw new Exception('Failed to save sales menu');
                                }
                            }
                        }
                    }

                    $i++;
                }
            }
            if (count($menuPackageHeadModel) > 0) {
                foreach ($salesmenuPackageArr as $salesmenuPackageID) {
                    foreach ($newSalesNum as $newSales) {
                        $salesMenuPackageData = SalesMenu::findOne(['ID' => $salesmenuPackageID]);
                        $salesMenuPackageChildData = SalesMenu::findOne([
                            'menuRefID' => $salesmenuPackageID,
                            'salesNum' => $newSales
                            ]);
                        if ($salesMenuPackageData) {
                            if($salesMenuPackageChildData){
                                $hubMenu = HubMenu::findOne(['menuID' => $salesMenuPackageData->menuID]);
                                if (!$hubMenu) {
                                    $newSalesMenuModel = new SalesMenu([
                                        'attributes' => $salesMenuPackageData->getAttributes()
                                    ]);

                                    $newSalesMenuModel->salesNum = $newSales;
                                    if ($salesHeadModel->flagInclusive == 0) {
                                        $newSalesMenuModel->calculateTotal(0, 0);
                                    } else {
                                        $newSalesMenuModel->calculateTotal(1, $newSalesMenuModel->inclusivePrice);
                                    }
                                    if (!$newSalesMenuModel->save()) {
                                        Yii::error($newSalesMenuModel->errors);
                                        throw new Exception('Failed to save sales menu');
                                    }

                                    SalesMenu::UpdateAll(['menuRefID' => $newSalesMenuModel->ID],
                                    [
                                        'salesNum' => $newSales,
                                        'menuRefID' => $newSalesMenuModel->menuRefID]);
                                    $newSalesMenuModel->menuRefID = $newSalesMenuModel->ID;
                                    if (!$newSalesMenuModel->save()) {
                                        Yii::error($newSalesMenuModel->errors);
                                        throw new Exception('Failed to save sales menu');
                                    }
                                }
                            }
                            
                            $newSalesHeadModel = SalesHead::findOne(['salesNum' => $newSales]);
                            if (!$newSalesHeadModel->save()) {
                                Yii::error($newSalesHeadModel->errors);
                                throw new Exception('Failed to update sales head');
                            }
                        }
                    }
                }
            }
            $this->salesModel->allGrandTotal = $this->allGrandTotal;
            $this->salesModel->allOrderFee = $this->allOrderFee;
            $this->salesModel->selfOrderPaymentMethodID = $this->selfOrderPaymentMethodID;
            if (!$this->salesModel->save()) {
                Yii::error($salesHeadModel->errors);
                throw new Exception('Failed to update sales head');
            }

            $transaction->commit();

            return $this->grandTotalHead;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesPayment', $ex->getMessage());
            return false;
        }
    }

    public function updateBillNum($grandTotal) {

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $branchID = Setting::getCurrentBranch();
            foreach ($this->salesHeadArr as $salesNumData) {
                $newSalesHeadModel = SalesHead::findOne(['salesNum' => $salesNumData]);

                $newSalesHeadModel->billNum = AppHelper::createNewTransactionNumber('Bill',
                        $newSalesHeadModel->salesDate, $branchID);
                $newSalesHeadModel->allGrandTotal = $this->allGrandTotal;
                $newSalesHeadModel->allOrderFee = $this->allOrderFee;
                if (!$newSalesHeadModel->save()) {
                    Yii::error($newSalesHeadModel->errors);
                    throw new Exception('Failed to update sales head');
                }
            }
            $this->calculateDiscountMultiple($this->headArr, $grandTotal);
            $transaction->commit();

            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesPayment', $ex->getMessage());
            return false;
        }
    }

    public function calculateDiscountMultiple($arrayHead, $grandTotal) {
        $settings = Setting::getPrintingSettings();
        $roundingMode = isset($settings['Rounding Mode']) ? $settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($settings['Rounding Nearest Value']) ? $settings['Rounding Nearest Value'] : 0;

        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

        $rounding = $roundingNearestValue;

        $sumGrandTotal = 0;
        $dataSalesHead = SalesHead::findOne(['salesNum' => $this->salesNum]);
        if ($dataSalesHead->promotionID > 0) {
            $grandTotalData = (new Query())
                ->select([
                    'sumTotal' => new Expression('SUM(a.subtotal - a.menuDiscountTotal + a.otherTaxTotal + a.vatTotal + a.voucherTotal - a.roundingTotal)')
                ])
                ->from(SalesHead::tableName() . ' a')
                ->where(['IN', 'a.salesNum', $arrayHead])
                ->one();

            $promotionModel = PromotionHead::find()
                ->andWhere(['promotionID' => $dataSalesHead->promotionID])
                ->one();

            $i = 1;
            $tempTotalDiscount = 0;
            foreach ($arrayHead as $salesNumArr) {
                $perSalesDiscount = 0;
                $headModel = SalesHead::findOne(['salesNum' => $salesNumArr]);
                $grandTotalTemp = $headModel->subtotal - $headModel->menuDiscountTotal + $headModel->otherTaxTotal + $headModel->vatTotal + $headModel->voucherTotal;
                if (count($arrayHead) == $i) {
                    $perSalesDiscount = $this->discountTotalHead - $tempTotalDiscount;
                } else {
                    $perSalesDiscount = ceil($this->discountTotalHead * ($grandTotalTemp - $headModel->roundingTotal) / $grandTotalData['sumTotal']);
                    $tempTotalDiscount += $perSalesDiscount;
                }
                $headModel->discountTotal = $perSalesDiscount;
                $headModel->grandTotal = $headModel->subtotal - $headModel->discountTotal - $headModel->menuDiscountTotal + $headModel->otherTaxTotal + $headModel->vatTotal + $headModel->voucherTotal;
                
                
                $finalGrandTotal = $headModel->grandTotal;
                if ($rounding != 0) {
                    if ($roundingMode == 'DOWN') {
                        $headModel->roundingTotal = $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding);
                    } elseif ($roundingMode == 'UP') {
                        $headModel->roundingTotal = $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding);
                    } elseif ($roundingMode == 'AUTO') {
                        $headModel->roundingTotal = $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding;
                    }
                }
   
                if ($promotionModel->promotionTypeID == 1) {
                    $headModel->promotionDiscount = $headModel->discountTotal / $grandTotalData['sumTotal'] * 100;
                }

                SalesHead::updateAll([
                    'promotionDiscount' => $headModel->promotionDiscount,
                    'discountTotal' => $headModel->discountTotal,
                    'grandTotal' => $headModel->grandTotal,
                    'roundingTotal' => $headModel->roundingTotal
                    ], ['salesNum' => $headModel->salesNum]);
                $sumGrandTotal += $headModel->grandTotal;
                $lastSalesNum =  $headModel->salesNum;
                $i++;
            }

            if ($sumGrandTotal != $grandTotal) {
                $difGrandTotal = $grandTotal - $sumGrandTotal;
                SalesHead::updateAll([
                    'grandTotal' =>  new Expression("grandTotal + $difGrandTotal"),
                    ], ['salesNum' => $lastSalesNum]);
            }
        }
    }

    public function checkSalesEsbVoucher() {
        if (isset($this->salesNumMenus) && count($this->salesNumMenus) > 0) {
            $checkSalesMenu = SalesMenu::findSalesPromotionEsbVoucher($this->salesNumMenus);
            $checkPhoneNum = SalesContactInfo::checkSalesPhoneNumber($this->salesNum);
            if ($checkSalesMenu) {
                if ($checkPhoneNum) {
                    self::validateMember($checkPhoneNum);
                } else {
                    self::setErrorMessage();
                }
            }
        } else if (isset($this->paymentMethodID)) {
            $checkPaymentMethod = PaymentMethod::checkPaymentMethodEsbVoucher($this->paymentMethodID);
            $checkPhoneNum = SalesContactInfo::checkSalesPhoneNumber($this->salesNum);
            
            if ($checkPaymentMethod) {
                if ($checkPhoneNum) {
                    self::validateMember($checkPhoneNum);
                } else {
                    self::setErrorMessage();
                }
            }
        }
    }

    public function validateMember($checkPhoneNum, $flagMemberLoop = false) {
        $salesContactModel = [
            'salesNum' => $checkPhoneNum->salesNum,
            'customerPhoneNum' => $checkPhoneNum->customerPhoneNum
        ];

        if ($flagMemberLoop) {
            if($salesContactModel) {
                Yii::$app->db->createCommand()
                ->delete(SalesContactInfo::tableName(), 
                "salesNum = :salesNum", 
                [':salesNum' => $this->salesNum])
                ->execute();
            }
        } else {
            $checkMember = ExternalMember::validateMemberPhoneNumber($salesContactModel);
            if (isset($checkMember->code)) {
                Yii::$app->db->createCommand()
                ->delete(SalesContactInfo::tableName(), 
                "salesNum = :salesNum", 
                [':salesNum' => $this->salesNum])
                ->execute();
                
                $this->responseErrorMessage = [
                    'status' => false,
                    'message' => 'Member not found'
                ];
            }
        }
    }

    public function setErrorMessage() {
        $this->responseErrorMessage = [
            'status' => false,
            'message' => 'Member or Phone Num Not Found'
        ];
    }

    public function saveUltraVoucherPayment($salesPayment) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($salesPayment) {
                $salesPaymentModel = new SalesPayment([
                    'attributes' => $salesPayment
                ]);
                if (!$salesPaymentModel->save()) {
                    throw new Exception('Failed to save payment');
                }
            }
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $transaction->rollBack();
            $this->addError('savePayment', $ex->getMessage());
            return false;
        }
    }

    public function removeUltraVoucherPayment($salesNum, $paymentMethodID, $voucherCode) {
        try {
            $salesPaymentModel = SalesPayment::find()
                ->where(['=', 'salesNum', $salesNum])
                ->andWhere(['=', 'paymentMethodID', $paymentMethodID])
                ->andWhere(['=', 'voucherCode', $voucherCode])
                ->one();

            if ($salesPaymentModel) {
                $dataLogging = [
                    'voucherCode' => $voucherCode,
                    'voucherType' => $salesPaymentModel->notes,
                    'provider' => 'ultra_voucher',
                    'remarks' => $salesNum
                ];

                SalesPayment::deleteAll([
                    'salesNum' => $salesNum,
                    'paymentMethodID' => $paymentMethodID,
                    'voucherCode' => $voucherCode
                ]);

                Logging::save($salesNum, Logging::REMOVE_ULTRA_VOUCHER, $dataLogging);
            }

            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $this->addError('savePayment', $ex->getMessage());
            return false;
        }
    }
}
