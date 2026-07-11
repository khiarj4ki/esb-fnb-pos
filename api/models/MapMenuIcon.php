<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_menuicon".
 *
 * @property int $menuIconID
 * @property int $menuID
 */
class MapMenuIcon extends ActiveRecord {
    
    public static function tableName() {
        return 'map_menuicon';
    }
    
    public function rules() {
        return [
            [['menuIconID', 'menuID'], 'required'],
            [['menuIconID', 'menuID'], 'safe']
        ];
    }
    
    public function attributeLabels() {
        return [
            'menuIconID' => 'Menu Icon ID',
            'menuID' => 'Menu ID'
        ];
    }
    
    public function getMenuIcon() {
        return $this->hasOne(MenuIcon::class,
                        ['menuIconID' => 'menuIconID']);
    }
}