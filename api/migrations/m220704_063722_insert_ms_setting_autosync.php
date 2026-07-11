<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m220704_063722_insert_ms_setting_autosync
 */
class m220704_063722_insert_ms_setting_autosync extends Migration
{
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Auto Sync POS'])->exists()) {
            $this->insert(Setting::tableName(), ['key1' => 'Local Setting', 'key2' => 'Auto Sync POS', 'value1' => '1']);
        }
    }

    public function down()
    {
        return true;
    }
}
