<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_stireadersettingdetail".
 *
 * @property integer $ID
 * @property integer $headID
 * @property integer $TID
 */
class MsReaderSettingTamanSafariDetail extends ActiveRecord
{

    public static function tableName() {
        return 'ms_stireadersettingdetail';
    }

    public function rules()
    {
        return [
            [['ID', 'headID', 'TID'], 'safe'],
        ];
    }
}
