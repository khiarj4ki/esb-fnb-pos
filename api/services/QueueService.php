<?php

namespace app\services;

use app\models\EsoProcessQueue;
use Yii;

class QueueService {

    private $queueCount;
    private $queueAction;
    private $yiiLocation;

    public function __construct()
    {
        $this->yiiLocation = \Yii::$app->basePath . '/yii';
    }


    public function prepareQueue()
    {
        if ($this->queueCount == 0 || self::getLastQueueRunTime()) {
            $yiiLocation = $this->yiiLocation;
            $queueAction = $this->queueAction;
            if (substr(php_uname(), 0, 3) == "Win") {
                pclose(popen("start /B php $yiiLocation $queueAction", "r"));
            } else {
                shell_exec("php $yiiLocation $queueAction > /dev/null 2>/dev/null &");
            }
        }
    }

    public static function getFileLocation()
    {
        return \Yii::$app->basePath . '/' . \Yii::$app->params['esoProcessQueueLogFile'];
    }

    public function runQueue($runQueueAction)
    {
        $this->queueCount = EsoProcessQueue::getCount();
        $this->queueAction = $runQueueAction;
        $this->prepareQueue();
    }

    public static function getLastQueueRunTime($setQueueRunTime = 10)
    {
        $fileLocation = self::getFileLocation();
        $fileValue = file_exists($fileLocation) ? file_get_contents($fileLocation) : 0;
        $lastQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);

        return (microtime(true) - $lastQueueRunTime > $setQueueRunTime);
    }

}
