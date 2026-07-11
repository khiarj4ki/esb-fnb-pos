<?php

namespace app\commands;

use app\components\AppHelper;
use app\models\Branch;
use app\models\Queue;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\TerminalSetting;
use Exception;
use Yii;
use yii\console\Controller;
use yii\httpclient\Client;

class QueueController extends Controller
{

    public function actionRun()
    {
        $count = Queue::find()->count();
        $sleepTime = 3;
        $retryThreeTimesErr = [
            Queue::COMPANY_NOT_FOUND,
            Queue::BRANCH_NOT_FOUND,
            Queue::INVALID_CREDENTIALS,
            Queue::INVALID_IDENTITY_INTERFACE,
        ];

        $NUM_OF_ATTEMPTS = 3;
        $needToDeactiveEsoFs = false;

        while ($count > 0) {
            try {
                file_put_contents(Yii::$app->basePath . '/' . Yii::$app->params['queueLogFile'], microtime(true));
                $queueModel = Queue::find()->orderBy(['id' => SORT_ASC])->all();
                foreach ($queueModel as $data) {
                    $attempts = 0;
                    if ($data->type == Queue::TYPE_SALESNUM) {
                        $salesNum = $data->salesNum;
                        $id = $data->id;

                        $transaction = Yii::$app->db->beginTransaction();
                        $salesModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
                        if ($salesModel) {
                            $result = AppHelper::sendSales($salesModel, $salesNum);
                            if ($result->getIsOk()) {
                                if ($transaction->isActive) {
                                    $transaction->commit();
                                }
                                $data->delete();
                            } else {
                                do {
                                    if ($transaction->isActive) {
                                        $transaction->rollBack();
                                    }
                                    try {
                                        if ($data->type === Queue::TYPE_SALESNUM) {
                                            $salesModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
                                            if ($salesModel) {
                                                $retryRes =AppHelper::sendSales($salesModel, $salesNum);
                                                if ($retryRes->getIsOk()) {
                                                    if ($transaction->isActive) {
                                                        $transaction->commit();
                                                    }
                                                    $data->delete();
                                                } else {
                                                    if ($transaction->isActive) {
                                                        $transaction->rollBack();
                                                    }
                                                    throw new Exception($retryRes->getData()['message']);
                                                }
                                            } else {
                                                if ($transaction->isActive) {
                                                    $transaction->rollBack();
                                                }
                                                $data->delete();
                                            }
                                        }
                                    } catch (Exception $ex) {
                                        if ($transaction->isActive) {
                                            $transaction->rollBack();
                                        }
                                        if (in_array($ex->getMessage(), $retryThreeTimesErr)) {
                                            $attempts = 3;
                                        } else {
                                            $attempts++;
                                        }
                                        if (strpos(QUEUE::REQUEST_DATA_EXPIRED, $ex->getMessage()) === false) {
                                            $needToDeactiveEsoFs = true;
                                        }

                                        sleep($sleepTime);
                                        if ($needToDeactiveEsoFs && $attempts === 3) {
                                            $this->deactivateEsoFs($id);
                                        }
                                        continue;
                                    }
                                    break;
                                } while ($attempts < $NUM_OF_ATTEMPTS);
                            }
                        } else {
                            if ($transaction->isActive) {
                                $transaction->rollBack();
                            }
                            $data->delete();
                        }
                    }
                }

                $count = Queue::find()->count();
            } catch (Exception $ex) {
                if ($transaction->isActive) {
                    $transaction->rollBack();
                }
                $count = Queue::find()->count();
                sleep($sleepTime);
            }
        }
    }

    private function deactivateEsoFs($id)
    {
        try {
            $settingModel = Setting::findOne([
                'key1' => 'EZO',
                'key2' => 'Activate EZO'
            ]);

            if ($settingModel) {
                $settingModel->value1 = 0;
                if (!$settingModel->save()) {
                    throw new Exception('Failed to update activate eso fs');
                }
            }

            $flagEsoFsDeactivated = Setting::findOne([
                'key1' => 'Local Setting',
                'key2' => 'ESO FS Deactivated'
            ]);

            if($flagEsoFsDeactivated) {
                $flagEsoFsDeactivated->value1 = 1;
                if(!$flagEsoFsDeactivated->save()) {
                    throw new Exception('Failed to update flag ESO FS Deactivated');
                }
            }

            $queueModel = Queue::findOne($id);
            if ($queueModel) {
                if (!$queueModel->delete()) {
                    throw new Exception('Failed to delete queue data');
                }
            }

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
}
