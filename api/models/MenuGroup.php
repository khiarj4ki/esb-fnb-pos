<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menugroup".
 *
 * @property int $menuGroupID
 * @property int $menuID
 * @property string $menuGroup
 * @property string $minQty
 * @property string $maxQty
 * @property string $notes
 * @property int $flagActive
 * 
 * @property MenuPackage[] $activeMenuPackages
 */
class MenuGroup extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menugroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuID', 'menuGroup', 'minQty', 'maxQty', 'notes', 'flagActive'], 'required'],
            [['menuID', 'flagActive', 'orderID'], 'integer'],
            [['minQty', 'maxQty'], 'number'],
            [['menuGroup'], 'string', 'max' => 50],
            [['notes'], 'string', 'max' => 100],
            [['menuGroupID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuGroupID' => 'Menu Group ID',
            'menuID' => 'Menu ID',
            'menuGroup' => 'Menu Group',
            'minQty' => 'Min Qty',
            'maxQty' => 'Max Qty',
            'notes' => 'Notes',
            'flagActive' => 'Flag Active'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['minQty'] = function ($model) {
            return (float) $model->minQty;
        };
        $fields['maxQty'] = function ($model) {
            return (float) $model->maxQty;
        };

        return $fields;
    }

    public function getActiveMenuPackages() {
        return $this->hasMany(MenuPackage::class,
                    ['menuGroupID' => 'menuGroupID'])
                ->andOnCondition([MenuPackage::tableName() . '.flagActive' => 1])
                ->innerJoinWith('menu.menuCategoryDetail')
                ->orderBy([
                    'ms_menupackage.orderID' => SORT_ASC,
                    'ms_menupackage.ID' => SORT_ASC
                ]);
    }

    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID']);
    }

}
