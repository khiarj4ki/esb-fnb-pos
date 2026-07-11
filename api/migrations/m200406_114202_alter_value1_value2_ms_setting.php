<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200406_114202_alter_value1_value2_ms_setting
 */
class m200406_114202_alter_value1_value2_ms_setting extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(Setting::tableName(), true)->getColumn('value1') !== null) {
            $this->alterColumn(
                Setting::tableName(),
                'value1',
                $this->text()
            );
        }
        if ($this->db->getTableSchema(Setting::tableName(), true)->getColumn('value2') !== null) {
            $this->alterColumn(
                Setting::tableName(),
                'value2',
                $this->text()
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(Setting::tableName(), true)->getColumn('value1') !== null) {
            $this->alterColumn(
                Setting::tableName(),
                'value1',
                $this->string(500)
            );
        }

        if ($this->db->getTableSchema(Setting::tableName(), true)->getColumn('value2') !== null) {
            $this->alterColumn(
                Setting::tableName(),
                'value2',
                $this->string(500)
            );
        }
    }
}
