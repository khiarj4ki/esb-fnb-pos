<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesvoucherusage".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property string $voucherID
 * @property string $voucherSalesPrice
 * @property string $syncDate
 * 
 * @property Voucher $voucher
 */
class SalesVoucherUsage extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesvoucherusage';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID'], 'integer'],
            [['salesNum', 'paymentMethodID'], 'required'],
            [['voucherAmount', 'fullVoucherAmount'], 'number'],
            [['syncDate', 'notes', 'coaNo'], 'safe'],
            [['voucherCode'], 'string', 'max' => 50]
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
            'paymentMethodID' => 'Payment Method ID',
            'voucherCode' => 'Voucher Code'
        ];
    }

    public function getVoucher() {
        return $this->hasOne(Voucher::class, ['voucherID' => 'voucherID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

}
