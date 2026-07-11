<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200526_054946_insert_ms_setting
 */
class m200526_054946_insert_ms_setting extends Migration
{
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Pending Sales'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Pending Sales', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales Voucher Usage'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales Voucher Usage', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales By Menu'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales By Menu', 'value1' => '1']);
        }
    }
}
