<?php
namespace app\models;

use yii\db\ActiveRecord;

class SalesPaymentGateway extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salespaymentgateway';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'selfOrderIdKiosk'], 'required'],
            [['salesNum'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'salesNum' => 'Sales Num',
            'selfOrderIdKiosk' => 'Self Order ID Kiosk'
        ];
    }

}
