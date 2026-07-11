<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200605_060845_add_column_locktable_tr_saleshead
 */
class m200605_060845_add_column_locktable_tr_saleshead extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('lockTable') === null) {
            $this->addColumn(SalesHead::tableName(), 'lockTable',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(2)')->after('flagInclusive'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('lockTable') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'lockTable');
        }
    }
}
