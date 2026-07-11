<?php

namespace app\models;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_externaltoken".
 *
 * @property string $terminalID
 * @property string $token
 * @property string $transactionID
 * @property string $batchID
 */
class ExternalToken extends ActiveRecord {
    public static function tableName() {
        return 'ms_externaltoken';
    }

    public function rules() {
        return [
            [['terminalID', 'token'], 'required'],
            [['terminalID', 'transactionID', 'batchID'], 'string', 'max' => 50]
        ];
    }
}
