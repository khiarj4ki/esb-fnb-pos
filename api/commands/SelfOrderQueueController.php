<?php

namespace app\commands;

use app\components\AppHelper;
use app\models\EsoProcessQueue;
use app\models\QueueSelfOrder;
use app\models\SalesHead;
use Exception;
use Yii;
use yii\console\Controller;

class SelfOrderQueueController extends Controller
{

    public function actionRun()
    {
        $count = QueueSelfOrder::find()->count();
        while ($count > 0) {
            try {
                file_put_contents(Yii::$app->basePath . '/' . Yii::$app->params['selfOrderQueueLogFile'], microtime(true));
                sleep(1);
                $queueModel = QueueSelfOrder::find()->all();
                foreach ($queueModel as $data) {
                    $salesNum = $data->salesNum;
                    $orderID = $data->orderID;
                    $salesModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
                    if ($salesModel) {
                        $salesVoid = $data->type == 'VOID';
                        if ($salesVoid) {
                            $response = AppHelper::notifSelfOrderVoidApi($salesModel, $orderID);
                        } else {
                            $response = AppHelper::notifSelfOrderApi($salesModel, $orderID);
                        }
                        if ($response->getIsOk()) {
                            $content = json_decode($response->getContent(), true);
                            if ($content && $content['status'] == '00') {
                                $data->delete();
                            }
                        } else {
                            sleep(3);
                        }
                    } else {
                        $data->delete();
                    }
                }
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
            $count = QueueSelfOrder::find()->count();
        }
    }
}
