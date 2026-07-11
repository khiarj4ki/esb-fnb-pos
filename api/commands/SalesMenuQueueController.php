<?php

namespace app\commands;

use app\models\SalesMenuQueue;
use app\models\Setting;
use app\models\forms\SyncPush;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\console\Controller;
use yii\httpclient\Client;

class SalesMenuQueueController extends Controller {

    public function actionRun() {
        $queueFileFolder = isset(Yii::$app->params['salesMenuQueueLogFile']) ? Yii::$app->params['salesMenuQueueLogFile'] : 'web/salesmenuqueue.log';
        $count = SalesMenuQueue::find()->count();
        $sleepTime = 3;

        while ($count > 0) {
            try {
                file_put_contents(Yii::$app->basePath . '/' . $queueFileFolder, microtime(true));
                $queueModel = SalesMenuQueue::find()->orderBy(['ID' => SORT_ASC])->all();
                foreach ($queueModel as $data) {
                    $apiKey = Setting::getApiKey();
                    $apiUrl = Setting::getApiUrl();
                    $branchID = Setting::getCurrentBranch();
                    $salesNum = $data->salesNum;
                    $salesMenu = $data->salesMenu;
                    if ($salesMenu){
                        // @refactor http_helper
                        $httpService = new HttpHelperService();
                        $url = $apiUrl . '/esb_api/pull/pull-sales-menu';
                        $headers = ['Authorization' => 'Bearer ' . $apiKey];
                        $datas =   [
                            'salesMenu' => $salesMenu
                        ];
                        $options = ['timeOut' => 300];
                        $result = $httpService->post($url, $headers, $datas, $options);
                        if (isset($result->getData()['status']) && $result->getData()['status'] == '00') {
                            $data->delete();
                        } else {
                            if (isset($result->getData()['error']) && $result->getData()['error'] == 'Sales Head Not Exist') {
                                $pushModel = new SyncPush();
                                $pushModel->syncType = 'pushSales';
                                $pushModel->doSync();
                            } else {
                                $data->delete();
                                if (isset($result->getData()['error'])) {
                                    throw new Exception($result->getData()['error']);
                                } else {
                                    throw new Exception($result->getStatusCode() . ' Unknown Errors');
                                }
                            } 
                        }
                    } else {
                        $data->delete();
                    }
                }

                $count = SalesMenuQueue::find()->count();
            } catch (Exception $ex) {
                $count = SalesMenuQueue::find()->count();
                sleep($sleepTime);
            }
        }
    }

}
