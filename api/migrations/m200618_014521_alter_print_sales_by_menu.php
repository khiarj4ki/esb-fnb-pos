<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200618_014521_alter_print_sales_by_menu
 */
class m200618_014521_alter_print_sales_by_menu extends Migration
{
    public function up()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu'])->exists()) {
            $this->update(Setting::tableName(), ['key2' => 'Print Sales by Menu Qty & Value'],
                "key2 = 'Print Sales by Menu' AND key1 = 'Local Setting'");
        }
    }

    public function down()
    {
        
    }
}
