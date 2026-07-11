<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200319_085642_insert_ms_setting_ezo_fs_print_table_checker
 */
class m200319_085642_insert_ms_setting_ezo_fs_print_table_checker extends Migration
{
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'EZO', 'key2' => 'EZO FS Print Table Checker'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'EZO', 'key2' => 'EZO FS Print Table Checker', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "EZO FS Print Table Checker"');
    }
}
