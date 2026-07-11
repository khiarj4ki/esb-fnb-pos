<?php

namespace app\models;
use yii\db\ActiveRecord;

class SalesShiftPaymentDenom extends ActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'tr_salesshiftpaymentdenom';
    }

    public function rules() {
        return [
            [['salesShiftPaymentHeadID', 'denomAmount', 'denomQty', 'denomTotal'], 'required'],
            [['localID', 'syncDate'], 'safe']
        ];
    }

}
