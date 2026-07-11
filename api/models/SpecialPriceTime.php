<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_specialpricetime".
 *
 * @property integer $ID
 * @property integer $specialPriceID
 * @property string $startTime
 * @property string $endTime
 */
class SpecialPriceTime extends ActiveRecord {

    public static function tableName() {
        return 'ms_specialpricetime';
    }

    public function rules() {
        return [
            [['specialPriceID', 'startTime', 'endTime'], 'required'],
            [['endTime'], 'validateDate'],
            [['specialPriceID', 'ID'], 'integer'],
            [['specialPriceID', 'ID'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'ID' => Yii::t('app', 'ID'),
            'specialPriceID' => Yii::t('app', 'Special Price ID'),
            'menuID' => Yii::t('app', 'Menu ID'),
            'price' => Yii::t('app', 'Price'),
        ];
    }

    public function validateDate($attribute) {
        if ($this->endTime < $this->startTime) {
            $this->addError($attribute, 'End time must be greater than start time');
            return false;
        }
    }
    
}
