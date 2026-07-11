<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property integer $paymentMethodID
 * @property string $prefix
 * @property integer $amount
 */
class PaymentMethodExternalVoucher extends ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_paymentmethodexternalvoucher';
    }

    public function rules() {
        return [
            [['paymentMethodID', 'prefix', 'voucherType'], 'required'],
            [['paymentMethodID', 'amount', 'percentageAmount', 'percentageMaxValue'], 'number'],
            [['prefix'], 'string', 'max' => 20]
        ];
    }

    public static function prefixExternalVoucherGiftee($prefixVoucherCode, $paymentMethodID) {
        return PaymentMethodExternalVoucher::find()
            ->where(['prefix' => $prefixVoucherCode])
            ->andWhere(['paymentMethodID' => $paymentMethodID])
            ->one();
    }

    public static function getPrefixExternalVoucherGlobaltix($paymentMethodID, $voucherPrefix) {
        return PaymentMethodExternalVoucher::find()
            ->where(['paymentMethodID' => $paymentMethodID])
            ->andWhere(['prefix' => $voucherPrefix])
            ->andWhere(['voucherType' => 'amount'])
            ->one();
    }
}
