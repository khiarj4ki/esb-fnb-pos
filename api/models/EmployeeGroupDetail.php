<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_employeegroupdetail".
 *
 * @property int $employeeGroupID
 * @property int $employeeCode
 */
class EmployeeGroupDetail extends ActiveRecord {

    public static function tableName() {
        return 'ms_employeegroupdetail';
    }

    public function rules() {
        return [
            [['employeeGroupID', 'employeeCode'], 'required'],
            [['employeeGroupID'], 'integer'],
            [['employeeCode'], 'string', 'max' => 50]
        ];
    }

    public function attributeLabels() {
        return [
            'employeeGroupID' => Yii::t('app', 'Employee Group ID'),
            'employeeCode' => Yii::t('app', 'Employee Code')
        ];
    }

}
