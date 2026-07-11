<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200218_040030_add_print_shift_sales_by_menu_value_ms_setting
 */
class m200218_040030_add_print_shift_sales_by_menu_value_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Shift Sales by Menu Value'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Shift Sales by Menu Value', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Shift Sales by Menu Value"');
    }
}
