<?php

namespace app\commands;

use app\models\EsoLogEvent;
use app\models\EsoProcessQueue;
use app\models\forms\EsbOrder;
use app\models\forms\Logging;
use app\models\forms\VoidSales;
use app\models\PosUser;
use yii\console\Controller;
use Yii;

class EsoProcessQueueController extends Controller
{
    protected $retry = 0;

    public function actionRun()
    {
        $user = new PosUser();
        $user->username = 'SYSTEM';
        Yii::$app->user->setIdentity($user);
        
        $count = $this->getCount();
        $this->retry++;

        while ($count > 0) {
            // Log the start time of the process
            file_put_contents(\Yii::$app->basePath . '/' . \Yii::$app->params['esoProcessQueueLogFile'], microtime(true));
            sleep(1);
            // Fetch all records with status 'PENDING'
            $esoQueueModels = EsoProcessQueue::findPendingData();

            if ($esoQueueModels) {
                foreach ($esoQueueModels as $data) {
                    try {


                        if ($data->eventType == EsoProcessQueue::TYPE_NEW)
                        {
                            if (EsoProcessQueue::checkExistingOrderId($data->orderID)) {
                                EsoProcessQueue::updateAll(
                                    ['status' => EsoProcessQueue::SUCCESS],
                                    ['orderID' => $data->orderID]
                                );
                                $count = $this->getCount();
                                continue;
                            }

                            $model = new EsbOrder();
                            // Attempt to process the order
                            $model->loadOrderId($data->orderID);

                            // Save order and update queue status if successful
                            if (!$model->save()) {
                                throw new \Exception(json_encode($model->getErrors()));
                            }
                        } else {
                            $checkSales = EsoProcessQueue::checkStatusSalesNum($data->salesNum);
                            if ($checkSales && $checkSales->statusID == 24)
                            {
                                EsoProcessQueue::updateAll(
                                    ['status' => EsoProcessQueue::SUCCESS],
                                    ['orderID' => $data->orderID]
                                );
                                $count = $this->getCount();
                                continue;
                            }

                            $model = new VoidSales();
                            // Attempt to void process the order
                            $model->orderID = $data->orderID;
                            $model->salesNum = $data->salesNum;
                            $model->voidNotes = $data->voidNotes;

                            // Save void order and update queue status if successful
                            if (!$model->voidSalesEso()) {
                                throw new \Exception(json_encode($model->getErrors()));
                            }
                        }

                        EsoProcessQueue::updateAll(
                            ['status' => EsoProcessQueue::SUCCESS],
                            ['orderID' => $data->orderID]
                        );

                        // @success log event eso error
                        EsoLogEvent::updateAll(
                            ['isSuccess' => 1],
                            ['refNum' => $data->orderID]
                        );

                    } catch (\Exception $e) {
                        // Log the failure and continue with the next record
                        $attributes = [
                            'orderID' => $data->orderID,
                            'message' => "- Error on order ID: {$data->orderID}, Error: {$e->getMessage()}"
                        ];
                        Logging::save($data->orderID, Logging::ESO_PROCESS_QUEUE, $attributes);

                        EsoProcessQueue::deleteAll(['AND',
                            ['orderID' => $data->orderID],
                            ['status' => EsoProcessQueue::PENDING]
                        ]);

                    }
                    $count = $this->getCount();
                }
            }
        }

        if ($this->retry < 3) {
            sleep(5);
            $this->actionRun();
        }
    }    

    private function getCount()
    {
        return EsoProcessQueue::find()
            ->where(['status' => EsoProcessQueue::PENDING])
            ->count();
    }
    
}
