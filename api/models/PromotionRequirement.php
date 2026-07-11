<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionrequirement".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $menuCategoryID
 * @property int $menuCategoryDetailID
 * @property int $menuID
 * @property int $reqValue
 * @property int $reqQty
 *
 */

 class PromotionRequirement extends ActiveRecord
{
    public static function tableName()
    {
        return 'ms_promotionrequirement';
    }

    public function rules() {
        return [
            [['promotionID'], 'required'],
            [['promotionID'], 'integer'],
            [['reqValue', 'reqQty'], 'number'],
            [['ID', 'menuCategoryID', 'menuCategoryDetailID', 'menuID', 'reqValue', 'reqQty'], 'safe']
        ];
    }

    public function fields()
    {
        $fields = parent::fields();

        $fields['reqValue'] = function ($model) {
            return $model->reqValue ? (float) $model->reqValue : null;
        };

        $fields['reqQty'] = function ($model) {
            return (float) $model->reqQty;
        };

        return $fields;
    }

    public function getPromotionHead()
    {
        return $this->hasOne(PromotionHead::tableName(), ['promotionID' => 'promotionID']);
    }
    public function getMenu()
    {
        return $this->hasOne(Menu::tableName(), ['menuID' => 'menuID']);
    }

    public function getMenuCategory()
    {
        return $this->hasOne(MenuCategory::tableName(), ['menuCategoryID' => 'menuCategoryID']);
    }

    public function getMenuCategoryDetail()
    {
        return $this->hasOne(MenuCategoryDetail::tableName(), ['ID' => 'menuCategoryDetailID']);
    }
}
