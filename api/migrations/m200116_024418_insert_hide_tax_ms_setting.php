<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200116_024418_insert_hide_tax_ms_setting
 */
class m200116_024418_insert_hide_tax_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Inclusive Tax'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Inclusive Tax', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Inclusive Other Tax'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Inclusive Other Tax', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Inclusive Tax"');
        $this->delete('ms_setting', 'key2 = "Show Inclusive Other Tax"');
    }
}
