<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenuqueue".
 *
 * @property integer $ID
 * @property string $salesNum
 * @property string $salesMenu
 */
class SalesMenuQueue extends ActiveRecord {
    public static function tableName() {
        return 'tr_salesmenuqueue';
    }
    
    public function rules() {
        return [
            [['ID', 'salesNum', 'salesMenu'], 'required'],
            [['salesNum'], 'string', 'max' => 50]
        ];
    }
    
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'salesNum' => 'Sales Number',
            'salesMenu' => 'Sales Menu'
        ];
    }
}
