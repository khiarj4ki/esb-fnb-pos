<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m191129_114352_insert_ms_setting_checker_mode
 */
class m191129_114352_insert_ms_setting_checker_mode extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Settle Checker Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Settle Checker Mode', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Settle Checker Mode"');
    }
}
