<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_shiftlogdetail".
 *
 * @property int $ID
 * @property int $shiftID
 * @property string $shiftTime
 * @property string $shiftUsername
 * @property string $syncDate
 * 
 * @property ShiftLog $shiftLog
 * @property PosUser $shiftUser
 */
class ShiftLogDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_shiftlogdetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['shiftID', 'shiftTime', 'shiftUsername'], 'required'],
            [['shiftID'], 'integer'],
            [['shiftTime', 'syncDate'], 'safe'],
            [['shiftUsername'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'shiftID' => 'Shift ID',
            'shiftTime' => 'Shift Time',
            'shiftUsername' => 'Shift Username',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['shiftUser'] = function ($model) {
            return $model->shiftUser ? $model->shiftUser->fullName: $model->shiftUsername;
        };
        $fields['shiftTime'] = function ($model) {
            return str_replace("-", "/", $model->shiftTime);
        };

        return $fields;
    }

    public function getShiftLog() {
        return $this->hasOne(ShiftLog::class, ['shiftID' => 'shiftID']);
    }

    public function getShiftUser() {
        return $this->hasOne(PosUser::class, ['username' => 'shiftUsername']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

}
