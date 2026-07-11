<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menucategory".
 *
 * @property int $menuCategoryID
 * @property string $menuCategoryCode
 * @property string $menuCategoryDesc
 * @property string $salesCoaNo
 * @property string $cogsCoaNo
 * @property string $discountCoaNo
 * @property string $notes
 * @property int $orderID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property MenuCategoryDetail[] $menuCategoryDetails
 */
class MenuCategory extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menucategory';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuCategoryDesc', 'salesCoaNo', 'cogsCoaNo', 'discountCoaNo', 'notes', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['orderID', 'flagActive'], 'integer'],
            [['menuCategoryID', 'createdDate', 'editedDate','buttonColor', 'menuCategoryCode'], 'safe'],
            [['menuCategoryDesc'], 'string', 'max' => 50],
            [['salesCoaNo', 'cogsCoaNo', 'discountCoaNo'], 'string', 'max' => 20],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuCategoryID' => 'Menu Category ID',
            'menuCategoryCode' => 'Menu Category Code',
            'menuCategoryDesc' => 'Menu Category Desc',
            'salesCoaNo' => 'Sales Coa No',
            'cogsCoaNo' => 'Cogs Coa No',
            'discountCoaNo' => 'Discount Coa No',
            'notes' => 'Notes',
            'orderID' => 'Order',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getMenuCategoryDetails() {
        return $this->hasMany(MenuCategoryDetail::class,
                    ['menuCategoryID' => 'menuCategoryID'])
                ->andOnCondition([MenuCategoryDetail::tableName() . '.flagActive' => 1])
                ->leftJoin(MenuTemplateCategoryDetail::tableName(),
                    'ms_menutemplatecategorydetail.menuCategoryDetailID = ms_menucategorydetail.ID')
                ->orderBy('ms_menutemplatecategorydetail.orderID ASC, ms_menucategorydetail.orderID ASC');
    }

    public static function findActive($menuTemplateID = null) {
        return MenuCategory::find()
                ->leftJoin(MenuTemplateCategory::tableName(), 
                    'ms_menutemplatecategory.menuCategoryID = ms_menucategory.menuCategoryID')
                ->andWhere([MenuCategory::tableName() . '.flagActive' => 1])
                ->andFilterWhere(['ms_menutemplatecategory.menuTemplateID' => $menuTemplateID])
                ->orderBy(['ms_menutemplatecategory.orderID' => SORT_ASC, 'ms_menucategory.orderID' => SORT_ASC]);
    }

}
