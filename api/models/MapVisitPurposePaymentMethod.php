<?php

namespace app\models;
use Yii;
use yii\db\ActiveRecord;


/**
 * This is the model class for table "ms_visitpurposegroup".
 *
 * @property int $paymentMethodID 
 * @property int $visitPurposeID 
 */
class MapVisitPurposePaymentMethod extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'map_visitpurposepaymentmethod';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['paymentMethodID', 'visitPurposeID'], 'required'],
            [['paymentMethodID', 'visitPurposeID'], 'integer'],
            [
                [
                'paymentMethodID', 'visitPurposeID'
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
