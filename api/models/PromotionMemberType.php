<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lk_promotionmembertype".
 *
 * @property int $promotionMemberTypeID
 * @property string $promotionMemberTypeName
 */
class PromotionMemberType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lk_promotionmembertype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['promotionMemberTypeName'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'promotionMemberTypeID' => 'Promotion Member Type ID',
            'promotionMemberTypeName' => 'Promotion Member Type Name',
        ];
    }
}
