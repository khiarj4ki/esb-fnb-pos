<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m210310_074117_insert_ms_setting_setting_queue_number
 */
class m210310_074117_insert_ms_setting_setting_queue_number extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Queue Number Reset After Reach'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Queue Number Reset After Reach', 'value1' => '999']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }
}
