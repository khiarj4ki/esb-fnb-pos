<?php

namespace app\models;

use app\components\AppHelper;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salespaymentgateway".
 *
 * @property string $salesPaymentGatewayNum
 * @property string $salesNum
 */
class SalesShiftPaymentDetail extends ActiveRecord {
    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'tr_salesshiftpaymentdetail';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['salesShiftPaymentHeadID', 'actualPaymentAmount', 'expectedPaymentAmount', 'paymentMethodID'], 'required']
        ];
    }

}
