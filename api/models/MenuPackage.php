<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menupackage".
 *
 * @property int $ID
 * @property int $menuGroupID
 * @property int $menuID
 * @property string $price
 * @property int $flagDefault
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property Menu $menu
 * @property BranchMenu $branchMenu
 */
class MenuPackage extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menupackage';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuGroupID', 'menuID', 'price', 'flagDefault', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['menuGroupID', 'menuID', 'flagDefault', 'flagActive', 'orderID'], 'integer'],
            [['price'], 'number'],
            [['ID', 'createdDate', 'editedDate'], 'safe'],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'menuGroupID' => 'Menu Group ID',
            'menuID' => 'Menu ID',
            'price' => 'Price',
            'flagDefault' => 'Flag Default',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['menuName'] = function ($model) {
            return $model->menu->menuName;
        };
        $fields['menuShortName'] = function ($model) {
            return $model->menu->menuShortName;
        };
        $fields['price'] = function ($model) {
            return (float) $model->price;
        };
        $fields['displayPrice'] = function ($model) {
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
            $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
            return number_format($model->price, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
        };
        $fields['flagSoldOut'] = function ($model) {
            return $model->menu->branchMenu ? $model->menu->branchMenu->flagSoldOut : 0;
        };
//        $fields['menuExtras'] = function ($model) {
//            return $model->menu->menuExtras;
//        };

        return $fields;
    }

    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID'])
                ->andOnCondition([Menu::tableName() . '.flagActive' => 1]);
    }

    public function getMenuGroup() {
        return $this->hasOne(MenuGroup::class, ['menuGroupID' => 'menuGroupID']);
    }

    public function getMapMenuTemplatePackage() {
        return $this->hasOne(MapMenuTemplatePackage::class, ['menuID' => 'menuID', 'menuGroupID' => 'menuGroupID']);
    }

    public function getBranchMenu() {
        $branchID = Setting::getCurrentBranch();

        return $this->hasOne(BranchMenu::class, ['menuID' => 'menuID'])
                ->andOnCondition([BranchMenu::tableName() . '.branchID' => $branchID]);
    }

}
