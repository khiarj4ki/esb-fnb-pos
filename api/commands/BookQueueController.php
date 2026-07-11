<?php

namespace app\commands;

use app\models\PosUser;
use app\models\SalesHead;
use app\models\Setting;
use app\models\TrBookQueue;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\console\Controller;
use yii\httpclient\Client;

class BookQueueController extends Controller {

    // @notes: argument at command line
    public $username;

    public function options($actionID)
    {
        // @notes: consume argument at command
        return array_merge(parent::options($actionID), [
            'username'
        ]);
    }

    public function actionRun() {
        $count = TrBookQueue::find()->count();
        $sleepTime = 3;

        while ($count > 0) {
            try {
                file_put_contents(Yii::$app->basePath . '/' . Yii::$app->params['bookQueueLogFile'], microtime(true));
                $bookQueueModel = TrBookQueue::find()->orderBy(['salesNum' => SORT_ASC])->all();
                foreach ($bookQueueModel as $data) {
                    if ($data->actionType === 'Open Table') {
                        $action = 'open';
                    } else if ($data->actionType === 'Cancel Table') {
                        $action = 'cancel';
                    } else if ($data->actionType === 'Finish Table') {
                        $action = 'finish';
                    } else {
                        $action = 'error';
                    }

                    $salesHead = SalesHead::find()->where(['salesNum' => $data->salesNum])->one();
                    if ($salesHead) {
                        $result = $this->updateStatusToCloud($salesHead->bookNum, $data->salesNum, $action);
                        if ($result->getIsOk()) {
                            $data->delete();
                        } else {
                            throw new Exception($result->getData()['message']);
                        }
                    } else {
                        $data->delete();
                    }
                }

                $count = TrBookQueue::find()->count();
            } catch (Exception $ex) {
                $count = TrBookQueue::find()->count();
                sleep($sleepTime);
            }
        }
    }

    private function updateStatusToCloud($bookNum, $salesNum, $action)
    {
        $statusID = 35;
        if ($action == 'finish') {
            $statusID = 8;
        } else if ($action == 'cancel') {
            $statusID = 12;
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $url = $this->apiUrl . '/erp/esb-book/put-update-status/' . $action;
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
            'data-auth-username' =>  $this->getPasswordSalt()['username'],
            'data-auth-password' =>  $this->getPasswordSalt()['password'],
            'data-auth-salt' =>  $this->getPasswordSalt()['salt']
        ];
        $datas =   [
            'bookNum' => $bookNum,
            'salesNum' => $salesNum,
            'statusID' => $statusID,
            'action' => $action,
            'reason' => '',
        ];
        $options = ['timeOut' => 300];

        return $httpService->post($url, $headers, $datas, $options);

    }

    private function getPasswordSalt(){
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

}
