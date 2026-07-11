<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotioncategory".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $menuCategoryID
 * @property int $menuCategoryDetailID
 * @property int $menuID
 */
class PromotionCategory extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotioncategory';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'menuCategoryID', 'menuCategoryDetailID', 'menuID'], 'required'],
            [['promotionID', 'menuCategoryID', 'menuCategoryDetailID', 'menuID'], 'integer'],
            [['ID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'menuCategoryID' => 'Menu Category ID',
            'menuCategoryDetailID' => 'Menu Category Detail ID',
            'menuID' => 'Menu ID'
        ];
    }

}
