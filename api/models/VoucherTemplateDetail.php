<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\data\ActiveDataProvider;

/**
 * This is the model class for table "ms_vouchertemplatedetail".
 *
 * @property int $voucherTemplateDetailID
 * @property int $voucherTemplateID
 * @property int $minSalesPrice
 * @property int $minSalesUsagePrice
 * @property int $maxVoucherAmount
 * @property int $voucherAmount
 * @property int $voucherPercentage
 */
class VoucherTemplateDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_vouchertemplatedetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['voucherTemplateDetailID', 'voucherTemplateID', 'minSalesPrice', 'minSalesUsagePrice', 'maxVoucherAmount', 'voucherAmount', 'voucherPercentage'], 'required'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'voucherTemplateDetailID' => Yii::t('app', 'Voucher Template Detail ID'),
            'voucherTemplateID' => Yii::t('app', 'Voucher Template ID'),
            'minSalesPrice' => Yii::t('app', 'Minimum Sales Price'),
            'minSalesUsagePrice' => Yii::t('app', 'Minimum Sales Usage Price'),
            'maxVoucherAmount' => Yii::t('app', 'Max Voucher Amount'),
            'voucherAmount' => Yii::t('app', 'Voucher Amount'),
            'voucherPercentage' => Yii::t('app', 'Voucher Percentage')
        ];
    }

    public function searchVoucherTemplateDetail() {
        $query = VoucherTemplateDetail::find()
            ->where([VoucherTemplateDetail::tableName() . '.voucherTemplateID' => $this->voucherTemplateID]);
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 0,
            ],
        ]);
        
        return $dataProvider;
    }
}