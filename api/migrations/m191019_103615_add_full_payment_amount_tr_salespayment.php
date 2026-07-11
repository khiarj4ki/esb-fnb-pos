<?php
use app\models\SalesPayment;
use yii\db\Expression;
use yii\db\Migration;

/**
 * Class m191019_103615_add_full_payment_amount_tr_salespayment
 */
class m191019_103615_add_full_payment_amount_tr_salespayment extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('fullPaymentAmount') === null) {
            $this->addColumn(SalesPayment::tableName(), 'fullPaymentAmount',
                $this->decimal(20, 4)->after('paymentAmount'));

            $this->update(SalesPayment::tableName(),
                ['fullPaymentAmount' => new Expression('paymentAmount')]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('fullPaymentAmount') !== null) {
            $this->dropColumn(SalesPayment::tableName(), 'fullPaymentAmount');
        }
    }

}
