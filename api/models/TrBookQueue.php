<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_bookqueue".
 *
 * @property string $salesNum
 * @property string $actionType
 */
class TrBookQueue extends ActiveRecord {
    
    public static function tableName() {
        return 'tr_bookqueue';
    }
    
    public function rules() {
        return [
            [['salesNum', 'actionType'], 'required']
        ];
    }
    
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Number',
            'actionType' => 'Action Type'
        ];
    }
}
