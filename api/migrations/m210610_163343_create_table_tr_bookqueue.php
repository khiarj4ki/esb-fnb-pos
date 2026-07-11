<?php

use app\models\TrBookQueue;
use yii\db\Migration;

/**
 * Class m210610_163343_create_table_tr_bookqueue
 */
class m210610_163343_create_table_tr_bookqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(TrBookQueue::tableName(), true) === null) {
            $this->createTable(TrBookQueue::tableName(),
                [
                    'salesNum' => $this->string(50)->notNull(),
                    'actionType' => $this->string(20)->notNull()
                ]
            );
            $this->addPrimaryKey('trbook_pk', TrBookQueue::tableName(), ['salesNum', 'actionType']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(TrBookQueue::tableName(), true) !== null) {
            $this->dropTable(TrBookQueue::tableName());
        }
    }
}
