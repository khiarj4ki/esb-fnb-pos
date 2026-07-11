<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenucompletion".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $salesMenuID
 * @property string $qty
 * @property string $completedDate
 * @property string $syncDate
 */
class SalesMenuCompletion extends ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesmenucompletion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID', 'salesMenuID'], 'integer'],
            [['salesNum', 'salesMenuID', 'qty', 'completedDate', 'startDate'], 'required'],
            [['qty'], 'number'],
            [['completedDate', 'syncDate', 'startDate'], 'safe'],
            [['salesNum'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'salesMenuID' => 'Sales Menu ID',
            'qty' => 'Qty',
            'startDate' => 'Start Date',
            'completedDate' => 'Completed Date',
            'syncDate' => 'Sync Date',
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['menuCategoryID'] = function ($model) {
            return $model->salesMenu->menu->menuCategoryDetail->menuCategoryID;
        };
        $fields['menuCategoryDetailID'] = function ($model) {
            return $model->salesMenu->menu->menuCategoryDetailID;
        };
        $fields['menuID'] = function ($model) {
            return $model->salesMenu->menu->menuID;
        };
        $fields['menuName'] = function ($model) {
            return $model->salesMenu->menu->menuName;
        };
        $fields['menuShortName'] = function ($model) {
            return $model->salesMenu->menu->menuShortName;
        };
        $fields['customMenuName'] = function ($model) {
            return $model->salesMenu->customMenuName;
        };
        $fields['menuCode'] = function ($model) {
            return $model->salesMenu->menu->menuCode;
        };
        $fields['qty'] = function ($model) {
            return (float) $model->qty;
        };
        $fields['tableName'] = function ($model) {
            return $model->salesMenu->salesHead->tableID == 0 ? 'Quick Service' : $model->salesMenu->salesHead->table->tableName;
        };
        $fields['visitPurposeName'] = function ($model) {
            return $model->salesMenu->salesHead->visitPurpose->visitPurposeName;
        };
        $fields['statusName'] = function ($model) {
            return $model->salesMenu->status->statusName;
        };
        $fields['completedTime'] = function($model) {
            return date("H:i:s", strtotime($model->completedDate));
        };
        $fields['packages'] = function ($model) {
            return $model->salesMenu->childSalesMenus;
        };
        $fields['extras'] = function ($model) {
            return $model->salesMenu->salesExtras;
        };
        $fields['queue'] = function ($model) {
            return $model->salesMenu->salesHead->queueNum;
        };

        return $fields;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public function getSalesMenu() {
        return $this->hasOne(OdsSalesMenu::class, ['ID' => 'salesMenuID']);
    }

}
