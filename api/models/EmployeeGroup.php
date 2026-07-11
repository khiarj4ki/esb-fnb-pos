<?php

namespace app\models;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_employeegroup".
 *
 * @property integer $employeeGroupID
 * @property string $employeeGroupName
 * @property boolean $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class EmployeeGroup extends ActiveRecord {

    public static function tableName() {
        return 'ms_employeegroup';
    }

    public function behaviors() {
        return [
            [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'createdBy',
                'updatedByAttribute' => 'editedBy',
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'createdDate',
                'updatedAtAttribute' => 'editedDate',
                'value' => function() {
                    return date('Y-m-d H:i:s');
                }
            ],
        ];
    }

    public function rules() {
        return [
            [['employeeGroupID', 'employeeGroupName', 'flagActive', 
                'createdDate', 'editedDate'], 'safe'],
            [['createdBy', 'editedBy'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels() {
        return [
            'employeeGroupID' => Yii::t('app', 'Employee Group ID'),
            'employeeGroupName' => Yii::t('app', 'Employee Group Name'),
            'flagActive' => Yii::t('app', 'Flag Active'),
            'createdBy' => Yii::t('app', 'Created By'),
            'createdDate' => Yii::t('app', 'Created Date'),
            'editedBy' => Yii::t('app', 'Edited By'),
            'editedDate' => Yii::t('app', 'Edited Date')
        ];
    }

}
