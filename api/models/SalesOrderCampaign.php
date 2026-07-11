<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_menuicon".
 *
 * @property int $menuIconID
 * @property int $menuID
 * @property MsMenuIcon $menuIcon
 * @property MsMenu $menu
 */
class SalesOrderCampaign extends ActiveRecord {

    public static function tableName() {
        return 'tr_salesordercampaign';
    }

    public function rules() {
        return [
            [['selfOrderCampaignID', 'count'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
        ];
    }

    public function attributeLabels() {
        return [
            'selfOrderCampaignID' => Yii::t('app', 'Self Order Campaign ID'),
            'salesNum' => Yii::t('app', 'Sales Number'),
            'count' => Yii::t('app', 'Count'),
        ];
    }
    
}