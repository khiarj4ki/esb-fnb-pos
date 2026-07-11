<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_notesmenucategory".
 *
 * @property int $ID
 * @property int $menuCategoryID
 * @property int $notesCategoryID
 */
class MapNotesMenuCategory extends ActiveRecord
{

    public static function tableName()
    {
        return 'map_notesmenucategory';
    }

    public function rules()
    {
        return [
            [['menuCategoryID', 'notesCategoryID'], 'required'],
            [['menuCategoryID', 'notesCategoryID'], 'integer'],
        ];
    }

    public function getMenuCategory()
    {
        return $this->hasOne(MenuCategory::class, ['menuCategoryID' => 'menuCategoryID']);
    }

    public function getNotesCategory()
    {
        return $this->hasOne(NotesCategory::class, ['notesCategoryID' => 'notesCategoryID']);
    }

    public function getNotes()
    {
        return $this->hasMany(Notes::class, ['notesCategoryID' => 'notesCategoryID']);
    }

    public function attributeLabels()
    {
        return [
            'menuCategoryID' => Yii::t('app', 'Menu Category'),
        ];
    }
}
