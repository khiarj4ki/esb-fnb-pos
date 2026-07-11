<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menupromotion".
 *
 * @property int $menuPromotionID
 * @property int $headID
 * @property int $menuID
 * @property string $promotionPrice
 * @property int $flagActive
 * 
 * @property MenuPromotionHead[] $menuPromotionHead
 * @property MenuPromotionDay[] $menuPromotionDay
 */
class MenuPromotion extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menupromotion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['headID', 'menuID', 'promotionPrice', 'flagActive'], 'required'],
            [['headID', 'menuID', 'flagActive'], 'integer'],
            [['promotionPrice'], 'number'],
            [['menuPromotionID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuPromotionID' => 'Menu Promotion ID',
            'headID' => 'Head ID',
            'menuID' => 'Menu ID',
            'promotionPrice' => 'Promotion Price',
            'flagActive' => 'Flag Active'
        ];
    }

    public function getMenuPromotionHead() {
        return $this->hasMany(MenuPromotionHead::class, ['ID' => 'headID']);
    }

    public function getMenuPromotionDay() {
        return $this->hasMany(MenuPromotionDay::class, ['ID' => 'headID']);
    }

}
