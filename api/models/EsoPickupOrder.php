<?php

namespace app\models;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_esopickuporder".
 * @property string $salesNum
 * @property string $orderID
 */
class EsoPickupOrder extends ActiveRecord {
    public static function tableName() {
        return 'tr_esopickuporder';
    }

    public function rules() {
        return [
            [['salesNum', 'orderID'], 'required'],
            [['salesNum', 'orderID'], 'string', 'max' => 20]
        ];
    }

    public function getSalesHead() {
      return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
  }
}
