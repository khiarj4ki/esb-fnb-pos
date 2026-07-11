<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menuicon".
 *
 * @property int $menuIconID
 * @property string $menuIconName
 * @property string $menuIconUrl
 */
class MenuIcon extends ActiveRecord {
    
    public static function tableName() {
        return 'ms_menuicon';
    }
    
    public function rules() {
        return [
            [['menuIconID', 'menuIconName'], 'required'],
            [['menuIconUrl'], 'safe'],
            [['menuIconName'], 'string', 'max' => 100],
        ];
    }
    
    public function attributeLabels() {
        return [
            'menuIconID' => 'Menu Icon ID',
            'menuIconName' => 'Menu Icon Name',
            'menuIconUrl' => 'Menu Icon URL'
        ];
    }
}
