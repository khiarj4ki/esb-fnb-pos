<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_queue".
 *
 * @property string $type
 * @property string $id
 * @property string $salesNum
 */
class Queue extends ActiveRecord {
    const TYPE_SALESNUM = 'salesNum';
    const INVALID_IDENTITY_INTERFACE = 'The identity object must implement IdentityInterface.';
    const COMPANY_NOT_FOUND = 'Company Not Found.';
    const BRANCH_NOT_FOUND = 'Branch Not Found.';
    const INVALID_CREDENTIALS = 'Your request was made with invalid credentials.';
    const REQUEST_DATA_EXPIRED = 'Request data may need to be updated';
    
    public static function tableName() {
        return 'tr_queue';
    }
    
    public function rules() {
        return [
            [['type', 'salesNum'], 'required'],
            [['type', 'salesNum'], 'string', 'max' => 50],
        ];
    }
    
    public function attributeLabels() {
        return [
            'type' => 'Type',
            'salesNum' => 'Sales Number'
        ];
    }
}
