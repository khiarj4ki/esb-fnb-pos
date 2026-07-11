<?php

use app\models\Device;
use yii\db\Migration;

/**
 * Class m210319_035912_create_ms_device
 */
class m210319_035912_create_ms_device extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Device::tableName(), true) === null) {
            $this->createTable(Device::tableName(),
                [
                'ipAddress' => $this->string(50)->notNull(),
                'terminalID' => $this->string(50)->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', Device::tableName(),
                ['ipAddress', 'terminalID']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Device::tableName(), true) !== null) {
            $this->dropTable(Device::tableName());
        }
    }
}
