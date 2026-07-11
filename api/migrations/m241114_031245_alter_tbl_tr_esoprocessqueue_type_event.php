<?php

use app\models\EsoProcessQueue;
use yii\db\Migration;

/**
 * Class m241114_031245_alter_tbl_tr_esoprocessqueue_type_event
 */
class m241114_031245_alter_tbl_tr_esoprocessqueue_type_event extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('eventType') === null) {
            $this->addColumn(EsoProcessQueue::tableName(), 'eventType',
                $this->string(10)->notNull()->defaultValue(EsoProcessQueue::TYPE_NEW));
        }

        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('salesNum') === null) {
            $this->addColumn(EsoProcessQueue::tableName(), 'salesNum',
                $this->string(20)->after("eventType"));
        }

        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('voidNotes') === null) {
            $this->addColumn(EsoProcessQueue::tableName(), 'voidNotes',
                $this->string(200)->after("salesNum"));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('eventType') !== null) {
            $this->dropColumn(EsoProcessQueue::tableName(), 'eventType');
        }
        
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('salesNum') !== null) {
            $this->dropColumn(EsoProcessQueue::tableName(), 'salesNum');
        }

        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('voidNotes') !== null) {
            $this->dropColumn(EsoProcessQueue::tableName(), 'voidNotes');
        }
    }
}
