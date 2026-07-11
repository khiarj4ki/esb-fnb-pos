<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menurecommendationdetail".
 *
 * @property int $ID
 * @property int $menuRecommendationID
 * @property int $menuRecommendationGroupID
 * @property int $menuID
 * @property int $flagActive
 * @property int $orderID
 */
class MenuRecommendationDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menurecommendationdetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['orderID'], 'required'],
            [['menuRecommendationID', 'menuRecommendationGroupID', 'menuID', 'orderID', 'flagActive'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuRecommendationID' => 'Menu Recommendation ID',
            'menuRecommendationGroupID' => 'Menu Recommendation Group ID',
            'menuID' => 'Menu ID',
            'orderID' => 'Order ID',
            'flagActive' => 'Flag Active'
        ];
    }

    public function getMenuRecommendationGroup() {
        return $this->hasOne(MenuRecommendationGroup::class,
                ['menuRecommendationGroupID' => 'menuRecommendationGroupID']);
    }

    public function getMenuRecommendationHead() {
        return $this->hasOne(MenuRecommendationHead::class,
                ['menuRecommendationID' => 'menuRecommendationID']);
    }

    public function getMenu() {
        return $this->hasOne(Menu::class,
                ['menuID' => 'menuID']);
    }

    public function getActiveMenuTemplateDetails() {
        return $this->hasMany(MenuTemplateDetail::class, ['menuID' => 'menuID'])
                ->andOnCondition([MenuTemplateDetail::tableName() . '.flagActive' => 1])
                ->orderBy('ms_menutemplatedetail.orderID ASC');
    }

    public static function findActive() {
        return MenuRecommendationDetail::find()
            ->andOnCondition([MenuRecommendationDetail::tableName() . '.flagActive' => 1])
            ->innerJoinWith('menuRecommendationHead')
            ->innerJoinWith('menuRecommendationGroup')
            ->orderBy([
                'ms_menurecommendationdetail.orderID' => SORT_ASC,
                'ms_menurecommendationgroup.orderID' => SORT_ASC,
            ]);
    }

}
