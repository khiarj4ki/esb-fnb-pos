<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_visitpurposegroup".
 *
 * @property int $visitPurposeGroupID
 * @property int $visitPurposeID
 */
class MapVisitPurposeGroup extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'map_visitpurposegroup';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['visitPurposeGroupID', 'visitPurposeID'], 'required'],
            [['visitPurposeGroupID', 'visitPurposeID'], 'integer'],
            [['visitPurposeGroupID', 'visitPurposeID'], 'unique', 'targetAttribute' => ['visitPurposeGroupID', 'visitPurposeID']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'visitPurposeGroupID' => 'Visit Purpose Group ID',
            'visitPurposeID' => 'Visit Purpose ID',
        ];
    }
}
