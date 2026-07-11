<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m200420_133439_add_column_externalvoucher_tr_salespayment
 */
class m200420_133439_add_column_externalvoucher_tr_salespayment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('canceledVerificationCode') === null) {
            $this->addColumn(SalesPayment::tableName(), 'canceledVerificationCode',
                $this->string(100)->after('verificationCode'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('flagExternalVoucherAPI') === null) {
            $this->addColumn(SalesPayment::tableName(), 'flagExternalVoucherAPI',
                $this->integer(1)->after('canceledVerificationCode'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalVoucherCode') === null) {
            $this->addColumn(SalesPayment::tableName(), 'externalVoucherCode',
                $this->string(50)->after('flagExternalVoucherAPI'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalTransactionId') === null) {
            $this->addColumn(SalesPayment::tableName(), 'externalTransactionId',
                $this->string(50)->after('externalVoucherCode'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalBatchNumber') === null) {
            $this->addColumn(SalesPayment::tableName(), 'externalBatchNumber',
                $this->string(50)->after('externalTransactionId'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalCanceledTransactionId') === null) {
            $this->addColumn(SalesPayment::tableName(), 'externalCanceledTransactionId',
                $this->string(50)->after('externalBatchNumber'));
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalCanceledBatchNumber') === null) {
            $this->addColumn(SalesPayment::tableName(), 'externalCanceledBatchNumber',
                $this->string(50)->after('externalCanceledTransactionId'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('canceledVerificationCode') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'canceledVerificationCode');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('flagExternalVoucherAPI') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'flagExternalVoucherAPI');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalVoucherCode') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'externalVoucherCode');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalTransactionId') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'externalTransactionId');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalBatchNumber') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'externalBatchNumber');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalCanceledTransactionId') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'externalCanceledTransactionId');
        }
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('externalCanceledBatchNumber') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'externalCanceledBatchNumber');
        }
    }
}
