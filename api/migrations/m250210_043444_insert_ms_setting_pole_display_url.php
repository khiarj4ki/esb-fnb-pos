<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m250210_043444_insert_ms_setting_pole_display_url
 */
class m250210_043444_insert_ms_setting_pole_display_url extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Pole Display URL'])->exists()) {
            $this->insert(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Pole Display URL', 'value1' => "http://localhost:1945/display"]
            );
        }
    }

    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Pole Display URL'])->exists()) {
            $this->delete(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Pole Display URL']
            );
        }
    }
}
