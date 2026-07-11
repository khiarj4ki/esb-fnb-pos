<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menutemplatehead".
 *
 * @property int $menuTemplateID
 * @property string $menuTemplateName
 * @property string $activeDate
 * @property string $notes
 * @property bool $flagActive
 * @property bool $flagInclusive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property MapBranchVisitPurpose $branchVisit
 */
class MenuTemplateHead extends ActiveRecord {
    const INCLUSIVE_YES = 1;
    const INCLUSIVE_NO = 0;
    
    public static function tableName() {
        return 'ms_menutemplatehead';
    }

    public function rules() {
        return [
            [['menuTemplateID', 'menuTemplateName', 'activeDate', 'notes', 'flagActive', 'flagInclusive', 'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'required'],
            [['menuTemplateID', 'menuTemplateName', 'activeDate', 'notes', 'flagActive', 'flagInclusive', 'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'safe'],
        ];
    }

    public function attributeLabels() {
        return [
            'menuTemplateName' => Yii::t('app', 'Menu Template Name'),
            'activeDate' => Yii::t('app', 'Active Date'),
            'notes' => Yii::t('app', 'Notes'),
            'flagActive' => Yii::t('app', 'Status'),
            'flagInclusive' => Yii::t('app', 'Inclusive'),
            'createdBy' => Yii::t('app', 'Created By'),
            'createdDate' => Yii::t('app', 'Created Date'),
            'editedBy' => Yii::t('app', 'Edited By'),
            'editedDate' => Yii::t('app', 'Edited Date'),
        ];
    }
    
    public function getBranchVisit() {
        return $this->hasMany(MapBranchVisitPurpose::class, ['menuTemplateID' => 'menuTemplateID']);
    }
}
