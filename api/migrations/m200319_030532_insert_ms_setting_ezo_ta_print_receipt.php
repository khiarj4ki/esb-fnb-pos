<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200319_030532_insert_ms_setting_ezo_ta_print_receipt
 */
class m200319_030532_insert_ms_setting_ezo_ta_print_receipt extends Migration
{
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'EZO', 'key2' => 'EZO TA Print Receipt'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'EZO', 'key2' => 'EZO TA Print Receipt', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "EZO TA Print Receipt"');
    }
}
