<?php

namespace app\models;

use app\components\AppHelper;
use Exception;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_selfordercampaignhead".
 *
 * @property integer $selfOrderCampaignID
 * @property integer $selfOrderCampaignName
 * @property integer $selfOrderCampaignType
 * @property string $activeDateFrom
 * @property string $activeDateTo
 * @property string $effectType
 * @property integer $preAmountVal
 * @property string $preAmountMsg
 * @property integer $minAmountVal
 * @property string $postAmountMsg
 * @property integer $minQty
 * @property integer $menuID
 * @property string $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate

 */
class MsSelfOrderCampaignHead extends ActiveRecord {

    public $menuName;
    public $joinSelfOrderCampaignItems;
    public $branchIDs;

    public static function tableName() {
        return 'ms_selfordercampaignhead';
    }

    public function behaviors() {
        return [
            [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'createdBy',
                'updatedByAttribute' => 'editedBy',
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'createdDate',
                'updatedAtAttribute' => 'editedDate',
                'value' => function() {
                    return date('Y-m-d H:i:s');
                }
            ],
        ];
    }

    public function rules()
    {
        return [
            [['selfOrderCampaignName', 'selfOrderCampaignType', 'activeDateFrom', 'activeDateTo', 'effectType'], 'required'],
            ['menuName', 'required', 'when' => function($model) {
                return $model->selfOrderCampaignType == 'Minimum Item' || $model->selfOrderCampaignType == 'Minimum Item & Amount';
            }, 'whenClient' => "function (attribute, value) {
                return $('#menuNameInput').val() == 'Minimum Item' || $('#menuNameInput').val() == 'Minimum Item & Amount';
            }"],
            [['selfOrderCampaignName'], 'string', 'max' => 100],
            [['selfOrderCampaignType'], 'string', 'max' => 30],
            [['effectType'], 'string', 'max' => 20],
            [['preAmountMsg', 'postAmountMsg'], 'string', 'max' => 200],
            [['selfOrderCampaignID','selfOrderCampaignName', 'selfOrderCampaignType', 'activeDateFrom', 'activeDateTo',
                'effectType', 'preAmountVal', 'preAmountMsg', 'minAmountVal', 'postAmountMsg',
                'menuID', 'flagActive', 'joinSelfOrderCampaignItems', 'flagMultiple', 'branchIDs', 'minQty',
                'createdBy','createdDate','editedBy','editedDate', 'maxUsage'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'selfOrderCampaignName' => Yii::t('app', 'Self Order Campaign Name'),
            'selfOrderCampaignType' => Yii::t('app', 'Selft Order Campaign Type'),
            'activeDateFrom' => Yii::t('app', 'Active Date From'),
            'activeDateTo' => Yii::t('app', 'Active Date To'),
            'effectType' => Yii::t('app', 'Effect Type'),
            'preAmountVal' => Yii::t('app', 'Pre Amount Value'),
            'preAmountMsg' => Yii::t('app', 'Pre Amount Message'),
            'minAmountVal' => Yii::t('app', 'Minimum Amount Value'),
            'postAmountMsg' => Yii::t('app', 'Post Amount Message'),
            'menuID' => Yii::t('app', 'Menu'),
            'flagActive' => Yii::t('app', 'Status'),
            'flagMultiple' => Yii::t('app','Flag Multiple'),
            'createdBy' => Yii::t('app', 'Created By'),
            'createdDate' => Yii::t('app', 'Created Date'),
            'editedBy' => Yii::t('app', 'Edited By'),
            'editedDate' => Yii::t('app', 'Edited Date'),
            'branchIDs' => Yii::t('app', 'Branch'),
            'maxUsage' => Yii::t('app', 'Max Usage'),
        ];
    }

    public function getMenu() {
        return $this->hasOne(MsMenu::className(), ['menuID' => 'menuID']);
    }
}