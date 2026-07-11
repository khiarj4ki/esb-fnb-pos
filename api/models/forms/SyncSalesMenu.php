<?php

namespace app\models\forms;

use app\models\SalesMenu;
use app\models\SalesMenuQueue;
use Yii;
use yii\base\Model;

class SyncSalesMenu extends Model {
    CONST TYPE_SINGLE_MENU = 1;
    CONST TYPE_ALL_MENU = 2;
    public $type;
    public $salesNum;
    public $salesMenuID;
    public $order;
    public $salesMenu;

    public function rules() {
        return [
            [
                'salesNum',
                'required',
                'when' => function ($model) {
                    return $model->type == self::TYPE_SINGLE_MENU;
                }, 'enableClientValidation' => false
            ],
            [
                'salesMenuID',
                'required',
                'when' => function ($model) {
                    return $model->type == self::TYPE_SINGLE_MENU;
                }, 'enableClientValidation' => false
            ],
            [
                'order',
                'required',
                'when' => function ($model) {
                    return $model->type == self::TYPE_ALL_MENU;
                }, 'enableClientValidation' => false
            ],
            [['type', 'salesNum', 'salesMenuID', 'order', 'salesMenu'], 'safe'],
            [['type'], 'validateSalesMenu']
        ];
    }

    public function validateSalesMenu($attribute) {
        $salesMenuArray = [];
        if ($this->type == self::TYPE_SINGLE_MENU) {
            $salesMenuArray = $this->fillSalesMenuArray($this->salesNum, $this->salesMenuID);
        } elseif ($this->type == self::TYPE_ALL_MENU) {
            if ($this->order) {
                foreach ($this->order as $data) {
                    $this->salesNum = $data['salesNum'];
                    $salesMenuArrayDetail = $this->fillSalesMenuArray($data['salesNum'], $data['ID']);
                    array_push($salesMenuArray, ...$salesMenuArrayDetail);
                }
            }
        }

        if ($salesMenuArray == 0) {
            $this->addError('salesMenu', 'Invalid sales number');
        } else {
            $this->salesMenu = json_encode($salesMenuArray);
        }
    }

    private function fillSalesMenuArray($salesNum, $salesMenuID) {
        $salesMenuArray = [];
        $salesMenuModel = SalesMenu::find()
                ->andWhere(['salesNum' => $salesNum, 'ID' => $salesMenuID])
                ->asArray()
                ->one();
        if (!$salesMenuModel) {
            $this->addError('salesNum', 'Invalid sales number');
        } else {
            $salesMenuArray[] = $salesMenuModel;
        }
        if  ($salesMenuModel['menuRefID'] > 0 && $salesMenuModel['menuGroupID'] == 0) {
            $salesMenuPackage = SalesMenu::find()
                ->leftJoin(
                    ["packageHead" => SalesMenu::tableName()],
                    'tr_salesmenu.menuRefID = ' . $salesMenuModel['menuRefID']
                    . ' AND tr_salesmenu.salesNum = packageHead.salesNum')
                ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesNum])
                ->andWhere(['>', 'tr_salesmenu.menuGroupID', 0])
                ->asArray()
                ->all();
            if ($salesMenuPackage) {
                foreach ($salesMenuPackage as $package) {
                    $salesMenuArray[] = $package;
                }
            }
            
        } else if ($salesMenuModel['menuRefID'] > 0 && $salesMenuModel['menuGroupID'] > 0) {
            $salesMenuParentModel = SalesMenu::find()
                ->andWhere(['salesNum' => $salesNum, 'menuRefID' => $salesMenuModel['menuRefID']])
                ->andWhere(['=', 'tr_salesmenu.menuGroupID', 0])
                ->asArray()
                ->one();
            if ($salesMenuParentModel) {
                $salesMenuArray[] = $salesMenuParentModel;
            }    
        }

        return $salesMenuArray;
    }

    public function addQueue() {
        if (!$this->validate()) {
            return false;
        }
        $currentQueueCount = SalesMenuQueue::find()->count();
        $checkQueue = SalesMenuQueue::findOne($this->salesNum);
        if (!$checkQueue) {
            $queueModel = new SalesMenuQueue();
            $queueModel->ID = (microtime(true) * 100);
            $queueModel->salesNum = $this->salesNum;
            $queueModel->salesMenu = $this->salesMenu;
            if (!$queueModel->save()) {
                Yii::warning(json_encode($queueModel->getErrors()));
            }
        }

        $queueFileFolder = isset(Yii::$app->params['salesMenuQueueLogFile']) ? Yii::$app->params['salesMenuQueueLogFile'] : 'web/salesmenuqueue.log';
        $queueLogFileLocation = Yii::$app->basePath . '/' . $queueFileFolder;
        $fileValue = file_exists($queueLogFileLocation) ? file_get_contents($queueLogFileLocation) : 0;
        $lastQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);
        if ($currentQueueCount == 0 || (microtime(true) - $lastQueueRunTime > 60)) {
            $yiiLocation = Yii::$app->basePath . '/yii';
            $runQueueAction = 'sales-menu-queue/run';

            if (substr(php_uname(), 0, 3) == "Win") {
                pclose(popen("start /B php $yiiLocation $runQueueAction ", "r"));
            } else {
                shell_exec("php $yiiLocation $runQueueAction > /dev/null 2>/dev/null &");
            }
        }
    }

}
