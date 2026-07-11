<?php
namespace app\models\forms;

use app\models\PosUser;
use app\models\Setting;
use app\models\ShiftLog;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\db\Exception;

class OnlineFund extends Model {
    public $apiUrl;
    public $branchID;
    public $page;
    public $limit;
    public $paymentMethod;
    public $status;
    public $reference;
    public $action;
    
    public function rules() {
        return [
            [['page', 'limit', 'reference', 'paymentMethod', 'status', 'action'], 'safe'],
        ];
    }

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    private function getHttpClient($method, $action, $datas=null, $paramsFilter=null) {
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/online-fund/' . $action . $paramsFilter;
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $options = ['timeOut' => 300];

        if ($method == "get") {
            $response = $httpService->get($url, $headers, $options);
        } else if ($method == "post") {
            $response = $httpService->post($url, $headers, $datas, $options);
        }

        return $response->getData();
    }

    private function getPasswordSalt(){
        $posUser = PosUser::find()->where(['username' => Yii::$app->user->identity->username])->one();
        return [
            'username' => Yii::$app->user->identity->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public function getPaymentHistory()
    {
        try {
            $getAction = "get-payment-history";
            $shiftInDate = ShiftLog::getShiftInDate();
            $this->paymentMethod = implode(',', $this->paymentMethod);
            $this->status = implode(',', $this->status);
            $paramsFilterOpt = null;
            if ($this->paymentMethod) {
                $paramsFilterOpt .= '&paymentMethod=' . $this->paymentMethod;
            }
            if ($this->status) {
                $paramsFilterOpt .= '&status=' . $this->status;
            }
            if ($this->reference) {
                $paramsFilterOpt .= '&reference=' . $this->reference;
            }
            $paramsFilter = '?branchid=' . $this->branchID . '&date=' . $shiftInDate . '&page=' . $this->page . '&limit=' . $this->limit . $paramsFilterOpt;

            return $this->getHttpClient("get", $getAction, null, $paramsFilter);
        } catch (Exception $ex) {
            Yii::error($ex);
            $errMsg = $ex->getMessage();
            return [
                'status' => false,
                'message' => $errMsg
            ];
        }
    }

    public function getPaymentMethodDropdownList()
    {
        try {
            $getAction = "get-payment-method-dropdown-list";
            $paramsFilter = '?branchid=' . $this->branchID;

            return $this->getHttpClient("get", $getAction, null, $paramsFilter);
        } catch (Exception $ex) {
            Yii::error($ex);
            $errMsg = $ex->getMessage();
            return [
                'status' => false,
                'message' => $errMsg
            ];
        }
    }

    public function saveLog()
    {
        $dataLog = [
            'username' => Yii::$app->user->identity->username,
            'date' => date("Y-m-d H:i:s"),
            'action' => $this->action
        ];

        Logging::save('-', Logging::ONLINE_PAYMENT_TUTOR, $dataLog);

        return true;
    }
}