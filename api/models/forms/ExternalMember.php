<?php
namespace app\models\forms;

use app\components\AESEncryption;
use app\models\Branch;
use app\models\Brand;
use app\models\BrandApiContent;
use app\models\BrandSetting;
use app\models\PosUser;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesPayment;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use yii\web\HttpException;
use DateTime;

/**
 * @property SalesHead $salesModel
 * @property SalesPayment $salesPayments
 * @property number $burnPoints
 */
class ExternalMember extends Model {
    const GET_TOKEN_MEMBER_API_URL = 'Get Token API Url';
    const GET_STATIC_TOKEN = 'Static Token';
    const GET_MEMBER_API_URL = 'Get Member API Url';
    const TRANSACTION_MEMBER_API_URL = 'Transaction Member API Url';
    const BURN_VOUCHER_API_URL = 'Burn Voucher API Url';
    const UNBURN_VOUCHER_API_URL = 'Unburn Voucher API Url';
    const MEMBER_ID_BRANCH_CODE = 'Member ID Branch Code';
    const GET_TOKEN_VOUCHER_API_URL = 'Get Token Voucher API Url';
    const ENCRYPTION_KEY_CAPILLARY = 'Encryption Key Capillary';
    const BENEFIT_LIST_API_URL = 'Benefit List API URL';
    const BENEFIT_BURN_API_URL = 'Benefit Burn API URL';
    const BENEFIT_TYPE_FREE_ITEM = 'FREITBD';
    const BENEFIT_TYPE_AMOUNT = 'DISCAMBD';

    public $salesModel;
    public $salesPayments;
    public $burnPoints;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesModel', 'salesPayments', 'burnPoints'], 'required'],
        ];
    }

    public static function getToken($key, $forceUpdate = 0, $searchBy = null) {
        try {
            $settingModel = Setting::getSetting('Local Setting', $key);
            if($forceUpdate === 0 && $settingModel) {
                return $settingModel->value1;
            }

            if(!$settingModel){
                $settingModel = new Setting();
                $settingModel->key1 = 'Local Setting';
                $settingModel->key2 = $key;
            }
            
            $branchID = Setting::getCurrentBranch();
            $companyAuthKey = Setting::getApiKey();
            
            $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
            
            if(!$brandModel) {
                throw new Exception("Error get brand model", 1);
            }
            
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $brandApiContentModel = BrandApiContent::findApiContent($brandModel->brandID, SELF::GET_TOKEN_MEMBER_API_URL);

            $bodyRequest = [];
            $tadaAuthToken = null;
            foreach($brandApiContentModel->all() as $tokenContent) {
                if ($externalMemberSetting['Membership Type'] == 'tada') {
                    if($tokenContent->keyAttribute === 'username' || $tokenContent->keyAttribute === 'password' || $tokenContent->keyAttribute === 'grant_type' || $tokenContent->keyAttribute === 'scope'){
                        $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
                    }
                    if ($tokenContent->keyAttribute === 'auth_token') {
                        $tadaAuthToken = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
                    }
                } else {
                    $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
                }
            }

            $client = new Client();
            $tokenApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_MEMBER_API_URL]), $companyAuthKey);
            if ($externalMemberSetting['Membership Type'] == 'memberid') {
                $result = $client->post($tokenApiUrl)
                    ->setFormat(Client::FORMAT_JSON)
                    ->addData(
                        $bodyRequest
                    )->send();
            } else if ($externalMemberSetting['Membership Type'] == 'looplite') {
                $result = $client->post($tokenApiUrl)
                    ->setFormat(Client::FORMAT_JSON)
                    ->addData(
                        $bodyRequest
                    )->send();
            } else if($externalMemberSetting['Membership Type'] == 'esbloyalty') {
                $result = $client->post($tokenApiUrl)
                    ->addHeaders([
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                    ])
                    ->addData(
                        $bodyRequest
                    )
                    ->setFormat(Client::FORMAT_JSON)
                    ->send(); 
            } else if($externalMemberSetting['Membership Type'] == 'tada') {
                $result = $client->post($tokenApiUrl)
                    ->addHeaders([
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic '. $tadaAuthToken
                    ])
                    ->addData(
                        $bodyRequest
                    )->send();
            } else if($externalMemberSetting['Membership Type'] == 'capillary') {
                $capillaryStoreCode = Setting::getValue1('POS', 'Capillary Store Code');
                $capillaryAuthenticationCode = Setting::getValue1('POS', 'Capillary Authentication Code');
                $tokenApiUrl = $tokenApiUrl . '/' . $capillaryStoreCode;
                $result = $client->get($tokenApiUrl)
                    ->addHeaders([
                        'Accept' => '*/*',
                        'Authorization' => 'Bearer '. $capillaryAuthenticationCode
                    ])->send();
            } else if($externalMemberSetting['Membership Type'] == 'stamps') {
                $result = $client->post($tokenApiUrl)
                    ->addHeaders([
                        'Accept' => '*/*',
                        'Content-Type' => 'application/json'
                    ])
                    ->addData($bodyRequest)
                    ->setFormat(Client::FORMAT_JSON)
                ->send();
            } else {
                $result = $client->post($tokenApiUrl)
                    ->addHeaders([
                        'Accept' => '*/*',
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ])
                    ->addData(
                        $bodyRequest
                    )->send();
                
                $dataLogging = [
                    'body' => $bodyRequest,
                    'response' => json_decode($result->getContent(), true)
                ];
                Logging::save('-', Logging::MAP_CLUB_TOKEN, $dataLogging);
            }

            if ($result->getIsOk()) {
                $newToken = null;
                if ($externalMemberSetting['Membership Type'] == 'memberid' || $externalMemberSetting['Membership Type'] == 'esbloyalty' || $externalMemberSetting['Membership Type'] == 'looplite') {
                    $newToken = $result->getData()['data']['detail']['access_token'];
                } else if ($externalMemberSetting['Membership Type'] == 'capillary') {
                    $res = $result->getData();
                    $codeOk = isset($res['status']) && $res['status']['code'] === 200;
                    $statusOk = isset($res['status']) && $res['status']['success'] === true;
                    $dataStoresOk = isset($res['auth']) && isset($res['auth']['stores']);
                    if ($res && $codeOk && $statusOk && $dataStoresOk) {
                        $encryptionKeyCapillary = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::ENCRYPTION_KEY_CAPILLARY]), $companyAuthKey);
                        $encryptionKeyCapillary = base64_decode($encryptionKeyCapillary);
                        $encryptedPassword = $res['auth']['stores'][0]['encryptedPassword'];
                        $username = $res['auth']['stores'][0]['username'];
                        $aes = new AESEncryption($encryptedPassword, $encryptionKeyCapillary, null, '256', 'ECB');
                        $decryptedPassword = $aes->decrypt();

                        $newToken = base64_encode("$username:$decryptedPassword");
                    }
                } else {
                    $newToken = $result->getData()['access_token'];
                }
                $settingModel->value1 = $newToken;
                if($settingModel->save()){
                    return $newToken;
                } else {
                    throw $settingModel->getErrors();
                }
            }
        } catch (Exception $ex) {
            throw $ex;
        }        
    }

    public static function getCapillarySetting($apiUrl = null, $externalMemberSetting = null) {
        if (is_null($externalMemberSetting)) {
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        }

        $memberUrl = $apiUrl !== null ? $externalMemberSetting[$apiUrl] : null;
        $kanmoUserName = Setting::getValue1('POS', 'UIN Capillary');
        $kanmoPassword = Setting::getValue1('POS', 'Password UIN Capillary');

        if (empty($kanmoUserName) || empty($kanmoPassword)) {
            throw new Exception("Username Capillary and Password Capillary cannot be blank", 500);
        }

        $authorization = base64_encode("$kanmoUserName:$kanmoPassword");
        
        $data = [
            'apiUrl' => $memberUrl,
            'authorization' => $authorization
        ];

        return $data;
    }

    public static function fetchMemberInfo($searchBy, $searchValue){
        $cardID = null;
        $phone = null;
        $memberID = false;
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        $dataLogging = null;
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();

        $httpService = new HttpHelperService();

        $capillaryMembershipTypes = ['capillary', 'capillaryV2'];
        if($searchBy === 'phone' && !in_array($externalMemberSetting['Membership Type'], ['memberid', 'looplite', 'stamps', 'uvloyalty'])) {

            //@notes: cek phone prefix 0 / 62, jika bukan tambahkan 0;
            if(substr($searchValue, 0, 1) !== '0' && substr($searchValue, 0, 2) !== '62') {
                $phone = '0' . $searchValue;
            } else {
                $phone = $searchValue;
            }
            $memberRequest = '?phone=' . $phone;
            $dataLogging['body'] = [
                'phone' => $phone
            ];
        } else if ($searchBy === 'cardID' && ($externalMemberSetting['Membership Type'] != 'memberid' && $externalMemberSetting['Membership Type'] != 'looplite' && $externalMemberSetting['Membership Type'] != 'uvloyalty')) {
            $memberRequest = '?loyaltyCardId=' . $searchValue;
            $cardID = $searchValue;
            $dataLogging['body'] = [
                'cardID' => $cardID
            ];
        } else if ($searchBy === 'memberID' && $externalMemberSetting['Membership Type'] == 'looplite') {
            $memberRequest = '?search=' . $searchValue;
            $cardID = $searchValue;
            $memberID = true;
        } else if ($searchBy === 'mobile' && in_array($externalMemberSetting['Membership Type'], $capillaryMembershipTypes)) {
            $memberRequest = '?mobile=' . $searchValue;
            $cardID = $searchValue;
        } else if ($searchBy === 'email' && in_array($externalMemberSetting['Membership Type'], $capillaryMembershipTypes)) {
            $memberRequest = '?email=' . $searchValue;
            $cardID = $searchValue;
        } else if ($searchBy === 'phone' && $externalMemberSetting['Membership Type'] == 'stamps') {
            $memberRequest = '?user=' . $searchValue;
            $cardID = $searchValue;
        } else if (in_array($searchBy, ['email', 'phone']) && $externalMemberSetting['Membership Type'] == 'uvloyalty') {
            $cardID = $searchValue;
        } else {
            $param = is_numeric($searchValue) && !strpos(strtoupper($searchValue), 'E') ? '?phoneNumber=' : '?memberCode=';
            $memberRequest =  $param. $searchValue; 
            $cardID = $searchValue;
            $memberID = true;
        }

        //@notes : ulangi jika ada error token
        $maxAttempts = 2;
        $numOfAttempts = $maxAttempts+1;
        $attempts = 0;
        do {
            try
            {
                $accessToken = null;
                if (!in_array($externalMemberSetting['Membership Type'], ["capillaryV2", "uvloyalty"])) {
                    if (isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) && (in_array($externalMemberSetting['Membership Type'], ['memberid', 'looplite']))) {
                        $accessToken = $externalMemberSetting[SELF::GET_STATIC_TOKEN];
                    } else {
                        $accessToken = SELF::getToken('MAP Token', 0, $searchBy);
                    }
                }
                $staticToken = isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) ? $externalMemberSetting[SELF::GET_STATIC_TOKEN] : null;
                $memberApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_MEMBER_API_URL]), $companyAuthKey);
                $authorization = $memberID ? $accessToken : 'Bearer ' . $accessToken;

                $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);

                if ($externalMemberSetting['Membership Type'] === 'esbloyalty') {

                    $accessToken = self::getToken('Loyalty Token', 1, $searchBy);
                    $url = $memberApiUrl.$searchValue.'/';
                    $headers = ['Authorization' => 'Bearer '.$accessToken ];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($url, $headers, $options);

                } else if ($externalMemberSetting['Membership Type'] === 'tada') {
        
                    // @refactor http_helper
                    $url = $memberApiUrl;
                    $headers = ['Authorization' => $authorization];
                    $data =   ['phone_number' => $phone, 'country' => 'ID'];
                    $options = ['timeOut' => 300];
                    $result = $httpService->post($url, $headers, $data, $options);

                } else if($externalMemberSetting['Membership Type'] === 'memberid'){
                      
                    $url = $memberApiUrl . $memberRequest;
                    $headers = ['mid-client-key' => $authorization];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($url, $headers, $options);
                    $dataLogging['body'] = $searchValue; 
                } else if($externalMemberSetting['Membership Type'] === 'looplite'){

                    $urlx = $memberApiUrl . $memberRequest;
                    $headers = ['mid-client-key' => $authorization];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($urlx, $headers, $options);
                    
                } else if ($externalMemberSetting['Membership Type'] === 'capillary') {
                   
                    $url = $memberApiUrl . $memberRequest . '&format=json';
                    $headers = ['Authorization' => str_replace("Bearer", "Basic", $authorization)];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($url, $headers, $options);

                } else if ($externalMemberSetting['Membership Type'] === 'capillaryV2') {

                    $capillarySetting = self::getCapillarySetting();
                    $authorization = $capillarySetting['authorization'];
                    $url = $memberApiUrl . $memberRequest . '&format=json';
                    $headers = ['Authorization' => 'Basic ' . $authorization];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($url, $headers, $options);

                } else if ($externalMemberSetting['Membership Type'] === 'stamps') {

                    $memberRequest .= "&token=$staticToken";
                    $url = $memberApiUrl . $memberRequest;
                    $headers = ['Authorization' => $authorization];
                    $options = ['timeOut' => 300];
                    $result = $httpService->get($url, $headers, $options);

                } else if ($externalMemberSetting['Membership Type'] === 'uvloyalty'){
                    $apiUrl = Setting::getApiUrl();
                    $memberApiUrl = $apiUrl . '/erp/ultra-voucher-loyalty/get-member';
                    $authUsername = Yii::$app->params['restUsername'];
                    $authPassword = Yii::$app->params['restPassword'];
                    $merchantID = $externalMemberSetting['Ultra Voucher Loyalty Merchant ID'];
                    $bodyRequest = [
                        'merchantID' => $merchantID,
                        'brandID' => $brandModel->brandID,
                        $searchBy == 'email' ? 'email' : 'phoneNumber' => $cardID
                    ];
                    $dataLogging['body'] = $bodyRequest;

                    // @refactor http_helper
                    $url = $memberApiUrl;
                    $headers = [
                        'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                        'data-auth-username' =>  self::getPasswordSalt()['username'],
                        'data-auth-password' =>  self::getPasswordSalt()['password'],
                        'data-auth-salt' =>  self::getPasswordSalt()['salt']
                    ];
                    $data =   [
                        'merchantID' => $merchantID,
                        'brandID' => $brandModel->brandID,
                        'phoneNumber' => $cardID
                    ];
                    $options = ['timeOut' => 300];
                    $result = $httpService->post($url, $headers, $data, $options);

                    $dataLogging['response'] = json_decode($result->getContent(), true);
                    Logging::save('-', Logging::ULTRA_VOUCHER_GET_MEMBER , $dataLogging);
                } else {
                    $result = $client->get($memberApiUrl . $memberRequest)
                        ->addHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Authorization' => $authorization,
                        ])
                        ->setOptions([
                            CURLOPT_FRESH_CONNECT => TRUE,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_TIMEOUT => 10
                        ])
                        ->send();
                    $dataLogging['response'] = json_decode($result->getContent(), true);
                    Logging::save('-', Logging::MAP_CLUB_MEMBER , $dataLogging);
                }
                
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if (isset($response['code']) && $response['code'] == 10) {
                        throw new Exception("Invalid token", 401);
                    }
                    if (isset($response['statusCode']) && $response['statusCode'] != 200) {
                        throw new Exception("Invalid token", 401);
                    }

                    $response['phone'] = $phone;
                    $response['cardID'] = $cardID;
                    $response['flagExternalAPI'] = $externalMemberSetting['External Member'];
                    $response['externalMembershipTypeID'] = $externalMemberSetting['Membership Type'];
                    $response['balance']['pointConversion'] = $externalMemberSetting['Point Conversion'];

                    if ($externalMemberSetting['Membership Type'] == 'esbloyalty'){
                        $response['id'] = $response['data']['detail']['member_id'];
                        $response['firstName'] = $response['data']['detail']['name'];
                        $response['phone'] = $response['data']['detail']['mobile_number'];
                        $response['birthDate'] = $response['data']['detail']['birth_date'];
                        $response['membershipType'] = $response['data']['detail']['membershipType'];
                        $response['balance']['totalAvailablePoints'] = $response['data']['detail']['points'];

                        $memberPromotionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_MEMBER_API_URL]), $companyAuthKey);
                        $branchID = Setting::getCurrentBranch();
                        $branch = Branch::findOne(['branchID' => $branchID]);
                        $memberRequest = 'promotion/?outlet_code='.$branch->branchCode.'&member_code='.$response['id'];
                        
                        // @refactor http_helper
                        $url = $memberPromotionApiUrl . $memberRequest;
                        $headers = ['Authorization' => 'Bearer '.$accessToken];
                        $options = ['timeOut' => 300];
                        $dataVoucherList = $httpService->get($url, $headers, $options);

                        if ($dataVoucherList->getIsOk()) {
                            $promotionList = $dataVoucherList->getData();
                            $newVoucherList = self::getLoyaltyVoucherList($promotionList, $response['id']);
                            $response['voucherMember']['item'] = $newVoucherList['items'];
                        }
                    } else if ($memberID && $externalMemberSetting['Membership Type'] == 'memberid') {
                        
                        $response['id'] = $response['data']['memberCode'];
                        $response['firstName'] = $response['data']['fullname'];
                        $response['lastName'] = '';
                        $response['phone'] = $response['data']['phoneNumber'];
                        $response['birthDate'] = $response['data']['dateOfBirth'];
                        $response['membershipType'] = $response['data']['membershipType'];
                        $response['email'] = $response['data']['email'];
                        $response['point'] = isset($response['data']['point']) ? $response['data']['point'] : 0;
                        $response['balance']['pointConversion'] = $externalMemberSetting['Point Conversion'];
                        $response['balance']['totalAvailablePoints'] = $response['point'];
                        $response['voucherMember'] = [];
                        $response['lastTransaction'] = isset($response['data']['lastTransaction']) ? $response['data']['lastTransaction'] : [];
                        $response['favoriteMenu'] = isset($response['data']['favoriteMenu']) ? $response['data']['favoriteMenu'] : [];

                        $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
                        $memberIdBranchCode = Setting::getMemberIdBranchCode();
                        $memberRequest = '?memberCode=' . $response['id'] . '&outletCode=' .$memberIdBranchCode;

                        // @refactor http_helper
                        $url = $tokenVoucherApiUrl . $memberRequest;
                        $headers = ['mid-client-key' => $authorization ];
                        $options = ['timeOut' => 300];
                        $dataVoucherList = $httpService->get($url, $headers, $options);

                        if ($dataVoucherList->getIsOk()) {
                            $voucherList = $dataVoucherList->getData();
                            $newVoucherList = self::getMemberVoucherList($voucherList);
                            $response['voucherMember']['item'] = $newVoucherList['items'];
                            $response['voucherMember']['amount'] = $newVoucherList['amounts'];
                        }

                    } else if ($memberID && $externalMemberSetting['Membership Type'] == 'looplite') {
                        $response['id'] = $response['data']['memberCode'];
                        $response['firstName'] = $response['data']['fullname'];
                        $response['lastName'] = '';
                        $response['phone'] = $response['data']['phoneNumber'];
                        $response['birthDate'] = $response['data']['dateOfBirth'];
                        $response['membershipType'] = $response['data']['membershipType'];
                        $response['lastTransaction'] = isset($response['data']['lastTransaction']) ? $response['data']['lastTransaction'] : [];
                        $response['favoriteMenu'] = isset($response['data']['favoriteMenu']) ? $response['data']['favoriteMenu'] : [];
                        $response['voucherMember'] = [];

                        $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
                        $branch = Branch::findOne(['branchID' => $branchID]);
                        $memberRequest = '?memberCode=' . $response['id'] . '&outletCode=' .$branch->branchCode . '&companyCode=' .$branch->companyCode;
   
                        // @refactor http_helper
                        $url = $tokenVoucherApiUrl . $memberRequest;
                        $headers = ['mid-client-key' => $authorization ];
                        $options = ['timeOut' => 300];
                        $dataVoucherList = $httpService->get($url, $headers, $options);

                        if ($dataVoucherList->getIsOk()) {
                            $voucherList = $dataVoucherList->getData();
                            $newVoucherList = self::getMemberVoucherList($voucherList);
                            $response['voucherMember']['item'] = $newVoucherList['items'];
                            $response['voucherMember']['amount'] = $newVoucherList['amounts'];
                        }

                        $loopBrandSetting = BrandSetting::getLoopSetting();
                        $benefitListApiUrl = Yii::$app->security->decryptByKey(base64_decode($loopBrandSetting[SELF::BENEFIT_LIST_API_URL]), $companyAuthKey);
                        $benefitListParams = "?companyCode=$branch->companyCode&branchCode=$branch->branchCode&memberCode=" . $response['id'];
                        $dataBenefitList = $client->get($benefitListApiUrl . $benefitListParams)
                            ->addHeaders([
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                                'mid-client-key' => $authorization,
                            ])
                            ->setOptions([
                                CURLOPT_FRESH_CONNECT => TRUE,
                                CURLOPT_CONNECTTIMEOUT => 10,
                                CURLOPT_TIMEOUT => 10
                            ])
                            ->send();
                        if ($dataBenefitList->getIsOk()) {
                            $benefitList = $dataBenefitList->getData();
                            $newBenefitList = self::getMemberBenefitList($benefitList);
                            $response['benefitMember']['item'] = $newBenefitList['items'];
                            $response['benefitMember']['amount'] = $newBenefitList['amounts'];
                        }
                    } else if($externalMemberSetting['Membership Type'] === 'tada'){
                        $response = $response[0];
                        $response['id'] = strval($response['id']);
                        if (isset($response['card'])) {
                            $response['firstName'] = 'Member';
                        }else{
                            $response['firstName'] = 'New Member';
                        }
                        $response['lastName'] = '';
                        $response['phone'] = $phone;
                        $response['externalMembershipTypeID'] = $externalMemberSetting['Membership Type'];
                    } else if($externalMemberSetting['Membership Type'] === 'capillary'){
                        $phoneNumber = preg_replace('/[^0-9]/', '', $searchValue);
                        $responseMember = $response['response']['customers']['customer'][0];
                        $response['cardID'] = $phoneNumber ? ($responseMember && $responseMember['email'] ? $responseMember['email'] : NULL) : $responseMember['email'];
                        $response['id'] = $response['response']['customers']['customer'][0]['email'];
                        $response['email'] = $response['response']['customers']['customer'][0]['email'];
                        $response['phone'] = $response['response']['customers']['customer'][0]['mobile'];
                        $response['externalTransID'] = $response['response']['customers']['customer'][0]['external_id'];
                        $response['firstName'] = $response['response']['customers']['customer'][0]['firstname'];
                        $response['lastName'] = $response['response']['customers']['customer'][0]['lastname'];
                        $response['point'] = $response['response']['customers']['customer'][0]['loyalty_points'];
                        $response['data'] = ["type" => $response['response']['customers']['customer'][0]['current_slab']];
                        $response['balance']['totalAvailablePoints'] = $response['response']['customers']['customer'][0]['loyalty_points'];
                        unset($response['response']);
                    } else if($externalMemberSetting['Membership Type'] === 'stamps'){
                        $response['id'] = $response['user']['email'];
                        $response['email'] = $response['user']['email'];
                        $response['phone'] = str_replace("+", "", $response['user']['phone']);
                        $response['birthDate'] = $response['user']['birthday'];
                        $response['firstName'] = $response['user']['name'];
                        $response['membershipType'] = $response['membership']['level_text'];
                        $response['point'] = $response['membership']['stamps'];
                        $response['balance']['totalAvailablePoints'] = $response['membership']['stamps'];
                        $response['voucherMember'] = [];
                        unset($response['user']);
                        unset($response['membership']);

                        $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
                        $response['voucherMember']['item'] = self::getStampVoucherList(
                            $tokenVoucherApiUrl,
                            $staticToken,
                            $authorization,
                            $searchValue
                        );
                    } else if ($externalMemberSetting['Membership Type'] === 'capillaryV2') {
                        $responseMember = $response['response']['customers']['customer'][0];
                        $email = $responseMember['email'];
                        $phoneNumber = preg_replace('/[^0-9]/', '', $searchValue);
                        $response['cardID'] = $phoneNumber ? ($responseMember && $email ? $email : NULL) : $email;
                        $response['id'] = $email;
                        $response['email'] = $email;
                        $response['phone'] = $responseMember['mobile'];
                        $response['externalTransID'] = $responseMember['external_id'];
                        $response['firstName'] = $responseMember['firstname'];
                        $response['lastName'] = $responseMember['lastname'];
                        $response['point'] = $responseMember['loyalty_points'];
                        $response['data'] = ["type" => $responseMember['current_slab']];
                        $response['balance']['totalAvailablePoints'] = $responseMember['loyalty_points'];
                        unset($response['response']);
                    } else if ($externalMemberSetting['Membership Type'] == 'uvloyalty') {
                        $response['id'] = $response['cardID'];
                        $response['firstName'] = $response['full_name'];
                        $response['lastName'] = '';
                        $response['birthDate'] = '';
                        $response['membershipType'] = $response['tier'];
                        $response['point'] = isset($response['point']) ? $response['point'] : 0;
                        if ($searchBy == 'email') {
                            $response['email'] = $response['email'];
                        } else {
                            $response['phone'] = $response['phone_number'];
                        }
                        unset($response['response']);
                    }

                    return $response;
                } else {
                    throw new Exception("Error get request", $result->getStatusCode());
                }
            } catch (\Exception $ex) {
                if($ex->getCode() == 401 && $attempts < $maxAttempts - 1) {
                    $externalMemberSetting = BrandSetting::getExternalMemberSetting();
                    if (!in_array($externalMemberSetting['Membership Type'], ['memberid', 'looplite'])) {
                        SELF::getToken('MAP Token', 1, $searchBy);
                    }
                    $attempts++;
                    sleep(1);
                    continue;
                }

                if ($dataLogging && $externalMemberSetting['Membership Type'] != 'uvloyalty' && $externalMemberSetting['Membership Type'] != 'memberid') {
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save('', Logging::MAP_CLUB_MEMBER , $dataLogging);
                }
                if ($dataLogging && $externalMemberSetting['Membership Type'] == 'memberid') {
                    $dataLogging['response'] = $ex->getMessage();
                    $currentTimestamp = new DateTime();
                    $dataLogging['timeEvent'] = $currentTimestamp->format('Y-m-d H:i:s');
                    Logging::save($dataLogging['body'], Logging::FAILED_GET_MEMBER , $dataLogging);
                }
            }
            break;
        } while($attempts < $numOfAttempts);
    }

    public static function fetchVoucherMemberId($memberCode, $externalMembershipTypeID)
    {
        try {
            $accessToken = null;
            $memberID = $externalMembershipTypeID == 'memberid';
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $companyAuthKey = Setting::getApiKey();

            if (!in_array($externalMemberSetting['Membership Type'], ["capillaryV2", "uvloyalty"])) {
                if (isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) && (in_array($externalMemberSetting['Membership Type'], ['memberid']))) {
                    $accessToken = $externalMemberSetting[SELF::GET_STATIC_TOKEN];
                }
            }

            $httpService = new HttpHelperService();
            $tokenVoucherApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::GET_TOKEN_VOUCHER_API_URL]), $companyAuthKey);
            $authorization = $memberID ? $accessToken : 'Bearer ' . $accessToken;

            if ($memberID == 'memberid') {

                $memberIdBranchCode = Setting::getMemberIdBranchCode();
                $memberRequest = '?memberCode=' . $memberCode . '&outletCode=' . $memberIdBranchCode;
                $response['voucherMember'] = [];

                // @refactor http_helper
                $url = $tokenVoucherApiUrl . $memberRequest;
                $headers = ['mid-client-key' => $authorization];
                $options = ['timeOut' => 10];
                $dataVoucherList = $httpService->get($url, $headers, $options);

                if ($dataVoucherList->getIsOk()) {
                    $voucherList = $dataVoucherList->getData();
                    $newVoucherList = self::getMemberVoucherList($voucherList);
                    $response['voucherMember']['item'] = $newVoucherList['items'];
                    $response['voucherMember']['amount'] = $newVoucherList['amounts'];
                } else {
                    throw new Exception("Error get request", $dataVoucherList->getStatusCode());
                }
            }
            return $response;
        } catch (Exception $e) {
            throw new HttpException(400, 'Failed to get Response');
        }
    }

    public static function getMemberVoucherList($voucherList) {
        $voucherItems = [];
        $voucherAmounts = [];
        if (isset($voucherList['statusCode']) && $voucherList['statusCode'] == 200) {
            $vouchersItem = [];
            $vouchersAmount = [];
            $benefitsItem = [];
            $benefitsAmount = [];
            $now = date('Y-m-d H:i:s');
            if ($voucherList['data']['vouchers']['item']) {
                foreach ($voucherList['data']['vouchers']['item'] as $voucher) {
                    $voucherExpiredDate = date('Y-m-d H:i:s', strtotime($voucher['expiredAt']));
                    if ($voucherExpiredDate >  $now) {
                        array_push($vouchersItem, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "promotionCode" => $voucher['promotionCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "itemCode" => $voucher['itemCode'],
                            "itemName" => $voucher['itemName'],
                            "price" => $voucher['price'],
                            "discount" => $voucher['discount'],
                            "minimumSpending" => $voucher['minimumSpending'],
                            "expiredAt" => $voucher['expiredAt']
                        ));
                    }
                }
            }
            if ($voucherList['data']['vouchers']['amount']) {
                foreach ($voucherList['data']['vouchers']['amount'] as $voucher) {
                    if ($voucher['voucherType'] === 'DISCOUNT') {
                        array_push($vouchersItem, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "promotionCode" => $voucher['promotionCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "discount" => $voucher['discountAmount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    } else if ($voucher['voucherType'] === 'OPEN BILL DISCOUNT RP') {
                        array_push($vouchersItem, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "promotionCode" => $voucher['promotionCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "amount" => $voucher['amount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    } else {
                        array_push($vouchersAmount, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "amount" => $voucher['amount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    }
                }
            }
            if ($voucherList['data']['benefits']['item']) {
                foreach ($voucherList['data']['benefits']['item'] as $voucher) {
                    $voucherExpiredDate = date('Y-m-d H:i:s', strtotime($voucher['expiredAt']));
                    if ($voucherExpiredDate >  $now) {
                        array_push($benefitsItem, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "promotionCode" => $voucher['promotionCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "itemCode" => $voucher['itemCode'],
                            "itemName" => $voucher['itemName'],
                            "price" => $voucher['price'],
                            "discount" => $voucher['discount'],
                            "minimumSpending" => $voucher['minimumSpending'],
                            "expiredAt" => $voucher['expiredAt']
                        ));
                    }
                }
            }
            if ($voucherList['data']['benefits']['amount']) {
                foreach ($voucherList['data']['benefits']['amount'] as $voucher) {
                    if ($voucher['voucherType'] === 'DISCOUNT') {
                        array_push($benefitsItem, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "promotionCode" => $voucher['promotionCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "discount" => $voucher['discountAmount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    } else {
                        array_push($benefitsAmount, array(
                            "voucherType" => $voucher['voucherType'],
                            "voucherCode" => $voucher['voucherCode'],
                            "voucherDescription" => $voucher['voucherDescription'],
                            "amount" => $voucher['amount'],
                            "minimumSpending" => $voucher['minimumSpending']
                        ));
                    }
                }
            }
            $voucherItems = array_merge($vouchersItem, $benefitsItem);
            $voucherAmounts = array_merge($vouchersAmount, $benefitsAmount);
        }

        return [
            'items' => $voucherItems,
            'amounts' => $voucherAmounts
        ];
    }

    public static function getMemberBenefitList($benefitList) {
        $benefitItems = [];
        if (isset($benefitList['statusCode']) && $benefitList['statusCode'] == 200) {
            $benefitItem = [];
            $now = date('Y-m-d H:i:s');
            if ($benefitList['data']['item']) {
                foreach ($benefitList['data']['item'] as $item) {
                    $expiredDate = date('Y-m-d H:i:s', strtotime($item['expiredDate']));
                    if ($expiredDate > $now) {
                        $menuName = isset($item['menuName']) ? $item['menuName'] : null;
                        array_push($benefitItem, array(
                            "voucherType" => "Free Item",
                            "displayLabel" => $item['benefitTypeName'],
                            "voucherCode" => $item['benefitCode'],
                            "promotionCode" => $item['promotionCode'],
                            "voucherDescription" => $item['description'],
                            "itemCode" => $item['menuCode'],
                            "itemName" => $menuName != null ? $menuName : '-',
                            "price" => 0,
                            "discount" => 100,
                            "minimumSpending" => 0,
                            "expiredAt" => $expiredDate
                        ));
                    }
                }
            }
            if ($benefitList['data']['amount']) {
                foreach ($benefitList['data']['amount'] as $amount) {
                    $expiredDate = date('Y-m-d H:i:s', strtotime($amount['expiredDate']));
                    if ($expiredDate > $now) {
                        array_push($benefitItem, array(
                            "voucherType" => "DISCOUNT",
                            "displayLabel" => $amount['benefitTypeName'],
                            "voucherCode" => $amount['benefitCode'],
                            "promotionCode" => $amount['promotionCode'],
                            "voucherDescription" => $amount['description'],
                            "discount" => $amount['discountAmount'],
                            "minimumSpending" => 0,
                            "expiredAt" => $expiredDate
                        ));
                    }
                }
            }
            $benefitItems = array_merge($benefitItems, $benefitItem);
        }

        return [
            'items' => $benefitItems,
            'amounts' => []
        ];
    }

    public static function getLoyaltyVoucherList($voucherList, $memberCode) {
        $voucherItems = [];
        if ($voucherList['meta']['code'] == 200) {
            $vouchersItem = [];
            $benefitsItem = [];
            //voucher for free item
            if ($voucherList['data']['detail']['voucher_plu']) {
                foreach ($voucherList['data']['detail']['voucher_plu'] as $voucher_plu) {
                    array_push($vouchersItem, array(
                        "inList"    => false,
                        "voucherType" => "ITEM",
                        "voucherCode" => $voucher_plu['voucher_code'],
                        "itemCode" => $voucher_plu['plu'],
                        "itemName" => $voucher_plu['plu_name'],
                        "discount" => 100,
                        "expiredAt" => $voucher_plu['expired_at'],
                        "promotionCode" => $voucher_plu['acc_promo_code'],
                        "voucherDescription" => $voucher_plu['reward_name'],
                    ));
                }
            }
            //benefit for free item and discount item
            if ($voucherList['data']['detail']['discount_plu']) {
                foreach ($voucherList['data']['detail']['discount_plu'] as $discount_plu) {
                    array_push($benefitsItem, array(
                        "inList"    => true,
                        "voucherType" => "ITEM",
                        "voucherCode" => "Benefit-".$discount_plu['promo_id'],
                        "promotionCode" => $discount_plu['acc_promo_code'],
                        "itemCode" => $discount_plu['plu'],
                        "itemName" => $discount_plu['plu_name'],
                        "price" => $discount_plu['price'],
                        "discount" => strtolower($discount_plu['result_type']) == 'free_sku' ? 100 : $discount_plu['discount'],
                        "minimumSpending" => 0,
                        "voucherDescription" => $discount_plu['promo_desc'],
                    ));
                }
            }
            //benefit for birthday
            if ($voucherList['data']['detail']['birthday']) {
                $birthdayBenefit = $voucherList['data']['detail']['birthday'][0];
                if ($birthdayBenefit['is_birthday'] == true) {
                    if ($birthdayBenefit['claimed'][0]['is_claimed'] == false) {
                        array_push($benefitsItem, array(
                            "inList"    => true,
                            "voucherType" => "ITEM",
                            "voucherCode" => $memberCode."|".$birthdayBenefit['promo_id'],
                            "promotionCode" => $birthdayBenefit['acc_promo_code'],
                            "itemCode" => $birthdayBenefit['claimed'][0]['plu'],
                            "itemName" => $birthdayBenefit['claimed'][0]['plu_name'],
                            "price" => 0,
                            "discount" => 100,
                            "minimumSpending" => 0,
                            "voucherDescription" => $birthdayBenefit['promo_desc'],
                        ));
                    }
                }
            }
            //benefit for discount category menu
            if ($voucherList['data']['detail']['discount_dept_plu']) {
                foreach ($voucherList['data']['detail']['discount_dept_plu'] as $discount_dept_plu) {
                    if ($discount_dept_plu['result_type'] != "DISCOUNT_PRICE") {
                        array_push($benefitsItem, array(
                            "inList"    => true,
                            "voucherType" => "ITEM",
                            "voucherCode" => "Benefit-".$discount_dept_plu['promo_id'],
                            "promotionCode" => $discount_dept_plu['acc_promo_code'],
                            "groupCode" => $discount_dept_plu['group_plu'],
                            "groupName" => $discount_dept_plu['group_name'],
                            "price" => 0,
                            "discount" => $discount_dept_plu['discount'],
                            "minimumSpending" => 0,
                            "voucherDescription" => $discount_dept_plu['promo_desc'],
                        ));
                    }
                }
            }
            //benefit for free item with minimum spending
            if ($voucherList['data']['detail']['minimum_spending']) {
                foreach ($voucherList['data']['detail']['minimum_spending'] as $minimum_spending) {
                    if ($minimum_spending['result_type'] != "DISCOUNT_PRICE" && $minimum_spending['free_plu'] != []) {
                        array_push($benefitsItem, array(
                            "inList"    => true,
                            "voucherType" => "ITEM",
                            "voucherCode" => "Benefit-".$minimum_spending['promo_id'],
                            "promotionCode" => $minimum_spending['acc_promo_code'],
                            "itemCode" => $minimum_spending['free_plu'][0]['plu'],
                            "itemName" => $minimum_spending['free_plu'][0]['plu_name'],
                            "price" => $minimum_spending['free_plu'][0]['price'],
                            "discount" => 100,
                            "minimumSpending" => $minimum_spending['minimum_amount'],
                            "voucherDescription" => $minimum_spending['promo_desc'],
                        ));
                    }
                }
            }
            //benefit for bill discount
            if ($voucherList['data']['detail']['bill_discount']) {
                foreach ($voucherList['data']['detail']['bill_discount'] as $voucher_amount) {
                    if ($voucher_amount['result_type'] != "DISCOUNT_PRICE") {
                        array_push($benefitsItem, array(
                            "inList"    => true,
                            "voucherType" => "DISCOUNT",
                            "voucherCode" => "Benefit-".$voucher_amount['promo_id'],
                            "promotionCode" => $voucher_amount['acc_promo_code'],
                            "itemCode" => '',
                            "itemName" => '',
                            "price" => 0,
                            "discount" => $voucher_amount['discount'],
                            "minimumSpending" => 0,
                            "voucherDescription" => $voucher_amount['promo_desc'],
                        ));
                    }
                }
            }
            $voucherItems = array_merge($vouchersItem, $benefitsItem);
        }

        return [
            'items' => $voucherItems
        ];
    }

    public static function getBalance($flagExternalMemberPhone, $flagExternalCardID) {
        $balance = null;
        if ($flagExternalMemberPhone) {
            $searchBy = 'phone';
            $searchValue = $flagExternalMemberPhone;
        } else {
            $searchBy = 'cardID';
            $searchValue = $flagExternalCardID;
        }
        $memberInfo = SELF::fetchMemberInfo($searchBy, $searchValue);
        if ($memberInfo) {
            $balance = $memberInfo['balance'];
        }
        return $balance;
    }

    public static function getStampVoucherList($tokenVoucherApiUrl, $staticToken, $authorization, $user) {
        $lastVoucherID = null;
        $hasNext = false;
        $voucherItems = [];
        try {
            $promotionModel = PromotionHead::findActiveForLoyalty();
            do {
                if ($lastVoucherID != null && $hasNext) {
                    $voucherApiParams = "?token=$staticToken&user=$user&last_voucher_id=$lastVoucherID";
                } else {
                    $voucherApiParams = "?token=$staticToken&user=$user";
                }

                $client = new Client();
                $result = $client->get($tokenVoucherApiUrl . $voucherApiParams)
                    ->addHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => $authorization,
                    ])->send();

                $response = $result->getData();
                if ($result->getIsOk()) {
                    $currentDate = date('Y-m-d');
                    $hasNext = isset($response['has_next']) ? $response['has_next'] : false;
                    if (isset($response['vouchers'])) {
                        foreach ($response['vouchers'] as $voucher) {
                            $lastVoucherID = $voucher['id'];
                            $voucherStartDate = date('Y-m-d', strtotime($voucher['start_date']));
                            $voucherEndDate = date('Y-m-d', strtotime($voucher['end_date']));
                            if (($currentDate >= $voucherStartDate) && ($currentDate <= $voucherEndDate)) {
                                $extraDataEsb = $voucher['template']['extra_data']['esb'];
                                $extraDataEsb['voucherType'] = isset($extraDataEsb['voucherType']) ? $extraDataEsb['voucherType'] : null;

                                //find promotionhead
                                $currentPromotion = null;
                                foreach ($promotionModel as $promotion) {
                                    if (
                                        in_array($extraDataEsb['voucherType'], ['ITEM', 'DISCOUNT'])
                                        && $promotion['promotionMasterCode'] == $extraDataEsb['promotionCode']
                                    ) {
                                        $currentPromotion = $promotion;
                                        if (strlen($voucher['template']['short_description']) == 0) {
                                            $voucher['template']['short_description'] = $promotion['notes'];
                                        }
                                        break;
                                    }
                                }

                                if ($extraDataEsb['voucherType'] === "ITEM") {
                                    array_push($voucherItems, array(
                                        "voucherType" => $extraDataEsb['voucherType'],
                                        "voucherCode" => $voucher['code'],
                                        "promotionCode" => $extraDataEsb['promotionCode'],
                                        "voucherDescription" => $voucher['template']['short_description'],
                                        "itemCode" => $extraDataEsb['itemCode'],
                                        "itemName" => $extraDataEsb['itemName'],
                                        "price" => $extraDataEsb['price'],
                                        "discount" => $extraDataEsb['discount'],
                                        "minimumSpending" => $extraDataEsb['minimumSpending'],
                                        "expiredAt" => $voucherEndDate
                                    ));
                                } else if ($extraDataEsb['voucherType'] === "DISCOUNT") {
                                    array_push($voucherItems, array(
                                        "voucherType" => $extraDataEsb['voucherType'],
                                        "voucherCode" => $voucher['code'],
                                        "promotionCode" => $extraDataEsb['promotionCode'],
                                        "voucherDescription" => $voucher['template']['short_description'],
                                        "discount" => $currentPromotion ? (float)$currentPromotion['discount'] : 0,
                                        "minimumSpending" => $extraDataEsb['minimumSpending']
                                    ));
                                }
                            }
                        }
                    }
                }
            } while ($hasNext);
        } catch (\Exception $ex) {
            Yii::error($ex);
        }
        return $voucherItems;
    }

    public static function registerExternalMember($phoneNumber, $customerName){
       
        $isValidate = false;
        $authorization = null;
        $dataLogging = [];
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $companyAuthKey = Setting::getApiKey();

        if (isset($externalMemberSetting[SELF::GET_STATIC_TOKEN]) && (in_array($externalMemberSetting['Membership Type'], [ 'looplite' ]))) {
            $authorization = $externalMemberSetting[SELF::GET_STATIC_TOKEN];
            $isValidate = true;
        }
        //@notes: remove front number of 0
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = substr($phoneNumber, 1);
        }
        //@notes: remove front number of 62
        if (substr($phoneNumber, 0, 2) === '62') {
            $phoneNumber = substr($phoneNumber, 2);
        }

        $customerName = $customerName ? $customerName : '';
        $datas = [
            'countryCode' => '+62',
            'phoneNumber' => $phoneNumber,
            'firstName' => $customerName
        ];

        try {

            if(!$isValidate) {
                throw new Exception("External Member Setting Wrong, Please Change!", 400);
            }

            // @notes: hitpoint register member loop
            $httpService = new HttpHelperService();
            $apiUrl = Setting::getValue1('POS', 'Register Member Loop');
            $headers = [
                'mid-client-key' => $authorization
            ];
            $options = ['timeOut' => 300];
            $dataLogging['data']['payloads'] = $datas;
            $result = $httpService->post($apiUrl, $headers, $datas, $options);

            if ($result->getIsOk()) {
                $responseContent = $result->getContent();
                $responseData = json_decode($responseContent, true);
                $dataLogging['data']['response'] = $responseData;
                if ($responseData && $responseData['statusCode'] == 200) {
                    $validate = self::fetchMemberInfo('phone', $phoneNumber);
                    $response = $validate;

                    if (!$validate) {
                        throw new Exception("Member Not Found!", 400);
                    }
                } else {
                    $response = $responseData;
                }
            }  else {
                throw new Exception("Cannot get Response!", 400);
            }

            Logging::save($phoneNumber, Logging::CALL_API_REGISTER_MEMBER, $dataLogging);

            return $response;
        } catch (\Exception $ex) {

            $dataLogging['statusCode'] = 500;
            $dataLogging['message'] = $ex->getMessage();
            $dataLogging['data']['payloadss'] = $datas;
            $dataLogging['data']['response'] = $ex->getMessage();
            Logging::save($phoneNumber, Logging::CALL_API_REGISTER_MEMBER, $dataLogging);

            return [
                'statusCode' => 500,
                'message' => $ex->getMessage(),
                'data' => null
            ];
        }
    }

    public function saveTransaction(&$errMsg) {
        $branchID = Setting::getCurrentBranch();
        $branch = Branch::findOne(['branchID' => $branchID]);
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $settings = Setting::getPrintingSettings();
        $roundingMode = isset($settings['Rounding Mode']) ? $settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($settings['Rounding Nearest Value']) ? $settings['Rounding Nearest Value'] : 0;
        //@notes : ulangi jika ada error token
        $numOfAttempts = 2;
        $attempts = 0;
        $dataLogging = null;
        do {
            try
            {
                $retailValueTax = $this->salesModel->flagInclusive === 1 ? 'retailValueAfterTax' : 'retailValueBeforeTax';
                $brandApiContentModel = BrandApiContent::findApiContent($brandModel->brandID, SELF::TRANSACTION_MEMBER_API_URL);
                $basketItems = [];
                $channelStatusReference = false;
                $isAfterBillDiscount = $branch->posTaxCalculationID == 2 && $branch->posOtherTaxCalculationID == 2;
                foreach ($this->salesModel->mainSalesMenus as $menu) {
                    $price = $this->calculateRetailValueTaxAmount($isAfterBillDiscount, $menu, 1, $roundingNearestValue, $roundingMode);
                    $basketItems[] = (object) array(
                        'productReference' => $menu->menu->menuCode ?  $menu->menu->menuCode : $menu->menu->menuName,
                        'quantity' => (int) $menu->qty,
                        $retailValueTax => $price,
                    );
                    if ($menu->otherTaxValue > 0) {
                        $channelStatusReference = true;
                    }
                    foreach($menu->childSalesMenus as $package) {
                        $packagePrice = $this->calculateRetailValueTaxAmount($isAfterBillDiscount, $package, (float)$menu->qty, $roundingNearestValue, $roundingMode);
                        $basketItems[] = (object) array(
                            'productReference' => $package->menu->menuCode ?  $package->menu->menuCode : $package->menu->menuName,
                            'quantity' => (int) $package->qty * (int) $menu->qty,
                            $retailValueTax => $packagePrice,
                        );
                        if ($package->otherTaxValue > 0) {
                            $channelStatusReference = true;
                        }
                    }
                    foreach($menu->salesExtras as $extra) {
                        $extraPrice = $this->calculateRetailValueTaxAmount($isAfterBillDiscount, $extra, (float)$menu->qty, $roundingNearestValue, $roundingMode);
                        $extraMenuCode = isset($extra->menuExtra->menu) ? ($extra->menuExtra->menu->menuCode ? $extra->menuExtra->menu->menuCode : '') : '';
                        $basketItems[] = (object) array(
                            'productReference' => $extraMenuCode,
                            'quantity' => (int) $extra->qty * (int) $menu->qty,
                            $retailValueTax => $extraPrice,
                        );
                        if ($extra->otherTaxValue > 0) {
                            $channelStatusReference = true;
                        }
                    }
                }

                $payments = [];
                foreach($this->salesPayments as $payment) {
                    $paymentObj = array(
                        'paidAmount' =>  $payment['paymentAmount'],
                        'typeReference' => $payment['paymentMethodName']
                    );
                    if (array_key_exists('cardNumber', $payment)) {
                        $paymentObj['binNo'] = $payment['cardNumber'];
                    } else if(array_key_exists('voucherCode', $payment)) {
                        $paymentObj['binNo'] = $payment['voucherCode'];
                    }
                    $payments[] = (object) $paymentObj;
                }

                $bodyRequest = [];
                $bodyRequest['status'] = "COMPLETED";
                $bodyRequest['memberId'] = $this->salesModel->flagExternalMemberID;
                $bodyRequest['locationReference'] = $branch->extBranchCode;
                $bodyRequest['reference'] = $this->salesModel->salesNum;
                $bodyRequest['burnMethod'] = $this->burnPoints > 0 ? "EXACT" : "DISABLED";
                $bodyRequest['burnExactPoints'] = $this->burnPoints > 0 ? $this->burnPoints : 0;
                $bodyRequest['earnMethod'] = "ONLINE";
                $bodyRequest['basketItems'] = $basketItems;
                $bodyRequest['payments'] = $payments;
                

                foreach($brandApiContentModel->all() as $transactionContent) {
                    $bodyRequest[$transactionContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($transactionContent->valueAttribute), $companyAuthKey);
                }
                $bodyRequest['channelReference'] = $channelStatusReference ? 'fnb_dine_in' : 'fnb_take_away';
                $dataLogging['body'] = $bodyRequest;
                $accessToken = SELF::getToken('MAP Token');
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::TRANSACTION_MEMBER_API_URL]), $companyAuthKey);

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl;
                $headers = ['Authorization' => 'Bearer ' . $accessToken,];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);

                $dataLogging['response'] = json_decode($result->getContent(), true);
                if ($dataLogging) {
                    Logging::save($this->salesModel->salesNum, Logging::MAP_CLUB_TRANSACTION , $dataLogging);
                }
                if ($result->getIsOk()) {
                    $response = $result->getData();
                    $errMsg = '';
                    return $response;
                } else {
                    throw new Exception("Error get request", $result->getStatusCode());
                }
            } catch (Exception $ex) {
                Yii::warning($ex);
                if ($dataLogging) {
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save($this->salesModel->salesNum, Logging::MAP_CLUB_TRANSACTION , $dataLogging);
                }
                if($ex->getCode() == 401) {
                    SELF::getToken('MAP Token', 1);
                    $attempts++;
                    sleep(1);
                    continue; 
                }
                $errMsg .= $ex->getMessage();
            }
            break;
        } while($attempts < $numOfAttempts);
    }

    public function saveTransactionMemberID(&$errMsg) {
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
        $externalSetting = Setting::getExternalSettings();
        //@notes : ulangi jika ada error token
        $numOfAttempts = 2;
        $attempts = 0;
        do {
            try
            {
                $payments = [];
                foreach($this->salesPayments as $payment) {
                    if (isset($payment['voucherCode'])) {
                        if ($payment['voucherCode'] != '' && $payment['voucherCode'] != null) {
                            $payment = array(
                                'voucher_code' =>  $payment['voucherCode'],
                                'voucher_type' => "Amount",
                            );
    
                            $payments[] = $payment;
                        }
                    }
                }

                $bodyRequest = [];
                $bodyRequest['outlet_code'] = $externalSetting[self::MEMBER_ID_BRANCH_CODE];
                $bodyRequest['cashier_id'] = $this->salesModel->editor->fullName;
                $bodyRequest['member_id'] = $this->salesModel->flagExternalMemberID;
                $bodyRequest['list'] = $payments;

                $accessToken = SELF::getToken('MAP Token', 0, 'memberID');
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::BURN_VOUCHER_API_URL]), $companyAuthKey);

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl;
                $headers = ['Authorization' => 'Bearer ' . $accessToken,];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);

                if ($result->getIsOk()) {
                    $response = $result->getData();
                    if ($response['meta']['code'] != 200) {
                        $errorMessage = $response['meta']['message'];
                        $errMsg .= $errorMessage;
                        throw new Exception($errorMessage);
                    }
                    return $response;
                } else {
                    $errMsg = "Error get request";
                }
            } catch (Exception $ex) {
                Yii::warning($ex);
                if($ex->getCode() == 401) {
                    SELF::getToken('MAP Token', 1, 'memberID');
                    $attempts++;
                    sleep(1);
                    continue; 
                }
            }
            break;
        } while($attempts < $numOfAttempts);
    }

    public static function voidTransaction($externalTransID) {
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
        //@notes : ulangi jika ada error token
        $numOfAttempts = 2;
        $attempts = 0;
        do {
            try
            {
                $accessToken = SELF::getToken('MAP Token');
                $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::TRANSACTION_MEMBER_API_URL]), $companyAuthKey);
        
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $transactionApiUrl;
                $headers = ['Authorization' => 'Bearer ' . $accessToken,];
                $datas = [];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $datas, $options);

                if ($result->getIsOk()) {
                    $response = $result->getData();
                    return $response;
                } else {
                    throw new Exception("Error get request", $result->getStatusCode());
                }
            } catch (Exception $ex) {
                Yii::warning($ex);
                if($ex->getCode() == 401) {
                    SELF::getToken('MAP Token', 1);
                    $attempts++;
                    sleep(1);
                    continue; 
                }
            }
            break;
        } while($attempts < $numOfAttempts);
    }

    public static function voidTransactionTada($salesModel, $voidNotes, &$errMsg) {
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
        $externalSetting = Setting::getExternalSettings();
        //@notes : ulangi jika ada error token
        $numOfAttempts = 2;
        $attempts = 0;
        do {
            try
            {
                $apiKey = Setting::getApiKey();
                $apiUrl = Setting::getApiUrl();

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . '/esb_api/sales/get-external-trans-id';
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $datas = [
                    'salesNum' => $salesModel->salesNum
                ];
                $options = ['timeOut' => 300];
                $resExtTrId = $httpService->post($url, $headers, $datas, $options);

                if ($resExtTrId->getIsOk()) {
                    $res = $resExtTrId->getData();
                    if (isset($res['externalTransID'])) {
                        $bodyRequest = [];
                        $bodyRequest['transactionNumber'] = $res['externalTransID'];
                        $bodyRequest['reason'] = $voidNotes;    
                        $accessToken = SELF::getToken('MAP Token', 0);
                        $transactionApiUrl = Yii::$app->security->decryptByKey(base64_decode($externalMemberSetting[SELF::UNBURN_VOUCHER_API_URL]), $companyAuthKey);
              
                        // @refactor http_helper
                        $httpService = new HttpHelperService();
                        $url = $transactionApiUrl;
                        $headers = ['Authorization' => 'Bearer ' . $accessToken,];
                        $options = ['timeOut' => 300];
                        $result = $httpService->post($url, $headers, $bodyRequest, $options);
                        if ($result->getIsOk()) {
                            $response = $result->getData();
                            if (isset($response['error'])) {
                                $errorMessage = $response['message'];
                                $errMsg .= $errorMessage;
                            }
                            return $response;
                        } else {
                            throw new Exception("Error get request", $result->getStatusCode());
                        }
                    }
                }else{
                    throw new Exception("Error get external transaction id", $resExtTrId->getStatusCode());
                }

            } catch (Exception $ex) {
                Yii::warning($ex);
                if($ex->getCode() == 401) {
                    SELF::getToken('MAP Token', 0);
                    $attempts++;
                    sleep(1);
                    continue; 
                }
            }
            break;
        } while($attempts < $numOfAttempts);
    }

    private function calculateRetailValueTaxAmount($isAfterBillDiscount, $salesMenu, $headSalesMenuQty, $rounding, $roundingMode){
        $retailValueTaxAmount = 0;
        $discountTotal = (float)$this->salesModel->discountTotal;
        $billDiscount = 0;
        $totalMenuDiscountValue = (float)$salesMenu->discountValue * (float)$headSalesMenuQty;
        $menuSubTotal = (float)$salesMenu->price * (float)$salesMenu->qty * (float)$headSalesMenuQty;
        
        if($discountTotal > 0){
            if($isAfterBillDiscount){
                //after discount
                $vatDifference = abs(($menuSubTotal * (float)$salesMenu->vat / 100) - (float)$salesMenu->vatValue);
                $otherVatDifference = abs(($menuSubTotal * (float)$salesMenu->otherVat / 100) - (float)$salesMenu->otherVatValue);
                
                $validVatDisc = (($vatDifference > 2 && $salesMenu->vatValue > 0) || $salesMenu->vatValue == 0) ? true : false;
                $validOtherVatDisc = (($otherVatDifference > 2 && $salesMenu->otherVatValue > 0) || $salesMenu->otherVatValue == 0) ? true : false;
    
                $billDiscount = ($validVatDisc && $validOtherVatDisc) //eligibleForBillDiscount
                    ? $menuSubTotal / (float)$this->salesModel->subtotal * (float)$discountTotal
                    : 0
                ;
            }else{
                //before discount
                $billDiscount = $menuSubTotal / (float)$this->salesModel->subtotal * (float)$discountTotal;
            }
        }
        $retailValueTaxAmount = $menuSubTotal - $billDiscount - $totalMenuDiscountValue;
        if ($rounding != 0) {
            if ($roundingMode == 'DOWN') {
                $retailValueTaxAmount = (floor($retailValueTaxAmount / $rounding) * $rounding);
            } else if ($roundingMode == 'UP') {
                $retailValueTaxAmount = (ceil($retailValueTaxAmount / $rounding) * $rounding);
            } else if ($roundingMode == 'AUTO') {
                $retailValueTaxAmount = ROUND($retailValueTaxAmount / $rounding) * $rounding;
            }
        }
        return $retailValueTaxAmount;
    }

    public static function capillaryCallOtp($email, $point)
    {
        try {
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();

            $client = new Client();
            $authorization = ExternalMember::getToken('MAP Token', 0);
            $apiUrl = $externalMemberSetting['Check Membership Point API URL Capillary'];

            if ($authorization == null) {
                throw new Exception("The token has not been set", 500);
            }

            $params = "&email=$email&points=$point";
            $result = $client->get($apiUrl . '?issue_otp=true&format=json' . $params)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->send();

            $response = $result->getData();
            if ($result->getIsOk() && $response['response']['status']['success'] === 'true') {
                return true;
            } else {
                throw new Exception("Point can not be redeemed", 400);
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            if ($ex->getCode() == 400) {
                throw new HttpException($ex->getCode(), $ex->getMessage());
            } else {
                throw new HttpException(500, "Internal Server Error");
            }
        }
    }

    public static function capillaryCallOtpV2($email, $point)
    {
        try {
            $client = new Client();
            $url = 'Check Membership Point API URL Capillary';
            $capillarySetting = self::getCapillarySetting($url);
            $apiUrl = $capillarySetting['apiUrl'];
            $authorization = $capillarySetting['authorization'];

            $params = "&email=$email&points=$point";
            $result = $client->get($apiUrl . '?issue_otp=true&format=json' . $params)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->send();

            $response = $result->getData();
            if ($result->getIsOk() && $response['response']['status']['success'] === 'true') {
                return true;
            } else {
                throw new Exception("Point can not be redeemed", 400);
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            if ($ex->getCode() == 400) {
                throw new HttpException($ex->getCode(), $ex->getMessage());
            } else {
                throw new HttpException(500, "Internal Server Error");
            }
        }
    }

    public static function capillaryRedeemPoint($salesNum, $email, $point, $otpCode)
    {
        try {
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();

            $authorization = ExternalMember::getToken('MAP Token', 0);
            $apiUrl = $externalMemberSetting['Burn Membership Point API URL Capillary'];

            if ($authorization == null) {
                throw new Exception("The token has not been set", 500);
            }

            $client = new Client();
            $bodyRequest = [
                "root" => [
                    "redeem" => [
                        "notes" => "",
                        "customer" => [
                            "email" => $email
                        ],
                        "points_redeemed" => $point,
                        "redemption_time" => date('Y-m-d H:i:s'),
                        "validation_code" => $otpCode,
                        "transaction_number" => $salesNum
                    ]
                ]
            ];

            $result = $client->post($apiUrl . '?format=json')
                ->setFormat(Client::FORMAT_JSON)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->addData($bodyRequest)
                ->send();

            $response = $result->getData();
            if ($result->getIsOk() && $response['response']['status']['success'] === 'true') {
                return true;
            } else {
                throw new Exception("Incorrect OTP, try again", 400);
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            if ($ex->getCode() == 400) {
                throw new HttpException($ex->getCode(), $ex->getMessage());
            } else {
                throw new HttpException(500, "Internal Server Error");
            }
        }
    }

    public static function capillaryRedeemPointV2($salesNum, $email, $point, $otpCode)
    {
        try {
            $url = 'Burn Membership Point API URL Capillary';
            $capillarySetting = self::getCapillarySetting($url);
            $apiUrl = $capillarySetting['apiUrl'];
            $authorization = $capillarySetting['authorization'];

            $client = new Client();
            $bodyRequest = [
                "root" => [
                    "redeem" => [
                        "notes" => "",
                        "customer" => [
                            "email" => $email
                        ],
                        "points_redeemed" => $point,
                        "redemption_time" => date('Y-m-d H:i:s'),
                        "validation_code" => $otpCode,
                        "transaction_number" => $salesNum
                    ]
                ]
            ];

            $result = $client->post($apiUrl . '?format=json')
                ->setFormat(Client::FORMAT_JSON)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                ])
                ->addData($bodyRequest)
                ->send();

            $response = $result->getData();
            if ($result->getIsOk() && $response['response']['status']['success'] === 'true') {
                return true;
            } else {
                Yii::error($response);
                throw new Exception("Incorrect OTP, try again", 400);
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            if ($ex->getCode() == 400) {
                throw new HttpException($ex->getCode(), $ex->getMessage());
            } else {
                throw new HttpException(500, "Internal Server Error");
            }
        }
    }

    public static function memberidRedeemPoint($salesNum, $memberCode, $amountCoin) {
        set_time_limit(0);
        $rejectOrTimeoutMsg = "Please try again after confirming your points";
        try {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne(['branchID' => $branchID]);
            $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
            $visitPurposeID = SalesHead::find()
                ->select('visitPurposeID')
                ->where(['salesNum' => $salesNum])
                ->scalar();
            
            if(!$brandModel || !$branchModel || !$visitPurposeID) {
                throw new Exception("Brand or Branch or Visit Purpose not found", 1);
            }

            $memberIdBranchCode = Setting::getMemberIdBranchCode();
            if (strlen($memberIdBranchCode) == 0) {
                throw new Exception("Branch Code For External Integration not set", 1);
            }
            
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $apiUrl = $externalMemberSetting['Burn Point API MemberID'];
            $token = $externalMemberSetting[SELF::GET_STATIC_TOKEN];

            if (strlen($apiUrl) == 0 || strlen($token) == 0) {
                throw new Exception("The url or token has not been set in brand setting", 1);
            }
            
            $bodyRequest = [
                "salesNum" => (string)$salesNum,
                "memberCode" => (string)$memberCode,
                "amountCoin" => (int)$amountCoin,
                "outletName" => $branchModel->branchName,
                "outletCode" => $memberIdBranchCode,
                "brandName" => $brandModel->brandName,
                "companyCode" => $branchModel->companyCode,
                "visitPurposeId" => $visitPurposeID,
            ];

            $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
            $result = $client->post($externalMemberSetting['Burn Point API MemberID'])
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'mid-client-key' => $externalMemberSetting[SELF::GET_STATIC_TOKEN],
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($bodyRequest)
                ->setOptions([
                    CURLOPT_FRESH_CONNECT => TRUE,
                    CURLOPT_CONNECTTIMEOUT => 60,
                    CURLOPT_TIMEOUT => 60
                ])
                ->send();

            $response = $result->getData();
           
            if ($result->getIsOk()) {
                if ($response['data'] === 'APPROVE') {
                    return true;
                } else {
                    //REJECT
                    throw new HttpException(400, $rejectOrTimeoutMsg, 1);
                }
            } else {
                $responseMessage = isset($response['message']) ? $response['message'] : "Server Error";
                $errorCode = $responseMessage === "coin balance not enough" ? 2 : 0;
                throw new HttpException(400, $responseMessage, $errorCode);
            }
        } catch (HttpException $httpEx) {
            Yii::error($httpEx->getMessage());
            throw new HttpException($httpEx->statusCode, $httpEx->getMessage(), $httpEx->getCode());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
            if (strpos($ex->getMessage(), 'Curl error: #28 - Operation timed out') !== false) {
                throw new HttpException(400, "Your transaction has timed out, please try again", 1);
            } else if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                throw new HttpException(500, "Please try again after checking your internet connection", 1);
            }
            
            if ($ex->getCode() == 1) {
                throw new HttpException(500, $ex->getMessage());
            }
            
            throw new HttpException(500, "Internal Server Error");
        }
    }

    public static function validateMemberPhoneNumber($salesContactModel, $tableID = 0) {
        $salesNum = isset($salesContactModel['salesNum']) ? $salesContactModel['salesNum'] : '-';
        $checkMemberUrl = BrandSetting::getBrandSetting('EXTERNAL', 'Subway API Check Member Url');
        $authToken = BrandSetting::getBrandSetting('EXTERNAL', 'Subway API Check Member Token');

        if ((empty($checkMemberUrl) || $checkMemberUrl == '') || (empty($authToken) || $authToken == '')) {
            throw new Exception("Please check your brandsetting", 1);
        }
        
        try {
            $customerPhoneNum = isset($salesContactModel['customerPhoneNum']) ? $salesContactModel['customerPhoneNum'] : 0;
            $client = new Client();
            $result = $client->get($checkMemberUrl . '/' . $customerPhoneNum)
                ->addHeaders([
                    'X-API-Key' => $authToken
                ])
                ->send();
            
            $response = $result->getData();
            if ($result->getIsOk() && $response['status'] == 200) {
                $responseData = $response['data'];
                $vouchersData = (isset($responseData['vouchers']) && count($responseData['vouchers']) > 0) ? count($responseData['vouchers']) : 0;
                $memberName = $responseData['name'];
                $memberData = [
                    'name' => $responseData['name'],
                    'email' => $responseData['email'],
                    'phone' => $responseData['phone'],
                    'birthDate' => $responseData['birth_date'],
                    'totalStamp' => $responseData['total_stamp'],
                    'totalSubPrize' => $vouchersData,
                    'celebrationStamp' => $responseData['celebration_stamp']
                ];

                if (isset($salesContactModel['salesNum']) || $salesContactModel['salesNum'] != '') {
                    $salesHeadModel = SalesHead::find()->where(['salesNum' => $salesContactModel['salesNum']])->one();
                    if ($salesHeadModel && $salesHeadModel['additionalInfo'] != $memberName) {
                        Yii::$app->db->createCommand()->update(
                            SalesHead::tableName(), 
                            ['additionalInfo' => $memberName], "salesNum = :salesNum", 
                            [':salesNum' => $salesContactModel['salesNum']]
                        )->execute();
                    }

                    if (isset($tableID) && $tableID != 0) {
                        return $memberData;
                    }
                    return $memberData;
                } else {
                    return $memberData;
                }
            } else if ($result->getStatusCode() == $response['statusCode']) {
                if ($response['message'] == 'Phone number not existed') {
                    $dataLogging = json_decode($result->getContent(), true);
                    if (isset($salesNum) && $salesNum != '-' && $salesNum != '') {
                        Logging::save($salesNum, Logging::LOG_CHECK_MEMBER, $dataLogging);
                    }

                    return (object) array(
                        'code' => $response['statusCode'],
                        'message' => $response['message']
                    );
                } else {
                    $dataLogging = [
                        'message' => 'Server unreachable',
                        'error' => $response['statusCode']
                    ];
                    if (isset($salesNum) && $salesNum != '-' && $salesNum != '') {
                        Logging::save($salesNum, Logging::LOG_CHECK_MEMBER, $dataLogging);
                    }

                    return (object) array(
                        'code' => 400,
                        'message' => 'Server unreachable'
                    );
                }
            } else {
                if (isset($salesNum) && $salesNum != '-' && $salesNum != '') {
                    $dataLogging = json_decode($result->getContent(), true);
                    Logging::save($salesNum, Logging::LOG_CHECK_MEMBER, $dataLogging);
                }

                return (object) array(
                    'code' => $response['statusCode'],
                    'message' => 'Server unreachable'
                );
            }
        } catch (\Exception $e) {
            Yii::error($e);
            return (object) array(
                'code' => 500,
                'message' => 'Server unreachable'
            );
        }
    }

    public static function saveLoggingValidateQs($salesContactModel, $getErrorValidateQs) {
        $salesNum = isset($salesContactModel['salesNum']) ? $salesContactModel['salesNum'] : '-';
        $dataLogging = [
            'message' => $getErrorValidateQs['message'],
            'error' => $getErrorValidateQs['error'] == 404 ? 'Not Found' : $getErrorValidateQs['error']
        ];

        Logging::save($salesNum, Logging::LOG_CHECK_MEMBER, $dataLogging);
    }

    private static function getPasswordSalt(){
        $posUser = PosUser::find()->where(['username' => Yii::$app->user->identity->username])->one();
        return [
            'username' => Yii::$app->user->identity->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public static function fetchUsablePointMemberid($memberCode, $subtotal)
    {
        try {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne(['branchID' => $branchID]);
            $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
            
            if(!$brandModel || !$branchModel) {
                throw new Exception("Brand or Branch", 1);
            }

            $memberIdBranchCode = Setting::getMemberIdBranchCode();
            if (strlen($memberIdBranchCode) == 0) {
                throw new Exception("Branch Code For External Integration not set", 1);
            }
            
            $externalMemberSetting = BrandSetting::getExternalMemberSetting();
            $apiUrl = $externalMemberSetting['Limit Point Usage URL'];
            $token = $externalMemberSetting[SELF::GET_STATIC_TOKEN];

            if (strlen($apiUrl) == 0 || strlen($token) == 0) {
                throw new Exception("The url or token has not been set in brand setting", 1);
            }

            $bodyRequest = [
                "memberCode" => (string)$memberCode,
                "subtotal" => (int)$subtotal,
                "outletCode" => $memberIdBranchCode
            ];

            $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
            $result = $client->post($externalMemberSetting['Limit Point Usage URL'])
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'mid-client-key' => $token,
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($bodyRequest)
                ->setOptions([
                    CURLOPT_FRESH_CONNECT => TRUE,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_TIMEOUT => 30
                ])
                ->send();

            $response = $result->getData();
           
            if ($result->getIsOk()) {
                if (isset($response['data']) && $response['data']) {
                    return $response['data'];
                } else {
                    throw new HttpException(400, $response['message'], 1);
                }
            } else {
                $responseMessage = isset($response['message']) ? $response['message'] : "Server Error";
                $errorCode = $responseMessage === "Member with that code is not found" ? 2 : 0;
                throw new HttpException(400, $responseMessage, $errorCode);
            }
        } catch (HttpException $httpEx) {
            throw new HttpException($httpEx->statusCode, $httpEx->getMessage(), $httpEx->getCode());
        }
    }
}
