<?php

use app\models\DeviceTransaction;
use yii\db\Migration;

/**
 * Class m200701_022639_add_column_tr_devicetransaction
 */
class m200701_022639_add_column_tr_devicetransaction extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(DeviceTransaction::tableName(), true)->getColumn('syncDate') === null) {
            $this->addColumn(DeviceTransaction::tableName(), 'syncDate', 'DATETIME NULL AFTER `deviceMacAddress`');
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(DeviceTransaction::tableName(), true)->getColumn('syncDate') !== null) {
            $this->dropColumn(DeviceTransaction::tableName(), 'syncDate');
        }
    }
}
