<?php

namespace app\models;

use Yii;
use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenurecommendation".
 *
 * @property string $id
 * @property string $localID
 * @property string $salesNum
 * @property string $salesMenuID
 * @property string $salesType
 */
class SalesMenuRecommendation extends ActiveRecord
{

    public static function tableName()
    {
        return 'tr_salesmenurecommendation';
    }

    public function rules()
    {
        return [
            [['salesNum', 'salesMenuID'], 'required'],
            [['localID', 'salesMenuID'], 'integer'],
            [['salesNum', 'salesType'], 'string', 'max' => 50]
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'id',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Number',
            'salesMenuID' => 'Sales Menu ID',
            'salesType' => 'Sales Type'
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $this->localID = $this->id;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public static function saveSalesMenuRecommendation($salesNum, $salesMenuID, $salesType)
    {
        try {
            $salesMenuRecommendationModel = SalesMenuRecommendation::findOne([
                'salesNum' => $salesNum,
                'salesMenuID' => $salesMenuID
            ]);
    
            if (!$salesMenuRecommendationModel) {
                $salesMenuRecommendationModel = new SalesMenuRecommendation();
                $salesMenuRecommendationModel->salesNum = $salesNum;
                $salesMenuRecommendationModel->salesMenuID = $salesMenuID;
                $salesMenuRecommendationModel->salesType = $salesType;
                
                if (!$salesMenuRecommendationModel->save()) {
                    throw new Exception("Failed to save sales menu recommendation");
                }
            }
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
}