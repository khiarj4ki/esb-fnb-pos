<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m211208_042359_insert_ms_setting_print_sales_by_table_section
 */
class m211208_042359_insert_ms_setting_print_sales_by_table_section extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Print Sales By Table Section'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Print Sales By Table Section', 'value1' => '0']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        return true;
    }
}
