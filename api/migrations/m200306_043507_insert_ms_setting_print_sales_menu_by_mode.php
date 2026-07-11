<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200306_043507_insert_ms_setting_print_sales_menu_by_mode
 */
class m200306_043507_insert_ms_setting_print_sales_menu_by_mode extends Migration
{
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales Menu by Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales Menu by Mode', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales Menu by Mode"');
    }
}
