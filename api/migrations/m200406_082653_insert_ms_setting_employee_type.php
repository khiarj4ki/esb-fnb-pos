<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200406_082653_insert_ms_setting_employee_type
 */
class m200406_082653_insert_ms_setting_employee_type extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Employee Type'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Employee Type']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Employee Type"');
    }
}
