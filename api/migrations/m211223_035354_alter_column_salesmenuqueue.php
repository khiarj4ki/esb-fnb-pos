<?php

use app\models\SalesMenuQueue;
use yii\db\Migration;

/**
 * Class m211223_035354_alter_column_salesmenuqueue
 */
class m211223_035354_alter_column_salesmenuqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true)->getColumn('salesMenu') !== null) {
            $this->alterColumn(SalesMenuQueue::tableName(), 'salesMenu',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('mediumtext'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true)->getColumn('salesMenu') !== null) {
            $this->alterColumn(SalesMenuQueue::tableName(), 'salesMenu', 
            $this->getDb()->getSchema()->createColumnSchemaBuilder('text'));
        }
    }
}
