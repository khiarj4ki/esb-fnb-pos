<?php

namespace app\models;

use Yii;
use Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenuvat".
 *
 * @property string $id
 * @property string $salesMenuID
 * @property string $localID
 * @property string $salesNum
 * @property string $dpp
 * @property string $dppValue
 */
class SalesMenuVat extends ActiveRecord
{

    public static function tableName()
    {
        return 'tr_salesmenuvat';
    }

    public function rules()
    {
        return [
            [['salesNum', 'salesMenuID'], 'required'],
            [['localID', 'salesMenuID'], 'integer'],
            [['salesNum'], 'string', 'max' => 50],
            [['dpp', 'dppValue'], 'safe']
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'id',
            'salesMenuID' => 'Sales Menu ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Number',
            'dpp' => 'DPP',
            'dppValue' => 'DPP Value'
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

    public static function saveSalesMenuVat($salesNum, $salesMenuID, $dppValue, $flagLuxuryItem)
    {
        try {
            $dppRatio1 = Setting::getValue1("VAT", "DPP Calculation Ratio") ? 
                Setting::getValue1("VAT", "DPP Calculation Ratio") : 1;
            $dppRatio2 = Setting::getValue2("VAT", "DPP Calculation Ratio") ? 
                Setting::getValue2("VAT", "DPP Calculation Ratio") : 1;

            if ($flagLuxuryItem == 1) {
                $dppRatio1 = 1;
                $dppRatio2 = 1;
            }
            $dpp = "$dppRatio1/$dppRatio2";

            $salesMenuVatModel = SalesMenuVat::findOne([
                'salesNum' => $salesNum,
                'salesMenuID' => $salesMenuID
            ]);
    
            if (!$salesMenuVatModel) {
                $salesMenuVatModel = new SalesMenuVat();
                $salesMenuVatModel->salesNum = $salesNum;
                $salesMenuVatModel->salesMenuID = $salesMenuID;
            }
            $salesMenuVatModel->dpp = $dpp;
            $salesMenuVatModel->dppValue = $dppValue;
            
            if (!$salesMenuVatModel->save()) {
                throw new Exception("Failed to save sales menu vat");
            }

            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public static function updateSalesNum($sourceSalesNum, $destinationSalesNum, $sourceSalesMenuID)
    {
        try {
            if ($sourceSalesNum && $destinationSalesNum && $sourceSalesMenuID) {
                $salesMenuVatModel = SalesMenuVat::findOne([
                    'salesNum' => $sourceSalesNum,
                    'salesMenuID' => $sourceSalesMenuID
                ]);

                if ($salesMenuVatModel) {
                    $salesMenuVatModel->salesNum = $destinationSalesNum;
                    if (!$salesMenuVatModel->save()) {
                        throw new Exception("Failed to update sales menu vat");
                    }
                }
            }

            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public static function deleteSalesMenuVat($salesNum, $salesMenuID)
    {
        try {
            if ($salesNum && $salesNum) {
                $salesMenuVatModel = SalesMenuVat::findOne([
                    'salesNum' => $salesNum,
                    'salesMenuID' => $salesMenuID
                ]);

                if ($salesMenuVatModel) {
                    if (!$salesMenuVatModel->delete()) {
                        throw new Exception("Failed to delete sales menu vat");
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