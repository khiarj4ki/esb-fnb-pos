<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200213_072343_add_show_sales_number_ms_setting
 */
class m200213_072343_add_show_sales_number_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Sales Number'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Sales Number', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Sales Number"');
    }
}
