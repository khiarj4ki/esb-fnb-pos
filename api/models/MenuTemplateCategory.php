<?php

namespace app\models;

use yii\db\ActiveRecord;

class MenuTemplateCategory extends ActiveRecord {

    public static function tableName() {
        return 'ms_menutemplatecategory';
    }

    public function rules() {
        return [
            [['menuTemplateID', 'menuCategoryID', 'orderID'], 'required'],
        ];
    }
}