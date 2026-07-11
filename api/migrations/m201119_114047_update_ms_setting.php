<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m201119_114047_update_ms_setting
 */
class m201119_114047_update_ms_setting extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Take Away Table Text'])->exists()) {
            $this->update(Setting::tableName(), ['key2' => 'Print Quick Service Table Text'], ['key1' => 'Local Setting', 'key2' => 'Print Take Away Table Text']);
        }
    }

    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Quick Service Table Text'])->exists()) {
            $this->update(Setting::tableName(), ['key2' => 'Print Take Away Table Text'], ['key1' => 'Local Setting', 'key2' => 'Print Quick Service Table Text']);
        }
    }
}
