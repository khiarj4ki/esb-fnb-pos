<?php

use app\models\TerminalSetting;
use yii\db\Migration;

/**
 * Class m250116_064457_adjust_ms_terminal_setting_field_value_max
 */
class m250116_064457_adjust_ms_terminal_setting_field_value_max extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(TerminalSetting::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(
                TerminalSetting::tableName(),
                'value',
                $this->string(100)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(TerminalSetting::tableName(), true)->getColumn('value') !== null) {
            $this->alterColumn(
                TerminalSetting::tableName(),
                'value',
                $this->string(50)
            );
        }
    }

}
