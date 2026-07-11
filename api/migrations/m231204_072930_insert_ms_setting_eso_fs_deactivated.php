<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m231204_072930_insert_ms_setting_eso_fs_deactivated
 */
class m231204_072930_insert_ms_setting_eso_fs_deactivated extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'ESO FS Deactivated'])->exists()) {
            $this->insert(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'ESO FS Deactivated', 'value1' => 0]
            );
        }
    }

    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'ESO FS Deactivated'])->exists()) {
            $this->delete(
                Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'ESO FS Deactivated']
            );
        }
    }
}
