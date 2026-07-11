<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191028_104912_insert_ms_settings_v_2_2_11
 */
class m191028_104912_insert_ms_settings_v_2_2_11 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Additional Info'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Additional Info', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Menu Notes'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Menu Notes', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Value'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Value', 'value1' => '0']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
