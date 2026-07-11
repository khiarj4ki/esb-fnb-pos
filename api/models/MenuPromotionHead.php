<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menupromotionhead".
 *
 * @property int $ID
 * @property string $startDate
 * @property string $endDate
 * @property int $branchID
 * @property string $notes
 * @property bool $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class MenuPromotionHead extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menupromotionhead';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['startDate', 'endDate', 'branchID', 'notes', 'createdBy', 'createdDate'], 'required'],
            [['ID', 'startDate', 'endDate', 'createdDate', 'editedDate'], 'safe'],
            [['branchID'], 'integer'],
            [['flagActive'], 'boolean'],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'startDate' => 'Start Date',
            'endDate' => 'End Date',
            'branchID' => 'Branch ID',
            'notes' => 'Notes',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

}
