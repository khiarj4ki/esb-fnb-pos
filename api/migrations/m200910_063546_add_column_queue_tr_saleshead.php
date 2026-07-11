<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200910_063546_add_column_queue_tr_saleshead
 */
class m200910_063546_add_column_queue_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('queueNum') === null) {
            $this->addColumn(SalesHead::tableName(), 'queueNum',
                $this->integer()->after('billNum')->defaultValue(NULL));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('queueNum') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'queueNum');
        }
    }
}
