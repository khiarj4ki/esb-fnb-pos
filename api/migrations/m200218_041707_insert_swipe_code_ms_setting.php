<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200218_041707_insert_swipe_code_ms_setting
 */
class m200218_041707_insert_swipe_code_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Swipe Code'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Swipe Code', 'value1' => ';7004']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Swipe Code"');
    }

}
