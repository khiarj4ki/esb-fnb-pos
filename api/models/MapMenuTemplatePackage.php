<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_menutemplatepackage".
 *
 * @property int $ID
 * @property int $menuTemplateID
 * @property int $menuGroupID
 * @property int $menuID
 * @property int $price
 */
class MapMenuTemplatePackage extends ActiveRecord {
    public static function tableName() {
        return 'map_menutemplatepackage';
    }

    public function rules() {
        return [
            [['ID', 'menuTemplateID', 'menuGroupID', 'menuID', 'price'], 'safe'],
        ];
    }
}