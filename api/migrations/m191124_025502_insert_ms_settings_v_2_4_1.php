<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191124_025502_insert_ms_settings_v_2_4_1
 */
class m191124_025502_insert_ms_settings_v_2_4_1 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        // ODS Mode : 1 = Kitchen & Checker, 2 = Kitchen Only, 3 = Checker, 4 = No Kitchen or Checker
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'ODS Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'ODS Mode', 'value1' => '4']);
        }

        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Finish All Packages'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Finish All Packages', 'value1' => '1']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
