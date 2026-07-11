<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "map_branchvisitpurpose".
 *
 * @property int $ID
 * @property int $branchID
 * @property int $visitPurposeID
 * @property int $additionalTaxValue
 * @property string $flagOtherTaxVat
 * @property int $taxValue
 * @property string $menuTemplateID
 * @property int $flagSelfOrder
 * @property int $flagKiosk
 */
class MapBranchVisitPurpose extends ActiveRecord {
    CONST OTHERTAX_YES = 1;
    CONST OTHERTAX_NO = 0;
    CONST SELF_ORDER = 1;
    CONST NOT_SELF_ORDER = 0;
    
    public static function tableName() {
        return 'map_branchvisitpurpose';
    }
    
    public function rules() {
        return [
            [['ID', 'branchID', 'visitPurposeID', 'additionalTaxValue', 'flagOtherTaxVat', 
                'taxValue', 'menuTemplateID', 'flagSelfOrder', 'flagKiosk', 'orderFee', 'pendingOrder', 'vatSubject'], 'safe'],
        ];
    }
    
    public function fields() {
        $fields = parent::fields();
        $fields['flagInclusive'] = function ($model) {
            return isset($model->menuTemplateHead) ? $model->menuTemplateHead->flagInclusive : 0;
        };
        
        return $fields;
    }
    
    public function getMenuTemplateHead() {
        return $this->hasOne(MenuTemplateHead::class,
                ['menuTemplateID' => 'menuTemplateID']);
    }
    
    public static function getInclusiveMenuTemplateID($visitPurposeID) {
        $inclusiveMenuTemplate = MapBranchVisitPurpose::find()
            ->innerJoinWith('menuTemplateHead')
            ->where([
                'map_branchvisitpurpose.visitPurposeID' => $visitPurposeID,
                'ms_menutemplatehead.flagInclusive' => 1
            ])
            ->one();
        $menuTemplateID = $inclusiveMenuTemplate ? $inclusiveMenuTemplate->menuTemplateID : 0;

        return $menuTemplateID;
    }
    
}

