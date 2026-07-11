<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_visitpurposegroup".
 *
 * @property int $visitPurposeGroupID
 * @property string $visitPurposeGroupName
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class VisitPurposeGroup extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ms_visitpurposegroup';
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['visitPurposeGroupName', 'visitPurposeDetail'], 'required'],
            [['visitPurposeGroupID', 'createdDate', 'editedDate', 'visitPurposeDetail'], 'safe'],
            [['visitPurposeGroupName', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['visitPurposeGroupID'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'visitPurposeGroupID' => 'Visit Purpose Group ID',
            'visitPurposeGroupName' => 'Visit Purpose Group Name',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'visitPurposeDetail' => 'Visit Purpose'
        ];
    }

    public function getMapVisitPurposeGroup() {
        return $this->hasMany(MapVisitPurposeGroup::class,
                ['visitPurposeGroupID' => 'visitPurposeGroupID']);
    }

    public static function findVisitPurposeGroups() {
        $vpModel = VisitPurposeGroup::find()
            ->with('mapVisitPurposeGroup')
            ->all();
        
        $visitPurposeGroups = [];
        if ($vpModel) {
            $i = 0;
            foreach ($vpModel as $visitPurpose) {
                $visitPurposeGroups[$i]['visitPurposeGroupID'] = $visitPurpose->visitPurposeGroupID;
                $visitPurposeGroups[$i]['visitPurposeGroupName'] = $visitPurpose->visitPurposeGroupName;
                $visitPurposeGroups[$i]['mapVisitPurposeGroup'] = $visitPurpose->mapVisitPurposeGroup;
                $i++;
            }
        }
        return $visitPurposeGroups;
    }
}
