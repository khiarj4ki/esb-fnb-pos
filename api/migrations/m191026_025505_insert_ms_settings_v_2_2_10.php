<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191026_025505_insert_ms_settings_v_2_2_10
 */
class m191026_025505_insert_ms_settings_v_2_2_10 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Print Category Subtotal'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Print Category Subtotal', 'value1' => '0']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
