<?php

use app\models\Branch;
use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m211227_063746_insert_payment_method_gift_voucher
 */
class m211227_063746_insert_payment_method_gift_voucher extends Migration
{
    public function up() {
        if (!PaymentMethod::find()->where(['paymentMethodID' => -1])->exists()) {
            $branchID = Branch::find()->select('branchID')->scalar();
            $this->insert(PaymentMethod::tableName(),
                [
                    'paymentMethodID' => -1, 
                    'paymentMethodTypeID' => 4, 
                    'voucherTypeID' => '',
                    'voucherSourceID' => 1,
                    'voucherCategoryID' => 2,
                    'paymentMethodName' => 'ESB Voucher',
                    'paymentMethodCode' => 'ESBVOUCHER',
                    'posExternalPaymentID' => null,
                    'cardNumberValidationTypeID' => 0,
                    'branchID' => $branchID,
                    'parentID' => 0,
                    'coaNo' => '0 0 0 0',
                    'printedCount' => 1,
                    'fixedAmount' => null,
                    'flagMandatoryCardNumber' => 0,
                    'flagMandatoryVerificationCode' => 0,
                    'flagOpenCashDrawer' => 0,
                    'flagAuthorization' => 0,
                    'flagUseEmployeeLimit' => 0,
                    'flagActive' => 1,
                    'createdBy' => 'MIGRATION',
                    'createdDate' => date('Y-m-d H:i:s'),
                    'editedBy' => 'MIGRATION',
                    'editedDate' => date('Y-m-d H:i:s'),
                    'syncDate' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (PaymentMethod::find()->where(['paymentMethodID' => -1])->exists()) {
            PaymentMethod::deleteAll(['paymentMethodID' => -1]);
        }
    }
}
