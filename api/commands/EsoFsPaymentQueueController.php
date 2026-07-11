<?php

namespace app\commands;

use app\models\EsoFSPaymentQueue;
use app\models\forms\SelfOrderTakeAway;
use app\models\forms\Logging;
use app\models\PosUser;
use yii\console\Controller;
use Yii;

class EsoFsPaymentQueueController extends Controller
{
    public function actionRun()
    {
        $user = new PosUser();
        $user->username = 'SYSTEM';
        Yii::$app->user->setIdentity($user);
        
        $count = EsoFSPaymentQueue::getCount();

        while ($count > 0) {
            // Log the start time of the process
            $esoQueueModels = EsoFSPaymentQueue::findPendingData();

            if ($esoQueueModels) {
                // Fetch all records with status 'PENDING'
                file_put_contents(\Yii::$app->basePath . '/' . \Yii::$app->params['esoFsProcessQueueLogFile'], microtime(true));
                sleep(1);
                foreach ($esoQueueModels as $data) {
                    try {

                        if (EsoFSPaymentQueue::checkExistingOrderId($data->orderID)) {                       
                            EsoFSPaymentQueue::updateAll(
                                ['status' => EsoFSPaymentQueue::SUCCESS],
                                ['orderID' => $data->orderID]
                            );
                            $count = EsoFSPaymentQueue::getCount();
                            continue;
                        }

                        $model = new SelfOrderTakeAway();
                        $model->orderID = $data->orderID;
                        $model->flagSavePaymentFs = true;

                        // Save order and update queue status if successful
                        if (!$result = $model->preSave($data->salesNum, $data->paymentMethod, $data->paymentTotal)) {
                            Yii::error($model->errors);
                            throw new ServerErrorHttpException();
                        }

                        
                        EsoFSPaymentQueue::updateAll(
                            ['status' => EsoFSPaymentQueue::SUCCESS],
                            ['orderID' => $data->orderID]
                        );
                        
                    } catch (\Exception $e) {
                        // Log the failure and continue with the next record
                        $attributes = [
                            'orderID' => $data->orderID,
                            'message' => "- Error on order ID: {$data->orderID}, Error: {$e->getMessage()}"
                        ];
                        Logging::save($data->orderID, Logging::ESO_FS_PROCESS_QUEUE, $attributes);
                    }
                    $count = EsoFSPaymentQueue::getCount();
                }
            }
        }
    }    
}
