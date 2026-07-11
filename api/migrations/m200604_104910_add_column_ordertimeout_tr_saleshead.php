<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200604_104910_add_column_ordertimeout_tr_saleshead
 */
class m200604_104910_add_column_ordertimeout_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('orderTimeOut') === null) {
            $this->addColumn(SalesHead::tableName(), 
                'orderTimeOut',
                $this->dateTime()->defaultValue(NULL)->after('salesDateIn'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('orderTimeOut') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'orderTimeOut');
        }
    }
}
