<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m210727_073414_add_column_ms_paymentmethod
 */
class m210727_073414_add_column_ms_paymentmethod extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('cardNumberValidationTypeID') === null) {
            $this->addColumn(
                PaymentMethod::tableName(),
                'cardNumberValidationTypeID',
                $this->integer(11)->after('posExternalPaymentID')->null()
            );
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagMandatoryCardNumber') === null) {
            $this->addColumn(
                PaymentMethod::tableName(),
                'flagMandatoryCardNumber',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->after('fixedAmount')->null()
            );
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagMandatoryVerificationCode') === null) {
            $this->addColumn(
                PaymentMethod::tableName(),
                'flagMandatoryVerificationCode',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->after('flagMandatoryCardNumber')->null()
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('cardNumberValidationTypeID') !== null) {
            $this->dropColumn(
                PaymentMethod::tableName(),
                'cardNumberValidationTypeID'
            );
        }
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagMandatoryCardNumber') !== null) {
            $this->dropColumn(
                PaymentMethod::tableName(),
                'companyID'
            );
        }
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagMandatoryVerificationCode') !== null) {
            $this->dropColumn(
                PaymentMethod::tableName(),
                'companyID'
            );
        }
    }
}
