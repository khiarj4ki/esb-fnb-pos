<?php

namespace app\models\forms;

use app\models\Queue;
use app\models\SalesHead;
use Yii;
use yii\base\Model;

class SyncSelfOrder extends Model
{

    public $refNum;
    public $type;

    public function rules()
    {
        return [
            [['refNum', 'type'], 'required'],
            [['refNum', 'type'], 'safe'],
            [['refNum'], 'validateRefNum']
        ];
    }

    public function validateRefNum($attribute)
    {
        if ($this->type == 'salesNum') {
            $salesHeadModel = SalesHead::find()
                ->andWhere(['salesNum' => $this->refNum])
                ->one();
            if (!$salesHeadModel) {
                $this->addError($attribute, 'Invalid sales number');
            }
        }
    }

    public function addQueue()
    {
        if (!$this->validate()) {
            return false;
        }
        $currentQueueCount = Queue::find()->count();
        $checkEsoFsQr = SalesHead::find()
            ->where(['salesNum' => $this->refNum])
            ->andWhere(['printEsoFsQr' => 1])
            ->one();

        if ($checkEsoFsQr) {
            $queueModel = new Queue();
            $queueModel->type = $this->type;
            $queueModel->salesNum  = $this->refNum;
            if (!$queueModel->save()) {
                Yii::warning($queueModel->errors());
            }
        }

        $queueLogFileLocation = Yii::$app->basePath . '/' . Yii::$app->params['queueLogFile'];
        $fileValue = file_exists($queueLogFileLocation) ? file_get_contents($queueLogFileLocation) : 0;
        $lastQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);
        if ($currentQueueCount == 0 || (microtime(true) - $lastQueueRunTime > 60)) {
            $yiiLocation = Yii::$app->basePath . '/yii';
            $runQueueAction = 'queue/run';

            if (substr(php_uname(), 0, 3) == "Win") {
                pclose(popen("start /B php $yiiLocation $runQueueAction ", "r"));
            } else {
                shell_exec("php $yiiLocation $runQueueAction > /dev/null 2>/dev/null &");
            }
        }
    }
}
