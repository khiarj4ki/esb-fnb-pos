<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191120_024117_add_setting_print_checker_ms_setting
 */
class m191120_024117_add_setting_print_checker_ms_setting extends Migration
{
    
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Header'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Header', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Table'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Table', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Order', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Date'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Date', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Waiter'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Waiter', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Sender'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Sender', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Batch'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Batch', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Detail', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Checker Footer'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Checker Footer', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Checker Header"');
        $this->delete('ms_setting', 'key2 = "Show Checker Table"');
        $this->delete('ms_setting', 'key2 = "Show Checker Order"');
        $this->delete('ms_setting', 'key2 = "Show Checker Date"');
        $this->delete('ms_setting', 'key2 = "Show Checker Waiter"');
        $this->delete('ms_setting', 'key2 = "Show Checker Sender"');
        $this->delete('ms_setting', 'key2 = "Show Checker Batch"');
        $this->delete('ms_setting', 'key2 = "Show Checker Detail"');
        $this->delete('ms_setting', 'key2 = "Show Checker Footer"');
    }
}
