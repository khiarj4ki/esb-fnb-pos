<?php

use app\models\SalesShiftPaymentHead;
use app\models\SalesShiftPaymentDetail;
use yii\db\Migration;

/**
 * Class m201218_084501_add_column_expectedpayment_tr_salesshiftpayment
 */
class m201218_084501_add_column_expectedpayment_tr_salesshiftpayment extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('expectedTotalPaymentNonCash') === null) {
            $this->addColumn(SalesShiftPaymentHead::tableName(),
                'expectedTotalPaymentNonCash',
                $this->decimal(20, 4)->null()->after('actualTotalPaymentNonCash'));
        }
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('expectedTotalPaymentCash') === null) {
            $this->addColumn(SalesShiftPaymentHead::tableName(),
                'expectedTotalPaymentCash',
                $this->decimal(20, 4)->null()->after('actualTotalPaymentCash'));
        }
        if ($this->db->getTableSchema(SalesShiftPaymentDetail::tableName(), true)->getColumn('expectedPaymentAmount') === null) {
            $this->addColumn(SalesShiftPaymentDetail::tableName(),
                'expectedPaymentAmount',
                $this->decimal(20, 4)->null()->after('actualPaymentAmount'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('expectedTotalPaymentNonCash') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(),
                'expectedTotalPaymentNonCash');
        }
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('expectedTotalPaymentCash') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(),
                'expectedTotalPaymentCash');
        }
        if ($this->db->getTableSchema(SalesShiftPaymentDetail::tableName(), true)->getColumn('expectedPaymentAmount') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(),
                'expectedPaymentAmount');
        }
    }
}
