<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\PosUser;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\web\HttpException;

class MemberDepositWithdrawalOnline extends Model
{
    public $apiUrl;
    public $username;
    public $action;
    public $memberCode;
    public $paymentMethodID;
    public $additionalInfo;
    public $memberDepositNum;
    public $depositTotal;
    public $depositWithdrawalNum;
    public $withdrawalTotal;
    public $responseData;
    public $salesNum;

    public function rules()
    {
        return [
            [[
                'username', 'action', 'memberCode', 'paymentMethodID', 'additionalInfo',
                'memberDepositNum', 'depositTotal', 'depositWithdrawalNum', 'withdrawalTotal',
                'salesNum'
            ], 'safe'],
        ];
    }

    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
        $this->username = Yii::$app->user->identity->username;
    }

    public function getMember()
    {
        $memberMode = Setting::getSetting('POS', 'Member Mode');
        if ($memberMode) {
            if ($memberMode->value1 == "offline") {
                throw new HttpException(400, "Invalid setting. current member mode: offline");
            } else {
                try {
                    //@check sales burn deposit
                    $modelBalanceMemberDeposit = new MemberDepositWithdrawalOnline();
                    $memberDepositBurn = $modelBalanceMemberDeposit->checkBalanceDepositMember($this->salesNum, $this->memberCode);
                    if($memberDepositBurn['status']) {
                        $valueBurn = isset($memberDepositBurn['data']) ? $memberDepositBurn['data']['paymentTotal'] : 0;
                    }

                    // @refactor http_helper
                    $httpService = new HttpHelperService();
                    $url = $this->apiUrl . '/erp/member/?memberCode=' . $this->memberCode;
                    $authUsername = Yii::$app->params['restUsername'];
                    $authPassword = Yii::$app->params['restPassword'];
                    $headers = [
                        'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                        'data-auth-username' =>  $this->getPasswordSalt()['username'],
                        'data-auth-password' =>  $this->getPasswordSalt()['password'],
                        'data-auth-salt' =>  $this->getPasswordSalt()['salt']
                    ];
                    $datas = [];
                    $options = ['timeOut' => 300];
                    $response = $httpService->post($url, $headers, $datas, $options);

                    $responseData = $response->getData();
                    if ($response->getIsOk()) {
                        return [
                            "memberCode" => $responseData["memberCode"],
                            "memberName" => $responseData["memberName"],
                            "deposit" => $responseData["balance"],
                            "depositBurn" => isset($valueBurn) ?  floatval($valueBurn) : 0,
                            "activeBalance" => $responseData["activeBalance"]
                        ];
                    } else {
                        throw new HttpException(($responseData["code"] >= 500) ? 500 : $responseData["code"], $responseData["message"]);
                    }
                } catch (HttpException $httpEx) {
                    throw new HttpException(($httpEx->statusCode) ? $httpEx->statusCode : 500, $httpEx->getMessage());
                } catch (Exception $ex) {
                    $errorMessage = "";
                    $translate = 0;
                    self::transformExceptionMessage($ex, $errorMessage, $translate);
                    throw new HttpException(500, $errorMessage, $translate);
                }
            }
        }
    }

    public function sendDeposit($branchID)
    {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/erp/member/deposit';
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'memberCode' => $this->memberCode,
            'memberDepositNum' => $this->memberDepositNum,
            'branchID' => $branchID,
            'paymentMethodID' => $this->paymentMethodID,
            'depositTotal' => $this->depositTotal,
            'additionalInfo' => ($this->additionalInfo == null) ? '' : $this->additionalInfo
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);
  
        $this->responseData = $response->getData();
        if ($response->getIsOk()) {
            return true;
        } else {
            $this->addError($this->responseData["message"]);
            return false;
        }
    }

    public function sendWithDrawal($branchID)
    {

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/erp/member/withdrawal';
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'memberCode' => $this->memberCode,
            'depositWithdrawalNum' => $this->depositWithdrawalNum,
            'branchID' => $branchID,
            'paymentMethodID' => $this->paymentMethodID,
            'withdrawalTotal' => $this->withdrawalTotal,
            'additionalInfo' => ($this->additionalInfo == null) ? '' : AppHelper::checkSpecialChar($this->additionalInfo)
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        $this->responseData = $response->getData();
        if ($response->getIsOk()) {
            return true;
        } else {
            $this->addError($this->responseData["message"]);
            return false;
        }
    }

    public function getAllOutstandingDeposit(){
        $data = [];
        try {
            $memberMode = Setting::getSetting('POS', 'Member Mode');
            if ($memberMode && $memberMode->value1 == "online") {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/erp/member/all-outstanding-deposit';
                $authUsername = Yii::$app->params['restUsername'];
                $authPassword = Yii::$app->params['restPassword'];
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                    'data-auth-username' =>  $this->getPasswordSalt()['username'],
                    'data-auth-password' =>  $this->getPasswordSalt()['password'],
                    'data-auth-salt' =>  $this->getPasswordSalt()['salt']
                ];
                $datas = [];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);
                
                if ($response->getIsOk()) {
                    $data = $response->getData();
                } else {
                    throw new Exception($response->getStatusCode() . " - " . $response->getData()["message"]);
                }
            }
        } catch (Exception $ex) {
            Yii::error($ex);
        }
        return $data;
    }

    public function sendSalesDeposit($salesNum, $memberCode, $total)
    {
        try {

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/erp/member/sales';
            $authUsername = Yii::$app->params['restUsername'];
            $authPassword = Yii::$app->params['restPassword'];
            $headers = [
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ];
            $datas = [
                'memberCode' => $memberCode,
                'salesNum' => $salesNum,
                'total' => $total
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
    
            $this->responseData = $response->getData();
            if ($response->getIsOk()) {
                return true;
            } else {
                $this->addError($this->responseData["message"]);
                return false;
            }
        } catch (Exception $ex) {
            $errorMessage = "";
            self::transformExceptionMessage($ex, $errorMessage);
            $this->responseData["message"] = $errorMessage;
            return false;
        }
    }

    public function getHttpClient($action)
    {
        $client = new Client();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        return $client->post($this->apiUrl . '/erp/member' . $action)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ]);
    }

    private function getPasswordSalt()
    {
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public static function apiError($errorCode = 500, $errorMsg = 'Internal Server Error', $errorData = null)
    {
        $errorCode = $errorCode <= 100 || $errorCode >= 600 ? 500 : $errorCode;
        $errorResponse = [
            'path' => Yii::$app->request->absoluteUrl,
            'code' => $errorCode,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $errorMsg
        ];
        if ($errorData != null) {
            $errorResponse['data'] = $errorData;
        }

        Yii::$app->response->statusCode = $errorCode;
        return $errorResponse;
    }
    
    public function checkBalanceDepositMember($salesNum = null, $memberCode = null)
    {

        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/erp/member/balance-deposit';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'salesNum' => $salesNum,
            'memberCode' => $memberCode
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);
  
        $this->responseData = $response->getData();
        if ($this->responseData['status']) {
            return $this->responseData;
        } else {
            $this->addError($this->responseData['data']);
            return $this->responseData;
        }
    }

    public static function getActiveMemberBalance($memberCode, $availableDepositTotal = 0) {
        try {
            $memberMode = Setting::getSetting('POS', 'Member Mode');
            if ($memberMode && $memberMode->value1 == "online") {
                $memberOnlineModel = new MemberDepositWithdrawalOnline();
                $memberOnlineModel->memberCode = $memberCode;
                $res = $memberOnlineModel->getMember();
                if ($res && isset($res['activeBalance'])) {
                    return $res['activeBalance'];
                } else {
                    throw new Exception("Cannot get active balance");
                }
            }
            return $availableDepositTotal;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return $availableDepositTotal;
        }
    }

    public static function transformExceptionMessage($exception, &$errorMessage, &$translate = 0) 
    {
        $exMessage = $exception->getMessage();
        if (strpos($exMessage, 'Curl error: #28 - Operation timed out') !== false) {
            $errorMessage = "Operation timed out, please try again";
        } else if (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false) {
            $errorMessage = "Please try again after checking your internet connection";
            $translate = 1;
        } else {
            $errorMessage = $exMessage;
        }
    }
}
