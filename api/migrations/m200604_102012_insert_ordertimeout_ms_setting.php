<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200604_102012_insert_ordertimeout_ms_setting
 */
class m200604_102012_insert_ordertimeout_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Customer Order Time'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Customer Order Time', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Customer Order Time"');
    }
}
