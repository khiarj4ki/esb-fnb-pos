<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200213_083913_add_printing_order_ms_setting
 */
class m200213_083913_add_printing_order_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Table Section Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Table Section Order', 'value1' => '1']);
        }
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Kitchen Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Kitchen Visit Purpose', 'value1' => '1']);
        }
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Waiter Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Waiter Order', 'value1' => '1']);
        }
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Sender Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Sender Order', 'value1' => '1']);
        }
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Info Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Info Order', 'value1' => '1']);
        }
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Batch Order'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Batch Order', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Printing Table Section Order"');
        $this->delete('ms_setting', 'key2 = "Show Printing Kitchen Visit Purpose"');
        $this->delete('ms_setting', 'key2 = "Show Printing Waiter Order"');
        $this->delete('ms_setting', 'key2 = "Show Printing Sender Order"');
        $this->delete('ms_setting', 'key2 = "Show Printing Info Order"');
        $this->delete('ms_setting', 'key2 = "Show Printing Batch Order"');
    }
}
