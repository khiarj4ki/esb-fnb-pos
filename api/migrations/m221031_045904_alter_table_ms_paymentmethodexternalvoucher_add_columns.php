<?php

use app\models\PaymentMethodExternalVoucher;
use yii\db\Migration;

/**
 * Class m221031_045904_alter_table_ms_paymentmethodexternalvoucher_add_columns
 */
class m221031_045904_alter_table_ms_paymentmethodexternalvoucher_add_columns extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('amount') !== null) {
            $this->alterColumn(PaymentMethodExternalVoucher::tableName(), 'amount', $this->decimal(20,4));
        }

        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('voucherType') === null) {
            $this->addColumn(PaymentMethodExternalVoucher::tableName(), 'voucherType', $this->string(50)->notNull()->after('prefix'));
        }

        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('percentageAmount') === null) {
            $this->addColumn(PaymentMethodExternalVoucher::tableName(), 'percentageAmount', $this->decimal(20,4)->after('amount'));
        }

        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('percentageMaxValue') === null) {
            $this->addColumn(PaymentMethodExternalVoucher::tableName(), 'percentageMaxValue', $this->decimal(20,4)->after('percentageAmount'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('amount') !== null) {
            $this->alterColumn(PaymentMethodExternalVoucher::tableName(), 'amount', $this->decimal(20,4)->notNull());
        }
        
        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('voucherType') !== null) {
            $this->dropColumn(PaymentMethodExternalVoucher::tableName(), 'voucherType');
        }

        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('percentageAmount') !== null) {
            $this->dropColumn(PaymentMethodExternalVoucher::tableName(), 'percentageAmount');
        }

        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true)->getColumn('percentageMaxValue') !== null) {
            $this->dropColumn(PaymentMethodExternalVoucher::tableName(), 'percentageMaxValue');
        }

    }
}
