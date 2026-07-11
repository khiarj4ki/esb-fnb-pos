<?php

use yii\db\Migration;
Use app\models\Setting;

/**
 * Class m200710_105518_insert_local_setting_print_sales_per_menu_category
 */
class m200710_105518_insert_local_setting_print_sales_per_menu_category extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales per Menu Category'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales per Menu Category', 'value1' => '0']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales per Menu Category'])->exists()) {
            $this->delete(Setting::tableName(), ['key1' => 'Local Setting', 'key2' => 'Print Sales per Menu Category']);
        }        
    }
}
