<?php

namespace app\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;
use yii\log\FileTarget;
use yii\log\Logger;

/**
 * This is the model class for table "tr_websocket ".
 *
 * @property string $timestamp
 */
class WebSocket extends ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tr_websocket';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['timestamp'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'timestamp' => 'Time Stamp',
        ];
    }

    public function getWebSocket() {
        return self::find()->one();
    }

    public static function updateWebSocket($newTimeStamp){
        $transaction = Yii::$app->db->beginTransaction();
        try {
            
            $webSocketModel = self::find()->one();
            $webSocketModel->timestamp = $newTimeStamp;

            if (!$webSocketModel->save()) {
                throw new Exception('Failed to update timestamp');
            }

            $transaction->commit();

            return $webSocketModel->timestamp;

        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return '';
        }
    }

    private static function deleteOldLogFiles() {
        // @Notes: Check if the log file is older than 4 days and delete it

        $logDirectory = Yii::getAlias('@runtime/socketlogs/');

        if (!is_dir($logDirectory)) {
            return; 
        }
        
        $files = scandir($logDirectory);
    
        foreach ($files as $file) {
            if (preg_match('/^socket_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $filePath = $logDirectory . $file;
                if (time() - strtotime($matches[1]) > 4 * 24 * 60 * 60) {  
                    unlink($filePath);  
                }
            }
        }
    }

    public static function saveErrorLog(string $message){
        $logFile = Yii::getAlias('@runtime/socketlogs/socket_' . date('Y-m-d') . '.log');

        self::deleteOldLogFiles();

        $logTarget = new FileTarget([
            'logFile' => $logFile,
            'levels' => ['info'],
            'logVars' => [],
            'categories' => ['socket'],
        ]);

        $logTarget->messages[] = [$message, Logger::LEVEL_INFO, 'socket', time()];
        $logTarget->export();
        return true;
    }
}
