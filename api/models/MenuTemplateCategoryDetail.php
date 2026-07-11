<?php

namespace app\models;

use yii\db\ActiveRecord;

class MenuTemplateCategoryDetail extends ActiveRecord {

    public static function tableName() {
        return 'ms_menutemplatecategorydetail';
    }

    public function rules() {
        return [
            [['menuTemplateID', 'menuCategoryDetailID', 'orderID'], 'required'],
        ];
    }

    public function getMenuCategoryDetail() {
        return $this->hasOne(MenuCategoryDetail::class, ['ID' => 'menuCategoryDetailID']);
    }
}