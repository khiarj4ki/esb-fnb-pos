<?php
namespace app\models\forms;

use app\models\PosUser;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use yii\web\HttpException;

class Installation extends Model {

    public $apiUrl;
    public $username;
    public $password;
    public $responseData;
    public $personalServer;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
    }

    public function rules() {
        return [
            [['apiUrl','username','password','responseData','personalServer'], 'safe']
        ];
    }

    public function checkAccessInstallation()
    {
        try {
            
            $apiUrl = $this->personalServer ? $this->personalServer : Yii::$app->params['coreUrl'];
            if (!$apiUrl) {
                throw new HttpException(400);
            }
    
            // @refactor http_helper
            $authUsername = Yii::$app->params['restUsername'];
            $authPassword = Yii::$app->params['restPassword'];
             $httpService = new HttpHelperService();
             $url = $apiUrl . '/erp/pos-hybrid/access-pos-install';
             $headers = ['Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),];
             $requestBody = [
                'username' => $this->username,
                'password' => $this->password
             ];
             $options = ['timeOut' => 300];
             $response = $httpService->post($url, $headers, $requestBody, $options);
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

    public function getHttpClient($personalServer, $action)
    {
        $apiUrl = $personalServer ?
                $personalServer : Yii::$app->params['coreUrl'];

        if (!$apiUrl) {
            throw new HttpException(400);
        }

        $client = new Client();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        
        return $client->post($apiUrl . '/erp/pos-hybrid' . $action)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            ]);
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

    public static function transformExceptionMessage($exception, &$errorMessage, &$translate = 0) 
    {
        $exMessage = $exception->getMessage();
        if (strpos($exMessage, 'Curl error: #28 - Operation timed out') !== false) {
            $errorMessage = "Operation timed out, please try again";
        } elseif (strpos($exMessage, 'fopen') !== false || strpos($exMessage, 'Curl error: #6') !== false) {
            $errorMessage = "Please try again after checking your internet connection";
            $translate = 1;
        } else {
            $errorMessage = $exMessage;
        }
    }
}
