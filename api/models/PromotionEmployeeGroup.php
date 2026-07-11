<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionemployeegroup".
 *
 * @property int $promotionID
 * @property int $employeeGroupID
 */
class PromotionEmployeeGroup extends ActiveRecord {

    public static function tableName() {
        return 'ms_promotionemployeegroup';
    }

    public function rules() {
        return [
            [['promotionID', 'employeeGroupID'], 'required'],
            [['promotionID', 'employeeGroupID'], 'integer']
        ];
    }

    public function attributeLabels() {
        return [
            'employeeGroupID' => Yii::t('app', 'Employee Group ID'),
            'employeeCode' => Yii::t('app', 'Employee Code')
        ];
    }

}
