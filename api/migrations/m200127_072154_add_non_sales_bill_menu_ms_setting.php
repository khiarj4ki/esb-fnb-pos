<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200127_072154_add_non_sales_bill_menu_ms_setting
 */
class m200127_072154_add_non_sales_bill_menu_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales Bill Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales Bill Summary', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales Menu Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales Menu Summary', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Non Sales Bill Summary"');
        $this->delete('ms_setting', 'key2 = "Print Non Sales Menu Summary"');
    }
}
