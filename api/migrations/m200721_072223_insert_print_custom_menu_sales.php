<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200721_072223_insert_print_custom_menu_sales
 */
class m200721_072223_insert_print_custom_menu_sales extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Custom Menu Sales'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Custom Menu Sales', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Custom Menu Sales"');
    }
}
