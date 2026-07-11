<?php

namespace app\models;

use Yii;
use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesheadvat".
 *
 * @property string $id
 * @property string $salesNum
 * @property string $dppValue
 */
class SalesHeadVat extends ActiveRecord
{

    public static function tableName()
    {
        return 'tr_salesheadvat';
    }

    public function rules()
    {
        return [
            [['salesNum'], 'required'],
            [['salesNum'], 'string', 'max' => 50],
            [['dppValue'], 'safe']
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'id',
            'salesNum' => 'Sales Number',
            'dppValue' => 'DPP Value'
        ];
    }

    public static function saveSalesHeadVat($salesNum, $dppValue)
    {
        try {
            $salesHeadVatModel = SalesHeadVat::findOne([
                'salesNum' => $salesNum
            ]);
    
            if (!$salesHeadVatModel) {
                $salesHeadVatModel = new SalesHeadVat();
                $salesHeadVatModel->salesNum = $salesNum;
            } else {
                $salesMenuVatModel = SalesMenuVat::find()
                ->where(['salesNum' => $salesNum])
                ->all();

                $dppValue=0;
                if ($salesMenuVatModel) {
                    foreach ($salesMenuVatModel as $vat) {
                        $dppValue += $vat->dppValue;
                    }
                }
            }
            $salesHeadVatModel->dppValue = $dppValue;
            if (!$salesHeadVatModel->save()) {
                throw new Exception("Failed to save sales head vat");
            }

            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }
}