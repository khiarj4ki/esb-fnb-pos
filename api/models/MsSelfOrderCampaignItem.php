<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_selfordercampaignhead".
 * 
 * @property integer $ID
 * @property integer $selfOrderCampaignID
 * @property string $itemType
 * @property string $itemQty
 * @property integer $itemMenuID
 * @property string $itemDiscountVal
 * @property string $itemText
 */

class MsSelfOrderCampaignItem extends ActiveRecord {

    public static function tableName() {
        return 'ms_selfordercampaignitem';
    }
    
    public function rules() {
        return [
            [['ID','selfOrderCampaignID', 'itemType', 'itemQty', 'itemMenuID', 'itemDiscountVal', 'itemText', 'itemPromotionID'], 'safe']
        ];
    }

    public function getMenu() {
        return $this->hasOne(MsMenu::className(), ['menuID' => 'itemMenuID']);
    }

    public function getSelfOrderCampaignHead() {
        return $this->hasOne(MsSelfOrderCampaignHead::className(), ['selfOrderCampaignID' => 'selfOrderCampaignID']);
    }

}