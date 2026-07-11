<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200624_022813_add_column_terminalid_tr_saleshead
 */
class m200624_022813_add_column_terminalid_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('terminalID') === null) {
            $this->addColumn(SalesHead::tableName(), 'terminalID',
                $this->string(50)->after('externalCancelTransID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('terminalID') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'terminalID');
        }
    }
}
