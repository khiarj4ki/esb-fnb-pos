<?php

namespace app\models\forms;

use app\models\MsEmployee;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use yii\httpclient\Exception;
use yii\web\NotFoundHttpException;

/**
 * @property string $employeeCode * 
 * @property string $paymentMethodID 
 * @property float $amount
 * 
 */
class Employee extends Model {
    const SCENARIO_VALIDATE = 'VALIDATE';
    const SCENARIO_GET_BALANCE = 'GET_BALANCE';
    const SCENARIO_USE_BALANCE = 'USE_BALANCE';

    public $employeeCode;
    public $salesNum;
    public $paymentMethodID;
    public $amount;
    public $employeeType;
    
    public $apiKey;
    public $apiUrl;
    public $branchID;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiKey = Setting::getApiKey();
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['employeeCode'], 'required', 'on' => [self::SCENARIO_VALIDATE, self::SCENARIO_GET_BALANCE, self::SCENARIO_USE_BALANCE]],
            [['salesNum', 'paymentMethodID', 'amount'], 'required', 'on' => [self::SCENARIO_USE_BALANCE]],
            [['branchID'], 'required', 'on' => [self::SCENARIO_GET_BALANCE, self::SCENARIO_USE_BALANCE]],
            [['salesNum'], 'safe', 'on' => [self::SCENARIO_GET_BALANCE]],
            [['amount'], 'number'],
            [['employeeType'], 'safe']
        ];
    }

    private function getHttpClient($action) {

        $folderPathUrl = "/esb_api/employee/";
        if($this->employeeType === 'External') {
            $folderPathUrl = "/esb_api/external-employee/";
        }

        $client = new Client();
        return $client->post($this->apiUrl . $folderPathUrl . $action)
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey
        ]);
    }

    public function validateEmployeeCheck() {
        $result = false;
        $this->employeeCode = base64_decode($this->employeeCode, true);
        if ($this->employeeType === 'External') {
            $result =  $this->validateExternalEmployee();
        } else {
            $result = $this->validateEmployee();
        }

        return $result;
    }

    public function validateEmployee() {
        if (!$this->validate()) {
            return false;
        }
        try {
            
            $folderPathUrl = "/esb_api/employee/";
            if($this->employeeType === 'External') {
                $folderPathUrl = "/esb_api/external-employee/";
            }
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . $folderPathUrl . 'validate';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'branchID' => $this->branchID,
                'employeeCode' => $this->employeeCode
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getData()['status'] == '00') {
                $employeeModel = MsEmployee::findEmployeeActive($this->employeeCode);
                if ($employeeModel) {
                    $result = $employeeModel;
                } else {
                    $result = [];
                    throw new NotFoundHttpException();
                }
            } else {
                $result = [];
                throw new NotFoundHttpException();
            }
            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public function validateExternalEmployee() {
        if (!$this->validate()) {
            return false;
        }
        try {
            $folderPathUrl = "/esb_api/employee/";
            if($this->employeeType === 'External') {
                $folderPathUrl = "/esb_api/external-employee/";
            }
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . $folderPathUrl . 'validate';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'branchID' => $this->branchID,
                'employeeCode' => $this->employeeCode
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            $responseData = $response->getData();
            if ($responseData['status'] == 200 ) {
                $result = $responseData['result'];
            } else {
                $result = [];
                throw new NotFoundHttpException();
            }
            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public function getBalance() {
        if (!$this->validate()) {
            return false;
        }
        try {
            $datas = [
                'branchID' => $this->branchID,
                'employeeCode' => $this->employeeCode
            ];
            if (strlen($this->salesNum) > 0) {
                $datas['salesNum'] = $this->salesNum;
            }

            $folderPathUrl = "/esb_api/employee/";
            if($this->employeeType === 'External') {
                $folderPathUrl = "/esb_api/external-employee/";
            }
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . $folderPathUrl . 'get-balance';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getIsOk()) {
                if ($response->getData()['status'] == '00') {
                    $result = $response->getData()['result'];
                } else {
                    $result = null;
                }
            } else {
                throw new Exception("Server unreachable", $response->getStatusCode());
            }
            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public function useBalance() {
        if (!$this->validate()) {
            return false;
        }
        try {
                   
            $folderPathUrl = "/esb_api/employee/";
            if($this->employeeType === 'External') {
                $folderPathUrl = "/esb_api/external-employee/";
            }
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . $folderPathUrl . 'use-balance';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas = [
                'branchID' => $this->branchID,
                'employeeCode' => $this->employeeCode,
                'salesNum' => $this->salesNum,
                'paymentMethodID' => $this->paymentMethodID,
                'amount' => $this->amount
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getData()['status'] == '00') {
                $result = $response->getData()['result'];
            } else {
                $result = null;
            }
            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public static function getDataEmployee($employeeCode) {
        return MsEmployee::find()
            ->where(['employeeCode' => $employeeCode])
            ->andWhere(['flagActive' => 1])
            ->one();
    }

}
