<?php

namespace app\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_devicetransaction".
 *
 * @property date $transactionDate
 * @property string $deviceMacAddress
 */
class DeviceTransaction extends ActiveRecord {
    public static function tableName() {
        return 'tr_devicetransaction';
    }

    public function rules() {
        return [
            [['transactionDate', 'syncDate'], 'safe'],
            [['deviceMacAddress'], 'string', 'max' => 50],
        ];
    }

    public function saveTodayMac($deviceMacAddress) {
        try {
            $todayDate = date('Y-m-d');

            $model = self::find()
                ->where(['deviceMacAddress' => strtoupper($deviceMacAddress)])
                ->andWhere(['transactionDate' => $todayDate])
                ->one();

            if (!$model) {
                $model = new DeviceTransaction();
                $model->deviceMacAddress = strtoupper($deviceMacAddress);
                $model->transactionDate = $todayDate;
                $model->save();
            }
        } catch (Exception $ex) {
            Yii::error($ex);
        }

        return true;
    }
    
    public static function syncUpdate($transactionDate, $deviceMacAddress, $syncDate) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            DeviceTransaction::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['transactionDate' => $transactionDate], ['deviceMacAddress' => $deviceMacAddress]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

}
