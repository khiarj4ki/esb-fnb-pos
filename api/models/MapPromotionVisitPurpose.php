<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "MapPromotionVisitPurpose".
 *
 * @property int $promotionID
 * @property int $visitPurposeID
 */
class MapPromotionVisitPurpose extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'map_promotionvisitpurpose';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['ID', 'promotionID', 'visitPurposeID'], 'required'],
            [['ID', 'promotionID', 'visitPurposeID'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'promotionID' => 'Promotion ID',
            'visitPurposeID' => 'Visit Purpose ID',
        ];
    }
}