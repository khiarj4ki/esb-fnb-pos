<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200618_014551_insert_print_sales_by_menu_qty
 */
class m200618_014551_insert_print_sales_by_menu_qty extends Migration
{
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Qty'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Qty', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales by Menu Qty"');
    }
}
