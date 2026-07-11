<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_notescategory".
 *
 * @property int $notesCategoryID
 * @property string $notesCategoryDesc
 * @property string $notes
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property Notes[] $notesDetails
 */
class NotesCategory extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_notescategory';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['notesCategoryDesc', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['flagActive'], 'integer'],
            [['notesCategoryID', 'createdDate', 'editedDate'], 'safe'],
            [['notesCategoryDesc'], 'string', 'max' => 50],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'notesCategoryID' => 'Notes Category ID',
            'notesCategoryDesc' => 'Notes Category Desc',
            'notes' => 'Notes',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getNotesDetails() {
        return $this->hasMany(Notes::class,
                    ['notesCategoryID' => 'notesCategoryID'])
                ->andOnCondition([Notes::tableName() . '.flagActive' => 1]);
    }

    public function getNotesMenuCategory()
    {
        return $this->hasMany(MapNotesMenuCategory::class, ['notesCategoryID' => 'notesCategoryID']);
    }

    public function getNotesMenuCategoryDetail()
    {
        return $this->hasMany(MapNotesMenuCategoryDetail::class, ['notesCategoryID' => 'notesCategoryID']);
    }

    public static function findActive() {
        return NotesCategory::find()->andWhere([NotesCategory::tableName() . '.flagActive' => 1])
                ->orderBy(NotesCategory::tableName() . '.notesCategoryDesc');
    }

}
