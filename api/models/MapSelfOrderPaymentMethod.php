<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "fnb_bma.map_selforderpaymentmethod".
 *
 * @property string $selfOrderPaymentMethodID
 * @property int $branchID
 * @property int $paymentMethodID
 */
class MapSelfOrderPaymentMethod extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'map_selforderpaymentmethod';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['selfOrderPaymentMethodID', 'branchID', 'paymentMethodID'], 'required'],
            [['branchID', 'paymentMethodID'], 'integer'],
            [['selfOrderPaymentMethodID'], 'string', 'max' => 10],
            [['selfOrderPaymentMethodID', 'branchID', 'paymentMethodID'], 'unique', 'targetAttribute' => ['selfOrderPaymentMethodID', 'branchID', 'paymentMethodID']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'selfOrderPaymentMethodID' => 'Self Order Payment Method ID',
            'branchID' => 'Branch ID',
            'paymentMethodID' => 'Payment Method ID',
        ];
    }
    
}
