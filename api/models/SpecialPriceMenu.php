<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_specialpricemenu".
 *
 * @property integer $ID
 * @property integer $specialPriceID
 * @property integer $menuID
 * @property float   $price
 */
class SpecialPriceMenu extends ActiveRecord {


    public static function tableName() {
        return 'ms_specialpricemenu';
    }

    public function rules() {
        return [
            [['specialPriceID', 'menuID', 'price'], 'required'],
            [['specialPriceID', 'menuID'], 'integer'],
            [['specialPriceID', 'menuID'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'ID' => Yii::t('app', 'ID'),
            'specialPriceID' => Yii::t('app', 'Special Price ID'),
            'menuID' => Yii::t('app', 'Menu ID'),
            'price' => Yii::t('app', 'Price'),
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['price'] = function ($model) {
            return (float) $model->price;
        };

        $fields['startTime'] = function ($model) {
            return $model->specialPriceHead->specialPriceTime['startTime'];
        };

        $fields['endTime'] = function ($model) {
            return $model->specialPriceHead->specialPriceTime['endTime'];
        };

        $fields['times'] = function ($model) {
            return $model->specialPriceHead->specialPriceTimes;
        };

        return $fields;
    }
    
    public function getSpecialPriceHead() {
        return $this->hasOne(SpecialPriceHead::class,
                ['specialPriceID' => 'specialPriceID']);
    }

    public function getMenu() {
        return $this->hasOne(Menu::class,
                ['menuID' => 'menuID']);
    }

    public static function findActiveArrayValue($menuTemplateID, $filterDate = null) {
        return SpecialPriceMenu::find()
            ->select('price')
            ->with('menu')
            ->innerJoinWith(['specialPriceHead' => function ($query) {
                $query->joinWith('specialPriceTime');
            }])
            ->innerJoinWith('specialPriceHead.specialPriceDays')
            ->andWhere([SpecialPriceHead::tableName() . '.flagActive' => 1])
            ->andWhere([SpecialPriceHead::tableName() . '.menuTemplateID' => $menuTemplateID])
            ->andWhere('CURRENT_DATE() BETWEEN startDate AND endDate')
            ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
            ->andWhere(['or',
                'startTime IS NULL AND endTime IS NULL',
                ($filterDate == null) ? 'TIME(NOW()) BETWEEN startTime AND endTIme' : "TIME('$filterDate') BETWEEN startTime AND endTIme"
            ])
            ->indexBy('menuID')
            ->column();
    }
}
