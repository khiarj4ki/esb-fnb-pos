<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionprefix".
 *
 * @property int $promotionID
 * @property string $prefix
 */
class PromotionPrefix extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotionprefix';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'prefix'], 'required'],
            [['promotionID'], 'number'],
            [['prefix'], 'string', 'max' => 20]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'promotionID' => 'Promotion ID',
            'prefix' => 'Prefix'
        ];
    }

    public static function prefixPromotionVoucherGiftee($prefixVoucherCode, $promotionID) {
        return PromotionPrefix::find()
            ->where(['prefix' => $prefixVoucherCode])
            ->andWhere(['promotionID' => $promotionID])
            ->one();
    }
}
