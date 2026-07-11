<?php
namespace app\models\forms;

use app\models\PosUser;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMergeTable;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use app\models\Setting;
use app\models\TrBookQueue;
use app\services\http_helper\HttpHelperService;

class BookingList extends Model {

    public $apiUrl;
    public $branchID;
    public $username;
    public $bookDateStart;
    public $bookDateEnd;
    public $bookNum;
    public $salesNum;
    public $statusID;
    public $tableID;
    public $paxTotal;
    public $visitPurposeID;
    public $visitorTypeID;
    public $bookTable;
    public $bookDate;
    public $action;
    public $actionType;

    public function rules() {
        return [
            [['branchID', 'username', 'bookDateStart', 'bookDateEnd',
            'paxTotal', 'visitPurposeID', 'visitorTypeID', 'bookTable', 'bookDate',
            'bookNum', 'salesNum', 'statusID', 'tableID', 'action', 'actionType'], 'safe'],
        ];
    }

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    private function getPasswordSalt(){
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public function getBookingList() {
    
         // @refactor http_helper
         $httpService = new HttpHelperService();
         $authUsername = Yii::$app->params['restUsername'];
         $authPassword = Yii::$app->params['restPassword'];
         $url = $this->apiUrl . '/erp/esb-book/get-booking-list';
         $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
         $datas =   [
            'branchID' => $this->branchID,
            'bookDateStart' => $this->bookDateStart,
            'bookDateEnd'=> $this->bookDateEnd,
            'statusID' => $this->statusID
        ];
         $options = ['timeOut' => 300];
         $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getData();
    }

    public function getBookingOne() {

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/esb-book/get-booking-list';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'branchID' => $this->branchID,
            'salesNum' => $this->salesNum
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getData();
    }

    public function getBookingInfo() {
     
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/esb-book/get-booking-info';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'branchID' => $this->branchID,
            'bookDate' => $this->bookDate,
            'tableID' => $this->tableID
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getData();
    }

    public function updateStatus()
    {

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/esb-book/put-update-status';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'bookNum' => $this->bookNum,
            'salesNum' => $this->salesNum,
            'statusID' => $this->statusID,
            'action' => $this->action,
            'reason' => ''
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getData();
    }

    public function updateData()
    {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/esb-book/put-update-status';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'bookNum' => $this->bookNum,
            'salesNum' => $this->salesNum,
            'statusID' => $this->statusID,
            'paxTotal' => $this->paxTotal,
            'visitPurposeID' => $this->visitPurposeID,
            'visitorTypeID' => $this->visitorTypeID,
            'bookTable' => $this->bookTable,
            'action' => $this->action,
            'reason' => ''
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return $response->getData();
    }

    public function checkStatusTable()
    {
        $salesModel = SalesHead::find()
            ->where(['IN', 'tableID', $this->tableID])
            ->andWhere(['IS NOT', 'salesDateIn', NULL])
            ->andWhere(['IS', 'salesDateOut', NULL])
            ->all();

        if (!$salesModel) {
            $salesModel = SalesMergeTable::find()
                                ->select([
                                    'tr_saleshead.salesNum'
                                ])
                                ->joinWith('salesHead')
                                ->andWhere(['IS NOT', 'tr_saleshead.salesDateIn', NULL])
                                ->andWhere(['IS', 'tr_saleshead.salesDateOut', NULL])
                                ->andWhere(['IN', 'tr_salesmergetable.tableID', $this->tableID])
                                ->all();
        }
        return $salesModel;
    }
    
    public function insertBookQueue()
    {

        $currentBookQueueCount = TrBookQueue::find()->count();
        $checkBookQueue = TrBookQueue::findOne(['salesNum' => $this->salesNum, 'actionType' => $this->actionType]);
        if (!$checkBookQueue) {
            $bookQueueModel = new TrBookQueue();
            $bookQueueModel->salesNum = $this->salesNum;
            $bookQueueModel->actionType = $this->actionType;
            if (!$bookQueueModel->save()) {
                Yii::warning($bookQueueModel->errors());
            }
        }

        $bookQueueLogFileLocation = Yii::$app->basePath . '/' . Yii::$app->params['bookQueueLogFile'];
        $fileValue = file_exists($bookQueueLogFileLocation) ? file_get_contents($bookQueueLogFileLocation) : 0;
        $lastBookQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);
        if ($currentBookQueueCount == 0 || (microtime(true) - $lastBookQueueRunTime > 60)) {
            $yiiLocation = Yii::$app->basePath . '/yii';
            // @notes: set username login pos as argument
            $runBookQueueAction = 'book-queue/run --username='.$this->username;

            if (substr(php_uname(), 0, 3) == "Win") {
                pclose(popen("start /B php $yiiLocation $runBookQueueAction ", "r"));
            } else {
                shell_exec("php $yiiLocation $runBookQueueAction > /dev/null 2>/dev/null &");
            }
        }
    }

    public function getSalesLinks()
    {
        $linkSalesNums = SalesLink::find()
            ->select('linkSalesNum')
            ->andWhere(['salesNum' => $this->salesNum])
            ->column();

        $salesNums = array_merge([$this->salesNum], $linkSalesNums);

        return $salesNums;
    }

}