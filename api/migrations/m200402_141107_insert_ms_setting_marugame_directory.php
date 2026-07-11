<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200402_141107_insert_ms_setting_marugame_directory
 */
class m200402_141107_insert_ms_setting_marugame_directory extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Marugame Directory'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Marugame Directory', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Marugame Directory"');
    }
}
