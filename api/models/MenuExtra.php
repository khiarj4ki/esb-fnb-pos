<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menuextra".
 *
 * @property int $menuExtraID
 * @property int $menuID
 * @property int $bomID
 * @property int $menuGroupID
 * @property string $menuExtraName
 * @property string $menuExtraShortName
 * @property string $price
 * @property int $flagMandatory
 * @property string $minExtraQty
 * @property string $maxExtraQty
 * @property string $notes
 * @property string $buttonColor
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property MenuGroup $menuGroup
 * @property BranchMenu $branchMenu
 */
class MenuExtra extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menuextra';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuID', 'menuGroupID', 'menuExtraName', 'menuExtraShortName', 'price', 'flagMandatory', 'minExtraQty', 'maxExtraQty', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['menuID', 'bomID', 'menuGroupID', 'flagMandatory', 'orderID', 'flagActive'], 'integer'],
            [['price', 'minExtraQty', 'maxExtraQty'], 'number'],
            [['menuExtraID', 'menuRefID', 'createdDate', 'editedDate', 'buttonColor'], 'safe'],
            [['menuExtraName', 'notes', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['menuExtraShortName'], 'string', 'max' => 20]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuExtraID' => 'Menu Extra ID',
            'menuID' => 'Menu ID',
            'bomID' => 'Bom ID',
            'menuGroupID' => 'Menu Group ID',
            'menuExtraName' => 'Menu Extra Name',
            'menuExtraShortName' => 'Menu Extra Short Name',
            'price' => 'Price',
            'flagMandatory' => 'Flag Mandatory',
            'minExtraQty' => 'Min Extra Qty',
            'maxExtraQty' => 'Max Extra Qty',
            'notes' => 'Notes',
            'buttonColor' => 'Button Color',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['menuGroup'] = function ($model) {
            return $model->menuGroup;
        };
        $fields['price'] = function ($model) {
            return (float) $model->price;
        };
        $fields['displayPrice'] = function ($model) {
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
            $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
            return '(' . number_format($model->price, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator") . ')';
        };
        $fields['minExtraQty'] = function ($model) {
            return (float) $model->minExtraQty;
        };
        $fields['maxExtraQty'] = function ($model) {
            return (float) $model->maxExtraQty;
        };
        $fields['buttonColor'] = function ($model) {
            return $model->buttonColor;
        };
        $fields['flagSoldOut'] = function ($model) {
            return $model->menu->branchMenu ? $model->menu->branchMenu->flagSoldOut : 0;
        };
        return $fields;
    }
    
    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuRefID']);
    }

    public function getMenuGroup() {
        return $this->hasOne(MenuGroup::class, ['menuGroupID' => 'menuGroupID'])
                ->andOnCondition([MenuGroup::tableName() . '.flagActive' => 1]);
    }

    public function getBranchMenu() {
        $branchID = Setting::getCurrentBranch();

        return $this->hasOne(BranchMenu::class, ['menuID' => 'menuID'])
                ->andOnCondition(['branchID' => $branchID]);
    }

}
