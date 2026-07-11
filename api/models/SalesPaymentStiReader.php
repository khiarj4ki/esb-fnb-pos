<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salespaymentstireader".
 *
 * @property string $ID
 * @property string $TID
 * @property string $salesNum
 * @property float $remainBalance
 * @property int $branchID
 * @property string $createdBy
 * @property string $createdDate
 * 
 * @property PaymentMethod $paymentMethod
 */
class SalesPaymentStiReader extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salespaymentstireader';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['ID', 'TID', 'MID', 'salesNum', 'branchID', 'remainBalance', 'branchID', 'createdBy', 'createdDate'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'TID' => 'TID',
            'MID' => 'TID',
            'salesNum' => 'Sales Number',
            'remainBalance' => 'Remaining Balance',
            'branchID' => 'Branch ID',
            'createdBy' => 'Create By',
            'createdDate' => 'created Date'
        ];
    }

    public static function getRemainingBalance($salesNum)
    {
        return self::find()
            ->where(['salesNum' => $salesNum])
            ->one();
    }

}
