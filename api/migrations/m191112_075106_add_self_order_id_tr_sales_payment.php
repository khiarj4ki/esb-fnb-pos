<?php
use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m191112_075106_add_self_order_id_tr_sales_payment
 */
class m191112_075106_add_self_order_id_tr_sales_payment extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('selfOrderID') === null) {
            $this->addColumn(SalesPayment::tableName(), 'selfOrderID',
                $this->string(50)->after('accountName'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('selfOrderID') !== null) {
            $this->dropColumn(SalesPayment::tableName(), 'selfOrderID');
        }
    }

}
