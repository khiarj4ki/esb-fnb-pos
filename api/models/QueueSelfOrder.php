<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_esoqueue".
 *
 * @property int $id
 * @property string $salesNum
 * @property string $orderID
 * @property string $type
 */
class QueueSelfOrder extends ActiveRecord {
    const TYPE_SALESNUM = 'salesNum';
    const TYPE_SALES_VOID = 'VOID';
    const TYPE_SALES_FINISH = 'FINISH';
    
    public static function tableName() {
        return 'tr_esoqueue';
    }
    
    public function rules() {
        return [
            [['salesNum', 'orderID'], 'required'],
            [['salesNum', 'orderID', 'type'], 'string', 'max' => 50],
        ];
    }
    
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Number',
            'orderID' => 'Order ID',
            'type' => 'Type'
        ];
    }
}
