<?php

use app\models\QueueSelfOrder;
use yii\db\Migration;

/**
 * Class m221004_065256_create_tbl_queue_selforder
 */
class m221004_065256_create_tbl_queue_selforder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true) === null) {
            $this->createTable(QueueSelfOrder::tableName(),
                [
                'salesNum' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'orderID' => $this->string(50)
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true) !== null) {
            $this->dropTable(QueueSelfOrder::tableName());
        }
    }
}
