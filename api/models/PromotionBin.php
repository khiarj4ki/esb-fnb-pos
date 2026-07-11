<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotiondetail".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $menuID
 * @property string $qty
 */
class PromotionBin extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotionbin';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'bankIdentificationNumber'], 'required'],
            [['bankIdentificationNumber'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'bankIdentificationNumber' => 'Bank Identification Number'
        ];
    }

    public function getPromotionHead() {
        return $this->hasOne(PromotionHead::class,['promotionID' => 'promotionID']);
    }

}
