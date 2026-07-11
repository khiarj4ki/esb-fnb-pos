<?php

use app\models\Device;
use yii\db\Migration;

/**
 * Class m210415_030115_rename_column_ms_device
 */
class m210415_030115_rename_column_ms_device extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(Device::tableName(), true)->getColumn('ipAddress') !== null) {
            $this->renameColumn('ms_device', 'ipAddress', 'macAddress');
        }
    }

    public function down() {
        if ($this->db->getTableSchema(Device::tableName(), true)->getColumn('macAddress') !== null) {
            $this->renameColumn('ms_device', 'macAddress', 'ipAddress');
        }
    }
}
