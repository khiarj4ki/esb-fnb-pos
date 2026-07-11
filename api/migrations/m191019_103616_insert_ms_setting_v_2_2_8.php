<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191019_103616_insert_ms_setting_v_2_2_8
 */
class m191019_103616_insert_ms_setting_v_2_2_8 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Shift Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Shift Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Type'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Type', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Payment Method Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Payment Method Detail', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Payment Method Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Payment Method Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment Method Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment Method Detail', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment Method Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment Method Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Void Payment Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Void Payment Detail', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Void Payment Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Void Payment Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Category'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Category', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Category Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Category Detail', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Promotion Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Promotion Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Cancelled Menu'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Cancelled Menu', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Payment by Cashier'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Payment by Cashier', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment by Cashier'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Non Sales Payment by Cashier', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Deposit Detail'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Deposit Detail', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Deposit Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Deposit Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales Per Date'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales Per Date', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Closing Notes'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Closing Notes', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Cancelled Menu Summary'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Cancelled Menu Summary', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales Menu Package'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales Menu Package', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Take Away Table Text'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Take Away Table Text', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Queue Number'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Queue Number', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Expected Cash'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Expected Cash', 'value1' => '1']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Menu Setting'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Menu Setting', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Shift Notification Closing'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Shift Notification Closing', 'value1' => '0', 'value2' => '06:00:00']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Time In'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Time In', 'value1' => '1']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
