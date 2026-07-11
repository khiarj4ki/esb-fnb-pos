<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m221018_031904_delete_old_auto_sync_pos
 */
class m221018_031904_delete_old_auto_sync_pos extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Auto Sync POS'])->exists()) {
            Setting::deleteAll(['key1' => 'Local Setting', 'key2' => 'Auto Sync POS']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        return true;
    }
}
