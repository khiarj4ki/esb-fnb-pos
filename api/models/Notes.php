<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_notes".
 *
 * @property int $ID
 * @property int $notesCategoryID
 * @property string $notes
 * @property int $flagActive
 * 
 * @property NotesCategory $notesCategory
 */
class Notes extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_notes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['notesCategoryID', 'notes', 'flagActive'], 'required'],
            [['notesCategoryID', 'flagActive'], 'integer'],
            [['notes'], 'string', 'max' => 100],
            [['ID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'notesCategoryID' => 'Notes Category ID',
            'notes' => 'Notes',
            'flagActive' => 'Flag Active'
        ];
    }

    public function getNotesCategory() {
        return $this->hasOne(NotesCategory::class,
                ['notesCategoryID' => 'notesCategoryID']);
    }

    public static function findActiveAsArray() {
        $notesData = [];
        $categoryModel = NotesCategory::findActive()
            ->joinWith('notesDetails')
            ->all();

        foreach ($categoryModel as $key => $category) {
            $notesData[$key]['notesCategoryID'] = $category->notesCategoryID;
            $notesData[$key]['notesCategoryDesc'] = $category->notesCategoryDesc;
            $notesData[$key]['notes'] = $category->notesDetails;
            $notesData[$key]['menuCategory'] = $category->notesMenuCategory;
            $notesData[$key]['menuCategoryDetail'] = $category->notesMenuCategoryDetail;
        }

        return $notesData;
    }

}
