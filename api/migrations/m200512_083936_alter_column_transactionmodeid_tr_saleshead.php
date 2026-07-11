<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200512_083936_alter_column_transactionmodeid_tr_saleshead
 */
class m200512_083936_alter_column_transactionmodeid_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('transactionModeID') === null) {
            $this->addColumn(SalesHead::tableName(), 'transactionModeID',
                $this->integer(11)->after('flagInclusive'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('transactionModeID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'transactionModeID');
        }
    }
}
