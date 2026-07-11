<?php

namespace app\models;
use Yii;

/**
 * This is the model class for table "ms_branchbusinesshour".
 *
 * @property int $branchID
 * @property int $dayID
 * @property string $startTime
 * @property string $endTime
 */
class MsBranchBusinessHour extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ms_branchbusinesshour';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['branchID', 'dayID'], 'required'],
            [['branchID', 'dayID'], 'integer'],
            [['startTime', 'endTime'], 'safe'],
            [['branchID', 'dayID'], 'unique', 'targetAttribute' => ['branchID', 'dayID']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'branchID' => 'Branch ID',
            'dayID' => 'Day ID',
            'startTime' => 'Start Time',
            'endTime' => 'End Time',
        ];
    }
    
    public function getDay() {
        return $this->hasOne(Day::className(),
                ['dayID' => 'dayID']);
    }
}
