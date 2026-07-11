<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200911_063845_add_fee_order_tr_saleshead
 */
class m200911_063845_add_fee_order_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('orderFee') === null) {
            $this->addColumn(SalesHead::tableName(), 'orderFee',
                $this->decimal(20, 4)->after('deliveryCost'));
            
            $this->update(SalesHead::tableName(), ['orderFee' => 0]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('orderFee') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'orderFee');
        }
    }
}
