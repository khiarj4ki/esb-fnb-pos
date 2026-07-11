<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200213_075420_add_printing_end_shift_ms_setting
 */
class m200213_075420_add_printing_end_shift_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales Menu Package'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales Menu Package', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales Menu Package"');
        $this->delete('ms_setting', 'key2 = "Print Sales by Menu"');
    }
}
