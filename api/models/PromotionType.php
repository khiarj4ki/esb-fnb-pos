<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_promotiontype".
 *
 * @property int $promotionTypeID
 * @property string $promotionTypeDesc
 */
class PromotionType extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_promotiontype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionTypeID', 'promotionTypeDesc'], 'required'],
            [['promotionTypeID'], 'integer'],
            [['promotionTypeDesc'], 'string', 'max' => 50],
            [['promotionTypeID'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'promotionTypeID' => 'Promotion Type ID',
            'promotionTypeDesc' => 'Promotion Type Desc',
        ];
    }

}
