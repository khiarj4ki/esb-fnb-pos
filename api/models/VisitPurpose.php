<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_visitpurpose".
 *
 * @property int $visitPurposeID
 * @property string $visitPurposeName
 * @property int $flagDineIn
 * @property int $flagQuickService
 * @property int $flagShowQueue
 * @property int $flagMaxOrder
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property SalesHead[] $salesHeads
 */
class VisitPurpose extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_visitpurpose';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['visitPurposeName', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['flagActive', 'flagDineIn', 'flagQuickService', 'flagShowQueue', 'flagMaxOrder', 'kioskModeID'], 'integer'],
            [['visitPurposeID', 'createdDate', 'editedDate'], 'safe'],
            [['visitPurposeName'], 'string', 'max' => 50],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'visitPurposeID' => 'Visit Purpose ID',
            'visitPurposeName' => 'Visit Purpose Name',
            'flagDineIn' => 'Flag Dine In',
            'flagQuickService' => 'Flag Quick Service',
            'flagShowQueue' => 'Flag Show Queue',
            'flagMaxOrder' => 'Flag Max Order',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getSalesHeads() {
        return $this->hasMany(VisitPurpose::class,
                ['visitPurposeID' => 'visitPurposeID']);
    }

    public function getMapBranchVisitPurpose() {
        return $this->hasOne(MapBranchVisitPurpose::class,
                ['visitPurposeID' => 'visitPurposeID']);
    }

    public static function findActive() {
        return VisitPurpose::find()->andWhere([VisitPurpose::tableName() . '.flagActive' => 1])
                ->orderBy(VisitPurpose::tableName() . '.visitPurposeName');
    }

}
