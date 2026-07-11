<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m220711_083751_add_tr_saleshead_deliveryTime
 */
class m220711_083751_add_tr_saleshead_deliveryTime extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('deliveryTime') === null) {
            $this->addColumn(SalesHead::tableName(), 'deliveryTime',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->defaultValue(null)->after('transactionModeID'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('deliveryTime') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'deliveryTime');
        }
    }
}
