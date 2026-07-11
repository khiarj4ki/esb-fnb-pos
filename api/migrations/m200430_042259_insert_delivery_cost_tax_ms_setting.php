<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200430_042259_insert_delivery_cost_tax_ms_setting
 */
class m200430_042259_insert_delivery_cost_tax_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'EZO', 'key2' => 'Delivery Cost Tax'])->exists()) {
            $this->insert(Setting::tableName(),
            [
                'key1' => 'EZO', 
                'key2' => 'Delivery Cost Tax', 
                'value1' => '0'
            ]);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Delivery Cost Tax"');
    }
}
