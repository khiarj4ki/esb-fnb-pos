<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_menutemplatelayout".
 *
 * @property int $menuTemplateID
 * @property int $menuID
 * @property int $menuSizeID
 * @property int $posX
 * @property int $posY
 */
class MenuTemplateLayout extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'map_menutemplatelayout';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuTemplateID', 'menuID', 'menuSizeID', 'posX', 'posY'], 'integer'],
            [['menuTemplateID', 'menuID', 'menuSizeID', 'posX', 'posY'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuTemplateID' => 'Menu Template ID',
            'menuID' => 'Menu ID',
            'menuSizeID' => 'Menu Size ID',
            'posX' => 'PosX',
            'posY' => 'PosY',
        ];
    }
    
    public function getMenuSize() {
        return $this->hasOne(LkMenuSize::class,
                ['menuSizeID' => 'menuSizeID']);
    }

}
