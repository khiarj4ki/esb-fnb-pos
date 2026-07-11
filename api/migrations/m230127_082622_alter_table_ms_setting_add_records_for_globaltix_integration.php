<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m230127_082622_alter_table_ms_setting_add_records_for_globaltix_integration
 */
class m230127_082622_alter_table_ms_setting_add_records_for_globaltix_integration extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Token'])->exists()) {
            $this->insert(Setting::tableName(), [
                'key1' => 'Local Setting', 
                'key2' => 'GlobalTix Token', 
                'value1' => null
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Token API Url'])->exists()) {
            $this->insert(Setting::tableName(), [
                'key1' => 'Local Setting', 
                'key2' => 'GlobalTix Token API Url', 
                'value1' => 'https://uat-api.globaltix.com/api/auth/login'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Get Ticket API Url'])->exists()) {
            $this->insert(Setting::tableName(), [
                'key1' => 'Local Setting', 
                'key2' => 'GlobalTix Get Ticket API Url', 
                'value1' => 'https://uat-api.globaltix.com/api/ticket/getTransactionAndTicketsByCode'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Redeem Ticket API Url'])->exists()) {
            $this->insert(Setting::tableName(), [
                'key1' => 'Local Setting', 
                'key2' => 'GlobalTix Redeem Ticket API Url', 
                'value1' => 'https://uat-api.globaltix.com/api/ticket/redeem'
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Token'])->exists()) {
            $this->delete(Setting::tableName(), ['key2' => 'GlobalTix Token']);
        }

        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Token API Url'])->exists()) {
            $this->delete(Setting::tableName(), ['key2' => 'GlobalTix Token API Url']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Get Ticket API Url'])->exists()) {
            $this->delete(Setting::tableName(), ['key2' => 'GlobalTix Get Ticket API Url']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'GlobalTix Redeem Ticket API Url'])->exists()) {
            $this->delete(Setting::tableName(), ['key2' => 'GlobalTix Redeem Ticket API Url']);
        }

    }
}
