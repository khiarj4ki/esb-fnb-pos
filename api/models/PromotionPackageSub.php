<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotionpackagesub".
 *
 * @property int $promotionID
 * @property int $menuID
 * @property int $menuSubsID
 */
class PromotionPackageSub extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotionpackagesub';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'menuID'], 'required'],
            [['promotionID', 'menuID', 'menuSubsID'], 'integer']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'promotionID' => 'Promotion ID',
            'menuID' => 'Menu ID',
            'menuSubsID' => 'Menu Subs ID'
        ];
    }

}
