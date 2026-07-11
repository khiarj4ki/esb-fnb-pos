<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200213_071518_add_sales_mode_ms_setting
 */
class m200213_071518_add_sales_mode_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Mode', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales by Mode"');
    }
}
