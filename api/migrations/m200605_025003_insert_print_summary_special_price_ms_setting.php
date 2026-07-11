<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200605_025003_insert_print_summary_special_price_ms_setting
 */
class m200605_025003_insert_print_summary_special_price_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Special Price Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Special Price Summary', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Special Price Summary"');
    }
}
