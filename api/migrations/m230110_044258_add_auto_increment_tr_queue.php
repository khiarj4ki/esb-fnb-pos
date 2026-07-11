<?php

use app\models\Queue;
use yii\db\Migration;

/**
 * Class m230110_044258_add_auto_increment_tr_queue
 */
class m230110_044258_add_auto_increment_tr_queue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Queue::tableName(), true) !== null) {
            $this->dropTable(Queue::tableName());
        }
        
        if ($this->db->getTableSchema(Queue::tableName(), true) === null) {
            $this->createTable(Queue::tableName(),
            [
                'id' => $this->primaryKey(11),
                'salesNum' => $this->string(50),
                'type' => $this->string(50)
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(Queue::tableName(), true) !== null) {
            $this->dropTable(Queue::tableName());
        }
    }

}
