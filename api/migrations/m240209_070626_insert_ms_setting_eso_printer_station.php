<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m240209_070626_insert_ms_setting_eso_printer_station
 */
class m240209_070626_insert_ms_setting_eso_printer_station extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'ESO Printer Station'])->exists()) {
            $value = 0;
            if(Setting::find()->where(['key1' => 'EZO', 'key2' => 'Printer Station'])->exists()) {
                $value = Setting::find()->select('value1')->where(['key1' => 'EZO', 'key2' => 'Printer Station'])->scalar();
            }
            $this->insert(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'ESO Printer Station', 'value1' => $value]
            );
        }
    }

    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'ESO Printer Station'])->exists()) {
            $this->delete(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'ESO Printer Station']
            );
        }
    }
}
