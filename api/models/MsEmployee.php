<?php

namespace app\models;

use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_employee".
 *
 * @property string $employeeCode
 * @property string $employeeName
 * @property int $genderID
 * @property string $birthDate
 * @property string $address
 * @property string $phone
 * @property string $email
 * @property bool $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */

class MsEmployee extends ActiveRecord {

    public static function tableName() {
        return 'ms_employee';
    }
    
    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate', 'editedDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy', 'editedBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
            ]
        ];
    }

    public function rules() {
        return [
            [['employeeCode', 'employeeName', 'genderID'], 'required'],
            [['employeeCode'], 'unique'],
            [['employeeCode', 'email'], 'string', 'max' => 50],
            [['employeeName', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['address'], 'string', 'max' => 100],
            [['phone'], 'string', 'max' => 20],
            [['employeeCode', 'employeeName', 'genderID', 'flagActive', 'birthDate', 'createdDate', 'editedDate'], 'safe'],
            [['address', 'phone', 'email'], 'default', 'value' => ''],
        ];
    }

    public function attributeLabels() {
        return [
            'employeeCode' => 'Employee Code',
            'employeeName' => 'Employee Name',
            'genderID' => 'Gender',
            'birthDate' => 'Birth Date',
            'address' => 'Address',
            'phone' => 'Phone',
            'email' => 'Email',
            'flagActive' => 'Status'
        ];
    }
    
    public function fields() {
        $fields = parent::fields();
        $fields['genderName'] = function ($model) {
            return $model->gender->genderName;
        };

        return $fields;
    }

    public function getGender() {
        return $this->hasOne(Gender::className(), ['genderID' => 'genderID']);
    }

    public static function findEmployeeActive($employeeCode) {
        return MsEmployee::find()
            ->where(['employeeCode' => $employeeCode])
            ->andWhere(['flagActive' => 1])
            ->one();
    }
}