<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * This is the model class for table "ms_branchmenudetail".
 *
 * @property int $ID
 * @property int $menuID
 */
class BranchMenuDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_branchmenudetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuID'], 'required'],
            [['ID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'menuID' => 'Menu ID'
        ];
    }

    public static function checkMenu($data){
        $existingRecord = BranchMenuDetail::find()
            ->where([
                'menuID' => $data['menuID']
            ])
            ->exists();
        if($existingRecord || ($data['editedDate'] == $data['createdDate'] && $data['checkerStationID'] == 0 && $data['stationID'] == 0)){
            return true;
        }
        return false;
    }

    public static function checkNewMenu(){
        $countA = BranchMenu::find()->count();  
        $countB = BranchMenuDetail::find()->count(); 

        if($countB == 0){
            return false;
        }else{
            return $countA !== $countB;
        }
    }

    public static function saveData()
    {
        BranchMenuDetail::deleteAll();
        $branchMenuModel = BranchMenu::find()->all();
        foreach ($branchMenuModel as $data) {
            $model = new BranchMenuDetail();  
            $model->menuID = $data['menuID'];
            if(!$model->save()){
                return false;
            }
        }
        return true;
    }


}
