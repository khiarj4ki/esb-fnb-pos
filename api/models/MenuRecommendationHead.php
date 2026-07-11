<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menurecommendationhead".
 *
 * @property int $menuRecommendationID
 * @property string $menuTemplateID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class MenuRecommendationHead extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menurecommendationhead';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuRecommendationID', 'menuTemplateID'], 'required'],
            [['menuRecommendationID', 'flagActive', 'menuTemplateID'], 'integer'],
            [['createdDate', 'editedDate'], 'safe'],
            [['createdBy', 'editedBy'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuRecommendationID' => 'Menu Recommendation ID',
            'menuTemplateID' => 'Menu Template ID',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
        ];
    }

    public function getMenuRecommendationGroup() {
        return $this->hasMany(MenuRecommendationGroup::class,
            ['menuRecommendationID' => 'menuRecommendationID']);
    }

    public function getMenuRecommendationDetail() {
        return $this->hasMany(MenuRecommendationDetail::class,
            ['menuRecommendationID' => 'menuRecommendationID']);
    }

    public static function findActive() {
        return MenuRecommendationHead::find()
            ->andOnCondition([MenuRecommendationHead::tableName() . '.flagActive' => 1])
            ->innerJoinWith('menuRecommendationGroup')
            ->innerJoinWith('menuRecommendationDetail')
            ->orderBy([
                'ms_menurecommendationdetail.orderID' => SORT_ASC,
                'ms_menurecommendationgroup.orderID' => SORT_ASC,
            ]);
    }
}
