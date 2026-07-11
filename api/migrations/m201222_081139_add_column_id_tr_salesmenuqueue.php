<?php

use app\models\SalesMenuQueue;
use yii\db\Migration;

/**
 * Class m201222_081139_add_column_id_tr_salesmenuqueue
 */
class m201222_081139_add_column_id_tr_salesmenuqueue extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true)->getColumn('ID') === null) {
            $this->truncateTable(SalesMenuQueue::tableName());
            $this->addColumn(SalesMenuQueue::tableName(), 'ID',
                $this->bigInteger(20)->first());
        }        
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true)->getColumn('salesNum') !== null) {
            $this->dropPrimaryKey('salesNum', SalesMenuQueue::tableName());
            $this->addPrimaryKey('PRIMARYKEY', SalesMenuQueue::tableName(), 'ID');
        }
        

    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesMenuQueue::tableName(), true)->getColumn('ID') !== null) {
            $this->dropColumn(SalesMenuQueue::tableName(), 'ID');
            $this->addPrimaryKey('PRIMARYKEY', SalesMenuQueue::tableName(), 'salesNum');
        }
    }
}
