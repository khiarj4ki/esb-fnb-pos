<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200226_082305_insert_sales_by_menu_group_ms_setting
 */
class m200226_082305_insert_sales_by_menu_group_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Menu Group'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales By Menu Group', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales By Menu Group"');
    }
}
