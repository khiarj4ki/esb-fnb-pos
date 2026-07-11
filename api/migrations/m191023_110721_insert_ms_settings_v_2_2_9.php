<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191023_110721_insert_ms_settings_v_2_2_9
 */
class m191023_110721_insert_ms_settings_v_2_2_9 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Epson Sticker Margin Left'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Epson Sticker Margin Left', 'value1' => '40']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Epson Sticker Width'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Epson Sticker Width', 'value1' => '500']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
