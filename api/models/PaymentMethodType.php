<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_paymentmethodtype".
 *
 * @property int $paymentMethodTypeID
 * @property string $paymentMethodTypeName
 * 
 * @property PaymentMethod[] $paymentMethods
 */
class PaymentMethodType extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_paymentmethodtype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['paymentMethodTypeID'], 'required'],
            [['paymentMethodTypeID'], 'integer'],
            [['paymentMethodTypeID'], 'unique'],
            [['paymentMethodTypeName'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'paymentMethodTypeID' => 'Payment Method Type ID',
            'paymentMethodTypeName' => 'Payment Method Type Name'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['paymentMethod'] = function ($model) {
            return $model->paymentMethods;
        };

        return $fields;
    }

    public function getPaymentMethods() {
        return $this->hasMany(PaymentMethod::class,
                    ['paymentMethodTypeID' => 'paymentMethodTypeID'])
                ->andOnCondition([PaymentMethod::tableName() . '.flagActive' => 1]);
    }

}
