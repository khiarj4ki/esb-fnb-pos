<?php

namespace app\models;

use app\components\AppHelper;
use DateTime;
use Exception;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_customnumber".
 * @property string $salesNum
 * @property string $customNum
 */
class CustomNumber extends ActiveRecord
{
    public static function tableName()
    {
        return 'tr_customnumber';
    }

    public function rules()
    {
        return [
            [['salesNum', 'customNum'], 'required']
        ];
    }

    public static function findBySalesNum($salesNum)
    {
        $model = self::find()
            ->where(['salesNum' => $salesNum])
            ->one();
        if ($model) {
            return $model->customNum;
        } else {
            return null;
        }
    }

    public static function saveCustomNumber($salesModel)
    {
        $currentTimestamp = new DateTime();
        $currentTimestamp = $currentTimestamp->format('Y-m-d H:i:s');
        $salesNum = $salesModel->salesNum;

        $randomDigit = substr($salesNum, -3);
        $year = substr($currentTimestamp, 2, 2);
        $month = substr($currentTimestamp, 5, 2);
        $date = substr($currentTimestamp, 8, 2);
        $hour = substr($currentTimestamp, 11, 2);
        $minute = substr($currentTimestamp, 14, 2);
        $billOrder = substr($salesModel->billNum, -4);

        $customNum = $randomDigit . $year . $month . $date . $hour . $minute . $billOrder;

        $customNumberModel = new CustomNumber();
        $customNumberModel->salesNum = $salesNum;
        $customNumberModel->customNum = $customNum;
        if (!$customNumberModel->save()) {
            throw new Exception('Failed to save Custom Number');
        }
    }
}
