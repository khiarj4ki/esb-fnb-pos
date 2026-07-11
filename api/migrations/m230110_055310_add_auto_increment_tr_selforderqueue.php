<?php

use app\models\QueueSelfOrder;
use yii\db\Migration;

/**
 * Class m230110_055310_add_auto_increment_tr_selforderqueue
 */
class m230110_055310_add_auto_increment_tr_selforderqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true) !== null) {
            $this->dropTable(QueueSelfOrder::tableName());
        }

        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true) === null) {
            $this->createTable(QueueSelfOrder::tableName(),
                [
                'id' => $this->primaryKey(),
                'salesNum' => $this->string(50),
                'orderID' => $this->string(50),
                'type' => $this->string(10)
            ]);
        }
    }

    public function down() {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true) !== null) {
            $this->dropTable(QueueSelfOrder::tableName());
        }
    }
}
