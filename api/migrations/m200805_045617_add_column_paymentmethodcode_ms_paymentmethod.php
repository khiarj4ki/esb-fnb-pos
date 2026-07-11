<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200805_045617_add_column_paymentmethodcode_ms_paymentmethod
 */
class m200805_045617_add_column_paymentmethodcode_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('paymentMethodCode') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'paymentMethodCode',
                $this->string(50)->after('paymentMethodName'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('paymentMethodCode') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'paymentMethodCode');
        }
    }
}
