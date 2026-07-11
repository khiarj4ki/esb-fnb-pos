<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "tr_shiftlogcash".
 *
 * @property int $ID
 * @property int $shiftID
 * @property int $shiftNumber
 * @property datetime $shiftInTime
 * @property datetime $shiftOutTime
 * @property string $startingCash
 * @property string $systemCashReceivedTotal
 * @property string $endingCash
 * @property string $shiftInUsername
 * @property string $shiftOutUsername
 * @property string $closingNotes
 * @property datetime syncDate
 */
class ShiftLogCash extends ActiveRecord {
    public static function tableName() {
        return 'tr_shiftlogcash';
    }

    public function rules() {
        return [
            [['shiftID', 'shiftNumber'], 'integer'],
            [['shiftInUsername', 'shiftOutUsername'], 'string', 'max' => 50],
            [['startingCash', 'systemCashReceivedTotal', 'endingCash'], 'number'],
            [['startingCash', 'systemCashReceivedTotal', 'endingCash','shiftOutTime', 
                'shiftOutUsername', 'closingNotes', 'syncDate'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'shiftID' => 'Shift ID',
            'shiftNumber' => 'Shift Number',
            'shiftInTime' => 'Shift In Time',
            'shiftOutTime' => 'Shift Out Time',
            'startingCash' => 'Starting Cash',
            'systemCashReceivedTotal' => 'System Cash Received Total',
            'endingCash' => 'Ending Cash',
            'shiftInUsername' => 'Shift In Username',
            'shiftOutUsername' => 'Shift Out Username',
            'closingNotes' => 'Closing Notes',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['shiftInUser'] = function ($model) {
            return $model->shiftInUser ? $model->shiftInUser->fullName : $model->shiftInUsername;
        };
        $fields['shiftOutUser'] = function ($model) {
            return $model->shiftOutUser ? $model->shiftOutUser->fullName : $model->shiftOutUsername;
        };

        return $fields;
    }

    public function getShiftLog() {
        return $this->hasOne(ShiftLog::class, ['shiftID' => 'shiftID']);
    }

    public function getShiftInUser() {
        return $this->hasOne(PosUser::class, ['username' => 'shiftInUsername']);
    }

    public function getShiftOutUser() {
        return $this->hasOne(PosUser::class, ['username' => 'shiftOutUsername']);
    }

    public static function getShiftNumber($shiftID)
    {
        $shiftNumberStartFrom = 1;
        $shiftLogCashQuery = (new Query())
                ->select(['shiftNumber' => new Expression('COUNT(shiftNumber)')])
                ->from(ShiftLogCash::tableName())
                ->where(['=', 'shiftID', $shiftID]);

        $shiftNumber = $shiftLogCashQuery->scalar();

        return $shiftNumberStartFrom + $shiftNumber;
    }

    public static function findActive() {
        $shiftLog = ShiftLog::findActive();
        $activeShiftID = $shiftLog ? $shiftLog->shiftID : NULL;
        
        return ShiftLogCash::find()
                ->where(['=', 'shiftID', $activeShiftID])
                ->andWhere(['IS', 'shiftOutTime', null])
                ->one();
    }

    public static function findByShift($shiftID) {
        return ShiftLogCash::find()
                ->where(['=', 'shiftID', $shiftID])
                ->andWhere(['IS', 'shiftOutTime', null])
                ->one();
    }

    public static function findByShiftId($shiftID) {
        return ShiftLogCash::find()
                ->where(['=', 'shiftID', $shiftID])
                ->andWhere(['IS NOT', 'shiftOutTime', null])
                ->one();
    }

}
