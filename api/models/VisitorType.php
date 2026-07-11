<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_visitortype".
 *
 * @property int $visitorTypeID
 * @property string $visitorTypeName
 * @property int $flagActive
 * @property int $flagDineIn
 * @property int $flagQuickService
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property SalesHead[] $salesHeads
 */
class VisitorType extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_visitortype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['visitorTypeName', 'flagActive', 'createdBy', 'createdDate', 'flagDineIn', 'flagQuickService'], 'required'],
            [['flagActive'], 'integer'],
            [['visitorTypeID', 'createdDate', 'editedDate'], 'safe'],
            [['visitorTypeName'], 'string', 'max' => 50],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'visitorTypeID' => 'Visitor Type ID',
            'visitorTypeName' => 'Visitor Type Name',
            'flagActive' => 'Flag Active',
            'flagDineIn' => 'Flag Dine In',
            'flagQuickService' => 'Flag Quick Service',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public static function findActive($flagDineIn = null, $flagQuickService = null) {
        return VisitorType::find()
            ->andWhere([VisitorType::tableName() . '.flagActive' => 1])
            ->andFilterWhere([VisitorType::tableName() . '.flagDineIn' => $flagDineIn])
            ->andFilterWhere([VisitorType::tableName() . '.flagQuickService' => $flagQuickService])
            ->orderBy(VisitorType::tableName() . '.visitorTypeName');
    }

}
