<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200430_015539_add_column_delivery_cost_tr_saleshead
 */
class m200430_015539_add_column_delivery_cost_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('deliveryCost') === null) {
            $this->addColumn(SalesHead::tableName(), 'deliveryCost',
                $this->decimal(20,4)->after('vatTotal'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('deliveryCost') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'deliveryCost');
        }
    }
}
