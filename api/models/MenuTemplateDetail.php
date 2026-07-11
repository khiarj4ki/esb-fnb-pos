<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menutemplatedetail".
 *
 * @property int $ID
 * @property string $menuTemplateID
 * @property int $menuID
 * @property string $beforePrice
 * @property string $price
 * @property int $orderID
 * @property bool $flagActive
 * 
 * @property MenuTemplateHead $menuTemplateHead
 * @property MapBranchVisitPurpose $branchVisit
 */
class MenuTemplateDetail extends ActiveRecord {
    
    public static function tableName() {
        return 'ms_menutemplatedetail';
    }
    
    public function rules() {
        return [
            [['ID', 'menuTemplateID', 'menuID', 'beforePrice', 'price', 'flagActive'], 'required'],
            [['orderID', 'flagActive', 'flagShowEzo'], 'integer'],
            [['ID', 'menuTemplateID', 'menuID', 'beforePrice', 'price', 'orderID', 'flagActive', 'flagShowEzo','startTime', 'endTime'], 'safe'],
        ];
    }

    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID']);
    }
    
    public function getMenuTemplateHead() {
        return $this->hasOne(MenuTemplateHead::class,
                ['menuTemplateID' => 'menuTemplateID']);
    }
    
    public function getBranchVisit() {
        return $this->hasMany(MapBranchVisitPurpose::class, ['menuTemplateID' => 'menuTemplateID']);
    }
    
    public function getMenuTemplateLayout() {
        return $this->hasOne(MenuTemplateLayout::class, ['menuID' => 'menuID']);
    }
    
    public function getActiveSpecialPriceMenu() {
        return $this->hasOne(SpecialPriceMenu::class, ['menuID' => 'menuID'])
                ->innerJoinWith(['specialPriceHead' => function ($query) {
                        $query->joinWith('specialPriceTime');
                    }])
                ->innerJoinWith('specialPriceHead.specialPriceDays')
                ->andWhere([SpecialPriceHead::tableName() . '.flagActive' => 1])
                ->andWhere('CURRENT_DATE() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->andWhere(['or',
                    'startTime IS NULL AND endTime IS NULL',
                    'TIME(NOW()) BETWEEN startTime AND endTIme'
        ]);
    }
}
