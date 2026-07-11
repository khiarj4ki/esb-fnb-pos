<?php

use app\models\TerminalSetting;
use yii\db\Migration;

/**
 * Class m240131_030430_alter_column_value_ms_terminalsetting
 */
class m240131_030430_alter_column_value_ms_terminalsetting extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(TerminalSetting::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(
                TerminalSetting::tableName(),
                'value',
                $this->string(50)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(TerminalSetting::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(
                TerminalSetting::tableName(),
                'value',
                $this->string(20)
            );
        }
    }
}
