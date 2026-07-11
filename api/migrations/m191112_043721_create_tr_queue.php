<?php
use app\models\Queue;
use yii\db\Migration;

/**
 * Class m191112_043721_create_tr_queue
 */
class m191112_043721_create_tr_queue extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Queue::tableName(), true) === null) {
            $this->createTable(Queue::tableName(),
                [
                'id' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'type' => $this->string(50)
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Queue::tableName(), true) !== null) {
            $this->dropTable(Queue::tableName());
        }
    }

}
