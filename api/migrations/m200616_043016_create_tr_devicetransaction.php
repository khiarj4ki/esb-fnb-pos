<?php

use app\models\DeviceTransaction;
use yii\db\Migration;

/**
 * Class m200616_043016_create_tr_devicetransaction
 */
class m200616_043016_create_tr_devicetransaction extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(DeviceTransaction::tableName(), true) === null) {
            $this->createTable(DeviceTransaction::tableName(),
                [
                    'transactionDate' => $this->date()->notNull(),
                    'deviceMacAddress' => $this->string(50)->notNull(),
            ]);

            $this->addPrimaryKey('PRIMARYKEY', DeviceTransaction::tableName(),
                ['transactionDate', 'deviceMacAddress']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(DeviceTransaction::tableName(), true) !== null) {
            $this->dropTable(DeviceTransaction::tableName());
        }
    }
}
