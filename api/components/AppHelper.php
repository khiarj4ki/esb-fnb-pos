<?php

namespace app\components;

use app\models\Branch;
use app\models\BrandSetting;
use app\models\DepositWithdrawalHead;
use app\models\DeviceTransaction;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\PosFilterAccess;
use app\models\PosUser;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPlatformFee;
use app\models\SalesRewardHead;
use app\models\SalesRewardMenu;
use app\models\Setting;
use app\models\TransNumber;
use app\services\http_helper\HttpHelperService;
use DateTime;
use Yii;
use Exception;
use yii\httpclient\Client;
use yii\validators\DateValidator;

class AppHelper {
    // @TODO: For testing purpose only
    public static function showVarDump($var) {
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
        Yii::$app->end();

        return true;
    }

    // @TODO: For testing purpose only
    public static function clearSchemaCache() {
        Yii::$app->cache->flush();
    }

    public static function convertDateTimeFormat($date, $formatFrom = "d-m-Y H:i", $formatTo = "Y-m-d H:i") {
        if (!empty($date)) {
            if (AppHelper::isValidDate($date, $formatFrom)) {
                $myDateTime = DateTime::createFromFormat($formatFrom, $date);
                return $myDateTime->format($formatTo);
            } else {
                return "";
            }
        } else {
            return "";
        }
    }

    public static function isValidDate($date, $format) {
        $validator = new DateValidator();
        $validator->format = "php:" . $format;
        return $validator->validate($date);
    }

    public static function createNewTransactionNumber($transType, $transDate, $branchID) {
        $lastTransNum = '';
        $newTransNum = '';
        $prefix = '';

        if ($transType == 'Bill') {
            $lastTransModel = SalesHead::find()
                    ->andWhere(['=', 'salesDate', date('Y-m-d',
                                strtotime($transDate))])
                    ->andWhere(['branchID' => $branchID])
                    ->orderBy('billNum DESC')
                    ->one();

            if (!empty($lastTransModel)) {
                $lastTransNum = $lastTransModel->billNum;
            }
        }

        if ($transType == 'Sales') {
            $branchModel = Branch::findOne($branchID);
            if (!empty($branchModel)) {
                $newTransNum = 'S' . $branchModel->branchCode . ceil((microtime(true) * 100));
            }
        } else if ($transType == 'Member Deposit') {
            $prefix = TransNumber::findOne(['transType' => 'Member Deposit'])->transAbbreviation;
            $branchModel = Branch::findOne($branchID);
            if (!empty($branchModel)) {
                $newTransNum = $prefix . $branchModel->branchCode . ceil((microtime(true) * 100));
            }
        } else if ($transType == 'Deposit Withdrawal') {
            $prefix = TransNumber::findOne(['transType' => 'Deposit Withdrawal'])->transAbbreviation;
            $branchModel = Branch::findOne($branchID);
            if (!empty($branchModel)) {
                $newTransNum = $prefix . $branchModel->branchCode . ceil((microtime(true) * 100));
            }
        } else {
            if ($lastTransNum == '') {
                $newTransNum = date('Y', strtotime($transDate)) . date('m',
                                strtotime($transDate)) . date('d', strtotime($transDate)) . '0001';
            } else {
                $newTransNum = substr($lastTransNum, strlen($lastTransNum) - 12,
                                12) + 1;
            }
            $branchModel = Branch::findOne($branchID);
            if (!empty($branchModel)) {
                $newTransNum = $prefix . $branchModel->branchCode . $newTransNum;
            }
        }

        return $newTransNum;
    }

    public static function createNewChildTransactionNumber($salesNumHead) {
        $stringChecker = $salesNumHead . "-";
        $lastTransModel = SalesHead::find()
                ->where(['like', 'salesNum', $stringChecker])
                ->orderBy([
                    new \yii\db\Expression("SUBSTRING_INDEX(salesNum, '-', -1) REGEXP '^[0-9]' DESC"),
                    'salesNum' => SORT_DESC
                ])
                ->one();

        if (!empty($lastTransModel)) {
            $lastTransNum = $lastTransModel->salesNum;
        } else {
            $lastTransNum = '';
        }

        if ($lastTransNum == '') {
            $newTransNum = $salesNumHead . '-A';
        } else {
            $firstChar = substr($lastTransNum, 0, strpos($lastTransNum, '-'));
            $newTransNum = self::generateChildTransactionNumber($firstChar, $lastTransNum, $salesNumHead);
        }

        return $newTransNum;
    }

    public static function generateChildTransactionNumber($firstChar, $lastTransNum, $salesNumHead){

        $char = substr($lastTransNum, strlen($salesNumHead) + 1, 1);
        if(is_string($char) && !is_numeric($char)){
            // @notes : after find until Z, reset to Numeric
            if($char == 'Z') {
                $char = 0;
            } else {
                $char = chr(ord($char) + 1);
                $newTransNum = $firstChar . '-' . $char;
            }
        }
        
        if(is_numeric($char)){
            $char = $char + 1;
            $newTransNum = $firstChar . '-' . $char;
        }

        return $newTransNum;
    }

    public static function createNewMemberCode() {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        $prefix = $branchModel->branchCode;
		$memberCodeLength = strlen($prefix) + 8;

        $memberModel = Member::find()
                ->where("memberCode LIKE '$prefix%'")
				->andWhere("LENGTH(memberCode) = $memberCodeLength")
                ->orderBy('memberCode DESC')
                ->one();

        if ($memberModel) {
            $newMemberCode = $prefix . str_pad(strval(substr($memberModel->memberCode,
                                            strlen($memberModel->memberCode) - 8, 8) + 1), 8,
                            '0', STR_PAD_LEFT);
        } else {
            $newMemberCode = $prefix . '00000001';
        }

        return $newMemberCode;
    }

    public static function hasAccess($url, $token = null) {
        //@Notes: Tampung daftar filterAccessID dari tabel lk_posfilteraccess yang kolom actionnya tidak kosong ke dalam array
        $arrayPosFilterAccessMain = [];
        $posFilterAccessModel = PosFilterAccess::find()
                ->andWhere(['not', ['action' => null]])
                ->all();

        if ($posFilterAccessModel) {
            foreach ($posFilterAccessModel as $data) {
                $arrayPosFilterAccessMain[] = [
                    "accessID" => $data['filterAccessID'],
                    "action" => explode(',', $data['action'])
                ];
            }
        }

        //@Notes: Jika token tidak kosong, tampung nilai filterAccessID milik user dalam array
        $arrayFilterAccessID = [];
        if ($token) {
            $user = PosUser::findIdentityByAccessToken($token);
            $userAccess = $user ? $user->getUserAccess() : [];
            if ($userAccess) {
                foreach ($userAccess as $data) {
                    foreach ($data['access'] as $data1) {
                        if ($data1['hasAccess'] == 1) {
                            $arrayFilterAccessID[] = $data1['filterAccessID'];
                        }
                    }
                }
            }
        }

        if ($arrayPosFilterAccessMain) {
            foreach ($arrayPosFilterAccessMain as $data) {
                //@Notes: Cek apakah url ada di dalam field action di lk_filteraccessid
                if (in_array($url, $data['action'])) {
                    //@Notes: Cek apakah token ada dan user memiliki akses ke filteraccessid
                    if ($arrayFilterAccessID) {
                        if (in_array($data['accessID'], $arrayFilterAccessID)) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }

        return true;
    }

    public static function toNum($data) {
        $alphabet = array('A', 'B', 'C', 'D', 'E',
            'F', 'G', 'H', 'I', 'J',
            'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y',
            'Z'
        );

        $return_value = array_search($data, $alphabet) + 1;

        return $return_value;
    }

    public static function writeToTextFile($fileName, $textVal) {
        $handle = fopen($fileName, 'a') or die('Cannot open file:  ' . $fileName);
        $data = $textVal;
        fwrite($handle, $data);
        fclose($handle);
    }

    public static function encryptSalesNum($salesNum) {
        $companyCode = self::getCompanyCode();
        return SimpleHash::encrypt($salesNum, $companyCode);
    }

    public static function decryptTransId($transId) {
        if (!ctype_xdigit($transId)) {
            return '';
        }

        $companyCode = self::getCompanyCode();
        $salesNum = SimpleHash::decrypt($transId, $companyCode);
        if (!$salesNum) {
            $salesNum = '';
        }

        return $salesNum;
    }

    public static function updateStatusMenu($salesMenu) {
        $transId = self::encryptSalesNum($salesMenu->salesNum);
        $salesMenuData = [
            'menuID' => $salesMenu->menuID,
            'batchID' => $salesMenu->batchID,
            'notes' => $salesMenu->notes
        ];

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = Yii::$app->params['selfOrderBaseUrl'] . '/web/v1/order/update-status-menu';
        $headers = [
            'data-company' => AppHelper::getCompanyCode(),
            'data-branch' => AppHelper::getBranchCode(),
            'data-transId' => $transId,
        ];
        $datas = [
            'salesMenu' => $salesMenuData
        ];
        $options = ['timeOut' => 300];
        $httpService->post($url, $headers, $datas, $options);
    }

    public static function getBranchCode() {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        if ($branchModel) {
            $branchCode = $branchModel->branchCode;
            if ($branchCode) {
                return $branchCode;
            }
        }
        return NULL;
    }

    public static function getCompanyCode() {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        if ($branchModel) {
            $companyCode = $branchModel->companyCode;
            if ($companyCode) {
                return $companyCode;
            }
        }
        return NULL;
    }

    public static function sendEmail($salesNum) {
        $ezoSettings = Setting::getEZOSetting();
        if ($ezoSettings['Activate EZO'] == 1) {
            $selfOrderApi = Setting::getEsoFsApiUrl();
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
                'data-transId' => $transId,
            ];
            $datas = [];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $datas, $options);
            if ($result->getIsOk()) {
                $orderPayment = SalesHead::findOrderPaymentAsArray(null,
                                $salesNum);
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

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $selfOrderApi . 'guest-send-email';
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-company' => AppHelper::getCompanyCode(),
                    'data-branch' => AppHelper::getBranchCode(),
                    'data-transId' => $transId,
                ];
                $datas = [
                    'salesDatas' => $salesData,
                    'salesPayments' => $salesPaymentData
                ];
                $options = ['timeOut' => 300];
                $httpService->post($url, $headers, $datas, $options);
            }
        }
    }
    
    public static function roundingDecimal($number,$precision,$mode){
        $separator = ".";
        $numberpart=explode($separator,floatVal($number));
        $returnValue = 0;

        if (!empty($numberpart[1])) {
            if ($precision != 0) {
                $leadingZeroCounter = 0;
                if (strlen($numberpart[1]) != $precision) {
                    $leadingZeroCounter = strlen($numberpart[1]) -  strlen(ltrim($numberpart[1], '0'));
                    $numberpart[1]=substr_replace($numberpart[1],$separator,$precision,0);
                    if ($mode == 'UP') {
                        if($numberpart[0]>=0)
                        {
                            $numberpart[1]=ceil($numberpart[1]);
                        } else{
                            $numberpart[1]=floor($numberpart[1]);
                        }
                    } else {
                        if($numberpart[0]>=0)
                        {
                            $numberpart[1]=floor($numberpart[1]);
                        } else{
                            $numberpart[1]=ceil($numberpart[1]);
                        }
                    }
                }

                $deductionValue = floor($numberpart[1] / POW(10,$precision-1));
                $leadingZeroCounter = $leadingZeroCounter - $deductionValue;
                if ($leadingZeroCounter > 0) {
                    $rounding_number= array($numberpart[0],str_pad($numberpart[1],$leadingZeroCounter + strlen($numberpart[1]),"0",STR_PAD_LEFT));
                } else {
                    $rounding_number= array($numberpart[0],$numberpart[1]);
                }
                $returnValue = implode($separator,$rounding_number);
            } else {
                if ($mode == 'UP') {
                    if($number>=0)
                    {
                        $number=ceil($number);
                    } else{
                        $number=floor($number);
                    }
                } else {
                    if($number>=0)
                    {
                        $number=floor($number);
                    } else{
                        $number=ceil($number);
                    }
                }

                $returnValue = $number;
            }
        } else {
            $returnValue = $numberpart[0];
        }

        return $returnValue;
    }

    public static function checkMacAddress() {
        $yiiLocation = Yii::$app->basePath . '/yii';
        $insertMacAction = 'insert-mac-address-log';
        $_IP_SERVER = $_SERVER['SERVER_ADDR'];
        $_IP_ADDRESS = $_SERVER['REMOTE_ADDR'];

        if (substr(php_uname(), 0, 3) == "Win") {
            pclose(popen("start /B php $yiiLocation $insertMacAction $_IP_SERVER $_IP_ADDRESS ", "r"));
        } else {
            shell_exec("php $yiiLocation $insertMacAction $_IP_SERVER $_IP_ADDRESS > /dev/null 2>/dev/null &");
        }
    }
    
    public static function getDsnAttribute($name, $dsn) {
        if (preg_match('/' . $name . '=([^;]*)/', $dsn, $match)) {
            return $match[1];
        } else {
            return null;
        }
    }

    public static function getMacAddress($_IP_SERVER, $_IP_ADDRESS)
    {
        $_RESULT = '';
        if ($_IP_ADDRESS == $_IP_SERVER) {
            ob_start();
            system('ipconfig /all');
            $_COMMAND  = ob_get_contents();
            ob_clean();
            $_SPLIT = strpos($_COMMAND, "Physical");
            $_RESULT = substr($_COMMAND, ($_SPLIT + 36), 17);
        } else {
            $_COMMAND = "arp -a $_IP_ADDRESS";
            ob_start();
            system($_COMMAND);
            $_RESULT = ob_get_contents();
            ob_clean();

            if (substr(php_uname(), 0, 3) == "Win") {
                $_SPLIT = strstr($_RESULT, $_IP_ADDRESS);
                $_SPLIT_STRING = explode($_IP_ADDRESS, str_replace(" ", "", $_SPLIT));
                if (isset($_SPLIT_STRING[1])) {
                    $_RESULT = substr($_SPLIT_STRING[1], 0, 17);
                } else {
                    $_RESULT = '-';
                }
            } else {
                $_SPLIT_STRING = explode(" ", $_RESULT);
                if (isset($_SPLIT_STRING[3])) {
                    $_RESULT = substr($_SPLIT_STRING[3], 0, 17);
                } else {
                    $_RESULT = '-';
                }
            }
        }

        return $_RESULT;
    }

    public static function formatNumberValue($number, $salesDecimalSetting = null, $salesDecimalSeparatorSetting = ".", $reverseDecimalSeparator = ","){
        $number = round($number, 2);
        return number_format(
            $number,
            $salesDecimalSetting != null ? $salesDecimalSetting : (fmod($number, 1) == 0 ? 0 : 2),
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        );
    }

    public static function sendSales($salesModel, $salesNum)
    {
        $selfOrderApi = Setting::getEsoFsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();
        $salesNum = $salesNum;
        $transId = AppHelper::encryptSalesNum($salesNum);
        $salesHead = [
            'salesNum' => $salesModel->salesNum,
            'billNum' => $salesModel->billNum,
            'salesDate' => $salesModel->salesDate,
            'salesDateIn' => $salesModel->salesDateIn,
            'orderTimeOut' => $salesModel->orderTimeOut,
            'salesDateOut' => $salesModel->salesDateOut,
            'branchID' => $salesModel->branchID,
            'memberID' => $salesModel->memberID,
            'memberCode' => $salesModel->memberCode,
            'tableID' => $salesModel->tableID,
            'visitPurposeID' => $salesModel->visitPurposeID,
            'paxTotal' => $salesModel->paxTotal,
            'subtotal' => $salesModel->subtotal,
            'discountTotal' => $salesModel->discountTotal,
            'menuDiscountTotal' => $salesModel->menuDiscountTotal,
            'promotionDiscount' => $salesModel->promotionDiscount,
            'otherTaxTotal' => $salesModel->otherTaxTotal,
            'vatTotal' => $salesModel->vatTotal,
            'otherVatTotal' => $salesModel->otherVatTotal,
            'orderFee' => $salesModel->orderFee,
            'grandTotal' => $salesModel->grandTotal,
            'voucherTotal' => $salesModel->voucherTotal,
            'roundingTotal' => $salesModel->roundingTotal,
            'paymentTotal' => $salesModel->paymentTotal,
            'billingPrintCount' => $salesModel->billingPrintCount,
            'paymentPrintCount' => $salesModel->paymentPrintCount,
            'additionalInfo' => $salesModel->additionalInfo,
            'promotionID' => $salesModel->promotionID,
            'promotionVoucherCode' => $salesModel->promotionVoucherCode ? $salesModel->promotionVoucherCode : '',
            'flagInclusive' => $salesModel->flagInclusive,
            'flagExternalAPI' => $salesModel->flagExternalAPI,
            'flagExternalMemberID' => $salesModel->flagExternalMemberID,
            'flagExternalMemberPhone' => $salesModel->flagExternalMemberPhone,
            'flagExternalCardID' => $salesModel->flagExternalCardID,
            'externalMemberName' => $salesModel->externalMemberName,
            'statusID' => $salesModel->statusID,
            'createdBy' => $salesModel->createdBy,
            'editedBy' => $salesModel->editedBy,
            'editedDate' => $salesModel->editedDate,
            'syncDate' => $salesModel->syncDate
        ];
        $salesMenu = SalesMenu::find()->where(['salesNum' => $salesNum])->orderBy('localID ASC')->asArray()->all();
        $salesLink = SalesLink::find()->where(['salesNum' => $salesNum])->asArray()->all();
        $salesMenuExtra = SalesMenuExtra::find()->where(['salesNum' => $salesNum])->asArray()->all();
        $salesMergeTable = SalesMergeTable::find()->where(['salesNum' => $salesNum])->asArray()->all();
        $salesRewardHead = SalesRewardHead::find()->where(['salesNum' => $salesNum])->asArray()->all();
        $salesRewardMenu = SalesRewardMenu::find()->where(['salesNum' => $salesNum])->asArray()->all();
        $salesPlatformFee = SalesPlatformFee::find()->where(['salesNum' => $salesNum])->asArray()->all();

        $platformFees = [];
        if ($salesPlatformFee) {
            foreach ($salesPlatformFee as $data) {
                $platformFees[] = [
                    "feeNameID" => $data['feeNameID'],
                    "feeNameEN" => $data['feeNameEN'],
                    "percentage" => (int) $data['percentage'],
                    "platformFeeTypeID" => (int) $data['platformFeeTypeID'],
                    "amount" => (int) $data['amount'],
                    "maxAmount" => (int) $data['maxAmount']
                ];
            }
        }
        

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $selfOrderApi . 'sync-save-menu';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
            'data-branch' => $branch->branchCode,
            'data-company' => $companyCode,
            'data-transId' => $transId,
            'data-clientTime' => time()
        ];
        $datas = [
            'salesHead' => $salesHead,
            'salesMenu' => $salesMenu,
            'salesLink' => $salesLink,
            'salesMenuExtra' => $salesMenuExtra,
            'salesMergeTable' => $salesMergeTable,
            'salesRewardHead' => $salesRewardHead,
            'salesRewardMenu' => $salesRewardMenu,
            'platformFee' => $platformFees
        ];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $datas, $options);

        return $result;
    }

    public static function getSalesDataForEmail($salesNum) {
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
                    'extras' => $extras,
                    'promotionDetailID' => (int)isset($salesMenu['promotionDetailID']) ? $salesMenu['promotionDetailID'] : 0
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

    public static function notifSelfOrderApi($salesHead, $orderID)
    {
        $salesNum = $salesHead->salesNum;
        $queueNum = $salesHead->queueNum;
        $billNum = $salesHead->billNum;
        $selfOrderApi = Setting::getEsoQsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();
        $client = new Client(['baseUrl' => $selfOrderApi]);
        $salesData = self::getSalesDataForEmail($salesNum);

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
                'orderID' => $orderID,
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

        return $response;
    }

    public static function notifSelfOrderVoidApi($salesHead, $orderID)
    {
        $salesNum = $salesHead->salesNum;
        $queueNum = $salesHead->queueNum;
        $billNum = $salesHead->billNum;
        $statusID = $salesHead->statusID;
        $selfOrderApi = Setting::getEsoQsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();
        $client = new Client(['baseUrl' => $selfOrderApi]);

        $response = $client->createRequest()
            ->setUrl('pos-void')
            ->setMethod('POST')
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                'data-branch' => $branch->branchCode,
                'data-company' => $companyCode
            ])
            ->setData([
                'orderID' => $orderID,
                'salesNum' => $salesNum,
                'billNum' => $billNum,
                'queueNum' => $queueNum,
                'statusID' => $statusID
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        return $response;
    }

    public static function unzipSyncData($value)
    {
        $result = null;
        $zipFile = tempnam(sys_get_temp_dir(), "SYNCZIP");
        try {
            $decodedZipData = base64_decode($value['zipData']);
            $decodedZipStream = fopen($zipFile, 'r+');
            fwrite($decodedZipStream, $decodedZipData);
            $decodedMd5 = strtolower(md5_file($zipFile));
            fclose($decodedZipStream);

            if ($decodedMd5 != $value['zipMd5']) {
                throw new Exception('Cannot validate data integrity because MD5 Checksum is not match');
            }

            $result = json_decode(file_get_contents("zip://$zipFile#data"), true);
        } catch (Exception $ex) {
            $result = [
                'error' => $ex->getMessage(),
                'errorLine' => $ex->getLine()
            ];
        }
        unlink($zipFile);

        return $result;
    }

    public static function checkDataInconsistency($salesModel) {
        $errorMessage = [];
        if ($salesModel->promotionID > 0) {
            if (isset($salesModel->promotion)) {
                if ($salesModel->promotion->flagActive == 0) {
                    $errorMessage[] = [
                        'ID' => $salesModel->promotionID,
                        'name' => $salesModel->promotion->notes,
                        'source' => 'Master Promotion'
                    ];
                }
            } else {
                $errorMessage[] = [
                    'ID' => $salesModel->promotionID,
                    'source' => 'Master Promotion'
                ];
            }
        }

        if ($salesModel->salesMergeTables) {
            if (isset($salesModel->table)) {
                if ($salesModel->table->flagActive == 0) {
                    $errorMessage[] = [
                        'ID' => $salesModel->tableID,
                        'name' => $salesModel->table->tableName,
                        'source' => 'Master Table'
                    ];
                }
            } else {
                $errorMessage[] = [
                    'ID' => $salesModel->tableID,
                    'source' => 'Master Table'
                ];
            }

            foreach ($salesModel->salesMergeTables as $salesMerge) {
                if (isset($salesMerge->table)) {
                    if ($salesMerge->table->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $salesMerge->tableID,
                            'name' => $salesMerge->table->tableName,
                            'source' => 'Master Table'
                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $salesMerge->tableID,
                        'source' => 'Master Table'
                    ];
                }
            }
        }

        if ($salesModel->salesLinks) {
            foreach($salesModel->salesLinks as $linkSales) {
                if (isset($linkSales->salesHead->table)) {
                    if ($linkSales->salesHead->table->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $linkSales->salesHead->tableID,
                            'name' => $linkSales->salesHead->table->tableName,
                            'source' => 'Master Table'
                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $linkSales->salesHead->tableID,
                        'source' => 'Master Table'
                    ];
                }
            }
        }

        if ($salesModel->childSalesLinks){
            foreach($salesModel->childSalesLinks as $childLinkSales) {
                if (isset($childLinkSales->parentSalesHead->table)) {
                    if ($childLinkSales->parentSalesHead->table->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $childLinkSales->parentSalesHead->tableID,
                            'name' => $childLinkSales->parentSalesHead->table->tableName,
                            'source' => 'Master Table'
                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $childLinkSales->parentSalesHead->tableID,
                        'source' => 'Master Table'
                    ];
                }
            }
        }

        if (isset($salesModel->visitPurpose)) {
            if (isset($salesModel->visitPurpose->mapBranchVisitPurpose)) {
                if (isset($salesModel->visitPurpose->mapBranchVisitPurpose->menuTemplateHead)) {
                    if ($salesModel->visitPurpose->mapBranchVisitPurpose->menuTemplateHead->flagActive == 0)  {
                        $errorMessage[] = [
                            'ID' => $salesModel->visitPurpose->mapBranchVisitPurpose->menuTemplateHead->menuTemplateID,
                            'name' => $salesModel->visitPurpose->mapBranchVisitPurpose->menuTemplateHead->menuTemplateName,
                            'source' => 'Master Menu Template'
                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $salesModel->visitPurpose->mapBranchVisitPurpose->menuTemplateID,
                        'source' => 'Master Menu Template'
                    ];
                }
            } else {
                $errorMessage[] = [
                    'ID' => $salesModel->visitPurposeID,
                    'name' => $salesModel->visitPurpose->visitPurposeName,
                    'source' => 'Master Branch - Visit Purpose'
                ];
            }

            if ($salesModel->visitPurpose->flagActive == 0) {
                $errorMessage[] = [
                    'ID' => $salesModel->visitPurposeID,
                    'name' => $salesModel->visitPurpose->visitPurposeName,
                    'source' => 'Master Visit Purpose'
                ];
            }
        } else {
            $errorMessage[] = [
                'ID' => $salesModel->visitPurposeID,
                'source' => 'Master Visit Purpose'
            ];
        }

        foreach ($salesModel->mainSalesMenus as $salesMenu) {
            if ($salesMenu->promotionDetailID > 0) {
                if (isset($salesMenu->promotion)) {
                    if ($salesMenu->promotion->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $salesMenu->promotionDetailID,
                            'name' => $salesMenu->promotion->notes,
                            'source' => 'Master Promotion'

                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $salesMenu->promotionDetailID,
                        'source' => 'Master Promotion'
                    ];
                }
            }

            if (isset($salesMenu->menu)) {
                if ($salesMenu->menu->flagActive == 0) {
                    $errorMessage[] = [
                        'ID' => $salesMenu->menuID,
                        'name' => $salesMenu->menu->menuName,
                        'source' => 'Master Menu'
                    ];
                }
            } else {
                $errorMessage[] = [
                    'ID' => $salesMenu->menuID,
                    'source' => 'Master Menu'
                ];
            }

            foreach ($salesMenu->childSalesMenus as $package) {
                if (isset($package->menuGroup) && isset($package->menuGroup->activeMenuPackages)) {
                    if ($package->menuGroup->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $package->menuGroup->menuGroupID,
                            'name' => $package->menuGroup->menuGroup,
                            'source' => 'Master Menu Package'
                        ];
                    }

                    if (isset($package->menu)) {
                        if ($package->menu->flagActive == 0) {
                            $errorMessage[] = [
                                'ID' => $package->menuID,
                                'name' => $package->menu->menuName,
                                'source' => 'Master Menu'
                            ];
                        }
                    } else {
                        $errorMessage[] = [
                            'ID' => $package->menuID,
                            'source' => 'Master Menu'
                        ];
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $package->menuGroupID,
                        'source' => 'Master Menu Package'
                    ];
                }

            }

            foreach ($salesMenu->salesExtras as $extra) {
                if (isset($extra->menuExtra)) {
                    if ($extra->menuExtra->flagActive == 0) {
                        $errorMessage[] = [
                            'ID' => $extra->menuExtra->menuExtraID,
                            'name' => $extra->menuExtra->menuExtraName,
                            'source' => 'Master Menu Extra'
                        ];
                    }

                    if (isset($extra->menuExtra->menu)) {
                        if ($extra->menuExtra->menu->flagActive == 0) {
                            $errorMessage[] = [
                                'ID' => $extra->menuExtra->menu->menuID,
                                'name' => $extra->menuExtra->menu->menuName,
                                'source' => 'Master Menu'
                            ];
                        }
                    }
                } else {
                    $errorMessage[] = [
                        'ID' => $extra->menuExtraID,
                        'source' => 'Master Menu Extra'
                    ];
                }
            }

        }

        return [
            'status' => $errorMessage ? false : true,
            'message' => $errorMessage
        ];
    }
  
    public static function checkDataInconsistencyArray($salesModel, $mainSalesMenuModel) {
			$connection = Yii::$app->getDb();
      
			$errorMessage = [];
			$salesNum = $salesModel['salesNum'];

      if ($salesModel['promotionID'] > 0) {
				if ($salesModel['masterPromoID']) {
					if ($salesModel['flagPromoActive'] != 1) {
						$errorMessage[] = [
							'ID' => $salesModel['promotionID'],
							'name' => $salesModel['promotionName'],
							'source' => 'Master Promotion'
						];
					}
				} else {
					$errorMessage[] = [
						'ID' => $salesModel['promotionID'],
						'source' => 'Master Promotion'
					];
				}
      }

			// Checking Master Table Sales Merge is Active
      if ($salesModel['mergeTableSalesNum']) {
        if ($salesModel['masterTableID']) {
          if ($salesModel['flagTableActive'] != 1) {
            $errorMessage[] = [
              'ID' => $salesModel['tableID'],
              'name' => $salesModel['tableName'],
              'source' => 'Master Table'
            ];
          }
        } else {
          $errorMessage[] = [
            'ID' => $salesModel['tableID'],
            'source' => 'Master Table'
          ];
        }

				$salesMergeTableModel = $connection->createCommand("SELECT
						tr_salesmergetable.tableID,
						ms_table.tableID AS masterTableID,
						ms_table.tableName AS masterTableName,
						ms_table.flagActive AS masterTableActive
					FROM
						tr_salesmergetable
					LEFT JOIN
						ms_table ON tr_salesmergetable.tableID = ms_table.tableID
					WHERE
						tr_salesmergetable.salesNum = '$salesNum'")->queryAll();

				foreach ($salesMergeTableModel as $salesMerge) {
					if ($salesMerge['masterTableID']) {
						if ((int) $salesMerge['masterTableActive'] != 1) {
							$errorMessage[] = [
								'ID' => (int) $salesMerge['masterTableID'],
								'name' => $salesMerge['masterTableName'],
								'source' => 'Master Table'
							];
						}
					} else {
						$errorMessage[] = [
							'ID' => (int) $salesMerge['tableID'],
							'source' => 'Master Table'
						];
					}
				}
      }

			// Checking Master Table Link Sales is Active
			if ($salesModel['linkSalesNum']) {
        if ($salesModel['masterTableID']) {
          if ($salesModel['flagTableActive'] != 1) {
            $errorMessage[] = [
              'ID' => $salesModel['tableID'],
              'name' => $salesModel['tableName'],
              'source' => 'Master Table'
            ];
          }
        } else {
          $errorMessage[] = [
            'ID' => $salesModel['tableID'],
            'source' => 'Master Table'
          ];
        }
        
				$salesLinkModel = $connection->createCommand("SELECT
						ms_table.tableID AS masterTableID,
						ms_table.tableName AS masterTableName,
						ms_table.flagActive AS masterTableActive
					FROM
						tr_saleslink
					LEFT JOIN
						tr_saleshead ON tr_saleslink.salesNum = tr_saleshead.salesNum
					LEFT JOIN
						ms_table ON tr_saleshead.tableID = ms_table.tableID
					WHERE
						tr_saleshead.salesNum = '$salesNum'")->queryAll();
				
				foreach ($salesLinkModel as $linkSales) {
					if ($linkSales['masterTableID']) {
						if ((int) $linkSales['masterTableActive'] != 1) {
							$errorMessage[] = [
								'ID' => (int) $linkSales['masterTableID'],
								'name' => $linkSales['masterTableName'],
								'source' => 'Master Table'
							];
						}
					} else {
						$errorMessage[] = [
							'ID' => (int) $linkSales['masterTableID'],
							'source' => 'Master Table'
						];
					}
				}
			}

			if ($salesModel['childLinkSalesNum']) {
				$childSalesLinkModel = $connection->createCommand("SELECT
						ms_table.tableID AS masterTableID,
						ms_table.tableName AS masterTableName,
						ms_table.flagActive AS masterTableActive
					FROM
						tr_saleslink
					LEFT JOIN
						tr_saleshead ON tr_saleslink.linkSalesNum = tr_saleshead.salesNum
					LEFT JOIN
						ms_table ON tr_saleshead.tableID = ms_table.tableID
					WHERE
						tr_saleshead.salesNum = '$salesNum'")->queryAll();
				
				foreach ($childSalesLinkModel as $childLinkSales) {
					if ($childLinkSales['masterTableID']) {
						if ((int) $childLinkSales['masterTableActive'] != 1) {
							$errorMessage[] = [
								'ID' => (int) $childLinkSales['masterTableID'],
								'name' => $childLinkSales['masterTableName'],
								'source' => 'Master Table'
							];
						}
					} else {
						$errorMessage[] = [
							'ID' => (int) $linkSales['masterTableID'],
							'source' => 'Master Table'
						];
					}
				}
			}

			// Checking Master Visit Purpose is Active
      if ($salesModel['masterVisitPurposeID']) {
				if ($salesModel['mapBranchVispurID']) {
					if ($salesModel['menuTemplateHeadID']) {
						if ($salesModel['flagMenuTemplateActive'] != 1)  {
							$errorMessage[] = [
								'ID' => $salesModel['menuTemplateHeadID'],
								'name' => $salesModel['menuTemplateName'],
								'source' => 'Master Menu Template'
							];
						}
					} else {
						$errorMessage[] = [
							'ID' => $salesModel['mapBranchVispurTemplateID'],
							'source' => 'Master Menu Template'
						];
					}
				} else {
					$errorMessage[] = [
						'ID' => $salesModel['visitPurposeID'],
						'name' => $salesModel['visitPurposeName'],
						'source' => 'Master Branch - Visit Purpose'
					];
				}

				if ($salesModel['flagVispurActive'] != 1) {
					$errorMessage[] = [
						'ID' => $salesModel['visitPurposeID'],
						'name' => $salesModel['visitPurposeName'],
						'source' => 'Master Visit Purpose'
					];
				}
      } else {
				$errorMessage[] = [
					'ID' => $salesModel['visitPurposeID'],
					'source' => 'Master Visit Purpose'
				];
      }

      foreach ($mainSalesMenuModel as $main) {
        $salesTypeEzo = self::checkSalesTypeEzo($main['salesType']);
        if ($salesTypeEzo) continue;

        // Checking Master Promo is Active
        if ($main['promotionDetailID'] > 0) {
          if ($main['masterPromoID']) {
            if ($main['flagPromoActive'] != 1) {
              $errorMessage[] = [
                'ID' => $main['promotionDetailID'],
                'name' => $main['promotionDetailName'],
                'source' => 'Master Promotion'
              ];
            }
          } else {
            $errorMessage[] = [
              'ID' => $main['promotionDetailID'],
              'source' => 'Master Promotion'
            ];
          }
        }

        // Checking Master Menu Main is Active
        if (!in_array($main['statusID'], [13, 14, 34, 46, 19])) {
          if ($main['masterMenuID']) {
            if ($main['flagMenuActive'] != 1) {
              $errorMessage[] = [
                'ID' => $main['menuID'],
                'name' => $main['menuName'],
                'source' => 'Master Menu'
              ];
            }
          } else {
            $errorMessage[] = [
              'ID' => $main['menuID'],
              'source' => 'Master Menu'
            ];
          }
  
          // Checking Master Menu Package is Active
          foreach ($main['packages'] as $package) {
            if ($package['masterGroupID'] && $package['masterGroupPackageID']) {
              if ($package['masterGroupActive'] != 1) {
                $errorMessage[] = [
                  'ID' => $package['masterGroupID'],
                  'name' => $package['masterGroupName'],
                  'source' => 'Master Menu Package'
                ];
              }
  
              if ($package['masterMenuID']) {
                if ($package['flagMenuActive'] != 1) {
                  $errorMessage[] = [
                    'ID' => $package['menuID'],
                    'name' => $package['menuName'],
                    'source' => 'Master Menu'
                  ];
                }
              } else {
                $errorMessage[] = [
                  'ID' => $package['menuID'],
                  'source' => 'Master Menu'
                ];
              }
            } else {
              $errorMessage[] = [
                'ID' => $package['menuGroupID'],
                'source' => 'Master Menu Package'
              ];
            }
          }
  
          // Checking Master Menu Extra is Active
          foreach ($main['extras'] as $extra) {
            if ($extra['masterMenuExtraID']) {
              if ($extra['masterExtraActive'] != 1) {
                $errorMessage[] = [
                  'ID' => $extra['masterMenuExtraID'],
                  'name' => $extra['menuExtraName'],
                  'source' => 'Master Menu Extra'
                ];
              }
  
              if ($extra['masterMenuID']) {
                if ($extra['masterMenuActive'] != 1) {
                  $errorMessage[] = [
                    'ID' => $extra['masterMenuID'],
                    'name' => $extra['menuName'],
                    'source' => 'Master Menu'
                  ];
                }
              }
            } else {
              $errorMessage[] = [
                'ID' => $extra['menuExtraID'],
                'source' => 'Master Menu Extra'
              ];
            }
          }
        }
      }

      return [
          'status' => $errorMessage ? false : true,
          'message' => $errorMessage
      ];
  }

    public static function generateQrText($salesNum, $salesModel = null, $brandSetting = null)
    {
        $generate = true;
        $encryptedText = null;
        try {
            $companyAuthKey = Setting::getApiKey();
            if ($brandSetting == null) {
                $brandSetting = BrandSetting::getBrandPosSetting();
            }

            $membershipType = isset($brandSetting['Membership Type']) ? $brandSetting['Membership Type'] : null;
            if ($membershipType == "stamps") {
                if ($salesModel == null) {
                    //validate for customer display
                    $generate = (isset($brandSetting['Show QR on Customer Display']) && $brandSetting['Show QR on Customer Display'] == 1);
                } else {
                    //validate for print
                    $generate = (isset($brandSetting['Show QR on Customer Display']) && $brandSetting['Show QR on Customer Display'] == 0);
                }
            }

            if (
                $generate
                && isset($brandSetting['Loyalty Secret Key']) && strlen($brandSetting['Loyalty Secret Key']) > 0
                && isset($brandSetting['Receipt QR Code Encryption']) && strlen($brandSetting['Receipt QR Code Encryption']) > 0
                && isset($brandSetting['Encryption Key Code Loyalty']) && strlen($brandSetting['Encryption Key Code Loyalty']) > 0
            ) {
                $receiptQrCodeEncryption = $brandSetting['Receipt QR Code Encryption'];
                $encryptionKeyCodeLoyalty = $brandSetting['Encryption Key Code Loyalty'];
                $loyaltyReferenceNumberQrCode = isset($brandSetting['Loyalty Reference Number QR Code'])
                    && strlen($brandSetting['Loyalty Reference Number QR Code']) > 0
                    ? $brandSetting['Loyalty Reference Number QR Code'] : 'billNum';

                if ($salesModel == null) {
                    $salesModel = SalesHead::findOne($salesNum)->toArray();
                }

                if ($membershipType == "stamps" && strlen($salesModel['flagExternalMemberPhone']) > 0) {
                    //validate membership stamps if apply member
                    return null;
                }

                $branchID = Setting::getCurrentBranch();
                $branchModel = Branch::find()
                    ->andWhere([
                        Branch::tableName() . '.flagActive' => 1,
                        Branch::tableName() . '.branchID' => $branchID
                    ])->one();
                
                $branchCode = $branchModel['branchCode'];
                $referenceNumber = $salesModel[$loyaltyReferenceNumberQrCode]; //billNum or salesNum
                $salesDateOut = $salesModel['salesDateOut'];
                $billingGrandTotal = $salesModel['grandTotal'] - $salesModel['roundingTotal'];
                $visitPurposeID = $salesModel['visitPurposeID'];
                $qrText = $branchCode . '|' . $referenceNumber . '|' . $salesDateOut . '|' . $billingGrandTotal;

                if ($branchCode && $referenceNumber && $salesDateOut && $billingGrandTotal && $qrText) {
                    if ($receiptQrCodeEncryption == 'PC1') {
                        $pc1 = new PC1();
                        $decryptedKey = Yii::$app->security->decryptByKey(
                            base64_decode($encryptionKeyCodeLoyalty),
                            $companyAuthKey
                        );
                        $encryptedText = $pc1->encrypt($qrText, $decryptedKey);
                    } else if ($receiptQrCodeEncryption == 'AES' && isset($brandSetting['External Loyalty URL'])) {
                        $loyaltyHangryUrl = $brandSetting['External Loyalty URL'];
                        $qrText = $qrText . '|' . $visitPurposeID;
                        $decryptedKey = Yii::$app->security->decryptByKey(
                            base64_decode($encryptionKeyCodeLoyalty),
                            $companyAuthKey
                        );
                        $secretKey = base64_decode($decryptedKey);
                        $aes = new AESEncryption(
                            $qrText,
                            $secretKey,
                            null,
                            '256'
                        );
                        $ivEncrypt = base64_encode($aes->getIV());
                        $encryptedText = $aes->encrypt();
                        $encryptedText = "$ivEncrypt:$encryptedText:" . hash_hmac('sha256', "$ivEncrypt:$encryptedText", $decryptedKey);
                        if ($loyaltyHangryUrl) {
                            $encryptedText = str_replace("<QRPayload>", urlencode($encryptedText), $loyaltyHangryUrl);
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            Yii::error("Failed to generate QR Data: " . $ex->getMessage());
        }
        return $encryptedText;
    }

    public static function getConnectionArray() {
        $db = Yii::$app->db;
        $dbName = AppHelper::getDsnAttribute('dbname', $db->dsn);
        $dbHost = AppHelper::getDsnAttribute('host', $db->dsn);

        $connectionArray = [];
        if (strpos($dbName, '_trial') === false) {
            $connectionArray[0] = $dbName . '_trial';
            $connectionArray[1] = $dbName;
        } else {
            $connectionArray[0] = $dbName;
            $connectionArray[1] = str_replace('_trial', '', $dbName);
        }

        return (object) array(
            'connection' => $connectionArray,
            'host' => $dbHost
        );
    }

    public static function checkSpecialChar($string)
    {
        // Match Enclosed Alphanumeric Supplement
        $regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
        $clear_string = preg_replace($regex_alphanumeric, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $clear_string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);

        // Match Supplemental Symbols and Pictographs
        $regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
        $clear_string = preg_replace($regex_supplemental, '', $clear_string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clear_string = preg_replace($regex_dingbats, '', $clear_string);

        // Match Special Script
        $clear_string = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $clear_string);

        // takeout Special Script
        $clear_string = preg_replace('/[^a-zA-Z0-9_ -]/s',' ',$clear_string);

        return $clear_string;
    }

    public static function reformatTypeDataHead($salesModel) {
      $tempSalesModelArray = [];
      foreach ($salesModel as $key => $value) {
        if ((strpos(strtolower($key), 'id') !== false ||
            strpos(strtolower($key), 'active') !== false ||
            strpos(strtolower($key), 'flaginclusive') !== false ||
            strpos(strtolower($key), 'pax') !== false ||
            strpos(strtolower($key), 'print') !== false ||
            strpos(strtolower($key), 'flagexternalapi') !== false ||
            strpos(strtolower($key), 'locktable') !== false)
            &&
            (strpos(strtolower($key), 'flagexternalmemberid') === false &&
            strpos(strtolower($key), 'flagexternalcardid') === false &&
            strpos(strtolower($key), 'externalcanceltransid') === false &&
            strpos(strtolower($key), 'externalmembershiptypeid') === false &&
            strpos(strtolower($key), 'externaltransid') === false &&
            strpos(strtolower($key), 'selforderidkiosk') === false &&
            strpos(strtolower($key), 'terminalid') === false &&
            strpos(strtolower($key), 'transactionmodeid') === false)
          )
        {
          $tempSalesModelArray[$key] = (int) $value;
        } else if (strpos(strtolower($key), 'total') !== false ||
            strpos(strtolower($key), 'discount') !== false ||
            strpos(strtolower($key), 'deliverycost') !== false ||
            strpos(strtolower($key), 'orderfee') !== false
          )
        {
          $tempSalesModelArray[$key] = (float) $value;
        } else {
          $tempSalesModelArray[$key] = $value;
        }
      }
      return $tempSalesModelArray;
    }
    
    public static function reformatTypeDataMenu($salesMenu, $field) {
      $tempArray = [];
      foreach ($salesMenu as $item) {
        $tempItem = [];
        foreach ($item as $key => $value) {
          if ((strpos(strtolower($key), 'id') !== false && (strtolower($key) !== 'posexternalpaymentid') && (strtolower($key) !== 'selforderid')) ||
              strpos(strtolower($key), 'active') !== false ||
              strpos(strtolower($key), 'print') !== false ||
              strpos(strtolower($key), 'pending') !== false ||
              strpos(strtolower($key), 'zerovaluetext') !== false ||
              strpos(strtolower($key), 'luxury') !== false
            )
          {
            $tempItem[$key] = (int) $value;
          } else if (strpos(strtolower($key), 'qty') !== false ||
              strpos(strtolower($key), 'total') !== false ||
              strpos(strtolower($key), 'price') !== false ||
              strpos(strtolower($key), 'discount') !== false ||
              strpos(strtolower($key), 'vat') !== false ||
              strpos(strtolower($key), 'tax') !== false ||
              strpos(strtolower($key), 'amount') !== false
            )
          {
            $tempItem[$key] = (float) $value;
          } else {
            $tempItem[$key] = $value;
          }
        }
        $tempArray[$item[$field]][] = $tempItem;
      }
      return $tempArray;
    }

    public static function checkSalesTypeEzo($salesType) {
      return strpos($salesType, 'EZO') !== false;
    }
    
    public static function fromChinese($menuName) {

        return iconv("UTF-8", "GB2312//IGNORE", $menuName);
    }

    public static function convertErrorMessage($errData) {
    
        if (strpos($errData, 'Insufficient qty for:') !== false) {
            $errorMessages = explode(':', $errData);
            $menuDatas = explode(',', $errorMessages[1]);
            $menuIDs = [];
            foreach ($menuDatas as  $value) {
                $menuDatas = explode('-', $value);
                $menuIDs[] = trim($menuDatas[0]) . ' - '. trim($menuDatas[2], '\\"')  . " - " . trim($menuDatas[1]);
            }
            $errMsg = json_encode([
                'status' => 401,
                'message' => 'Insufficient qty',
                'data' => [
                    "menuID" => $menuIDs
                ]
            ]);
        } elseif (strpos($errData, 'Some menu does not exist.') !== false) {
            $menuIDs = $errData ? explode('-', $errData) : $errData;
            $errMsg = json_encode([
                'status' => 402,
                'message' => 'Some menu does not exist.',
                'data' => [
                    "menuID" => trim($menuIDs[0])
                ]
            ]);
        } elseif (strpos($errData, 'Some payment method is not found!') !== false) {
            $paymentMethods = $errData ? explode('-', $errData) : $errData;
            $errMsg = json_encode([
                'status' => 403,
                'message' => 'Some payment method is not found!',
                'data' => [
                    "paymentMethodID" => trim($paymentMethods[0])
                ]
            ]);
        } elseif (strpos($errData, "Invalid datetime format: 1292 Incorrect date value: '' for column 'salesDate'") !== false) {
            $errMsg = json_encode([
                'status' => 405,
                'message' => 'There’s no active shift. Please retry after starting a new shift.',
                'data' => $errData
            ]);
        } else {
            $errMsg = json_encode([
                'status' => 400,
                'message' => 'General Error Message',
                'data' => $errData
            ]);
        }

        return $errMsg;
    }

    public static function errorLogInformation(){
        $backtrace = debug_backtrace();
        $line = $backtrace[0]['line'];
        $file = $backtrace[0]['file'];

        return 'Error line : '.$line.' - on file :'. $file;
    }
}
