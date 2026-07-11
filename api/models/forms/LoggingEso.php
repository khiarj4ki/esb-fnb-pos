<?php

namespace app\models\forms;

use app\models\EsoLogEvent;
use app\models\Setting;
use Yii;
use yii\db\Expression;

class LoggingEso {
    const ESO_PROCESS_QUEUE = 'ESB Order Process Queue';

    public static function save($refNum, $eventSubject, $modelAttr) {
        $eventDescription = '';
        switch ($eventSubject) {
            case self::ESO_PROCESS_QUEUE:
                $eventDescription = $modelAttr;
                break;
            default:
                return;
        }
        
        LoggingEso::insertLog($refNum, $eventSubject, $eventDescription);
    }

    private static function insertLog($refNum, $eventSubject, $eventDescription) {

        $branchID = Setting::getCurrentBranch();

        $eventModel = EsoLogEvent::find()->where(['refNum' => strval($refNum)])->one();
        if (!$eventModel)
            $eventModel = new EsoLogEvent();
        
        $eventModel->branchID = $branchID;
        $eventModel->eventDate = new Expression('NOW()');
        $eventModel->refNum = strval($refNum);
        $eventModel->eventSubject = $eventSubject;
        $eventModel->eventType = Yii::$app->controller->action->id;
        $eventModel->eventDescription = substr($eventDescription, 0, 65535);
        
        if (!$eventModel->save()) {
            Yii::warning($eventModel->errors);
        }
    }
}
