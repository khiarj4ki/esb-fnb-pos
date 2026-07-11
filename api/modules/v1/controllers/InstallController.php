<?php
namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\models\BranchEvent;
use app\models\DepositWithdrawalDetail;
use app\models\DepositWithdrawalHead;
use app\models\forms\Installation;
use app\models\forms\Logging;
use app\models\forms\SyncFetch;
use app\models\forms\SyncPush;
use app\models\MemberDeposit;
use app\models\PosUser;
use app\models\SalesDepositWithdrawal;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPayment;
use app\models\SalesVoucher;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\ShiftLogDetail;
use app\models\TableUsage;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\httpclient\Client;
use yii\web\HttpException;

class InstallController extends BaseController {
    const API_VERSION = 'esb_apiv11';

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index', 'get-branch', 'run', 'check-installer', 'access-install'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();

        return $apiUrl == null && $apiKey == null && $branchID == null;
    }

    public function actionGetBranch() {

        $authDatas = base64_decode($this->request->post('auth'), true);
        $authData = explode('|', $authDatas);
        $username = $authData ? $authData[0] : null;
        $personalServer = $authData ? $authData[1] : null;
        $apiKey = $this->request->post('apiKey');
        $apiUrl = $personalServer ? $personalServer : Yii::$app->params['coreUrl'];

        if (!$apiUrl || !$apiKey || !$username) {
            throw new HttpException(400);
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $apiUrl . '/' . self::API_VERSION . '/main/get-branch-user';
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $options = ['timeOut' => 300];
        $datas =   ['username' => $username];
        $response = $httpService->post($url, $headers, $datas, $options);
                
        if ($response->getIsOk()) {
            return $response->getData();
        } else {
            switch ($response->getStatusCode()) {
                case '401':
                    throw new HttpException(500, 'Invalid API Key');
                case '404':
                    throw new HttpException(500, 'Invalid API URL');
                default:
                    throw new HttpException(500,
                    $response->getData() ? $response->getData()['message'] : 'Failed to fetch data');
            }
        }
    }

    public function actionRun() {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3000);

        $authDatas = base64_decode($this->request->post('auth'), true);
        $authData = explode('|', $authDatas);
        $userName = $authData ? $authData[0] : null;
        $personalServer = $authData ? $authData[1] : null;
        $branchID = $authData ? $authData[2] : null;
        $apiKey = $this->request->post('apiKey');

        $apiUrl = $personalServer ?
            $personalServer : Yii::$app->params['coreUrl'];
        
        try {
            $db = Yii::$app->db;
            $connectionArray = AppHelper::getConnectionArray();

            foreach ($connectionArray->connection as $name) {
                $db->close();
                $db->dsn = "mysql:host=$connectionArray->host;dbname=$name";
                $db->open();

                if (!Setting::saveLocalSetting('Api Url', $apiUrl, true)) {
                    throw new Exception('Failed to save api url');
                }
                if (!Setting::saveLocalSetting('Api Key', $apiKey, true)) {
                    throw new Exception('Failed to save api key');
                }
                if (!Setting::saveLocalSetting('Branch ID', $branchID, false)) {
                    throw new Exception('Failed to save branch id');
                }
    
                $db->createCommand()->truncateTable(BranchEvent::tableName())->execute();
                $db->createCommand()->truncateTable(DepositWithdrawalHead::tableName())->execute();
                $db->createCommand()->truncateTable(DepositWithdrawalDetail::tableName())->execute();
                $db->createCommand()->truncateTable(MemberDeposit::tableName())->execute();
                $db->createCommand()->truncateTable(SalesDepositWithdrawal::tableName())->execute();
                $db->createCommand()->truncateTable(SalesHead::tableName())->execute();
                $db->createCommand()->truncateTable(SalesLink::tableName())->execute();
                $db->createCommand()->truncateTable(SalesMenu::tableName())->execute();
                $db->createCommand()->truncateTable(SalesMenuCompletion::tableName())->execute();
                $db->createCommand()->truncateTable(SalesMenuExtra::tableName())->execute();
                $db->createCommand()->truncateTable(SalesMergeTable::tableName())->execute();
                $db->createCommand()->truncateTable(SalesPayment::tableName())->execute();
                $db->createCommand()->truncateTable(SalesVoucher::tableName())->execute();
                $db->createCommand()->truncateTable(ShiftLog::tableName())->execute();
                $db->createCommand()->truncateTable(ShiftLogDetail::tableName())->execute();
                $db->createCommand()->truncateTable(TableUsage::tableName())->execute();
    
                $fetchModel = new SyncFetch();
                $fetchModel->syncType = SyncFetch::FETCH_MASTER_SETTINGS;
                if (!$fetchModel->doSync()) {
                    throw new \Exception('Failed to sync master settings');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_BRANCH_SETTINGS;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync branch settings');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_MEMBER;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync member');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_MENU;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync menu');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_PROMOTION;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync promotion');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_TABLE;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync table');
                }
    
                $fetchModel->syncType = SyncFetch::FETCH_USER;
                if (!$fetchModel->doSync()) {
                    throw new Exception('Failed to sync user');
                }
                
                $fetchModel->syncType = SyncFetch::FETCH_SALES;
                if (!strpos($name, "_trial")) {
                    if (!$fetchModel->doSync()) {
                        throw new Exception('Failed to sync sales');
                    }
                }

                if (Setting::find()->where(['key1' => 'Local Setting'])
                    ->andWhere(['LIKE','key2','Print'])->exists()) {
                    Setting::updateAll(['value1' => '0'], 
                        ['and',
                            ['=', 'key1', 'Local Setting'],
                            ['LIKE', 'key2', 'Print'],
                        ]);
                }
    
                $pushModel = new SyncPush();
                $pushModel->syncType = SyncPush::PUSH_POS_VERSION;
                if (!$pushModel->doSync()) {
                    Yii::error('Failed to push POS version');
                }
            }

            $modelAttr = [
                'username' => $userName,
                'branchID' => $branchID,
                'apiUrl' => $apiUrl
            ];

            Logging::save('-', Logging::POS_INSTALLATION,  $modelAttr);

            return true;
        } catch (Exception $ex) {
            Setting::deleteAll(['AND',
                    ['key1' => 'Local Setting'],
                    ['IN', 'key2', ['Api Url', 'Api Key', 'Branch ID']],
            ]);
            Yii::warning($ex);
            return false;
        }
    }

    public function actionCheckInstaller() {
        $db = Yii::$app->db;
        $connectionArray = AppHelper::getConnectionArray();
        
        $statusArray = [];
        foreach ($connectionArray->connection as $name) {
            $db->close();
            $db->dsn = "mysql:host=$connectionArray->host;dbname=$name";
            $db->open();
            
            $apiUrl = Setting::getApiUrl();
            $apiKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();
            
            $status = true;
            if ($apiUrl == null && $apiKey == null && ($branchID == null || $branchID == '')) $status = false;
            $statusArray[] = $status;
        }

        return in_array('false', $statusArray) ? true : false;
    }

    public function actionAccessInstall() {

        try {

            $authDatas = base64_decode($this->request->post('auth'), true);
            $authData = explode('|', $authDatas);
            $username = $authData ? $authData[0] : null;
            $password = $authData ? $authData[1] : null;
            $personalServer = $authData ? $authData[2] : null;

            if (!$username || !$password) {
                throw new HttpException(400);
            }

            $model = new Installation();
            $model->username = $username;
            $model->password = $password;
            $model->personalServer = $personalServer;
            $model->checkAccessInstallation();
            return  $model->responseData;

        } catch (Exception $ex) {
            return false;
        }
    }
}
