<?php

namespace app\models;

use yii\db\ActiveRecord;
use Yii;

/**
 * This is the model class for table "tr_shiftlogmode".
 *
 * @property int $ID
 * @property int $shiftID
 * @property string $shiftMode
 * @property datetime syncDate
 */
class ShiftLogMode extends ActiveRecord {
    public static function tableName() {
        return 'tr_shiftlogmode';
    }

    public function rules() {
        return [
            [['shiftID'], 'integer'],
            [['shiftMode'], 'string', 'max' => 20],
            [['shiftMode', 'syncDate'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'shiftID' => 'Shift ID',
            'shiftMode' => 'Shift Mode',
            'syncDate' => 'Sync Date'
        ];
    }

    public function getShiftLog() {
        return $this->hasOne(ShiftLog::class, ['shiftID' => 'shiftID']);
    }

    public static function getActiveShiftMode($shiftID)
    {
        //@Notes: Shift Mode Checking
        $settings = Setting::getPrintingSettings();
        $shiftMode = isset($settings['Shift Mode']) ? $settings['Shift Mode'] : 'Regular';

        $shiftModeModel = ShiftLogMode::find()
                ->where(['=', 'shiftID', $shiftID])
                ->one();

        $shiftMode = $shiftModeModel ? $shiftModeModel->shiftMode : $shiftMode;

        return $shiftMode;
    }

}
