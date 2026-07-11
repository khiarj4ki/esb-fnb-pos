<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menutemplatedetail".
 *
 * @property int $ID
 * @property string $menuTemplateID
 * @property int $menuID
 * @property string $beforePrice
 * @property string $price
 * @property int $orderID
 * @property bool $flagActive
 * 
 * @property MenuTemplateHead $menuTemplateHead
 * @property MapBranchVisitPurpose $branchVisit
 */
class MenuTemplateDetailDay extends ActiveRecord {
    
    public static function tableName() {
        return 'ms_menutemplatedetailday';
    }
    
    public function rules() {
        return [
            [['menuTemplateID', 'menuID', 'dayID'], 'required'],
        ];
    }

    public function getDay() {
        return $this->hasOne(Day::className(), ['dayID' => 'dayID']);
    }

    public function getMenuTemplateID() {
        return $this->hasOne(MenuTemplateHead::className(), ['menuTemplateID' => 'menuTemplateID']);
    }

    public function getMenuID() {
        return $this->hasOne(Menu::className(), ['menuID' => 'menuID']);
    }
}
