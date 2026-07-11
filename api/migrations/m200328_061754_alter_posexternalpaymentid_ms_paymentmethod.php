<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200328_061754_alter_posexternalpaymentid_ms_paymentmethod
 */
class m200328_061754_alter_posexternalpaymentid_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('posExternalPaymentID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'posExternalPaymentID',
                $this->string(10)->after('paymentMethodName'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('posExternalPaymentID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'posExternalPaymentID');
        }
    }
}
