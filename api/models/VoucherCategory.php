<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_vouchercategory".
 *
 * @property int $voucherCategoryID
 * @property string $voucherCategoryName
 */
class VoucherCategory extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_vouchercategory';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['voucherCategoryID', 'voucherCategoryName'], 'required'],
            [['voucherCategoryID'], 'integer'],
            [['voucherCategoryID'], 'unique'],
            [['voucherCategoryName'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'voucherCategoryID' => 'Voucher Category',
            'voucherCategoryName' => 'Voucher Category'
        ];
    }

}
