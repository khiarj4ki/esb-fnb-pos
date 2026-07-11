<?php

namespace app\models;

use yii\db\ActiveRecord;

class SalesConditionalPromo extends ActiveRecord {
    public static function tableName() {
        return 'tr_salesconditionalpromo';
    }

    public function rules() {
        return [
            [['salesNum', 'conditionalPromoID'], 'required'],
        ];
    }
}