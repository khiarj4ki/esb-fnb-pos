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
class MapSelfOrderCampaignBranchDetail extends ActiveRecord {

    public static function tableName() {
        return 'map_selfordercampaignbranchdetail';
    }

    public function rules() {
        return [
            [['selfOrderCampaignID', 'branchID', 'detailID'], 'integer'],
            [['usedQty'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'selfOrderCampaignID' => Yii::t('app', 'Self Order Campaign ID'),
            'branchID' => Yii::t('app', 'Branch ID'),
            'detailID' => Yii::t('app', 'Detail ID'),
            'usedQty' => Yii::t('app', 'Used Qty')
        ];
    }
    
}