<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_tablesection".
 *
 * @property int $tableSectionID
 * @property string $tableSectionName
 * @property int $branchID
 * @property string $image
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property Branch $branch
 * @property Table[] $tables
 */
class TableSection extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_tablesection';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableSectionName', 'branchID', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['branchID', 'flagActive'], 'integer'],
            [['tableSectionID', 'createdDate', 'editedDate'], 'safe'],
            [['image'], 'string'],
            [['tableSectionName'], 'string', 'max' => 50],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'tableSectionID' => 'Table Section ID',
            'tableSectionName' => 'Table Section Name',
            'branchID' => 'Branch ID',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getBranch() {
        return $this->hasOne(Branch::class, ['branchID' => 'branchID']);
    }

    public function getTables() {
        return $this->hasMany(Table::class,
                ['tableSectionID' => 'tableSectionID']);
    }

    public static function findActive() {
        return TableSection::find()->andWhere([TableSection::tableName() . '.flagActive' => 1])
                ->orderBy(TableSection::tableName() . '.tableSectionName');
    }

}
