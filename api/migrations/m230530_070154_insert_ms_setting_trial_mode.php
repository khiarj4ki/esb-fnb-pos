<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m230530_070154_insert_ms_setting_trial_mode
 */
class m230530_070154_insert_ms_setting_trial_mode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Trial Mode'])->exists()) {
            $this->insert(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Trial Mode', 'value1' => 0]
            );
        }
    }

    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Trial Mode'])->exists()) {
            $this->delete(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Trial Mode']
            );
        }
    }
}
