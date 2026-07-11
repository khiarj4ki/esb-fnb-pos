<?php

namespace app\models;

use app\components\AppHelper;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_notesmenucategorydetail".
 *
 * @property int $ID
 * @property int $menuCategoryDetailID
 * @property int $notesCategoryID
 */
class MapNotesMenuCategoryDetail extends ActiveRecord
{

    public static function tableName()
    {
        return 'map_notesmenucategorydetail';
    }

    public function rules()
    {
        return [
            [['menuCategoryDetailID', 'notesCategoryID'], 'required'],
            [['menuCategoryDetailID', 'notesCategoryID'], 'integer'],
        ];
    }

    public function getMenuCategoryDetail()
    {
        return $this->hasOne(MenuCategory::class, ['ID' => 'menuCategoryDetailID']);
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
            'menuCategoryDetailID' => Yii::t('app', 'Menu Category Detail'),
        ];
    }
}
