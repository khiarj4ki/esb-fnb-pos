<?php
namespace app\models\forms;

use app\models\Branch;
use app\models\Brand;
use app\models\BrandApiContent;
use app\models\BrandSetting;
use app\models\ExternalToken;
use app\models\PaymentMethodExternalVoucher;
use app\models\PromotionPrefix;
use app\models\SalesHead;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use DateTime;
use Exception;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\httpclient\Client;

/**
 * @property string $salesNum
 * @property string $voucherCode
 */
class ExternalVoucher extends Model {
    CONST ERR_NO_INTERNET_CONNECTION = "You seem to be offline. Please check your internet connection and try again.";
    CONST GET_TOKEN_VOUCHER_API_URL = 'Get Token Voucher API Url';
    CONST TRANSACTION_VOUCHER_API_URL = 'Transaction Voucher API Url';
    CONST BURN_VOUCHER_API_URL = 'Burn Voucher API Url';
    CONST UNBURN_VOUCHER_API_URL = 'Unburn Voucher API Url';
    CONST GET_STATIC_TOKEN = 'Static Token';
    CONST GET_MEMBER_API_URL = 'Get Member API Url';
    CONST GET_GIFTEE_TOKEN = 'Giftee Get Token';
    CONST BENEFIT_LIST_API_URL = 'Benefit List API URL';
    CONST BENEFIT_BURN_API_URL = 'Benefit Burn API URL';

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'voucherCode'], 'required'],
            [['terminalID', 'dataPluxeeLogging'], 'safe'],
        ];
    }

    private static function getToken($brandID, $brandPosSetting, $companyAuthKey, $terminalID, $key, $forceUpdate = 0, $transactionID = null) {
        try {
            $settingModel = Setting::getSetting('Local Setting', $key);
            if($forceUpdate === 0 && $settingModel) {
                return $settingModel->value1;
            }

            if(!$settingModel) {
                $settingModel = new Setting();
                $settingModel->key1 = 'Local Setting';
                $settingModel->key2 = $key;
            }

            $brandApiContentModel = BrandApiContent::findApiContent($brandID, SELF::GET_TOKEN_VOUCHER_API_URL);

            $bodyRequest = [];
            foreach($brandApiContentModel->all() as $tokenContent) {
                $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
            }
            $bodyRequest['posId'] = $terminalID;
            $bodyRequest['clientTime'] = date('Y-m-d H:i:s');

            if ($transactionID != null) {
                $bodyRequest['transactionId'] = (string)$transactionID;
            }

            $client = new Client();
            $tokenApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
            $result = $client->post($tokenApiUrl)
                            ->addHeaders([
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                            ])
                            ->setFormat(Client::FORMAT_JSON)
                            ->addData(
                                $bodyRequest
                            )->send();
            $response = $result->getData();
            if ($result->getIsOk()) {
                $newToken = '';
                if (isset($response['responseCode'])) {
                    if ($response['responseCode'] === 0) {
                        $newToken = $response['token'];
                    } else {
                        throw new Exception(isset($response['responseMessage']) ? $response['responseMessage'] : 'Invalid Authorization', 400);
                    }
                } else {
                    $newToken = $response['token'];
                }

                $settingModel->value1 = $newToken;
                if ($settingModel->save()) {
                    return $newToken;
                } else {
                    throw $settingModel->getErrors();
                }
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
        
    }

    public static function getTokenQwikCilver($brandID, $brandPosSetting, $companyAuthKey, $terminalID, $forceUpdate) {
        try {
            $externalTokenModel = ExternalToken::findOne(['terminalID' => $terminalID]);
            if ($forceUpdate === 0 && $externalTokenModel) {
                return $externalTokenModel;
            }

            if(!$externalTokenModel) {
                $externalTokenModel = new ExternalToken();
                $externalTokenModel->terminalID = $terminalID;
            }

            $brandApiContentModel = BrandApiContent::findApiContent($brandID, SELF::GET_TOKEN_VOUCHER_API_URL);

            $bodyRequest = [];
            foreach($brandApiContentModel->all() as $tokenContent) {
                $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
            }

            $transactionID = 1;
            $bodyRequest['terminalID'] = $terminalID;
            $bodyRequest['dateAtClient'] = date('Y-m-d H:i:s');
            $bodyRequest['transactionId'] = $transactionID;

            $client = new Client();
            $tokenApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
            $result = $client->post($tokenApiUrl)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->addData($bodyRequest)
                ->send();

            $response = $result->getData();
            if ($result->getIsOk()) {
                if ($response['responseCode'] === 0) {
                    $newToken = $response['authToken'];
                    $externalTokenModel->token = $newToken;
                    $externalTokenModel->batchID = (string)$response['batchId'];
                    $externalTokenModel->transactionID = (string)$transactionID;
                    if ($externalTokenModel->save()) {
                        return $externalTokenModel;
                    } else {
                        throw $externalTokenModel->getErrors();
                    }
                } else {
                    throw new Exception(isset($response['responseMessage']) ? $response['responseMessage'] : 'Invalid Authorization', 400);
                }
            }
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function validateVoucher($voucherCode, $salesNum, $terminalID) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }

        $authToken = Setting::getExternalVoucherToken();
        $brandPosSetting = BrandSetting::getBrandPosSetting();

        $maxAttempts = 2;
        $numOfAttempts = $maxAttempts+1;
        $attempts = 0;
        $dataLogging = null;
        do {
            try {
                if (!$terminalID) {
                    throw new Exception("Terminal is not set", 1);
                }
                
                $rawArrayCardNumber = str_split($voucherCode);
                $cardNumber = "";
                if (count($rawArrayCardNumber) > 16 && count($rawArrayCardNumber) === 26) {
                    $positionExtractedCode = [2,3,5,6,8,9,11,12,14,15,17,18,20,21,23,24];
                    for($x=1; $x <= count($rawArrayCardNumber); $x++) {
                        $cardNumber .= (in_array($x, $positionExtractedCode)) ? $rawArrayCardNumber[$x-1] : '';
                    }
                } else if(count($rawArrayCardNumber) === 27) {
                    $positionExtractedCode = [10,11,7,8,4,5,26,27,12,14,16,18,20,22,1,2];
                    for ($i = 0; $i < count($positionExtractedCode); $i++) {
                        $cardNumber .= $rawArrayCardNumber[$positionExtractedCode[$i]-1];
                    }
                } else {
                    $cardNumber = $voucherCode;
                }
                
                $trackData = $voucherCode;
                
                $salesHead = SalesHead::findMainSales(null, $salesNum);
                $retryKey = $salesHead->salesNum;
                //@notes: temporary transactionID = microtime
                $bodyRequest = [
                    'transactionId' => (int) date("dHis"),
                    'modeId' => "0",
                    'clientTime' => date("Y-m-d H:i:s"),
                    'transactionMethodId' => 702,
                    'actionId' => "2",
                    'transactionAmount' => 0,
                    'invoiceDate' => $salesHead->salesDate,
                    'invoiceAmount' => $salesHead->grandTotal,
                    'invoiceNumber' => $salesHead->salesNum,
                    'notes' => "Validate Voucher",
                    'retryKey' => $retryKey,
                    'orderType' => "1",
                    'voucherItems' => [
                        array(
                            "itemNo" => "01",
                            "inputType" => "1",
                            "numberOfVouchers" => "1",
                            "voucherInfo" => array(
                                "voucherNumber" => $cardNumber,
                                "trackData" => $trackData
                            )
                        )
                    ]
                ];

                $dataLogging['body'] = $bodyRequest;

                if ($authToken === null || $authToken === '') {
                    $authToken = SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, $terminalID, 'External Voucher Token', 0);
                }
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);
             
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl;
                $headers = ['Authorization' => 'Bearer ' . $authToken,];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);

                $dataLogging['response'] = json_decode($result->getContent(), true);
                Logging::save($salesNum, Logging::MAP_VALIDATE_VOUCHER, $dataLogging);

                if ($result->getIsOk()) {

                    $response = $result->getData();
                    return [
                        'status' => 'Voucher is valid',
                        'voucher' => SELF::parseLineItemsExternalVoucher($response['voucherItems'])[0]
                    ];
                } else {

                    $response = $result->getData();
                    $errMsg = $response['responseMessage'];
                    if($response['rejectedVoucherList'] && count($response['rejectedVoucherList'][0]['vouchers']) > 0) {
                        $errMsg = $response['rejectedVoucherList'][0]['vouchers'][0]['responseMessage'];
                    }
                    $statusCode = $result->getStatusCode();
                    throw new Exception($errMsg, $statusCode);
                }
            } catch (Exception $ex) {
                if ($dataLogging) {
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save($salesNum, Logging::MAP_VALIDATE_VOUCHER, $dataLogging);
                }
                if (($ex->getCode() == 401 || $ex->getMessage() === 'Authorization Token Expired.' || $ex->getMessage() === 'Invalid batch.' || $ex->getMessage() === 'Invalid request.') && $attempts < $maxAttempts - 1) {
                    $authToken = SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, $terminalID, 'External Voucher Token', 1);
                    $attempts++;
                    sleep(1);
                    continue;
                    
                } else {
                    $status = $ex->getMessage();
                    if ($status == "Unrecognized format ''") {
                        $status = 'Server is unreachable. Please try again.';
                    }
                    $resp = [
                        'status' => $status,
                        'voucher' => null
                    ];
                    Logging::save($voucherCode, Logging::MAP_VALIDATE_VOUCHER, $resp);
                    return $resp;
                }
            }
            break;
        } while ($attempts < $numOfAttempts);
    }

    public static function getVoucherListMemberID($salesNum, $voucherType = null) {
        $memberCode = null;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        if ($salesNum) {
            $salesModel = SalesHead::findOne($salesNum);
            if (!$salesModel) {
                throw new Exception("Sales model not found", 1);
            }
            $memberCode = $salesModel->flagExternalMemberID;
        }
        if (!$memberCode) {
            throw new Exception("Member code not found", 1);   
        }
        $memberIdBranchCode = Setting::getMemberIdBranchCode();
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);

        try {
            $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
            $client = new Client();
            $memberRequest = '?memberCode=' . $memberCode . '&outletCode=' .$memberIdBranchCode . '&voucherType=' .$voucherType;
            $dataVoucherList = $client->get($tokenVoucherApiUrl . $memberRequest)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mid-client-key' => $authToken,
            ])->send();
            
            $response = [];
            if ($dataVoucherList->getIsOk()) { 
                $voucherList = $dataVoucherList->getData();
                if ($voucherList['data']['vouchers']['amount']) {
                    foreach ($voucherList['data']['vouchers']['amount'] as $voucher) {
                        array_push($response, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "amount" => $voucher['amount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    }
                }
                $response['amount'] = $response;
            }

            return $response;
        } catch (Exception $ex) {
           
            return [
                'status' => $ex->getMessage(),
                'voucher' => null
            ];
        }
    }

    public static function getVoucherListLoyalty($salesNum, $voucherType = null) {
        $memberCode = null;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        if ($salesNum) {
            $salesModel = SalesHead::findOne($salesNum);
            if (!$salesModel) {
                throw new Exception("Sales model not found", 1);
            }
            $memberCode = $salesModel->flagExternalMemberID;
            if (!$memberCode) {
                throw new Exception("Member code not found", 1);   
            }
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $branch = Branch::findOne(['branchID' => $branchID]);

        try {
            $memberPromotionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting['Get Member API Url']), $companyAuthKey);
            $accessToken = $externalMemberSetting[SELF::GET_STATIC_TOKEN];
            $client = new Client();
            $memberRequest = 'promotion/?outlet_code='.$branch->branchCode.'&member_code='.$memberCode;
            // $memberRequest = 'promotion/?outlet_code=010101&member_code='.$memberCode;

            $dataVoucherList = $client->get($memberPromotionApiUrl . $memberRequest)
                ->addHeaders([
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])->send();

            $response = [];
            if ($dataVoucherList->getIsOk()) { 
                $voucherList = $dataVoucherList->getData();
                //voucher for amount reduction
                if ($voucherList['data']['detail']['voucher_amount']) {
                    foreach ($voucherList['data']['detail']['voucher_amount'] as $voucher_amount) {
                        array_push($response, array(
                            "voucherCode" => $voucher_amount['voucher_code'],
                            "discount" => $voucher_amount['amount'],
                            "expiredAt" => $voucher_amount['expired_at'],
                            "promotionCode" => $voucher_amount['acc_promo_code'],
                            "voucherDescription" => $voucher_amount['reward_name'],
                        ));
                    }
                }
                //benefit for total amount reduction
                if ($voucherList['data']['detail']['discount_plu']) {
                    foreach ($voucherList['data']['detail']['discount_plu'] as $discount_plu) {
                        if ($discount_plu['result_type'] == "DISCOUNT_PRICE") {
                            array_push($response, array(
                                "voucherType" => "AMOUNT",
                                "voucherCode" => $discount_plu['promo_id'],
                                "promotionCode" => $discount_plu['acc_promo_code'],
                                "itemCode" => $discount_plu['plu'],
                                "itemName" => $discount_plu['plu_name'],
                                "price" => $discount_plu['price'],
                                "discount" => $discount_plu['discount'],
                                "minimumSpending" => 0,
                                "voucherDescription" => $discount_plu['promo_desc'],
                            ));
                        }
                    }
                }
                //benefit for bill amount reduction
                if ($voucherList['data']['detail']['voucher_amount']) {
                    foreach ($voucherList['data']['detail']['voucher_amount'] as $voucher_amount) {
                        array_push($response, array(
                            "voucherCode" => $voucher_amount['voucher_code'],
                            "discount" => $voucher_amount['amount'],
                            "expiredAt" => $voucher_amount['expired_at'],
                            "promotionCode" => $voucher_amount['acc_promo_code'],
                            "voucherDescription" => $voucher_amount['reward_name'],
                        ));
                    }
                }
                $response['amount'] = $response;
            }

            return $response;
        } catch (Exception $ex) {
       
            return [
                'status' => $ex->getMessage(),
                'voucher' => null
            ];
        }
    }

    public static function validateVoucherMemberID($voucherCode, $salesNum = null) {
        $memberCode = null;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        if ($salesNum) {
            $salesModel = SalesHead::findOne($salesNum);
            $memberCode = $salesModel->flagExternalMemberID;
        }

        if (!$memberCode) {
            throw new Exception("Member not found", 1);
        }
        $memberIdBranchCode = Setting::getMemberIdBranchCode();
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();    
        try {
                $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
                if (!$authToken) {
                    throw new Exception("Authentication token not found", 1);
                }
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);

                $memberRequest = '?memberCode=' . $memberCode . '&outletCode=' . $memberIdBranchCode .'&voucherCode='. $voucherCode .'&salesNum='. $salesNum;
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl . $memberRequest;
                $headers = [
                  'mid-client-key' => $authToken,
                ];
                $options = ['timeOut' => 300];
                $result = $httpService->get($url, $headers, $options);
       
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if (isset($response['statusCode'])) {
                        if ($response['statusCode'] == 404) {
                            throw new Exception($response['message'], 1);
                        } else if($response['statusCode'] == 200) {
                            if (isset($response['data'])) {
                                $voucher = (object) array(
                                    'ID' => null,
                                    'voucherCode' => $response['data']['voucherCode'],
                                    'voucherAmount' => isset($response['data']['amount']) ? $response['data']['amount'] : 0,
                                    'minimumSalesAmount' => isset($response['data']['minimumSpending']) ? $response['data']['minimumSpending'] : 0,
                                    'notes' => '',
                                    'verificationCode' => '',
                                    'externalVoucherCode' => '',
                                    'flagExternalVoucherAPI' => 1,
                                    'responseMessage' => ''
                                );
                                        
                            return [
                                'status' => 'Voucher is valid',
                                'voucher' => $voucher
                            ];
                            }
                            return [];
                        } else {
                            return [];
                        }
                    } else {
                        throw new Exception("Cannot validate voucher", 1);
                    }              
                } else {
                    $response = $result->getData();
                    $errMsg = $response['message'];
                    throw new Exception($errMsg, 1);
                }
            } catch (Exception $ex) {
            
                return [
                    'status' => $ex->getMessage(),
                    'voucher' => null
                ];
            }
    }

    public static function validateVoucherESBLoopLite($voucherCode, $salesNum = null) {
        $memberCode = null;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        if ($salesNum) {
            $salesModel = SalesHead::findOne($salesNum);
            $memberCode = $salesModel->flagExternalMemberID;
        }

        if (!$memberCode) {
            throw new Exception("Member not found", 1);
        }
        $branch = Branch::findOne(['branchID' => $branchID]);
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        try {
                $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
                if (!$authToken) {
                    throw new Exception("Authentication token not found", 1);
                }
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);
                $memberRequest = '?memberCode=' . $memberCode . '&companyCode=' . $branch->companyCode . '&outletCode=' . $branch->branchCode .'&voucherCode='. $voucherCode;
      
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl . $memberRequest;
                $headers = ['mid-client-key' => $authToken];
                $options = ['timeOut' => 300];
                $result = $httpService->get($url, $headers, $options);

                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if (isset($response['statusCode'])) {
                        if ($response['statusCode'] == 404) {
                            throw new Exception($response['message'], 1);
                        } else if($response['statusCode'] == 200) {
                            if (isset($response['data'])) {
                                $voucher = (object) array(
                                    'ID' => null,
                                    'voucherCode' => $response['data']['voucherCode'],
                                    'voucherAmount' => isset($response['data']['amount']) ? $response['data']['amount'] : 0,
                                    'minimumSalesAmount' => $response['data']['minimumSpending'],
                                    'notes' => '',
                                    'verificationCode' => '',
                                    'externalVoucherCode' => '',
                                    'flagExternalVoucherAPI' => 1,
                                    'responseMessage' => ''
                                );
                                        
                            return [
                                'status' => 'Voucher is valid',
                                'voucher' => $voucher
                            ];
                            }
                            return [];
                        } else {
                            return [];
                        }
                    } else {
                        throw new Exception("Cannot validate voucher", 1);
                    }              
                } else {
                    $response = $result->getData();
                    $errMsg = $response['message'];
                    throw new Exception($errMsg, 1);
                }
            } catch (Exception $ex) {
                
                return [
                    'status' => $ex->getMessage(),
                    'voucher' => null
                ];
            }
    }

    public static function validateVoucherLoyalty($voucherCode, $salesNum = null, $checkOnPaymentPage = false) {
        $memberCode = null;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        if ($salesNum) {
            $salesModel = SalesHead::findOne($salesNum);
            if (!$salesModel) {
                throw new Exception("Sales model not found", 1);
            }
            $memberCode = $salesModel->flagExternalMemberID;
            if (!$memberCode) {
                throw new Exception("Member code not found", 1);   
            }
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();

        try {
            $transactionVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting['Transaction Voucher API Url']), $companyAuthKey);
            $accessToken = ExternalMember::getToken('Loyalty Token', 1, 'cardID');
            $client = new Client();
            $voucherRequest = '?voucher_code='.$voucherCode;

            $dataVoucherList = $client->get($transactionVoucherApiUrl . $voucherRequest)
                ->addHeaders([
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])->send();

            if ($dataVoucherList->getIsOk()) {  
                $response = $dataVoucherList->getData();
                //voucher for amount reduction
                if ($response['meta']['code'] == 200) {
                    if ($response['data']['detail']) {
                        $voucher_amount = $response['data']['detail'];
                        if (!$checkOnPaymentPage || ($checkOnPaymentPage && !isset($voucher_amount['plu']))){
                            $voucher = (object) array(
                                'ID' => null,
                                'voucherCode' => $voucher_amount['voucher_code'],
                                'voucherAmount' => isset($voucher_amount['amount']) ? $voucher_amount['amount'] : $voucher_amount['price'],
                                'minimumSalesAmount' => 0,
                                'notes' => '',
                                'verificationCode' => '',
                                'externalVoucherCode' => $voucher_amount['acc_promo_code'],
                                'flagExternalVoucherAPI' => 1,
                                'responseMessage' => ''
                            );
                            return [
                                'status' => 'Voucher is valid',
                                'voucher' => $voucher
                            ];
                        } else {
                            return [
                                'status' => 'Voucher not available',
                                'voucher' => []
                            ];
                        }
                    }
                } else {
                    return [
                        'status' => $response['meta']['message'],
                        'voucher' => []
                    ];
                }
            } else {
                return [
                    'status' => 'Voucher not available',
                    'voucher' => []
                ];
            }
        } catch (Exception $ex) {
         
            return [
                'status' => $ex->getMessage(),
                'voucher' => null
            ];
        }
    }

    public static function validateVoucherGiftee($voucherCode, $paymentMethodID, $salesNum, $promotionID = null) {
        $client = new Client();
        try {
            $prefixVoucherCode = null;
            if ($voucherCode !== null && $voucherCode !== '' && strlen($voucherCode) >= 5) {
                $prefixVoucherCode = substr($voucherCode, 0, 5);
            }

            $externalVoucherModel = null;
            if ($paymentMethodID !== null) {
                $externalVoucherModel = PaymentMethodExternalVoucher::prefixExternalVoucherGiftee($prefixVoucherCode, $paymentMethodID);
                if (!$externalVoucherModel) {
                    throw new Exception("VOUCHER_NOT_FOUND");
                }
            } else {
                $promotionVoucherModel = PromotionPrefix::prefixPromotionVoucherGiftee($prefixVoucherCode, $promotionID);
                if (!$promotionVoucherModel) {
                    throw new Exception("VOUCHER_NOT_FOUND");
                }
            }

            $gifteeUrl = Setting::getValue1('External', 'Giftee Voucher API Url');
            $gifteeHeaders = json_decode(Setting::getValue1('External', 'Giftee Voucher API Header'), true);
            $token = BrandSetting::getBrandSetting('EXTERNAL', SELF::GET_GIFTEE_TOKEN);
            $arrHeaders = array_keys($gifteeHeaders);
            $headers = [
                'Authorization' => 'Bearer '.$token,
            ];
            foreach ($arrHeaders as $value) {
                $headers[$value] = $gifteeHeaders[$value];
            }

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $gifteeUrl . '/' . $voucherCode;
            $options = ['timeOut' => 300];
            $response = $httpService->get($url, $headers, $options);

            $voucher = [];
            if ($response->getIsOk()) {
                $response = $response->getData();
                if (isset($response['code'])) {
                    if ($response['code'] == $voucherCode) {
                        // check voucher availability
                        if (count($response['histories']) > 0) {
                            foreach ($response['histories'] as $value) {
                                if ($value['operation_kind'] == 'exchanged' && ($value['disabled_at'] == null || $value['disabled_at'] == '')) {
                                    throw new Exception("VOUCHER_USED");
                                    break;
                                }
                            }
                        }
                        // check voucher expired
                        $available_begin = $response['available_begin'];
                        $start_date = new DateTime("@$available_begin");
                        $start_date = $start_date->format('Y-m-d H:i:s');

                        $available_end = $response['available_end'];
                        $expired_date = new DateTime("@$available_end");
                        $expired_date = $expired_date->format('Y-m-d H:i:s');
                        
                        $now = date('Y-m-d H:i:s');
                        if ($now > $expired_date) {
                            throw new Exception("VOUCHER_EXPIRED");
                        }
                        
                        $voucherValue = null;
                        if ($externalVoucherModel) {
                            if ($externalVoucherModel->voucherType === 'Amount') {
                                $voucherValue = $externalVoucherModel->amount;
                            } else {
                                $modelSalesHead = SalesHead::findMainSales(null, $salesNum);
                                $modelSalesLink = SalesHead::findLinkSalesHeads($salesNum);

                                $grandTotal = null;
                                if ($modelSalesLink) {
                                    $grandTotal = SalesHead::getTotal($salesNum, 'grandTotal - roundingTotal');
                                } else {
                                    if ($modelSalesHead->roundingTotal < 0) {
                                        $grandTotal = $modelSalesHead->grandTotal + abs($modelSalesHead->roundingTotal); 
                                    } else {
                                        $grandTotal = $modelSalesHead->grandTotal - abs($modelSalesHead->roundingTotal); 
                                    }
                                }
                                
                                $totalAmountPercentage = $grandTotal * $externalVoucherModel->percentageAmount / 100;
                                if ((isset($externalVoucherModel->percentageMaxValue) && $externalVoucherModel->percentageMaxValue == 0) || $totalAmountPercentage < $externalVoucherModel->percentageMaxValue) {
                                    $voucherValue = $totalAmountPercentage;
                                } else if (isset($externalVoucherModel->percentageMaxValue) && $totalAmountPercentage > $externalVoucherModel->percentageMaxValue) {
                                    $voucherValue = $externalVoucherModel->percentageMaxValue;
                                } else if (isset($externalVoucherModel->percentageMaxValue) && $externalVoucherModel->percentageMaxValue > $grandTotal) {
                                    $voucherValue = $grandTotal;
                                }
                            }
                        }

                        $voucherAmount = $voucherValue ? $voucherValue : 0;
                        $voucher = (object) array(
                            'ID' => null,
                            'voucherCode' => $response['code'],
                            'voucherAmount' => (float) $voucherAmount,
                            'minimumSalesAmount' => 0,
                            'notes' => 'Voucher Giftee ' . intval($voucherAmount),
                            'verificationCode' => '',
                            'externalVoucherCode' => '',
                            'flagExternalVoucherAPI' => 1,
                            'voucherStartDate' => $start_date,
                            'voucherEndDate' => $expired_date,
                            'responseMessage' => ''
                        );
                    } else {
                        throw new Exception("VOUCHER_USED");
                    }
                } else {
                    throw new Exception("VOUCHER_NOT_FOUND");
                }
            } else {
                $response = $response->getData();
                if (isset($response['code']) && $response['code'] == 301) {
                    throw new Exception("VOUCHER_NOT_FOUND");
                } else {
                    throw new Exception("FAILED_CONNECT_EXTERNAL");
                }
            }

            return [
                'status' => 'VOUCHER_VALID',
                'voucher' => $voucher
            ];
        } catch (Exception $ex) {
           
            return [
                'status' => $ex->getMessage(),
                'voucher' => null
            ];
        }
    }

    public static function validateVoucherTada($voucherCode, $salesNum) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        } 
    
        try {
                $brandApiContentModel = BrandApiContent::findApiContent($brandModel->brandID, SELF::GET_TOKEN_VOUCHER_API_URL);
                $merchantID = null;
                foreach($brandApiContentModel->all() as $tokenContent) {
                    if ($tokenContent->keyAttribute === 'mID') {
                        $merchantID = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
                    }
                }
                if ($merchantID) {
                    $brandPosSetting = BrandSetting::getBrandPosSetting(); 
                    $checkVoucherApiURL = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
                    $authToken = ExternalMember::getToken('MAP Token', 0);

                    $client = new Client();
                    $result = $client->get($checkVoucherApiURL . '/' . $merchantID . '/' . $voucherCode)
                                ->addHeaders([
                                    'Accept' => 'application/json',
                                    'Authorization' => 'Bearer ' . $authToken,
                                ])->send();
                    
                    if ($result->getIsOk()) {
                        $response = $result->getData();
                        if (isset($response['id'])) {
                            $expiredAt = date('Y-m-d H:i:s', strtotime($response['expiredAt']));
                            $now = date('Y-m-d H:i:s');
                            if ($response['status'] === 'activated' && $now < $expiredAt && ($response['EgiftMaster']['egiftType'] === 'value' || $response['EgiftMaster']['egiftType'] === 'item')) {
                                $salesHead = SalesHead::findMainSales(null, $salesNum);
                                $grandTotal = $salesHead->grandTotal;
                                $validate = true;
                                if ($response['EgiftMaster']['minTransaction'] !== null && $response['EgiftMaster']['egiftType'] === 'value') {
                                    if ($salesHead['grandTotal'] >= $response['EgiftMaster']['minTransaction']) {
                                        $validate = true;
                                    }else{
                                        $errMsg = 'Voucher cannot be used. Minimum transaction is '. number_format($response['EgiftMaster']['minTransaction'],0,",",".");
                                        throw new Exception($errMsg, 1);
                                    }
                                }
                                if ($validate) {
                                    $voucher = (object) array(
                                        'ID' => $response['id'],
                                        'voucherCode' => $voucherCode,
                                        'voucherAmount' => $response['amount'],
                                        'notes' => '',
                                        'verificationCode' => '',
                                        'externalVoucherCode' => '',
                                        'maxDiscount' => $response['EgiftMaster']['maxDiscount'],
                                        'minTransaction' => $response['EgiftMaster']['minTransaction'],
                                        'flagExternalVoucherAPI' => 1,
                                        'responseMessage' => ''
                                    );
                                    return [
                                        'status' => 'Voucher is valid',
                                        'voucher' => $voucher
                                    ];
                                }
                            }else{
                                $errMsg = 'eVoucher is not valid';
                                throw new Exception($errMsg, 1);
                            }
                        } else {
                            $errMsg = $response['message'];
                            throw new Exception($errMsg, 1);
                        }             
                    } else {
                        $response = $result->getData();
                        $errMsg = $response['message'];
                        throw new Exception($errMsg, 1);
                    }
                }else{
                    throw new Exception("Merchant ID not found");
                    
                }
            } catch (Exception $ex) {
               
                return [
                    'status' => $ex->getMessage(),
                    'voucher' => null
                ];
            }
    }

    public static function validateVoucherCapillary($voucherCode, $salesNum, $authToken = null) {
        try {
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();

            $salesModel = SalesHead::findOne($salesNum);
            if(!$salesModel){
                throw new Exception("Sales not found", 500);
            }

            if(!$salesModel->flagExternalMemberID){
                throw new Exception("VOUCHER_NOT_FOUND", 404);
            }

            $client = new Client();
            $authorization = ($authToken != null) ? $authToken : ExternalMember::getToken('MAP Token', 0);
            $apiUrl = $externalMemberSetting['Check Status Membership Voucher API URL Capillary'];

            if ($authorization == null) {
                throw new Exception("The token has not been set", 500);
            }

            $params = "&email=$salesModel->flagExternalMemberID&code=$voucherCode";
            $result = $client->get($apiUrl . '?details=true&format=json' . $params)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->send();

            $response = $result->getData();
            if ($result->getIsOk()) {
                if ($response['response']['status']['success'] === 'true') {
                    $discountType = $response['response']['coupons']['redeemable']['series_info']['discount_type'];
                    $discountValue = $response['response']['coupons']['redeemable']['series_info']['discount_value'];
    
                    $voucherAmount = 0;
                    if ($discountType == "ABS") {
                        $voucherAmount = (float)$discountValue;
                    } else {
                        $discountUpto = (float)$response['response']['coupons']['redeemable']['series_info']['discount_upto'];
                        $voucherAmount = (float)$salesModel->grandTotal * ((float)$discountValue / 100);
                        $voucherAmount = ($voucherAmount < $discountUpto) ? $voucherAmount : $discountUpto;
                    }
                    
                    $voucher = (object) array(
                        'ID' => null,
                        'voucherCode' => $response['response']['coupons']['redeemable']['code'],
                        'voucherAmount' => $voucherAmount,
                        'minimumSalesAmount' => 0,
                        'notes' => $response['response']['coupons']['redeemable']['series_info']['description'],
                        'verificationCode' => '',
                        'externalVoucherCode' => '',
                        'flagExternalVoucherAPI' => 1,
                        'validTill' => $response['response']['coupons']['redeemable']['series_info']['valid_till'],
                        'responseMessage' => ''
                    );

                    return [
                        'status' => 'VOUCHER_VALID',
                        'voucher' => $voucher
                    ];
                } else {
                    $itemStatusCode = $response['response']['coupons']['redeemable']['item_status']['code'];
                    $voucherStatus = '';
                    switch ($itemStatusCode) {
                        case 711:
                            $voucherStatus = "VOUCHER_USED";
                            break;
                        case 713:
                            $voucherStatus = "VOUCHER_EXPIRED";
                            break;
                        case 719:
                            $voucherStatus = "VOUCHER_NOT_FOUND";
                            break;
                        default:
                            $voucherStatus = $response['response']['coupons']['redeemable']['item_status']['message'];
                            break;
                    }
                    throw new Exception($voucherStatus, in_array($itemStatusCode, [711, 713, 719]) ? 404 : 400);
                }
            } else {
                throw new Exception("Server is unreachable ($result->getStatusCode())", 500);
            }
        } catch (Exception $ex) {
            $exCode = $ex->getCode();
            $exMessage = $ex->getMessage();
           
            return [
                'code' => ($exCode != 0) ? $exCode : 500,
                'status' => ($exCode != 0 || $exCode != 500) ? $exMessage : "Internal Server Error",
                'voucher' => null
            ];
        }
    }

    public static function validateVoucherCapillaryV2($voucherCode, $salesNum, $authorization = null, $apiUrl = null) {
        try {
            if (is_null($authorization) && is_null($apiUrl)) {
                $url = 'Check Status Membership Voucher API URL Capillary';
                $capillarySetting = ExternalMember::getCapillarySetting($url);
                $apiUrl = $capillarySetting['apiUrl'];
                $authorization = $capillarySetting['authorization'];
            }

            $salesModel = SalesHead::findOne($salesNum);
            if (!$salesModel) {
                throw new Exception("Sales not found", 500);
            }

            if (!$salesModel->flagExternalMemberID) {
                throw new Exception("VOUCHER_NOT_FOUND", 404);
            }

            $client = new Client();

            if ($authorization == null) {
                throw new Exception("The authentication failed", 500);
            }

            $params = "&email=$salesModel->flagExternalMemberID&code=$voucherCode";
            $result = $client->get($apiUrl . '?details=true&format=json' . $params)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->send();

            $response = $result->getData();
            if ($result->getIsOk()) {
                if ($response['response']['status']['success'] === 'true') {
                    $discountType = $response['response']['coupons']['redeemable']['series_info']['discount_type'];
                    $discountValue = $response['response']['coupons']['redeemable']['series_info']['discount_value'];
    
                    $voucherAmount = 0;
                    if ($discountType == "ABS") {
                        $voucherAmount = (float)$discountValue;
                    } else {
                        $discountUpto = (float)$response['response']['coupons']['redeemable']['series_info']['discount_upto'];
                        $voucherAmount = (float)$salesModel->grandTotal * ((float)$discountValue / 100);
                        if ($discountUpto > 0) {
                            $voucherAmount = ($voucherAmount < $discountUpto) ? $voucherAmount : $discountUpto;
                        }
                    }
                    
                    $voucher = (object) array(
                        'ID' => null,
                        'voucherCode' => $response['response']['coupons']['redeemable']['code'],
                        'voucherAmount' => $voucherAmount,
                        'minimumSalesAmount' => 0,
                        'notes' => $response['response']['coupons']['redeemable']['series_info']['description'],
                        'verificationCode' => '',
                        'externalVoucherCode' => '',
                        'flagExternalVoucherAPI' => 1,
                        'validTill' => $response['response']['coupons']['redeemable']['series_info']['valid_till'],
                        'responseMessage' => ''
                    );

                    return [
                        'status' => 'VOUCHER_VALID',
                        'voucher' => $voucher
                    ];
                } else {
                    $itemStatusCode = $response['response']['coupons']['redeemable']['item_status']['code'];
                    $voucherStatus = '';
                    switch ($itemStatusCode) {
                        case 711:
                            $voucherStatus = "VOUCHER_USED";
                            break;
                        case 713:
                            $voucherStatus = "VOUCHER_EXPIRED";
                            break;
                        case 719:
                            $voucherStatus = "VOUCHER_NOT_FOUND";
                            break;
                        default:
                            $voucherStatus = $response['response']['coupons']['redeemable']['item_status']['message'];
                            break;
                    }
                    throw new Exception($voucherStatus, in_array($itemStatusCode, [711, 713, 719]) ? 404 : 400);
                }
            } else {
                throw new Exception("Server is unreachable ($result->getStatusCode())", 500);
            }
        } catch (Exception $ex) {
            $exCode = $ex->getCode();
            $exMessage = $ex->getMessage();
         
            return [
                'code' => ($exCode != 0) ? $exCode : 500,
                'status' => ($exCode != 0 || $exCode != 500) ? $exMessage : "Internal Server Error",
                'voucher' => null
            ];
        }
    }

    public static function validateVoucherQwikCilver($voucherCode, $voucherPIN, $trackData) {
        try {
            $companyAuthKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();
            $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
            if (!$brandModel) {
                throw new Exception("Brand Not Found", 1);
            }
            $branchModel = Branch::findOne(['branchID' => $branchID]);

            $dataLogging = null;
            $bodyRequest = [
                "TrackData" => (string)$trackData,
                "CardNumber" => (string)$voucherCode,
                "CardPIN" => (string)$voucherPIN,
                "BranchCode" => $branchModel->branchCode
            ];
            $dataLogging['body'] = $bodyRequest;
            
            $transactionApiUrl = Setting::getApiUrl() . "/qwikcilver/main/get-balance-inquiry";

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $transactionApiUrl;
            $headers = [
               'Authorization' => 'Bearer ' . $companyAuthKey
            ];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $bodyRequest, $options);
            $response = $result->getData();

            if ($result->getIsOk()) {
                $responseCode = $response['ResponseCode'];
                if ($responseCode === 0) {
                    if ($response['TotalRedeemedAmount'] == 0) {
                        $voucher = (object) array(
                            'ID' => null,
                            'voucherCode' => $voucherCode,
                            'voucherAmount' => (float)$response['Amount'],
                            'voucherPIN' => $voucherPIN,
                            'trackData' => $trackData,
                            'minimumSalesAmount' => 0,
                            'notes' => '',
                            'verificationCode' => '',
                            'externalVoucherCode' => '',
                            'flagExternalVoucherAPI' => 1,
                            'validTill' => '',
                            'responseMessage' => ''
                        );

                        return [
                            'status' => 'VOUCHER_VALID',
                            'voucher' => $voucher
                        ];
                    } else {
                        $voucherStatus = "VOUCHER_USED";
                        throw new Exception($voucherStatus, 404);
                    }
                } else {
                    $voucherStatus = '';
                    switch ($responseCode) {
                        case 10001:
                            $voucherStatus = "VOUCHER_EXPIRED";
                            break;
                        case 10004:
                            $voucherStatus = "VOUCHER_NOT_FOUND";
                            break;
                        default:
                            $voucherStatus = $response['ResponseMessage'];
                            break;
                    }
                    throw new Exception($voucherStatus, in_array($responseCode, [10001, 10004]) ? 404 : 400);
                }
            } else {
                throw new Exception(isset($response['ResponseMessage']) ? $response['ResponseMessage'] : "Server Unreachable", 500);
            }
        } catch (\Exception $ex) {
            $exCode = $ex->getCode();
            $exMessage = $ex->getMessage();
            
            if (strpos($exMessage, "Unrecognized format ''") !== false) {
                $exCode = 400;
                $exMessage = 'There is an issue with the Qwikcilver server,<br>please process the transaction with another payment method.';
            }

            if (in_array($exCode, [400, 404])) {
                return ['code' => $exCode, 'status' => $exMessage, 'voucher' => null];
            } else {
                $exMessage = (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false)
                    ? "Cannot connect to server. Please check your internet connection." : "Server Unreachable";
                return ['code' => 500, 'status' => $exMessage, 'voucher' => null];
            }
        }
    }

    public static function validateVoucherGlobalTix($voucherCode, $paymentMethodID) {
        try {
            $authToken = Setting::getTokenGlobalTix();
            $externalModels = Setting::getLocalSettings();

            $externalSetting = [
                'createTokenUrl' => $externalModels['GlobalTix Token API Url'],
                'validateVoucherUrl' => $externalModels['GlobalTix Get Ticket API Url'],
            ];
    
            if ($authToken === null || $authToken === '') {
                $getUserName = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Username');
                $getPassword = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Password');
                $userName = $getUserName ? $getUserName : '';
                $password = $getPassword ? $getPassword : '';

                $getAuthToken = SELF::createAuthTokenGlobalTix($externalSetting['createTokenUrl'], $userName, $password);
                if (isset($getAuthToken->responseCode)) {
                    return $getAuthToken;
                }
                
                $authToken = Setting::getTokenGlobalTix();
                if ($authToken) {
                    $validateVoucherCode = SELF::validateVoucherCode($authToken, $externalSetting['validateVoucherUrl'], $voucherCode, $paymentMethodID);
                    if (isset($validateVoucherCode->responseCode)) {
                        $attempt = 0;
                        $maxAttempts = 2;
                        while ($attempt <= $maxAttempts) {
                            $validateVoucherCode = SELF::validateVoucherCode($authToken, $externalSetting['validateVoucherUrl'], $voucherCode, $paymentMethodID);
                            if (isset($validateVoucherCode->voucher)) {
                                break;
                            }
                            $attempt++;
                        }

                        if(isset($validateVoucherCode->responseCode)) {
                            return $validateVoucherCode;
                        }
                    }
    
                    if (isset($validateVoucherCode->voucher)) {
                        return $validateVoucherCode;
                    }
                }
            } else {
                $validateVoucherCode = SELF::validateVoucherCode($authToken, $externalSetting['validateVoucherUrl'], $voucherCode, $paymentMethodID);
                if (isset($validateVoucherCode->responseCode)) { 
                    $attempt = 0;
                    $maxAttempts = 2;
                    $errorMessage = 'Token Invalid, please contact your ticket provider';

                    if (isset($validateVoucherCode->message) && $validateVoucherCode->message == $errorMessage) {
                        $getUserName = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Username');
                        $getPassword = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Password');
                        $userName = $getUserName ? $getUserName : '';
                        $password = $getPassword ? $getPassword : '';
                        $getAuthToken = SELF::createAuthTokenGlobalTix($externalSetting['createTokenUrl'], $userName, $password);
                        if (isset($getAuthToken->responseCode)) {
                            return $getAuthToken;
                        }
                    }

                    while ($attempt <= $maxAttempts) {
                        $authToken = Setting::getTokenGlobalTix();
                        if ($authToken) {
                            $validateVoucherCode = SELF::validateVoucherCode($authToken, $externalSetting['validateVoucherUrl'], $voucherCode, $paymentMethodID);
                            if (isset($validateVoucherCode->voucher)) {
                                break;
                            }
                            $attempt++;
                        }
                    }

                    if(isset($validateVoucherCode->responseCode)) {
                        return $validateVoucherCode;
                    }
                }

                if (isset($validateVoucherCode->voucher)) {
                    return $validateVoucherCode;
                }
            }
        } catch (Exception $e) {
           
            return $e;
        }
    }

    public static function createAuthTokenGlobalTix($createTokenUrl, $userName, $password) {
        try {
            $bodyRequest = [
                'username' => $userName,
                'password' => $password
            ];

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $createTokenUrl;
            $headers = [];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $bodyRequest, $options);
            
            $response = $result->getData();
            if ($result->getIsOk()) {
                $authToken = $response['data']['access_token'];
                Yii::$app->db->createCommand()->update(Setting::tableName(), ['value1' => $authToken], "key2 = 'GlobalTix Token'")->execute();
            } else {
                return (object) array(
                    'responseCode' => 400,
                    'message' => 'Username or Password invalid, please contact your ticket provider'
                );
            }
        } catch (Exception $e) {
        
            return $e;
        }
    }

    public static function validateVoucherCode($authToken, $validateVoucherUrl, $voucherCode, $paymentMethodID) {
        try {
            $bodyRequest = [
                'code' => $voucherCode
            ];
            $client = new Client();
            $result = $client->post($validateVoucherUrl)
                ->addHeaders([
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $authToken,
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($bodyRequest)
                ->send();
            
            $response = $result->getData();
            if ($result->getIsOk()) {
                if (
                    (isset($response['data']['tickets']) && $response['data']['tickets'] !== null) && 
                    (isset($response['data']['transaction']) && $response['data']['transaction'] !== null)
                ) {
                    foreach($response['data']['tickets'] as $voucher) {
                        $statusName = $voucher['status']['name'];
                        if ($statusName == 'VALID') {
                            $voucherAmount = 0;
                            $responsePrefixVoucher = $voucher['product']['id'];
                            $voucherPrefixModel = PaymentMethodExternalVoucher::getPrefixExternalVoucherGlobaltix($paymentMethodID, $responsePrefixVoucher);
                            if (isset($voucherPrefixModel)) {
                                if ($responsePrefixVoucher == $voucherPrefixModel['prefix']) {
                                    $voucherAmount = $voucherPrefixModel['amount'];
                                } else if ($responsePrefixVoucher == $voucherPrefixModel['prefix']) {
                                    $voucherAmount = $voucherPrefixModel['amount'];
                                } else if ($responsePrefixVoucher == $voucherPrefixModel['prefix']) {
                                    $voucherAmount = $voucherPrefixModel['amount'];
                                } else {
                                    return (object) array(
                                        'responseCode' => 400,
                                        'message' => 'Prefix not found, please contact your ticket provider'
                                    );
                                }
                            } else {
                                return (object) array(
                                    'responseCode' => 400,
                                    'message' => 'Prefix not found'
                                );
                            }
    
                            $notes = $voucher['displayStatus']['name'] ? $voucher['displayStatus']['name'] : '';
                            $voucher = (object) array(
                                'voucherID' => $voucher['id'],
                                'voucherCode' => $voucherCode,
                                'voucherAmount' => (float)$voucherAmount,
                                'notes' => $notes,
                            );

                            return (object) array(
                                'voucher' => $voucher
                            );
                        } else if ($statusName == 'EXPIRED') {
                            return (object) array(
                                'responseCode' => 400,
                                'message' => 'Ticket has been expired. Please use another ticket'
                            );
                        } else {
                            return (object) array(
                                'responseCode' => 400,
                                'message' => 'Ticket has been used. Please use another ticket'
                            );
                        }
                    }
                } else {
                    return (object) array(
                        'responseCode' => 400,
                        'message' => 'Ticket Not Found'
                    );
                }
            } else {
                return (object) array(
                    'responseCode' => 400,
                    'message' => 'Token Invalid, please contact your ticket provider'
                );
            }
        } catch (Exception $e) {
          
            return $e;
        }
    }

    public static function validateVoucherStamps($voucherCode, $authToken = null) {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne(['branchID' => $branchID]);

        if (!$branchModel) {
            return ['code' => 404, 'status' => 'Branch Not Found', 'voucher' => null];
        }

        $companyAuthKey = Setting::getApiKey();
        $brandPosSetting = BrandSetting::getBrandPosSetting();
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);
        $staticToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
        $store = $branchModel->branchCode;

        $maxAttempts = 2;
        $attempt = 1;
        $isTokenExpired = false;
        do {
            try {
                $authorization = ($authToken != null) ? $authToken : ExternalMember::getToken('MAP Token', ($isTokenExpired ? 1 : 0));
                $client = new Client();
                $result = $client->get("$transactionApiUrl?token=$staticToken,&voucher_code=$voucherCode&store=$store")
                    ->addHeaders([
                        "Content-Type" => "application/json",
                        "Authorization" => "Bearer $authorization",
                    ])
                    ->send();
                $response = $result->getData();

                if (!$result->getIsOk()) {
                    $statusCode = $result->getStatusCode();
                    $errorMessage = isset($response['error_message']) ? $response['error_message'] : "an error occurred in STAMPS ($statusCode)";
                    if ($statusCode == 401) {
                        throw new Exception($errorMessage, $statusCode);
                    }
                    return ['code' => 400, 'status' => $errorMessage, 'voucher' => null];
                }

                if (isset($response['is_redeemable']) && $response['is_redeemable'] === true) {
                    $voucher = (object) [
                        'ID' => null,
                        'voucherID' => $response['voucher']['id'],
                        'voucherCode' => $voucherCode,
                        'voucherAmount' => (float)$response['voucher']['value'],
                        'minimumSalesAmount' => 0,
                        'notes' => $response['voucher']['name'],
                        'verificationCode' => '',
                        'externalVoucherCode' => '',
                        'flagExternalVoucherAPI' => 1,
                        'responseMessage' => '',
                    ];

                    return ['code' => 200, 'status' => 'VOUCHER_VALID', 'voucher' => $voucher];
                } else {
                    $voucherStatus = "VOUCHER_NOT_FOUND";
                    if (isset($response['voucher']['start_date']) && isset($response['voucher']['end_date'])) {
                        $currentDate = new DateTime();
                        $startDate = new DateTime($response['voucher']['start_date']);
                        $endDate = new DateTime($response['voucher']['end_date']);

                        if ($currentDate >= $startDate && $currentDate <= $endDate) {
                            $voucherStatus = "VOUCHER_EXPIRED";
                        }
                    }
                    return ['code' => 404, 'status' => $voucherStatus, 'voucher' => null];
                }
            } catch (\Exception $ex) {
                $exMessage = $ex->getMessage();
                
                if ($ex->getCode() == 401) {
                    $isTokenExpired = true;
                    $attempt++;
                    sleep(1);
                    continue;
                }

                if (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false) {
                    $exCode = 400; $exMessage =  "Cannot connect to the server. Please check your internet connection.";
                } else {
                    $exCode = 500; $exMessage = "Server Unreacable";
                }
                return ['code' => $exCode, 'status' => $exMessage, 'voucher' => null];
                break;
            }
        } while ($isTokenExpired && $attempt <= $maxAttempts);
    }

    public static function saveVoucher($batchVoucher, $salesNum, $terminalID, $isFinalSave=false) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }

        $brandPosSetting = BrandSetting::getBrandPosSetting();
        $terminalID = $terminalID ? $terminalID : 'ESB';

        $maxAttempts = 2;
        $numOfAttempts = $maxAttempts+1;
        $attempts = 0;
        $dataLogging = null;
        do {
            try {
                if (!$terminalID) {
                    throw new Exception("Terminal is not set", 1);
                }

                $voucherItems = array();
                $voucherCodeRelationID = array();
                $increment = 1;
                $transactionAmount = 0;
                foreach($batchVoucher as $voucher) {
                    $transactionAmount += $voucher->fullPaymentAmount;
                    $rawArrayCardNumber = str_split($voucher->voucherCode);
                    $cardNumber = "";
                    if (count($rawArrayCardNumber) > 16 && count($rawArrayCardNumber) === 26) {
                        $positionExtractedCode = [2,3,5,6,8,9,11,12,14,15,17,18,20,21,23,24];
                        for($x=1; $x <= count($rawArrayCardNumber); $x++) {
                            $cardNumber .= (in_array($x, $positionExtractedCode)) ? $rawArrayCardNumber[$x-1] : '';
                        }
                    } else if(count($rawArrayCardNumber) === 27) {
                        $positionExtractedCode = [10,11,7,8,4,5,26,27,12,14,16,18,20,22,1,2];
                        for ($i = 0; $i < count($positionExtractedCode); $i++) {
                            $cardNumber .= $rawArrayCardNumber[$positionExtractedCode[$i]-1];
                        }
                    } else {
                        $cardNumber = (string) $voucher->voucherCode;
                    }
                    $voucherCodeRelationID[$cardNumber] = $voucher->ID;
                    
                    $trackData = (string) $voucher->voucherCode;
                    $voucherItems[] = array(
                            "itemNo" => str_pad($increment, 2, '0', STR_PAD_LEFT),
                            "inputType" => "1",
                            "amountPerVoucher" => $voucher->fullPaymentAmount,
                            "numberOfVouchers" => "1",
                            "voucherInfo" => array(
                                "voucherNumber" => $cardNumber,
                                "trackData" => $trackData
                            )
                        );
                    $increment++;
                }
                
                $salesHead = SalesHead::findMainSales(null, $salesNum);
				$retryKey = $salesHead->salesNum;
                //@notes: temporary transactionID = microtime
                $bodyRequest = [
                    'transactionId' => (int) date("dHis"),
                    'modeId' => "0",
                    'clientTime' => date("Y-m-d H:i:s"),
                    'transactionMethodId' => 702,
                    'actionId' => $isFinalSave ? "1" : "2",
                    'transactionAmount' => $transactionAmount,
                    'invoiceDate' => $salesHead->salesDate,
                    'invoiceAmount' => $salesHead->grandTotal,
                    'invoiceNumber' => $salesHead->salesNum,
                    'notes' => "Final Validate Voucher",
                    'retryKey' => $retryKey,
                    'orderType' => "1",
                    'voucherItems' => $voucherItems
                ];

                $dataLogging['body'] = $bodyRequest;

                $authToken = SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, $terminalID, 'External Voucher Token', 0);
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);
                $client = new Client();
                $result = $client->createRequest()
                                ->setMethod('POST')
                                ->setUrl($transactionApiUrl)
                                ->setHeaders([
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer ' . $authToken,
                                ])
                                ->setFormat(Client::FORMAT_JSON)
                                ->setData($bodyRequest)
                                ->setOptions([
                                    'timeout' => 30
                                ])
                                ->send();
                
                $dataLogging['response'] = json_decode($result->getContent(), true);
                Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                $response = $result->getData();
                if ($result->getIsOk()) {
                    if($response['responseCode'] === 0 && count($response['rejectedVoucherList']) === 0) {
                        return (object) array(
                            'status' => true,
                            'transactionId' => $response['transactionId'],
                            'batchId' => $response['batchId'],
                            'items' => SELF::parseLineItemsExternalVoucher($response['voucherItems'], $voucherCodeRelationID)
                        );
                    } else {
                        return (object) array(
                            'status' => false,
                            'transactionId' => $response['transactionId'],
                            'batchId' => $response['batchId'],
                            'items' => SELF::parseLineItemsExternalVoucher($response['rejectedVoucherList']),
                            'dataLogging' => $dataLogging
                        );
                    }
                } else {
                    $response = $result->getData();
                    $errMsg = $response['responseMessage'];
                    if($response['rejectedVoucherList']) {
                        return (object) array(
                            'status' => false,
                            'transactionId' => $response['transactionId'],
                            'batchId' => $response['batchId'],
                            'items' => SELF::parseLineItemsExternalVoucher($response['rejectedVoucherList']),
                            'dataLogging' => $dataLogging
                        );
                    }
                    throw new Exception($errMsg, 1);
                }
            } catch (Exception $ex) {
              
                if ($dataLogging) {
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                }
                if ($ex->getMessage() === 'Invalid Authorization Token.' && $attempts < $maxAttempts - 1) {
                    SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, $terminalID, 'External Voucher Token', 1);
                    $attempts++;
                    sleep(1);
                    continue;
                    
                } else {
                    $errorMessage = strpos($ex->getMessage(), 'HTTP request failed') !== false ? 'Request Timeout' : 'Server is unreachable. Please try again.';
                    $resp = array(
                        'status' => false,
                        'message' => $errorMessage,
                        'transactionId' => null,
                        'batchId' => null,
                        'items' => [],
                        'body' => $dataLogging,
                        'response' => $ex->getMessage(),
                    );
                    Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $resp);
                    return (object) $resp;
                }

            }
            break;
        } while ($attempts < $numOfAttempts);
    }

    public static function saveVoucherMemberID($batchVoucher, $salesModel) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $memberIdBranchCode = Setting::getMemberIdBranchCode();
        $brandPosSetting = BrandSetting::getBrandPosSetting();
        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        try {
            $status = true;
            $itemErrorArr = [];
            $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
            if (!$authToken) {
                throw new Exception("Authentication token not found", 1);
            }
            $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);
            $newBatchVoucher = [];
            if ($salesModel) {
                if ($salesModel->promotionVoucherCode && $salesModel->promotionVoucherCode != '') {
                    $checkVoucher = ExternalVoucher::validateVoucherMemberID($salesModel->promotionVoucherCode, $salesModel->salesNum);
                    if (isset($checkVoucher['status'])) {
                        if ($checkVoucher['status'] == 'Voucher is valid') {
                            array_push($newBatchVoucher, array(
                                "salesNum" => $salesModel->salesNum,
                                "memberCode" => $salesModel->flagExternalMemberID,
                                "outletCode" => $memberIdBranchCode,
                                "voucherCode" => $salesModel->promotionVoucherCode,
                                "minimumSpending" => intval($checkVoucher['voucher']->minimumSalesAmount),
                                "subtotal" => intval($salesModel->subtotal)
                            ));
                        } else {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $salesModel->promotionVoucherCode,
                                'responseMessage' => $checkVoucher['status']
                            );
                        }
                    }
                }
                foreach ($salesModel->salesMenus as $salesMenu) {
                    if ($salesMenu->promotion && $salesMenu->promotion->flagLoyalty == 1) {
                        if ($salesMenu->promotionVoucherCode && $salesMenu->promotionVoucherCode != '') {
                            $checkVoucher = ExternalVoucher::validateVoucherMemberID($salesMenu->promotionVoucherCode, $salesMenu->salesNum);
                            if (isset($checkVoucher['status'])) {
                                if ($checkVoucher['status'] == 'Voucher is valid') {
                                    array_push($newBatchVoucher, array(
                                        "salesNum" => $salesMenu->salesNum,
                                        "memberCode" => $salesModel->flagExternalMemberID,
                                        "outletCode" => $memberIdBranchCode,
                                        "voucherCode" => $salesMenu->promotionVoucherCode,
                                        "minimumSpending" => intval($checkVoucher['voucher']->minimumSalesAmount),
                                        "subtotal" => intval($salesModel->subtotal)
                                    ));
                                } else {
                                    $status = false;
                                    $itemErrorArr[] = (object) array(
                                        'voucherCode' => $salesMenu->promotionVoucherCode,
                                        'responseMessage' => $checkVoucher['status']
                                    );
                                }
                            }
                        }

                    }
                }
            }
            foreach ($batchVoucher as $voucher) {
                if ($voucher->voucherCode && $voucher->voucherCode != '') {
                    $checkVoucher = ExternalVoucher::validateVoucherMemberID($voucher->voucherCode, $salesModel->salesNum);
                    if (isset($checkVoucher['status'])) {
                        if ($checkVoucher['status'] == 'Voucher is valid') {
                            array_push($newBatchVoucher, array(
                                "salesNum" => $salesModel->salesNum,
                                "memberCode" => $salesModel->flagExternalMemberID,
                                "outletCode" => $memberIdBranchCode,
                                "voucherCode" => $voucher->voucherCode,
                                "minimumSpending" => intval($checkVoucher['voucher']->minimumSalesAmount),
                                "subtotal" => intval($salesModel->subtotal)
                            ));
                        } else {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $salesModel->promotionVoucherCode,
                                'responseMessage' => $checkVoucher['status']
                            );
                        }
                    }
                }
            }

            if (!$status) {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }
            foreach($newBatchVoucher as $voucher) {
                $bodyLogging[] = $voucher;
                $client = new Client();
                $result = $client->post($transactionApiUrl)
                                ->addHeaders([
                                    'Content-Type' => 'application/json',
                                    'mid-client-key' => $authToken,
                                ])
                                ->setFormat(Client::FORMAT_JSON)
                                ->setData($voucher)
                                ->send();
                $response = $result->getData();
         
                $responseLogging[] = json_decode($result->getContent(), true);
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if ($response['statusCode'] != 200) {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'voucherCode' => $voucher['voucherCode'],
                            'responseMessage' => $response['message']
                        );
                    }
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucher['voucherCode'],
                        'responseMessage' => isset($response['message']) ? $response['message'] : 'Server is unreachable'
                    );
                }
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesModel->salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $ex) {
       
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }
    }

    public static function saveVoucherESBLoopLite($salesModel) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $branch = Branch::findOne(['branchID' => $branchID]);
        $brandPosSetting = BrandSetting::getBrandPosSetting();        
        try {
            $rewardVoucher = 'voucher';
            $status = true;
            $itemErrorArr = [];
            $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
            if (!$authToken) {
                throw new Exception("Authentication token not found", 1);
            }
            $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);
            $newBatchVoucher = [];
            if ($salesModel) {
                $rewardType = $salesModel->salesRewardHead ? $salesModel->salesRewardHead->rewardType : null;
                if ($salesModel->promotionVoucherCode && $salesModel->promotionVoucherCode != '' && $rewardType == $rewardVoucher) {
                    $checkVoucher = ExternalVoucher::validateVoucherESBLoopLite($salesModel->promotionVoucherCode, $salesModel->salesNum);
                    if (isset($checkVoucher['status'])) {
                        if ($checkVoucher['status'] == 'Voucher is valid') {
                            array_push($newBatchVoucher, array(
                                "memberCode" => $salesModel->flagExternalMemberID,
                                "salesNo" => $salesModel->salesNum,
                                "companyCode" => $branch->companyCode,
                                "brandCode" => null,
                                "outletCode" => $branch->branchCode,
                                "voucherCode" => $salesModel->promotionVoucherCode,
                                "minimumSpending" => intval($checkVoucher['voucher']->minimumSalesAmount),
                                "subtotal" => intval($salesModel->subtotal)
                            ));
                        } else {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $salesModel->promotionVoucherCode,
                                'responseMessage' => $checkVoucher['status']
                            );
                        }
                    }
                }
                foreach ($salesModel->salesMenus as $salesMenu) {
                    $rewardTypeMenu = $salesMenu->salesRewardMenu ? $salesMenu->salesRewardMenu->rewardType : null;
                    if ($salesMenu->promotionVoucherCode && $salesMenu->promotionVoucherCode != '' && $rewardTypeMenu == $rewardVoucher) {
                        $checkVoucher = ExternalVoucher::validateVoucherESBLoopLite($salesMenu->promotionVoucherCode, $salesMenu->salesNum);
                        if (isset($checkVoucher['status'])) {
                            if ($checkVoucher['status'] == 'Voucher is valid') {
                                array_push($newBatchVoucher, array(
                                    "memberCode" => $salesModel->flagExternalMemberID,
                                    "salesNo" => $salesModel->salesNum,
                                    "companyCode" => $branch->companyCode,
                                    "brandCode" => null,
                                    "outletCode" => $branch->branchCode,
                                    "voucherCode" => $salesMenu->promotionVoucherCode,
                                    "minimumSpending" => intval($checkVoucher['voucher']->minimumSalesAmount),
                                    "subtotal" => intval($salesModel->subtotal)
                                ));
                            } else {
                                $status = false;
                                $itemErrorArr[] = (object) array(
                                    'voucherCode' => $salesMenu->promotionVoucherCode,
                                    'responseMessage' => $checkVoucher['status']
                                );
                            }
                        }
                    }
                }
            }

            if (!$status) {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }
            foreach($newBatchVoucher as $voucher) {
                $client = new Client();
                $result = $client->post($transactionApiUrl)
                        ->addHeaders([
                            'Content-Type' => 'application/json',
                            'mid-client-key' => $authToken,
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->setData($voucher)
                        ->send();
                $response = $result->getData();
             
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if ($response['statusCode'] != 200) {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'voucherCode' => $voucher['voucherCode'],
                            'responseMessage' => $response['message']
                        );
                    }
                }
            }

            if ($status) {
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }
        } catch (Exception $ex) {
         
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => []
            );
        }
    }

    public static function saveBenefitESBLoopLite($salesModel) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if (!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $branch = Branch::findOne(['branchID' => $branchID]);
        $loopBrandSetting = BrandSetting::getLoopSetting();
        try {
            $rewardBenefit = 'benefit';
            $status = true;
            $itemErrorArr = [];
            $authToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
            if (!$authToken) {
                throw new Exception("Authentication token not found", 1);
            }
            
            $benefitBurnApiUrl = Yii::$app->security->decryptByKey(base64_decode($loopBrandSetting[SELF::BENEFIT_BURN_API_URL]), $companyAuthKey);
            $newBatchBenefit = [];

            if ($salesModel) {
                $rewardType = $salesModel->salesRewardHead ? $salesModel->salesRewardHead->rewardType : null;
                if ($salesModel->promotionVoucherCode && $salesModel->promotionVoucherCode != '' && $rewardType == $rewardBenefit) {
                    array_push($newBatchBenefit, array(
                        "companyCode" => $branch->companyCode,
                        "branchCode" => $branch->branchCode,
                        "memberCode" => $salesModel->flagExternalMemberID,
                        "benefitCode" => $salesModel->promotionVoucherCode,
                        "salesNo" => $salesModel->salesNum
                    ));
                }
                foreach ($salesModel->salesMenus as $salesMenu) {
                    $rewardTypeMenu = $salesMenu->salesRewardMenu ? $salesMenu->salesRewardMenu->rewardType : null;
                    if ($salesMenu->promotionVoucherCode && $salesMenu->promotionVoucherCode != '' && $rewardTypeMenu == $rewardBenefit) {
                        array_push($newBatchBenefit, array(
                            "companyCode" => $branch->companyCode,
                            "branchCode" => $branch->branchCode,
                            "memberCode" => $salesModel->flagExternalMemberID,
                            "benefitCode" => $salesMenu->promotionVoucherCode,
                            "salesNo" => $salesModel->salesNum
                        ));
                    }
                }
            }

            if (!$status) {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }
            
            foreach($newBatchBenefit as $benefit) {
                $client = new Client();
                $result = $client->post($benefitBurnApiUrl)
                        ->addHeaders([
                            'Content-Type' => 'application/json',
                            'mid-client-key' => $authToken,
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->setData($benefit)
                        ->send();

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $benefitBurnApiUrl;
                $headers = [
                    'mid-client-key' => $authToken
                ];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $benefit, $options);

                $response = $result->getData();
              
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if ($response['statusCode'] != 200) {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'benefitCode' => $benefit['benefitCode'],
                            'responseMessage' => $response['message']
                        );
                    }
                }
            }

            if ($status) {
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => []
            );
        }
    }

    public static function saveVoucherGiftee($batchVoucher, $salesNum) {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        if (!$branchModel) {
            throw new Exception("Branch Not Found");
        }

        $bodyRequest = [
            'request_date' => date('Ymd'),
            'terminal_code' => 1,
            'cancel_code' => $salesNum,
            'exchanged_shop_code' => $branchModel->branchCode
        ];
        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        try {
            $status = true;

            $gifteeUrl = Setting::getValue1('External', 'Giftee Voucher API Url');
            $gifteeHeaders = json_decode(Setting::getValue1('External', 'Giftee Voucher API Header'), true);
            $token = BrandSetting::getBrandSetting('EXTERNAL', SELF::GET_GIFTEE_TOKEN);

            $arrHeaders = array_keys($gifteeHeaders);
            $headers = [
                'Accept' => '*/*',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ];
            foreach ($arrHeaders as $value) {
                $headers[$value] = $gifteeHeaders[$value];
            }

            foreach($batchVoucher as $voucher) {
                $bodyRequestLogging = $bodyRequest;
                $bodyRequestLogging['voucherCode'] = $voucher->voucherCode;
                $bodyLogging[] = $bodyRequestLogging;
                $sendUrl = $gifteeUrl . '/' .$voucher->voucherCode . '/exchange';
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $sendUrl;
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);
                $response = $result->getData();
                $responseLogging[] = json_decode($result->getContent(), true);
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if (isset($response['code'])) {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'voucherCode' => $voucher->voucherCode,
                            'responseMessage' => $response['message']
                        );
                    }
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucher->voucherCode,
                        'responseMessage' => isset($response['message']) ? $response['message'] : 'Server is unreachable'
                    );
                }
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $ex) {
      
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }

    }

    public static function saveVoucherTada($batchVoucher, $salesNum) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }

        $brandPosSetting = BrandSetting::getBrandPosSetting();    
        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        try {
            $status = true;
            $itemErrorArr = [];
            $authToken = ExternalMember::getToken('MAP Token', 0);
            $redeemptionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);
            foreach($batchVoucher as $voucher) {
                $bodyRequest = [
                    'number' => $voucher->voucherCode,
                    'billNumber' => $salesNum,
                    'extraSales' => $voucher->paymentAmount
                ];
                $bodyLogging[] = $bodyRequest;
                $client = new Client();
                $result = $client->post($redeemptionApiUrl)
                                ->addHeaders([
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer ' . $authToken,
                                ])
                                ->addData($bodyRequest)
                                ->send();
                $response = $result->getData();
                $responseLogging[] = json_decode($result->getContent(), true);
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if (isset($response['error'])) {
                        if ($response['error']['status'] === 400) {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $voucher->voucherCode,
                                'responseMessage' => $response['message']
                            );
                        }
                    }
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucher->voucherCode,
                        'responseMessage' => isset($response['message']) ? $response['message'] : 'Server is unreachable'
                    );
                }
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $ex) {
         
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }
    }

    public static function saveVoucherLoyalty($batchVoucher, $salesModel) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $branch = Branch::findOne(['branchID' => $branchID]);
        $brandPosSetting = BrandSetting::getBrandPosSetting();        
        try {
            $status = true;
            $authToken = ExternalMember::getToken('Loyalty Token', 1, 'cardID');
            if (!$authToken) {
                throw new Exception("Authentication token not found", 1);
            }
            $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);
            $newBatchVoucher = [
                'outlet_code' => $branch->branchCode,
                'cashier_id' => '',
                'member_id' => $salesModel->flagExternalMemberID,
                'list' => [],
            ];
            if ($salesModel) {
                if ($salesModel->promotionVoucherCode && $salesModel->promotionVoucherCode != '') {
                    array_push($newBatchVoucher['list'], array(
                        "voucher_code" => $salesModel->promotionVoucherCode,
                    ));
                }
                foreach ($salesModel->salesMenus as $salesMenu) {
                    if ($salesMenu->promotionVoucherCode && $salesMenu->promotionVoucherCode != '' && strpos($salesMenu->promotionVoucherCode, '|') === false) {
                        array_push($newBatchVoucher['list'], array(
                            "voucher_code" => $salesMenu->promotionVoucherCode,
                        ));
                    }
                }
            }
            foreach ($batchVoucher as $voucher) {
                if ($voucher->voucherCode && $voucher->voucherCode != '') {
                    array_push($newBatchVoucher['list'], array(
                        "voucher_code" => $voucher->voucherCode,
                    ));
                }
            }

            $client = new Client();
            $result = $client->post($transactionApiUrl)
                ->addHeaders([
                    'Accept' => '*/*',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$authToken
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($newBatchVoucher)
                ->send();
            $response = $result->getData();
            $errorMessage = '';
          
            if ($result->getIsOk()) {
                $response = $result->getData();
                if ($response['meta']['code'] != 200) {
                    $status = false;
                    $errorMessage = $response['meta']['message'];
                }
            } else {
                $status = false;
                $errorMessage = $response['meta']['message'];
            }

            if ($status) {
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'message' => $errorMessage
                );
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => []
            );
        }
    }

    public static function saveVoucherCapillary($batchVoucher, $salesModel) {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        if (!$branchModel) {
            throw new Exception("Branch Not Found");
        }

        $externalMemberSetting = BrandSetting::getExternalMemberSetting();

        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        try {
            $status = true;
            $itemErrorArr = [];

            $authorization = ExternalMember::getToken('MAP Token', 0);
            $apiUrl = $externalMemberSetting['Burn Membership Voucher API URL Capillary'];

            if ($authorization == null) {
                throw new Exception("The token has not been set", 500);
            }

            $newBatchVoucher = [];
            //check voucher (Get is Coupon Redeemable)
            foreach ($batchVoucher as $voucher) {
                $checkVoucher = ExternalVoucher::validateVoucherCapillary($voucher->voucherCode, $salesModel->salesNum, $authorization);
                if (isset($checkVoucher["status"]) && $checkVoucher["status"] === "VOUCHER_VALID") {
                    array_push($newBatchVoucher, array(
                        "voucherCode" => $voucher->voucherCode,
                        "paymentAmount" => $voucher->paymentAmount
                    ));
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucher->voucherCode,
                        'responseMessage' => $checkVoucher['status']
                    );
                }
            }

            if (!$status) {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }

            //burn voucher (Redeem Coupon)
            foreach ($newBatchVoucher as $voucher) {
                if ($voucher["voucherCode"] && $voucher["voucherCode"] != '') {
                    $bodyRequest = [
                        "root" => [
                            "coupon" => [
                                [
                                    "code" => $voucher["voucherCode"],
                                    "customer" => [
                                        "mobile" => "",
                                        "email" => $salesModel->flagExternalMemberID,
                                    ],
                                    "transaction" => [
                                        "number" => $salesModel->salesNum,
                                        "amount" => $voucher["paymentAmount"]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $bodyLogging[] = $bodyRequest;
    
                    $client = new Client();
                    $result = $client->post($apiUrl . "?format=json")
                        ->addHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Basic ' . $authorization,
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->setData($bodyRequest)
                    ->send();
        
                    $response = $result->getData();
                    $responseLogging[] = json_decode($result->getContent(), true);
                    
                    if ($result->getIsOk()) {
                        if ($response['response']['status']['success'] === 'false') {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $voucher["voucherCode"],
                                'responseMessage' => $response['response']['coupons']['coupon']['item_status']['message']
                            );
                        }
                    } else {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'voucherCode' => $voucher["voucherCode"],
                            'responseMessage' => isset($response['response']['status']['message']) ? $response['response']['status']['message'] : "Server is unreachable"
                        );
                    }
                }
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesModel->salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $ex) {
          
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }
    }

    public static function saveVoucherCapillaryV2($batchVoucher, $salesModel)
    {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        if (!$branchModel) {
            throw new Exception("Branch Not Found");
        }

        $externalMemberSetting = BrandSetting::getExternalMemberSetting();

        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        try {
            $status = true;
            $itemErrorArr = [];

            $url = 'Burn Membership Voucher API URL Capillary';
            $capillarySetting = ExternalMember::getCapillarySetting($url, $externalMemberSetting);
            $apiUrl = $capillarySetting['apiUrl'];
            $authorization = $capillarySetting['authorization'];

            $newBatchVoucher = [];
            //check voucher (Get is Coupon Redeemable)
            $checkVoucherUrl = $externalMemberSetting['Check Status Membership Voucher API URL Capillary'];
            foreach ($batchVoucher as $voucher) {
                $checkVoucher = ExternalVoucher::validateVoucherCapillaryV2(
                    $voucher->voucherCode,
                    $salesModel->salesNum,
                    $authorization,
                    $checkVoucherUrl
                );
                if (isset($checkVoucher["status"]) && $checkVoucher["status"] === "VOUCHER_VALID") {
                    array_push($newBatchVoucher, array(
                        "voucherCode" => $voucher->voucherCode,
                        "paymentAmount" => $voucher->paymentAmount
                    ));
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucher->voucherCode,
                        'responseMessage' => $checkVoucher['status']
                    );
                }
            }

            if (!$status) {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr
                );
            }

            //burn voucher (Redeem Coupon)
            foreach ($newBatchVoucher as $voucher) {
                if ($voucher["voucherCode"] && $voucher["voucherCode"] != '') {
                    $bodyRequest = [
                        "root" => [
                            "coupon" => [
                                [
                                    "code" => $voucher["voucherCode"],
                                    "customer" => [
                                        "mobile" => "",
                                        "email" => $salesModel->flagExternalMemberID,
                                    ],
                                    "transaction" => [
                                        "number" => $salesModel->salesNum,
                                        "amount" => $voucher["paymentAmount"]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $bodyLogging[] = $bodyRequest;

                    $client = new Client();
                    $result = $client->post($apiUrl . "?format=json")
                        ->addHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Basic ' . $authorization,
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->setData($bodyRequest)
                        ->send();

                    $response = $result->getData();
                    $responseLogging[] = json_decode($result->getContent(), true);

                    if ($result->getIsOk()) {
                        if ($response['response']['status']['success'] === 'false') {
                            $status = false;
                            $itemErrorArr[] = (object) array(
                                'voucherCode' => $voucher["voucherCode"],
                                'responseMessage' => $response['response']['coupons']['coupon']['item_status']['message']
                            );
                        }
                    } else {
                        $status = false;
                        $itemErrorArr[] = (object) array(
                            'voucherCode' => $voucher["voucherCode"],
                            'responseMessage' => isset($response['response']['status']['message']) ? $response['response']['status']['message'] : "Server is unreachable"
                        );
                    }
                }
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesModel->salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $ex) {
   
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }
    }

    public static function saveVoucherQwikCilver($batchVoucher, $salesNum) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if (!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $branchModel = Branch::findOne(['branchID' => $branchID]);

        $bodyRequest = [];
        try {
            //burn voucher (Redeem)
            $BurnApiUrl = Setting::getApiUrl() . "/qwikcilver/main/redeem-voucher";
            $indexVoucher = 0;
            foreach ($batchVoucher as $voucher) {
                $indexVoucher++;
                $bodyRequest[] = [
                    'CardNumber' => $voucher->voucherCode,
                    'CardPIN' => $voucher->voucherPIN,
                    'TrackData' => $voucher->trackData,
                    'Amount' => $voucher->paymentAmount,
                    'BillAmount' => $voucher->billAmount,
                    'InvoiceNumber' => $salesNum,
                    'IsOffline' => false,
                    'MerchantOutletNameToOverride' => $branchModel->branchName,
                    'IsProxy' => true,
                    'BranchCode' => $branchModel->branchCode
                ];
            }

            $client = new Client();
            $result = $client->post($BurnApiUrl)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $companyAuthKey,
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($bodyRequest)
            ->send();

            $response = $result->getData();
            $responseLogging = json_decode($result->getContent(), true);

            $status = false;
            $errorMessage = null;
            $unburnPayload = [];
            if ($result->getIsOk()) {
                $unburnPayload = $response['unburnPayload'];
                if ($response['status'] === true) {
                    $status = true;
                } else {
                    $errorMessage = isset($response['message']) ? $response['message'] : null;
                }
            }

            $dataLogging = [
                'data' => $batchVoucher,
                'body' => $bodyRequest,
                'response' => $responseLogging,
                'qwikcilverVouchers' => $unburnPayload
            ];

            if ($status) {
                Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                    'dataLogging' => $dataLogging
                );
            } else {
           
                return (object) array(
                    'status' => false,
                    'dataLogging' => $dataLogging,
                    'errorMessage' => $errorMessage
                );
            }
        } catch (\Exception $ex) {
            Yii::error($ex);
            $exMessage = $ex->getMessage();
            if ($ex->getCode() != 1) {
                $exMessage = (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false)
                ? "Cannot connect to server. Please check your internet connection." : "Server Unreachable";
            }
            return (object) array(
                'status' => false,
                'dataLogging' => [
                    'data' => $batchVoucher,
                    'body' => $bodyRequest,
                    'response' => null
                ],
                'errorMessage' => $exMessage
            );
        }
    }

    public static function saveVoucherGlobalTix($batchVoucher, $salesNum) {
        $authToken = Setting::getTokenGlobalTix();
        $externalModels = Setting::getLocalSettings();
        $externalSetting = [
            'createTokenUrl' => $externalModels['GlobalTix Token API Url'],
            'redeemVoucherUrl' => $externalModels['GlobalTix Redeem Ticket API Url'],
        ];
        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        $status = true;
        try {
            if ($authToken === null || $authToken === '') {
                $attempt = 0;
                $maxAttempts = 2;
                $getUserName = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Username');
                $getPassword = BrandSetting::getBrandSetting('EXTERNAL', 'GlobalTix Password');
                $userName = $getUserName ? $getUserName : '';
                $password = $getPassword ? $getPassword : '';

                while ($attempt <= $maxAttempts) {
                    $getAuthToken = SELF::createAuthTokenGlobalTix($externalSetting['createTokenUrl'], $userName, $password);
                    if (!isset($getAuthToken->responseCode)) {
                        break;
                    }
                    $attempt++;
                }
                
                if (isset($getAuthToken->responseCode)) {
                    return $getAuthToken;
                }
            }
            $authToken = Setting::getTokenGlobalTix();
            foreach($batchVoucher as $voucher) {
                $bodyRequest = [
                    "tickets" => array([
                        "id" => $voucher->voucherID,
                        "quantityToRedeem" => 1,
                        "manuallyRedeem" => null,
                        "kiosk" => false
                    ]),
                ];

                $bodyLogging[] = $bodyRequest;

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $externalSetting['redeemVoucherUrl'];
                $headers = ['Authorization' => 'Bearer ' . $authToken];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);

                $voucherCode = '';
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if ($response['data'] != '' || $response['data'] != null) {
                        foreach($response['data'] as $responseContent) {
                            $voucherCode = $responseContent['ticket']['code'];
                            $responseLogging[] = [
                                "tickets" => [
                                    "id" => $responseContent['ticket']['id'],
                                    "code" => $responseContent['ticket']['code'],
                                    "dateRedeemed" => $responseContent['ticket']['dateRedeemed'],
                                    "product" => [
                                        "id" => $responseContent['ticket']['product']['id']
                                    ],
                                    "status" => [
                                        "name" => $responseContent['ticket']['status']['name']
                                    ]
                                ]
                            ];
                        }
                    } else {
                        $status = false;
                    }
                } else if ($result->getStatusCode() == 404) {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucherCode,
                        'responseMessage' => "The page or resources you are looking for doesn't exist"
                    );
                } else if ($result->getStatusCode() == 500) {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucherCode,
                        'responseMessage' => 'Internal Server Error'
                    );
                } else {
                    $status = false;
                    $itemErrorArr[] = (object) array(
                        'voucherCode' => $voucherCode,
                        'responseMessage' => 'Server is unreachable'
                    );
                }              
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];

            if ($status) {
                Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                ); 
            } else {
                return (object) array(
                    'status' => false,
                    'items' => (object) $itemErrorArr,
                    'dataLogging' => $dataLogging
                );
            }
        } catch (Exception $e) {
            Yii::error($e);
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $e->getMessage()
            ];
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => [],
                'dataLogging' => $dataLogging
            );
        }
    }

    public static function saveVoucherStamps($batchVoucher, $salesModel) {
        $status = true;
        $dataLogging = null;
        $bodyLogging = [];
        $responseLogging = [];
        $unburnPayload = [];
        $errorMessage = null;
        try {
            //burn voucher (Redeem)
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne(['branchID' => $branchID]);
            $companyAuthKey = Setting::getApiKey();
            $brandPosSetting = BrandSetting::getBrandPosSetting();
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $burnApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);
            $staticToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
            $store = $branchModel->branchCode;

            //get saleshead promotionVoucherCode
            if (strlen($salesModel->promotionVoucherCode) > 0) {
                array_push($batchVoucher, (object) array(
                    'voucherCode' => $salesModel->promotionVoucherCode,
                    'salesPaymentID' => null
                ));
            }

            //get salesmenu promotionVoucherCode
            foreach ($salesModel->salesMenus as $salesMenu) {
                if (strlen($salesMenu->promotionVoucherCode) > 0) {
                    array_push($batchVoucher, (object) array(
                        'voucherCode' => $salesMenu->promotionVoucherCode,
                        'salesPaymentID' => null
                    ));
                }
            }

            //validate vouchers
            $validateErrors = [];
            $authorization = ExternalMember::getToken('MAP Token', 0);
            foreach($batchVoucher as $voucher) {
                $responseValidate = self::validateVoucherStamps($voucher->voucherCode, $authorization);
                if ($responseValidate['code'] !== 200) {
                    $status = false;
                    array_push($validateErrors, $voucher->voucherCode . " - " . $responseValidate["status"]);
                }
            }

            if (!$status) {
                //error validate
                $errorMessage = implode("<br/>" ,$validateErrors);
                $responseLogging[] = $validateErrors;
            } else {
                //burn vouchers
                $authorization = ExternalMember::getToken('MAP Token', 0);
                foreach($batchVoucher as $voucher) {
                    $responseLog = null;
                    $maxAttempts = 2;
                    $attempt = 1;
                    $isTokenExpired = false;
                    $bodyRequest = [
                        "token" => $staticToken,
                        "user" => $salesModel->flagExternalMemberPhone,
                        "store" => $store,
                        "voucher" => $voucher->voucherCode,
                        "invoice_number" => $salesModel->salesNum
                    ];
                    // $bodyLogging[] = $bodyRequest;
    
                    do {
                        try {
                            $client = new Client();
                            $result = $client->post($burnApiUrl)
                                ->addHeaders([
                                    'Accept' => 'application/json',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => "Bearer $authorization",
                                ])
                                ->setData($bodyRequest)
                                ->setFormat(Client::FORMAT_JSON)
                            ->send();
    
                            $response = $result->getData();
                            $responseLog = json_decode($result->getContent(), true);
    
                            if ($result->getIsOk()) {
                                $unburnPayload[] = (object) [
                                    "token" => $staticToken,
                                    "id" => $response['redemption']['id'],
                                    "voucherCode" => $voucher->voucherCode,
                                    "newVoucherCode" => $voucher->voucherCode . "|" . $response['redemption']['id'],
                                    "salesPaymentID" => $voucher->salesPaymentID
                                ];
                                $isTokenExpired = false;
                            } else {
                                $statusCode = $result->getStatusCode();
                                $errMsg = isset($response['error_message']) ? $response['error_message'] : "an error occurred in STAMPS ($statusCode)";
                                throw new Exception("$errMsg($statusCode)", $statusCode);
                            }
                        } catch (\Exception $ex) {
                            if ($ex->getCode() == 401) {
                                $authorization = ExternalMember::getToken('MAP Token', 1);
                                $isTokenExpired = true;
                                $attempt++;
                                sleep(1);
                                continue;
                            }
    
                            $status = false;
                            $errorMessage = $voucher->voucherCode . " - " . $ex->getMessage();
                            if ($responseLog == null) {
                                $responseLog = $errorMessage;
                            }
                            break 2;
                        }
                    } while ($isTokenExpired && $attempt <= $maxAttempts);
                    $responseLogging[] = $responseLog;
                }
            }

            $dataLogging = [
                'data' => $batchVoucher,
                'body' => $bodyLogging,
                'response' => $responseLogging,
                'stampsVouchers' => $unburnPayload
            ];

            if ($status) {
                Logging::save($salesModel->salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
                return (object) array(
                    'status' => true,
                    'dataLogging' => $dataLogging
                );
            } else {
  
                return (object) array(
                    'status' => false,
                    'dataLogging' => $dataLogging,
                    'errorMessage' => $errorMessage
                );
            }
        } catch (\Exception $ex) {
   
            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $ex->getMessage()
            ];
            return (object) array(
                'status' => false,
                'dataLogging' => $dataLogging
            );
        }
    }

    private static function parseLineItemsExternalVoucher($voucherItems, $voucherCodeRelationID = null) {
        $newLineItems = array();
        foreach($voucherItems as $voucherItem) {
            $voucher = $voucherItem['vouchers'][0];
            $newResponse = [
                'ID' => $voucherCodeRelationID ? (array_key_exists($voucher['voucherNumber'], $voucherCodeRelationID) ? $voucherCodeRelationID[$voucher['voucherNumber']] : null) : null,
                'voucherCode' => $voucher['voucherNumber'],
                'voucherAmount' => $voucher['voucherBalance'],
                'notes' => $voucher['vpgName'],
                'verificationCode' => array_key_exists('approvalCode', $voucher) ? $voucher['approvalCode'] : '',
                'externalVoucherCode' => $voucher['articleCode'] . '|'. $voucher['mopCode'],
                'flagExternalVoucherAPI' => 1,
                'responseMessage' => $voucher['responseMessage']
            ];
            $newLineItems[] = (object) $newResponse;
        }
        
        return $newLineItems;
    }

    public static function claimBirthdayOfLoyalty($externalMemberID, $menuCode) {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $branch = Branch::findOne(['branchID' => $branchID]);
        $brandPosSetting = BrandSetting::getBrandPosSetting();        
        try {
            $status = true;
            $authToken = ExternalMember::getToken('Loyalty Token', 1, 'cardID');
            if (!$authToken) {
                throw new Exception("Authentication token not found", 1);
            }
            $memberApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::GET_MEMBER_API_URL]), $companyAuthKey);
      
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $memberApiUrl. 'birthday/claim/';
            $headers = ['Authorization' => 'Bearer ' . $authToken];
            $datas =   [
                'member_code' => $externalMemberID,
                'outlet_code' => $branch->branchCode,
                'plu' => $menuCode
            ];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $datas, $options);

            $response = $result->getData();

            $errorMessage = '';
            if ($result->getIsOk()) {
                $response = $result->getData();
                if ($response['meta']['code'] != 200) {
                    $status = false;
                    $errorMessage = $response['message'];
                }
            }

            if ($status) {
                return (object) array(
                    'status' => true,
                );
            } else {
                return (object) array(
                    'status' => false,
                    'message' => $errorMessage
                );
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            return (object) array(
                'status' => false,
                'transactionId' => null,
                'batchId' => null,
                'items' => []
            );
        }
    }

    public static function validateVoucherLoop($memberID, $voucherID) {
        try {

            $client = new Client();
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $accessToken =  $externalMemberSetting[SELF::GET_STATIC_TOKEN]; 
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $companyAuthKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();
            $branch = Branch::findOne(['branchID' => $branchID]);
            $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::TRANSACTION_VOUCHER_API_URL]), $companyAuthKey);
            $voucherUrl = '?outletCode=' . $branch->branchCode . '&memberCode=' . $memberID . '&voucherCode=' . $voucherID .'&companyCode=' . $branch->companyCode;

                $result = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($tokenVoucherApiUrl . $voucherUrl)
                ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Mid-Client-Key' => $accessToken
                ])
                ->setOptions([
                    'timeout' => 5
                ])
                ->send();
            
            $response = $result->getData();
            
            if ($result->getIsOk()) {
                $statusCode = $response['status'];
                if($statusCode === false) {
                    $responseMessage = $response['message'];
                    return $responseMessage;
                } else {
                    throw new Exception(json_encode($response), 500);
                }

            } else {
                throw new Exception("Cannot connect to server. Please contact our support", 500);
            }

        } catch(\Exception $ex) {
            return null;
        }
    }

    public static function UnburnVoucherQwikCilver($batchVoucher, $salesNum) {
        try {
            $branchID = Setting::getCurrentBranch();
            $companyAuthKey = Setting::getApiKey();
            $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
            if (!$brandModel) {
                throw new Exception("Brand Not Found", 1);
            }
            
            $dataLogging = null;
            $UnburnApiUrl = Setting::getApiUrl() . "/qwikcilver/main/cancel-redeem-voucher";
            $client = new Client();
            $result = $client->post($UnburnApiUrl)
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $companyAuthKey,
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($batchVoucher)
                ->send();

            $status = $result->getIsOk();
            $responseLogging = json_decode($result->getContent(), true);

            $dataLogging = [
                'body' => $batchVoucher,
                'response' => $responseLogging,
                'httpStatusCode' => $result->getStatusCode()
            ];

            Logging::save($salesNum, Logging::UNBURN_VOUCHER_API_URL, $dataLogging);
            return $status;
        } catch (Exception $ex) {
            $exception = ['code' => $ex->getCode(), 'line' => $ex->getLine(), 'message' => $ex->getMessage()];
           
            $dataLogging = [
                'body' => [ 'payload' => $batchVoucher ],
                'response' => $exception
            ];
            Logging::save($salesNum, Logging::UNBURN_VOUCHER_API_URL, $dataLogging);
            return false;
        }
    }

    public static function UnburnVoucherStamps($batchVoucher, $salesNum) {
        try {
            $companyAuthKey = Setting::getApiKey();
            $brandPosSetting = BrandSetting::getBrandPosSetting();
            $unburnApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::UNBURN_VOUCHER_API_URL]), $companyAuthKey);
            $authorization = ExternalMember::getToken('MAP Token', 1); //sementara

            $dataLogging = null;
            $bodyLogging = [];
            $responseLogging = [];
            $status = true;
            foreach ($batchVoucher as $voucher) {
                $responseLog = null;
                $maxAttempts = 2;
                $attempt = 1;
                $isTokenExpired = false;
                $bodyRequest = [
                    "token" => $voucher->token,
                    "id" => $voucher->id,
                    "voucherCode" => $voucher->voucherCode
                ];
                // $bodyLogging[] = $bodyRequest;

                do {
                    try {
                        $client = new Client();
                        $result = $client->post($unburnApiUrl)
                            ->addHeaders([
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Basic ' . $authorization
                            ])
                            ->setData($bodyRequest)
                            ->setFormat(Client::FORMAT_JSON)
                            ->send();

                        $responseLog = json_decode($result->getContent(), true);
                        if (!$result->getIsOk()) {
                            $status = false;
                        }
                    } catch (\Exception $ex) {
                        if ($ex->getCode() == 401) {
                            $authorization = ExternalMember::getToken('MAP Token', 1);
                            $isTokenExpired = true;
                            $attempt++;
                            sleep(1);
                            continue;
                        }

                        if ($responseLog == null) {
                            $responseLog = $ex->getMessage();
                        }
                    }
                } while ($isTokenExpired && $attempt <= $maxAttempts);
                $responseLogging[] = $responseLog;
            }

            $dataLogging = [
                'body' => $bodyLogging,
                'response' => $responseLogging
            ];
            Logging::save($salesNum, Logging::UNBURN_VOUCHER_API_URL, $dataLogging);
            return $status;
        } catch (Exception $ex) {
            $exception = ['code' => $ex->getCode(), 'line' => $ex->getLine(), 'message' => $ex->getMessage()];
     
            $dataLogging = [
                'body' => [ 'payload' => $batchVoucher ],
                'response' => $exception
            ];
            Logging::save($salesNum, Logging::UNBURN_VOUCHER_API_URL, $dataLogging);
            return false;
        }
    }

    public static function findNewPromotionVoucherCodeStamps($promotionVoucherCode, $batchVoucher) {
        $newPromotionVoucherCode = $promotionVoucherCode;
        foreach($batchVoucher as $voucher) {
            if ($voucher->voucherCode == $promotionVoucherCode) {
                $newPromotionVoucherCode = $voucher->newVoucherCode;
                break;
            }
        }
        return $newPromotionVoucherCode;
    }

    public static function validateVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        try {
            if (!is_array($voucherCodes)) {
                $voucherCodes = [$voucherCodes];
            }

            $response = null;
            if ($voucherType == 'ePass') {
                $response = self::validateEpassVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes);
            } else if ($voucherType == 'gift_voucher') {
                $response = self::validateGiftVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes);
            }

            if (isset($response['status']) && $response['status'] == false) {
                throw new Exception($response['message']);
            }

            if (isset($response['status']) && $response['status'] == "fail") {
                throw new Exception($response['message']);
            }

            $voucher = isset($response['result']) && count($response['result']) > 0 ? $response['result'][0] : null;
            if ($voucher['isValid'] == false) {
                return [
                    'status' => false,
                    'message' => !empty($voucher['message']) ? $voucher['message'] : 'Voucher is invalid',
                    'voucher' => null
                ];
            }

            return [
                'status' => true,
                'message' => 'Voucher is valid',
                'voucher' => $voucher
            ];
        } catch (\Exception $ex) {
            
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }
            return [
                'status' => false,
                'message' => $errMsg,
                'voucher' => null
            ];
        }
    }

    public static function validateEpassVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $dataLogging = null;
        try {
            $bodyRequest = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "voucherCodes" => $voucherCodes,
                "branchID" => $branchID
            ];

            $client = new Client(['baseUrl' => $apiUrl]);
            $response = $client->createRequest()
                ->setUrl("/esb_api/external-voucher/validate")
                ->setMethod('POST')
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->setData($bodyRequest)
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            $responseLogging = json_decode($response->getContent(), true);

            if (!$response->getIsOk()) {
                return [
                    'status' => false,
                    'message' => 'Server Unreachable',
                    'voucher' => null
                ];
            }

            $dataLogging = [
                'body' => $bodyRequest,
                'response' => $responseLogging,
                'httpStatusCode' => $response->getStatusCode()
            ];

            $responseData = $response->getData();
            if ($response->statusCode == "200") {
                $decodedResponse = json_decode($response->content, true);
                Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);
                return $decodedResponse;
            } else {
                Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);
                return [
                    'status' => false,
                    'message' => isset($responseData['message']) ? $responseData['message'] : 'Server Unreachable',
                    'voucher' => null
                ];
            }
        } catch (\Exception $ex) {
        
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $requestBody = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "voucherCodes" => $voucherCodes,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);

            return [
                'status' => false,
                'message' => $errMsg,
                'voucher' => null
            ];
        }
    }

    public static function validateGiftVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $dataLogging = null;
        try {
            $bodyRequest = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "voucherCodes" => $voucherCodes,
                "branchID" => $branchID
            ];

            $client = new Client(['baseUrl' => $apiUrl]);
            $response = $client->createRequest()
                ->setUrl("/esb_api/external-voucher/validate")
                ->setMethod('POST')
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->setData($bodyRequest)
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            $responseLogging = json_decode($response->getContent(), true);

            if (!$response->getIsOk()) {
                return [
                    'status' => false,
                    'message' => 'Server Unreachable',
                    'voucher' => null
                ];
            }

            $dataLogging = [
                'body' => $bodyRequest,
                'response' => $responseLogging,
                'httpStatusCode' => $response->getStatusCode()
            ];

            $responseData = $response->getData();
            if ($response->statusCode == "200") {
                $decodedResponse = json_decode($response->content, true);
                Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);
                return $decodedResponse;
            } else {
                Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);
                return [
                    'status' => false,
                    'message' => isset($responseData['message']) ? $responseData['message'] : 'Server Unreachable',
                    'voucher' => null
                ];
            }
        } catch (\Exception $ex) {
   
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $requestBody = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "voucherCodes" => $voucherCodes,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::VALIDATE_PLUXEE_VOUCHER, $dataLogging);

            return [
                'status' => false,
                'message' => $errMsg,
                'voucher' => null
            ];
        }
    }

    public static function burnVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        try {
            $errorMessages = null;
            $invalidVouchers = [];
            $burnResponse = null;
            $explodedVouchers = null;
            if ($voucherType == 'ePass') {
                $burnResponse = self::burnEpassVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes);
            } else if ($voucherType == 'gift_voucher') {
                $burnResponse = self::burnGiftVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes);
            }

            if (isset($burnResponse['status']) && $burnResponse['status'] == false) {
                if ($voucherType == 'gift_voucher') {
                    $burnMessage = isset($burnResponse['message']) && $burnResponse['message'] ? $burnResponse['message'] : null;
                    $responseErrorGift = str_replace("failed to burn Voucher: invalid vouchers : ", "", $burnMessage);
                    $explodedVouchers = explode(",", $responseErrorGift);
                } else {
                    $errorMessages = isset($burnResponse['message']) && $burnResponse['message'] ? $burnResponse['message'] : null;
                }
            }

            $response = isset($burnResponse['response']) && $burnResponse['response'] ? $burnResponse['response'] : null;
            $vouchers = isset($response['result']) && count($response['result']) > 0 ? $response['result'] : null;
            if ($vouchers) {
                foreach ($vouchers as $voucher) {
                    if ($voucher['burnStatus'] == false && $voucher['value'] < 1) {
                        $invalidVouchers[] = $voucher['voucherCode'];
                    }
                }
            }

            if ($explodedVouchers) {
                foreach ($explodedVouchers as $voucher) {
                    $invalidVouchers[] = $voucher;
                }
            }

            if ($errorMessages) {
                $throwMessage = "ERR_PLUXEEVOUCHER:" . $errorMessages;
                $dataLogging = $burnResponse['dataLogging'];

                return [
                    'status' => false,
                    'message' => $throwMessage,
                    'dataLogging' => $dataLogging
                ];
            }

            if (!empty($invalidVouchers)) {
                $throwMessage = "ERR_PLUXEEVOUCHER:" . json_encode($invalidVouchers);
                if ($voucherType == 'gift_voucher') {
                    $dataLogging = $burnResponse['dataLogging'];
                } else {
                    $dataLogging = [
                        'body' => $burnResponse['bodyRequest'],
                        'response' => $response
                    ];
                }

                return [
                    'status' => false,
                    'message' => $throwMessage,
                    'dataLogging' => $dataLogging
                ];
            }

            return [
                'status' => true
            ];
        }  catch (\Exception $ex) {
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }
            return false;
        }
    }

    public static function burnEpassVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $requestBody = null;
        $response = null;
        $dataLogging = null;
        try {
            if (!empty($voucherCodes)) {
       
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/external-voucher/burn";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $datas =   [
                    "terminalID" => $terminalID,
                    "voucherType" => $voucherType,
                    "voucherCodes" => $voucherCodes,
                    "provider" => 'pluxee',
                    "remark" => $salesNum,
                    "branchID" => $branchID
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);

                $decodedResponse = json_decode($response->getContent(), true);
                if (!$response->getIsOk()) {
                    throw new Exception(isset($response['message']) ? $response['message'] : 'Server Unreachable');
                }

                if (isset($decodedResponse['status']) && $decodedResponse['status'] == "fail") {
                    throw new Exception($response['message']);
                }

                $dataLogging = [
                    'bodyRequest' => $requestBody,
                    'response' => $decodedResponse,
                    'httpStatusCode' => $response->getStatusCode()
                ];
                Logging::save($salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataLogging);

                return $dataLogging;
            }
        } catch (\Exception $ex) {
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $requestBody = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "voucherCodes" => $voucherCodes,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataLogging);
            
            return [
                'status' => false,
                'message' => $errMsg,
                'dataLogging' => $dataLogging
            ];
        }
    }

    public static function burnGiftVoucherPluxee($terminalID, $voucherType, $salesNum, $voucherCodes)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $requestBody = null;
        $response = null;
        $dataLogging = null;
        try {
            if (!empty($voucherCodes)) {
 
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/external-voucher/burn";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $datas =   [
                    "terminalID" => $terminalID,
                    "voucherType" => $voucherType,
                    "voucherCodes" => $voucherCodes,
                    "provider" => 'pluxee',
                    "remark" => $salesNum,
                    "branchID" => $branchID
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);

                $decodedResponse = json_decode($response->getContent(), true);

                if (!$response->getIsOk()) {
                    throw new Exception(isset($response['message']) ? $response['message'] : 'Server Unreachable');
                }

                if (isset($decodedResponse['status']) && $decodedResponse['status'] == "fail") {
                    throw new Exception($decodedResponse['message']);
                }

                $dataLogging = [
                    'body' => $requestBody,
                    'response' => $decodedResponse,
                    'httpStatusCode' => $response->getStatusCode()
                ];
                Logging::save($salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataLogging);

                return $dataLogging;
            }
        } catch (\Exception $ex) {
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $requestBody = [
                "terminalID" => $terminalID,
                "voucherType" => $voucherType,
                "voucherCodes" => $voucherCodes,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::BURN_PLUXEE_VOUCHER, $dataLogging);

            return [
                'status' => false,
                'message' => $errMsg,
                'dataLogging' => $dataLogging
            ];
        }
    }

    public static function unburnEpassVoucherPluxee($voucherType, $salesNum)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $requestBody = null;
        $response = null;
        $dataLogging = null;
        try {
            if ($salesNum) {

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/external-voucher/unburn";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $requestBody =   [
                    "voucherType" => $voucherType,
                    "provider" => 'pluxee',
                    "remark" => $salesNum,
                    "branchID" => $branchID
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $requestBody, $options);

                $decodedResponse = json_decode($response->getContent(), true);
                if (!$response->getIsOk()) {
                    throw new Exception(isset($decodedResponse['message']) ? $decodedResponse['message'] : 'Server Unreachable');
                }

                if (isset($decodedResponse['status']) && $decodedResponse['status'] == "fail") {
                    throw new Exception($decodedResponse['message']);
                }

                $dataLogging = [
                    'body' => $requestBody,
                    'response' => $decodedResponse,
                    'httpStatusCode' => $response->getStatusCode()
                ];
                Logging::save($salesNum, Logging::UNBURN_PLUXEE_VOUCHER, $dataLogging);
            }
        } catch (\Exception $ex) {

            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $requestBody = [
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::UNBURN_PLUXEE_VOUCHER, $dataLogging);

            return [
                'status' => false,
                'message' => $errMsg,
                'dataLogging' => $dataLogging
            ];
        }
    }

    public static function unburnGiftVoucherPluxee($voucherType, $salesNum)
    {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $requestBody = null;
        $response = null;
        $dataLogging = null;
        try {
            if ($salesNum) {
                
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/external-voucher/unburn";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $requestBody =   [
                    "voucherType" => $voucherType,
                    "provider" => 'pluxee',
                    "remark" => $salesNum,
                    "branchID" => $branchID
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $requestBody, $options);


                $decodedResponse = json_decode($response->getContent(), true);
                if (!$response->getIsOk()) {
                    throw new Exception(isset($decodedResponse['message']) ? $decodedResponse['message'] : 'Server Unreachable');
                }

                if (isset($decodedResponse['status']) && $decodedResponse['status'] == "fail") {
                    throw new Exception($decodedResponse['message']);
                }

                $dataLogging = [
                    'body' => $requestBody,
                    'response' => $decodedResponse,
                    'httpStatusCode' => $response->getStatusCode()
                ];
                Logging::save($salesNum, Logging::UNBURN_PLUXEE_VOUCHER, $dataLogging);
            }
        } catch (\Exception $ex) {
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }
            
            $requestBody = [
                "voucherType" => $voucherType,
                "provider" => 'pluxee',
                "remark" => $salesNum,
                "branchID" => $branchID
            ];

            $dataLogging = [
                'body' => $requestBody,
                'response' => $errMsg
            ];
            Logging::save($salesNum, Logging::UNBURN_PLUXEE_VOUCHER, $dataLogging);

            return [
                'status' => false,
                'message' => $errMsg,
                'dataLogging' => $dataLogging
            ];
        }
    }

    public static function validateUltraVoucher($voucherCode, $voucherType, $salesNum, $terminalID) {
        $bodyRequest = null;
        $responseData = null;
        $dataLogging = null;

        try {
            $apiUrl = Setting::getApiUrl();
            $apiKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();

            $bodyRequest = [
                'voucherCodes' => [$voucherCode],
                'voucherType' => $voucherType,
                'provider' => 'ultra_voucher',
                'remark' => $salesNum,
                'branchID' => $branchID,
                'terminalID' => $terminalID
            ];

            $client = new Client(['baseUrl' => $apiUrl]);
            $request = $client->createRequest()
                ->setUrl("/esb_api/external-voucher/validate")
                ->setMethod('POST')
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->setData($bodyRequest)
                ->setFormat(Client::FORMAT_JSON)
                ->send();

            $responseData = json_decode($request->getContent(), true);

            $dataLogging = [
                'body' => $bodyRequest,
                'response' => $responseData,
                'httpStatusCode' => $request->getStatusCode()
            ];

            if (!$request->getIsOk()) {
                throw new Exception(isset($request['message']) ? $request['message'] : 'Server Unreachable');
            }

            if ($request->statusCode == "200") {
                $voucher = isset($responseData['result']) && count($responseData['result']) > 0 ? $responseData['result'][0] : null;
                if ($voucher) {
                    if ($voucher['isValid']) {
                        Logging::save($salesNum, Logging::VALIDATE_ULTRA_VOUCHER, $dataLogging);

                        return [
                            'status' => true,
                            'message' => $voucher['message'],
                            'voucher' => $voucher
                        ];
                    } else {
                        throw new Exception(isset($voucher['message']) ? $voucher['message'] : 'Server Unreachable');
                    }
                }

                $errors = isset($responseData['errors']) && count($responseData['errors']) > 0 ? $responseData['errors'][0] : null;
                if ($errors) {
                    throw new Exception($errors['message']);
                }

                throw new Exception(isset($responseData['message']) ? $responseData['message'] : 'Server Unreachable');
            } else {
                Logging::save($salesNum, Logging::VALIDATE_ULTRA_VOUCHER, $dataLogging);
                throw new Exception(isset($responseData['message']) ? $responseData['message'] : 'Server Unreachable');
            }
        } catch (\Exception $ex) {
           
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            $errorCode = 500;
            if (strpos(strtolower($errMsg), 'redeem') !== false) {
                $errorCode = 400;
                $errMsg = "VOUCHER_USED";
            }

            if (strpos(strtolower($errMsg), 'not found') !== false ||
                strpos(strtolower($errMsg), 'tidak ditemukan') !== false ||
                strpos(strtolower($errMsg), 'tidak valid') !== false) {
                $errorCode = 404;
                $errMsg = "VOUCHER_NOT_FOUND";
            }

            if ($errMsg != 'VOUCHER_NOT_FOUND') {
                Logging::save($salesNum, Logging::VALIDATE_ULTRA_VOUCHER, $dataLogging);
            }

            Yii::$app->response->statusCode = $errorCode;
            throw new Exception($errMsg, $errorCode);
        }
    }

    public static function burnUltraVoucher($batchVoucher, $salesNum, $terminalID) {
        $bodyRequest = null;
        $responseData = null;
        $dataLogging = null;
        $invalidVouchers = [];

        try {
            $apiUrl = Setting::getApiUrl();
            $apiKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();

            foreach ($batchVoucher as $voucher) {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/external-voucher/burn";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $bodyRequest =   [
                'voucherCodes' => [$voucher->voucherCode],
                'voucherType' => $voucher->voucherType,
                'provider' => 'ultra_voucher',
                'remark' => $voucher->salesNum,
                'branchID' => $branchID,
                'terminalID' => $terminalID
                ];
                $options = ['timeOut' => 300];
                $request = $httpService->post($url, $headers, $bodyRequest, $options);
    
                $responseData = json_decode($request->getContent(), true);

                $dataLogging = [
                    'bodyRequest' => $bodyRequest,
                    'response' => $responseData,
                    'httpStatusCode' => $request->getStatusCode()
                ];
        
                if (!$request->getIsOk()) {
                    throw new Exception(isset($request['message']) ? $request['message'] : 'Server Unreachable');
                }

                Logging::save($salesNum, Logging::BURN_ULTRA_VOUCHER, $dataLogging);
    
                $vouchers = isset($responseData['result']) && count($responseData['result']) > 0 ? $responseData['result'] : null;
                if ($vouchers) {
                    foreach ($vouchers as $voucher) {
                        if (!$voucher['burnStatus']&& $voucher['value'] < 1) {
                            $invalidVouchers[] = $voucher['voucherCode'];
                        }
                    }
                }
    
                if (!empty($invalidVouchers)) {
                    throw new Exception("ERR_ULTRAVOUCHER:" . json_encode($invalidVouchers));
                }
    
                $errors = isset($responseData['errors']) && count($responseData['errors']) > 0 ? $responseData['errors'][0] : null;
                if ($errors) {
                    if (strpos($errors['message'], 'failed to burn voucher') !== false) {
                        $errors = str_replace("failed to burn voucher: ", "", $errors['message']);
                    }
                    throw new Exception("ERR_ULTRAVOUCHER:" . $errors . ' ' . json_encode($voucher->voucherCode));
                }
            }

            return [
                'status' => true,
            ];
        } catch (\Exception $ex) {
            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }

            return [
                'status' => false,
                'message' => $errMsg,
                'dataLogging' => $dataLogging
            ];
        }
    }
}
