<?php

use app\models\SalesMenuQueue;
use yii\db\Migration;

/**
 * Class m200916_022254_create_tr_salesmenuqueue
 */
class m200916_022254_create_tr_salesmenuqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true) === null) {
            $this->createTable(SalesMenuQueue::tableName(), [
                'salesNum' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'salesMenu' => $this->text()->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true) !== null) {
            $this->dropTable(SalesMenuQueue::tableName());
        }
    }
}
