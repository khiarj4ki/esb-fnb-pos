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
 * @property string $voucherSalesPrice
 * @property string $syncDate
 * 
 * @property Voucher $voucher
 */
class SalesVoucher extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesvoucher';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID'], 'integer'],
            [['salesNum', 'voucherID', 'voucherSalesPrice'], 'required'],
            [['voucherSalesPrice'], 'number'],
            [['syncDate'], 'safe'],
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
            'voucherSalesPrice' => 'Voucher Sales Price',
            'syncDate' => 'Sync Date'
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
