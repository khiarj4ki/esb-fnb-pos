<?php
namespace app\models\forms;

use app\models\Branch;
use app\models\Brand;
use app\models\PosUser;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * @property phoneNumber $phoneNumber
 */
class Steroid extends Model {
    CONST STEROID_FIND_CUSTOMER_API_URL = '/erp/steroid/find';

    public $apiUrl;
    public $branchID;
    public $phoneNumber;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    public function rules() {
        return [
            [['phoneNumber'], 'required'],
        ];
    }

    public function fetchMemberInfo()
    {
        if (!$this->validate()) {
            return false;
        }

        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne(['branchID' => $this->branchID]);
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $this->branchID])
            ->one();

        if (!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }

        try
        {
            $result = null;

            $phone = null;
            $searchValue = strval($this->phoneNumber);
            if (substr($searchValue, 0, 1) === '0') {
                $phone = substr($searchValue, 1);
            } elseif (substr($searchValue, 0, 2) === '62') {
                $phone = substr($searchValue, 2);
            } elseif (substr($searchValue, 0, 3) === '+62') {
                $phone = substr($searchValue, 3);
            } else {
                $phone = $searchValue;
            }
            $apiRequest = '?phoneNumber=' . $phone . '&companyCode=' . $branchModel->companyCode . '&brandID=' . $brandModel->brandID . '&branchID=' . $branchID;
            $apiUrl = $this->apiUrl . SELF::STEROID_FIND_CUSTOMER_API_URL . $apiRequest;

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = [
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ];
            $options = ['timeOut' => 300];
            $request = $httpService->get($url, $headers, $options);

            $response = $request->getData();
            if (isset($response['status']) && $response['status'] == 'OK') {
                $data = $response['result'];
                $result['recommendationMenu'] = $data['recommendationMenu'];
            } else {
                $result = $result['message'];
            }

            return $result;
        } catch (\Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    private function getPasswordSalt()
    {
        $posUser = PosUser::find()->where(['username' => Yii::$app->user->identity->username])->one();
        return [
            'username' => Yii::$app->user->identity->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }
}
