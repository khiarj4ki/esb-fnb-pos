<?php

use app\models\PaymentMethod;
use yii\db\Migration;
use app\models\SalesPayment;

/**
 * Class m210309_034757_add_column_voucherCategoryID_tr_salespayment
 */
class m210309_034757_add_column_voucherCategoryID_tr_salespayment extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCategoryID') === null) {
            $this->addColumn(SalesPayment::tableName(), 'voucherCategoryID',
                $this->integer()->after('voucherCode'));

            $salesPaymentTableName = SalesPayment::tableName();
            $paymentMethodTableName = PaymentMethod::tableName();
            Yii::$app->db->createCommand("UPDATE $salesPaymentTableName a
                JOIN $paymentMethodTableName b on a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 5
                SET a.voucherCategoryID = 1
                WHERE a.voucherCategoryID IS NULL")->execute();
            Yii::$app->db->createCommand("UPDATE $salesPaymentTableName a
            JOIN $paymentMethodTableName b on a.paymentMethodID = b.paymentMethodID AND b.paymentMethodTypeID = 4
            SET a.voucherCategoryID = 2
            WHERE a.voucherCategoryID IS NULL")->execute();
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCategoryID') !== null) {
            $this->dropColumn(SalesPayment::tableName(), 'voucherCategoryID');
        }
    }
}
