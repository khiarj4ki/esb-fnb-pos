<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesvoucher".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property string $voucherID
 * @property string $voucherAmount
 * @property string $voucherSalesPrice
 */
class SalesVoucherOnline extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesvoucheronline';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID'], 'integer'],
            [['salesNum', 'voucherID', 'voucherAmount', 'voucherSalesPrice'], 'required'],
            [['voucherAmount', 'voucherSalesPrice'], 'number'],
            [['salesNum'], 'string', 'max' => 50],
            [['voucherID'], 'string', 'max' => 20]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'voucherID' => 'Voucher ID',
            'voucherAmount' => 'Voucher Amount',
            'voucherSalesPrice' => 'Voucher Sales Price'
        ];
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

}
