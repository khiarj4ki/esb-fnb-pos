<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_specialpriceday".
 *
 * @property integer $ID
 * @property integer $specialPriceID
 * @property integer $dayID
 */
class SpecialPriceDay extends ActiveRecord {

    public static function tableName() {
        return 'ms_specialpriceday';
    }

    public function rules() {
        return [
            [['specialPriceID', 'dayID'], 'required'],
            [['specialPriceID', 'ID', 'dayID'], 'integer'],
            [['specialPriceID', 'ID', 'dayID'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'ID' => Yii::t('app', 'ID'),
            'specialPriceID' => Yii::t('app', 'Special Price ID'),
            'dayID' => Yii::t('app', 'Day ID'),
        ];
    }

}
