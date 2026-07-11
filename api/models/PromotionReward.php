<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionreward".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $menuCategoryID
 * @property int $menuCategoryDetailID
 * @property int $menuID
 * @property int $rewardQty
 *
 */

class PromotionReward extends ActiveRecord
{

    public static function tableName()
    {
        return 'ms_promotionreward';
    }

    public function rules() {
        return [
            [['promotionID'], 'required'],
            [['promotionID'], 'integer'],
            [['rewardQty'], 'number'],
            [['ID', 'menuCategoryID', 'menuCategoryDetailID', 'menuID', 'rewardQty'], 'safe']
        ];
    }

    public function fields()
    {
        $fields = parent::fields();

        $fields['rewardQty'] = function ($model) {
            return (float) $model->rewardQty;
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
    
    public function getMenuCategory() {
        return $this->hasOne(MenuCategory::tableName(), ['menuCategoryID' => 'menuCategoryID']);
    }
    
    public function getMenuCategoryDetail() {
        return $this->hasOne(MenuCategoryDetail::tableName(), ['menuCategoryDetailID' => 'menuCategoryDetailID']);
    }
}
