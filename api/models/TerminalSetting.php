<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;

class TerminalSetting extends ActiveRecord
{
    CONST TERMINAL_KIOSK_STATION_KEY = 'Station ID';
    CONST TERMINAL_KIOSK_POS_EXTERNAL_KEY = 'POS External Payment';
    CONST TERMINAL_KIOSK_PAY_AT_CASHIER_KEY = 'Pay At Cashier';
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ms_terminalsetting';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['terminalID', 'key'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'ID' => 'ID',
            'terminalID' => 'Terminal ID',
            'key' => 'Key',
            'value' => 'Value',
        ];
    }

    public static function findTerminalSetting($terminalID, $key)
    {
        return TerminalSetting::find()
            ->where(['terminalID' => $terminalID])
            ->andWhere(['key' => $key])
            ->one();
    }

    public static function getTerminalSettings($terminalID)
    {
        $terminalModel = TerminalSetting::find()->all();

        $terminalData = [];
        foreach ($terminalModel as $data) {

            if ($data->terminalID === $terminalID) {
                if ($data->key == 'Customer Display') {
                    $terminalData['customerDisplay'] = $data->value;
                }
    
                if ($data->key == 'Default Station') {
                    $terminalData['defaultStation'] = $data->value;
                }
    
                if ($data->key == 'Visit Purpose FS') {
                    $terminalData['visitPurposeFs'] = $data->value;
                }
    
                if ($data->key == 'Visit Purpose QS') {
                    $terminalData['visitPurposeQs'] = $data->value;
                }
               
                if ($data->key == 'Terminal ID') {
                    $terminalData['terminalID'] = $data->value;
                }
    
                if ($data->key == 'Qr Code Mode') {
                    $terminalData['qrCodeMode'] = $data->value;
                }
    
                if ($data->key == 'Direct Serving') {
                    $terminalData['directServing'] = $data->value;
                }
    
                if ($data->key == 'Pending Notes') {
                    $terminalData['pendingNotes'] = $data->value;
                }

                if ($data->key == 'Self Order Server') {
                    $terminalData['selfOrderServer'] = $data->value;
                }

                if ($data->key == 'Integrated Scale') {
                    $terminalData['flagIntegratedWeightScale'] = $data->value;
                }

                if ($data->key == 'Default Visit Purpose QS') {
                    $terminalData['defaultVisitPurposeQs'] = $data->value;
                }

                if ($data->key == 'Pole Display Length') {
                    $terminalData['poleDisplayLength'] = (int)$data->value;
                }

                if ($data->key == 'Pole Display Port') {
                    $terminalData['poleDisplayPort'] = $data->value;
                }
            }
        }
        
        //@notes: inject terminal setting defaultVisitPurposeQs
        if(!isset($terminalData['defaultVisitPurposeQs'])) {
            $terminalData['defaultVisitPurposeQs'] = "0";
        }

        return $terminalData;
    }

    public static function getTerminalSelfOrder()
    {
        $terminalCode = TerminalSetting::find()
            ->select('terminalID')
            ->where(['key' => 'Self Order Server'])
            ->andWhere(['>', 'value', 0])
            ->scalar();

        $terminalModel = null;
        if ($terminalCode) {
            $terminalModel = Terminal::find()->where(['terminalCode' => $terminalCode])->one();
        }
        
        return [
            'terminalID' => $terminalModel ? $terminalModel->terminalID : 0,
            'terminalCode' => $terminalModel ? $terminalModel->terminalCode : '',
            'caption' => $terminalModel ? $terminalModel->caption : ''
        ];
    }

    public static function getOdsTerminalSettings($terminalID)
    {
        $terminalModel = TerminalSetting::find()
            ->where(['terminalID' => $terminalID])
            ->all();
        $keys = [
            'Printing Station ID' => 'printingStationIds',
            'Station ID' => 'stationIds',
            'Visit Purpose ID' => 'visitPurposeIds',
            'Terminal ID' => 'terminalID',
            'Timer Danger' => 'timerDanger',
            'Timer Warning' => 'timerWarning',
            'View Mode' => 'viewMode',
            'ODS Barcode' => 'odsBarcode'
        ];
        $terminalData = [];
        foreach ($terminalModel as $data) {
            if(isset($keys[$data->key])) {
                $terminalData[$keys[$data->key]] = $data->value;
                if(in_array($keys[$data->key],['timerDanger','timerWarning','viewMode','odsBarcode'])) {
                    $terminalData[$keys[$data->key]] = (int) $data->value;
                }
            }
        }

        if(!$terminalData) {
            $terminalData['viewMode'] = 0;
            $terminalData['timerDanger'] = 0;
            $terminalData['timerWarning'] = 0;  
            $terminalData['odsBarcode'] = 0;
        }

        $settingModel = Setting::find()
            ->andWhere(['key1' => 'POS'])
            ->andWhere(['OR',
                ['key2' => 'ODS Mode'],
                ['key2' => 'Finish All Packages']               
            ])
            ->all();
            
        foreach ($settingModel as $setting) {
            $key = lcfirst(str_replace(' ', '', $setting->key2));
            if ($key == 'oDSMode') {
                $key = 'mode';
            }

            $terminalData[$key] = (float) $setting->value1;
        }
        
        if (!$terminalData) {      
            $terminalData['finishAllPackages'] = 0;
            $terminalData['mode'] = 1;
        }
        
        return $terminalData;
    }

    public static function getKioskTerminalSettings($terminalID)
    {
        $terminalModel = TerminalSetting::find()
            ->where(['terminalID' => $terminalID])
            ->andWhere(["IN", "key", [
                self::TERMINAL_KIOSK_STATION_KEY,
                self::TERMINAL_KIOSK_POS_EXTERNAL_KEY,
                self::TERMINAL_KIOSK_PAY_AT_CASHIER_KEY
            ]])->all();

        $keys = [
            'Station ID' => 'kioskStationID',
            'POS External Payment' => 'posExternalPaymentID',
            'Pay At Cashier' => 'payAtCashier'
        ];

        $terminalData = [];
        foreach ($terminalModel as $data) {
            if(isset($keys[$data->key])) {
                if ($data->key === self::TERMINAL_KIOSK_STATION_KEY) {
                    $stationId = Station::find()
                        ->select('stationID')
                        ->where(['stationID' => $data->value])
                        ->andWhere(['flagActive' => 1])
                        ->scalar();
                    
                    if (!$stationId) {
                        $data->delete();
                    } else {
                        $terminalData[$keys[$data->key]] = (int) $data->value;
                    }
                } else if ($data->key === self::TERMINAL_KIOSK_POS_EXTERNAL_KEY) {
                    $paymentMethodModel = PaymentMethod::find()
                        ->select('posExternalPaymentID')
                        ->where(['posExternalPaymentID' => $data->value])
                        ->andWhere(['flagActive' => 1])
                        ->scalar();
                    
                    if (!$paymentMethodModel) {
                        $data->delete();
                    } else {
                        $terminalData[$keys[$data->key]] = $data->value;
                    }
                } else {
                    $terminalData[$keys[$data->key]] = (int) $data->value;
                }
            }
        }

        return $terminalData;
    }
}
