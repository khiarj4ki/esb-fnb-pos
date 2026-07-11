<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200420_044351_add_column_voucherSourceID_ms_paymentmethod
 */
class m200420_044351_add_column_voucherSourceID_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherSourceID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'voucherSourceID',
                $this->integer()->after('voucherTypeID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherSourceID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'voucherSourceID');
        }
    }
}
