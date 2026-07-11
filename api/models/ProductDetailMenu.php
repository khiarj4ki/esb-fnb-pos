<?php

namespace app\models;

/**
 * This is the model class for table "ms_productdetailmenu".
 *
 * @property int $productID
 * @property int $productDetailID
 * @property int $menuID
 * @property string $convertionQty
 */

class ProductDetailMenu extends \yii\db\ActiveRecord {

    public static function tableName() {
        return 'ms_productdetailmenu';
    }

    public function rules() {
        return [
            [['productDetailID', 'productID', 'menuID', 'convertionQty'], 'required'],
        ];
    }

    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID']);
    }
}