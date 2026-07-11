<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menurecommendationgroup".
 *
 * @property int $menuRecommendationGroupID
 * @property int $menuRecommendationID
 * @property string $recommendationGroup
 * @property int $orderID
 */
class MenuRecommendationGroup extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menurecommendationgroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuRecommendationGroupID', 'menuRecommendationID', 'recommendationGroup', 'orderID'], 'required'],
            [['menuRecommendationGroupID', 'menuRecommendationID', 'orderID'], 'integer'],
            [['recommendationGroup'], 'string', 'max' => 50]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuRecommendationID' => 'Menu Recommendation ID',
            'recommendationGroup' => 'Recommendation Group',
            'orderID' => 'Order ID'
        ];
    }

    public function getMenuRecommendationDetail() {
        return $this->hasMany(MenuRecommendationDetail::class,
                ['menuRecommendationGroupID' => 'menuRecommendationGroupID']);
    }

    public function getMenuRecommendationHead() {
        return $this->hasOne(MenuRecommendationHead::class,
                ['menuRecommendationID' => 'menuRecommendationID']);
    }
}
