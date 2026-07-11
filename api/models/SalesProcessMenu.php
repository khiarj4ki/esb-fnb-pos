<?php

namespace app\models;

use Yii;
use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesprocessmenu".
 *
 * @property string $ID
 * @property string $localID
 * @property string $salesNum
 * @property string $salesMenuID
 * @property string $holdTime
 * @property string $fireTime
 */
class SalesProcessMenu extends ActiveRecord {

    public static function tableName() {
        return 'tr_salesprocessmenu';
    }

    public function rules() {
        return [
            [['salesNum', 'salesMenuID'], 'required'],
            [['localID', 'salesMenuID'], 'integer'],
            [['salesNum'], 'string', 'max' => 50],
            [['holdTime', 'fireTime'], 'safe']
        ];
    }

    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Number',
            'salesMenuID' => 'Sales Menu ID',
            'holdTime' => 'Hold Time',
            'fireTime' => 'Fire Time',
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public static function saveSalesProcessMenu($salesMenuModel) {
        try {
            $salesProcessMenuModel = SalesProcessMenu::findOne([
                'salesNum' => $salesMenuModel->salesNum,
                'salesMenuID' => $salesMenuModel->ID
            ]);
    
            if (!$salesProcessMenuModel) {
                $salesProcessMenuModel = new SalesProcessMenu();
                $salesProcessMenuModel->salesNum = $salesMenuModel->salesNum;
                $salesProcessMenuModel->salesMenuID = $salesMenuModel->ID;
                $salesProcessMenuModel->holdTime = $salesMenuModel->flagHoldOrder ? $salesMenuModel->createdDate : null;
                $salesProcessMenuModel->fireTime = !$salesMenuModel->flagHoldOrder ? $salesMenuModel->createdDate : null;
                
                if (!$salesProcessMenuModel->save()) {
                    throw new Exception("Failed to save sales process menu");
                }
            } else {
                if ($salesProcessMenuModel->fireTime == null) {
                    $salesProcessMenuModel->fireTime = $salesMenuModel->flagFireOrder ? $salesMenuModel->editedDate : null;
    
                    if (!$salesProcessMenuModel->save()) {
                        throw new Exception("Failed to save sales process menu");
                    }
                }
            }
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
    
}