<?php
namespace app\models\forms;

use app\models\Branch;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * @property string $token
 */
class FCM extends Model {
    public $token;
    private $branchModel;
    private $apiEndPoint = 'https://iid.googleapis.com/iid/v1/';
    private $url;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['token'], 'required'],
            [['token'], 'getBranch']
        ];
    }

    public function getBranch($attribute) {
        if (!$this->branchModel) {
            $branchID = Setting::getCurrentBranch();
            $this->branchModel = Branch::find()
                ->andWhere(['branchID' => $branchID])
                ->one();
        }

        if (!$this->branchModel) {
            $this->addError($attribute, 'Failed to get current branch');
        } else {
            $this->url = $this->apiEndPoint . $this->token . '/rel/topics/' . $this->branchModel->branchID . $this->branchModel->branchCode;
        }
    }

    public function subscribe() {
        if (!$this->validate()) {
            return false;
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->url;
        $headers = [
           'Authorization' => 'key=' . Yii::$app->params['firebaseKey']
        ];
        $datas = [];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getIsOk();
    }

    public function unsubscribe() {
        if (!$this->validate()) {
            return false;
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->url;
        $headers = [
           'Authorization' => 'key=' . Yii::$app->params['firebaseKey']
        ];
        $datas = [];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getIsOk();
    }

}
