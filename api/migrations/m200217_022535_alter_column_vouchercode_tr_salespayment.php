<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m200217_022535_alter_column_vouchercode_tr_salespayment
 */
class m200217_022535_alter_column_vouchercode_tr_salespayment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCode') === null) {
            $this->addColumn(SalesPayment::tableName(), 'voucherCode',
                $this->string(50)->after('paymentMethodTypeID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCode') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'voucherCode');
        }
    }
}
