<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionday".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $dayID
 */
class PromotionDay extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotionday';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'dayID'], 'required'],
            [['promotionID', 'dayID'], 'integer'],
            [['ID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'dayID' => 'Day ID'
        ];
    }

}
