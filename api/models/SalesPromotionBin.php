<?php

namespace app\models;

use yii\db\ActiveRecord;
use Yii;
use yii\console\Controller;
use yii\httpclient\Client;

/**
 * This is the model class for table "ms_promotiondetail".
 *
 * @property int $ID
 * @property int $promotionID
 * @property string $bankIdentificationNumber
 * @property string $salesNum
 */
class SalesPromotionBin extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salespromotionbin';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'bankIdentificationNumber'], 'required'],
            [['bankIdentificationNumber', 'salesNum'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'bankIdentificationNumber' => 'Bank Identification Number',
            'salesNum' => 'Sales Number'
        ];
    }


    public static function checkData($data){
        $model = new SalesPromotionBin();
        $existingRecord = SalesPromotionBin::find()
            ->where([
                'salesNum' => $data['salesNum']
            ])
            ->exists();

        if ($existingRecord) {
            SalesPromotionBin::deleteAll(['salesNum' => $data['salesNum']]);
            return "Data successfully deleted!";
        }
        return "There's no data!";   
    }

    public static function saveData($data){
        $model = new SalesPromotionBin();
        $existingRecord = SalesPromotionBin::find()
            ->where([
                'promotionID' => $data['promotionID'],
                'bankIdentificationNumber' => $data['promotionBin'],
                'salesNum' => $data['salesNum']
            ])
            ->exists();

        if (!$existingRecord) {
            $model->promotionID = $data['promotionID'];
            $model->bankIdentificationNumber = $data['promotionBin'];
            $model->salesNum = $data['salesNum'];

            if ($model->save()) {
                return "Data saved successfully!";
            } else {
                return "Failed to save data: " . json_encode($model->errors);
            }
        }
        
        return "Data already saved!";
    }

}
