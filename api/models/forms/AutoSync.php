<?php

namespace app\models\forms;


use app\models\Setting;
use Yii;
use yii\base\Model;

class AutoSync extends Model
{
    public function run()
    {
        // @Notes: Auto Sync POS (call command controller: auto-sync/run)
        $settingAutoSyncPOS = Setting::getSetting('Local Setting', 'POS Auto Sync');
        if ($settingAutoSyncPOS && $settingAutoSyncPOS->value1 == 1) {
            $autoSyncLogFile = Yii::$app->basePath . '/' . Yii::$app->params['autoSyncLogFile'];
            $fileValue = file_exists($autoSyncLogFile) ? file_get_contents($autoSyncLogFile) : 0;
            $lastQueueRunTime = floatval(is_numeric($fileValue) ? $fileValue : 0);
            if (microtime(true) - $lastQueueRunTime > 60 * 15) {
                file_put_contents($autoSyncLogFile, microtime(true));
                $endRunTime = date('Ymd', strtotime(date('Y-m-d') . ' +1 days'));
                $yiiLocation = Yii::$app->basePath . '/yii';
                $runQueueAction = 'auto-sync/run';

                if (substr(php_uname(), 0, 3) == "Win") {
                    pclose(popen("start /B php $yiiLocation $runQueueAction $endRunTime", "r"));
                } else {
                    shell_exec("php $yiiLocation $runQueueAction $endRunTime > /dev/null 2>/dev/null &");
                }
            }
        }
    }
}
