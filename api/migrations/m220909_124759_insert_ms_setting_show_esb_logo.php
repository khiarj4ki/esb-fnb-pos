<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m220909_124759_insert_ms_setting_show_esb_logo
 */
class m220909_124759_insert_ms_setting_show_esb_logo extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show ESB Logo'])->exists()) {
            $this->insert(
                Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show ESB Logo', 'value1' => '1']
            );
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
