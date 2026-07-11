<?php

namespace app\models;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_specialpricehead".
 *
 * @property integer $specialPriceID
 * @property string $startDate
 * @property string $endDate
 * @property integer $menuTemplateID
 * @property string $notes
 * @property boolean $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class SpecialPriceHead extends ActiveRecord {

    public static function tableName() {
        return 'ms_specialpricehead';
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

    public function rules() {
        return [
            [['startDate', 'endDate'], 'required'],
            [['specialPriceID', 'menuTemplateID'], 'integer'],
            [['menuTemplateID'], 'filter', 'filter' => 'intval'],
            [['notes'], 'string', 'max' => 100],
            [['startDate', 'endDate', 'menuTemplateID', 'flagActive', 
                'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'specialPriceID' => Yii::t('app', 'Special Price ID'),
            'startDate' => Yii::t('app', 'Start Date'),
            'endDate' => Yii::t('app', 'End Date'),
            'menuTemplateID' => Yii::t('app', 'Menu Template ID'),
            'notes' => Yii::t('app', 'Notes'),
            'flagActive' => Yii::t('app', 'Flag Active'),
            'createdBy' => Yii::t('app', 'Created By'),
            'createdDate' => Yii::t('app', 'Created Date'),
            'editedBy' => Yii::t('app', 'Edited By'),
            'editedDate' => Yii::t('app', 'Edited Date'),
            'priceDays' => Yii::t('app', 'Special Price Days'),
            'specifiedTimeStart' => Yii::t('app', 'Specific Time Start'),
            'specifiedTimeEnd' => Yii::t('app', 'Specific Time End'),
        ];
    }

    public function getSpecialPriceDays() {
        return $this->hasMany(SpecialPriceDay::class,
                ['specialPriceID' => 'specialPriceID']);
    }
    
    public function getSpecialPriceTime() {
        return $this->hasOne(SpecialPriceTime::class,
                ['specialPriceID' => 'specialPriceID']);
    }

    public function getSpecialPriceTimes() {
        return $this->hasMany(SpecialPriceTime::class,
                ['specialPriceID' => 'specialPriceID']);
    }

    public function getMenuTemplateHead() {
        return $this->hasOne(MenuTemplateHead::class,
                ['menuTemplateID' => 'menuTemplateID']);
    }

    public static function getSpecialPriceMenuList($menuID, $menuTemplateID)
    {
        //@notes: retrives special price menu and define in array
        $querySpecialPrice = "
            SELECT 
                *,
                ms_specialpricemenu.ID AS specialPriceMenuID,
                ms_specialpricemenu.price AS specialPrice,
                ms_specialpricetime.ID AS specialPriceTimeID
            FROM
                ms_specialpricemenu
            INNER JOIN
                ms_menu ON ms_specialpricemenu.menuID = ms_menu.menuID
            INNER JOIN
                ms_specialpricehead ON ms_specialpricemenu.specialPriceID = ms_specialpricehead.specialPriceID
            LEFT JOIN
                ms_specialpricetime ON ms_specialpricehead.specialPriceID = ms_specialpricetime.specialPriceID
            INNER JOIN
                ms_specialpriceday ON ms_specialpricehead.specialPriceID = ms_specialpriceday.specialPriceID
            INNER JOIN
                ms_menutemplatehead ON ms_specialpricehead.menuTemplateID = ms_menutemplatehead.menuTemplateID
            WHERE
                ms_specialpricehead.flagActive = 1
                AND (CURRENT_DATE() BETWEEN startDate AND endDate)
                AND (dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END)
                AND ((startTime IS NULL AND endTime IS NULL)
                    OR (TIME(NOW()) BETWEEN startTime AND endTIme))
                AND ms_menu.menuID IN ($menuID)
                ";
        
        if ($menuTemplateID) {
            $querySpecialPrice .= " AND ms_menutemplatehead.menuTemplateID = $menuTemplateID";
        }

        $connection = Yii::$app->getDb();
        $specialPriceQuery = $connection->createCommand($querySpecialPrice)->queryAll();

        $specialPriceIdxByMenu = [];
        foreach ($specialPriceQuery as $specialPriceMenu) {
            $specialPriceIdxByMenu[$specialPriceMenu['menuID']] = $specialPriceMenu;
        }

        return $specialPriceIdxByMenu;
    }

}
