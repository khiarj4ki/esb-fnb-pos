<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menucategorydetail".
 *
 * @property int $ID
 * @property int $menuCategoryID
 * @property string $menuCategoryDetailDesc
 * @property string $menuCategoryDetailCode
 * @property string $maxOrderQty
 * @property int $orderID
 * @property int $flagActive
 * 
 * @property MenuCategory $menuCategory
 * @property Menu[] $menus
 * @property Menu[] $activeBranchMenus
 */
class MenuCategoryDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menucategorydetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuCategoryID', 'menuCategoryDetailDesc', 'flagActive'], 'required'],
            [['menuCategoryID', 'orderID', 'flagActive'], 'integer'],
            [['menuCategoryDetailDesc', 'menuCategoryDetailCode'], 'string', 'max' => 50],
            [['ID', 'imageUrl', 'maxOrderQty','buttonColor'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'menuCategoryID' => 'Menu Category ID',
            'menuCategoryDetailDesc' => 'Menu Category Detail Desc',
            'menuCategoryDetailCode' => 'Menu Category Detail Code',
            'maxOrderQty' => 'Max Order Qty',
            'orderID' => 'Order',
            'flagActive' => 'Flag Active'
        ];
    }

    public function getMenuCategory() {
        return $this->hasOne(MenuCategory::class,
                ['menuCategoryID' => 'menuCategoryID']);
    }

    public function getMenus() {
        return $this->hasMany(Menu::class, ['menuCategoryDetailID' => 'ID'])
                ->andOnCondition([Menu::tableName() . '.flagActive' => 1]);
    }

    public function getActiveBranchMenus() {
        $branchID = Setting::getCurrentBranch();
        return $this->hasMany(Menu::class, ['menuCategoryDetailID' => 'ID'])
            ->andOnCondition([Menu::tableName() . '.flagActive' => 1])
            ->innerJoinWith('branchMenu')
            ->joinWith('productDetailMenu')
            ->leftJoin('ms_menutemplatedetail b', 'b.menuID = ms_menu.menuID')
            ->andWhere([BranchMenu::tableName() . '.flagActive' => 1])
            ->andWhere([BranchMenu::tableName() . '.branchID' => $branchID])
            ->orderBy('b.orderID');
    }


    public function getBranchMenus() {
        $branchID = Setting::getCurrentBranch();
        
        return $this->hasMany(Menu::class, ['menuCategoryDetailID' => 'ID'])
                ->andOnCondition([Menu::tableName() . '.flagActive' => 1])
                ->innerJoinWith('branchMenu')
                ->andWhere([BranchMenu::tableName() . '.flagActive' => 1])
                //->innerJoinWith('menuTemplateDetail')
                ->andWhere([BranchMenu::tableName() . '.branchID' => $branchID])
                ->orderBy(Menu::tableName() . '.menuName');
    }

    public static function findActive() {
        return MenuCategoryDetail::find()->andWhere([MenuCategoryDetail::tableName() . '.flagActive' => 1])
                ->orderBy(MenuCategoryDetail::tableName() . '.orderID ASC');
    }

}
