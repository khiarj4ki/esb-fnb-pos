<?php

namespace app\models;
use Yii;
use yii\db\ActiveRecord;


/**
 * This is the model class for table "ms_visitpurposegroup".
 *
 * @property int $cardNumberValidationTypeID 
 * @property int $cardNumberValidationName 
 */
class LkCardNumberValidationType extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'lk_cardnumbervalidationtype';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['cardNumberValidationTypeID', 'cardNumberValidationName'], 'required'],
            [['cardNumberValidationTypeID', 'cardNumberValidationName'], 'integer'],
            [
                [
                'cardNumberValidationTypeID', 'cardNumberValidationName'
                ], 'safe'
            ]
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        return true;
    }
    
}
