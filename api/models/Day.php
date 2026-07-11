<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_day".
 *
 * @property int $dayID
 * @property string $dayName
 */
class Day extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_day';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
                [['dayName'], 'string', 'max' => 50],
                [['dayID'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'dayID' => 'Day ID',
            'dayName' => 'Day Name',
        ];
    }

    public function getBusinessHour() {
        return $this->hasOne(MsBranchBusinessHour::class,
            ['dayID' => 'dayID']);
    }

    public static function findModel() {
        $branchID = Setting::getCurrentBranch();
        return Day::find()
            ->joinWith('businessHour')
            ->where(['branchID' => $branchID]);
    }

}
