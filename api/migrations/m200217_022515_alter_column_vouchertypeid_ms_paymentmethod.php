<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200217_022515_alter_column_vouchertypeid_ms_paymentmethod
 */
class m200217_022515_alter_column_vouchertypeid_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherTypeID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'voucherTypeID',
                $this->integer(11)->after('paymentMethodTypeID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherTypeID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'voucherTypeID');
        }
    }
}
