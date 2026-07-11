<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotiontime".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $dayID
 */
class PromotionTime extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotiontime';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID'], 'required'],
            [['promotionID'], 'integer'],
            [['ID', 'startTime', 'endTime'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'startTime' => 'Start Time',
            'endTime' => 'End Time'
        ];
    }

}
