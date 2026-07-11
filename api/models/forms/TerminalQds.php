<?php
namespace app\models\forms;

use app\models\Setting;
use app\models\Station;
use app\models\Terminal;
use app\models\TerminalSetting;
use app\models\VisitPurpose;
use Exception;
use Yii;
use yii\base\Model;

class TerminalQds extends Model {

    const TERMINAL_QDS_SALES_MODE = 'QDS Visit Purpose ID';
    const TERMINAL_QDS_SHOW_ADDITIONAL_INFO = 'QDS Show Additional Info';
    const TERMINAL_QDS_SHOW_TABLE_NUMBER = 'QDS Show Table Number';
    const TERMINAL_QDS_STATIONS = 'QDS Station ID';
    const TERMINAL_QDS_ACTIVE_STATION = 'QDS Active Station';
    const TERMINAL_QDS_LANGUAGE = 'QDS Language';

    public $visitPurposeIds;
    public $additionalInfo;
    public $showTableInfo;
    public $stationID;
    public $activeStation;
    public $selectedLanguage;

    public static function saveTerminalQds()
    {
        try {
            $branchID = Setting::getCurrentBranch();

            $terminalModel = Terminal::find()
                ->where(['branchID' => $branchID, 'statusID' => 48])
                ->andWhere([
                    'OR',
                    ['deviceType' => null],
                    ['deviceType' => ''],
                    ['deviceType' => 'LOUNGE']
                ])
                ->one();

            if (!$terminalModel) {
                throw new Exception('Terminal data not found');
            }

            $beforeValue = (object) array(
                'terminalID' => $terminalModel->terminalID,
                'caption' => $terminalModel->caption,
                'deviceType' => $terminalModel->deviceType,
                'activatedDate' => $terminalModel->activatedDate,
            );
            $terminalModel->statusID = 47;
            $terminalModel->caption = "LOUNGE #";
            $terminalModel->deviceType = 'LOUNGE';
            $terminalModel->activatedDate = date('Y-m-d H:i:s');
            if (!$terminalModel->save()) {
                throw new Exception('Failed to save data terminal');
            } else {
                $afterValue = [
                    'terminalID' => $terminalModel->terminalID,
                    'caption' => $terminalModel->caption,
                    'deviceType' => $terminalModel->deviceType,
                    'activatedDate' => $terminalModel->activatedDate,
                    'prevTerminal' => $beforeValue
                ];
                Logging::save($terminalModel->terminalCode, Logging::SAVE_TERMINAL, $afterValue);
                return $afterValue['terminalID'];
            }
        } catch (Exception $ex) {
            // self::addError('terminal', $ex->getMessage());
            Yii::warning($ex);
            return false;
        }
    }

    public static function getQdsTerminalSetting($terminalID){
        $terminalModel = TerminalSetting::find()
            ->where(['terminalID' => $terminalID])
            ->all();

        $keys = self::qdsSetting();
        $terminalData = [];
        foreach ($terminalModel as $data) {
            if(isset($keys[$data->key])) {
                $terminalData[$keys[$data->key]] = $data->value;
                if(in_array($keys[$data->key],['additionalInfo','showTableInfo','activeStation'])) {
                    $terminalData[$keys[$data->key]] = (int) $data->value;
                }
            }
        }

        if(!$terminalData) {
            $terminalData['additionalInfo'] = 0;
            $terminalData['showTableInfo'] = 0;
            $terminalData['activeStation'] = 0;  
            $terminalData['visitPurposeIds'] = null;
            $terminalData['selectedLanguage'] = 1;
            $terminalData['stationID'] = null;
        }

        return $terminalData;
    }

    private static function qdsSetting(){
        return [
            self::TERMINAL_QDS_SALES_MODE => 'visitPurposeIds',
            self::TERMINAL_QDS_SHOW_ADDITIONAL_INFO  => 'additionalInfo',
            self::TERMINAL_QDS_SHOW_TABLE_NUMBER => 'showTableInfo',
            self::TERMINAL_QDS_STATIONS => 'stationID',
            self::TERMINAL_QDS_ACTIVE_STATION  => 'activeStation',
            self::TERMINAL_QDS_LANGUAGE => 'selectedLanguage'
        ];
    }

    private static function qdsValue($data){
        return [
            self::TERMINAL_QDS_SALES_MODE => $data['visitPurposeIds'],
            self::TERMINAL_QDS_SHOW_ADDITIONAL_INFO  => $data['additionalInfo'],
            self::TERMINAL_QDS_SHOW_TABLE_NUMBER => $data['showTableInfo'],
            self::TERMINAL_QDS_STATIONS => $data['stationID'],
            self::TERMINAL_QDS_ACTIVE_STATION  => $data['activeStation'],
            self::TERMINAL_QDS_LANGUAGE => $data['selectedLanguage']
        ];
    }
    
    public static function saveQdsTerminalSettings($data)
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {

            $qdsSettings = self::qdsValue($data);

            foreach ($qdsSettings as $key => $value) {
                if($key == self::TERMINAL_QDS_STATIONS) {
                    $stationArray = explode(',', $value);
                    $stationList = Station::find()
                            ->select('stationID')
                            ->where(['stationID' => $stationArray])
                            ->andWhere(['flagActive' => 1])
                            ->column();
    
                    $value = $stationList ? implode(',',$stationList) : null;
                    $data['stationID'] = $value;
                }
                if($key == self::TERMINAL_QDS_SALES_MODE) {
                    $vpArray = explode(',', $value);
                    $vpList = VisitPurpose::find()
                            ->select('visitPurposeID')
                            ->where(['IN', 'visitPurposeID', $vpArray])
                            ->andWhere(['flagActive' => 1])
                            ->column();
    
                    $data['visitPurposeIds'] = $vpList ? implode(',',$vpList) : null;
                }
                if (!self::runSaveQdsTerminalSetting($key, $value, $data['terminalID'])) {
                    throw new Exception("Failed to save $key");
                }
            }

            // Logging::save($data['terminalID'], Logging::ODS_TERMINAL_SETTING_CHANGE, self::getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            $transaction->rollBack();
            return false;
        }
    }

    private static function runSaveQdsTerminalSetting($key, $value, $terminalID)
    {
        $settingModel = TerminalSetting::findTerminalSetting($terminalID, $key);
        if ($settingModel) {
            $settingModel->value = ($value);
            if (!$settingModel->save()) {
                Yii::error($settingModel->errors);
                throw new Exception('Failed to update qds terminal setting');
            }
        } else {
            $settingModel = new TerminalSetting();
            $settingModel->terminalID = $terminalID;
            $settingModel->key = $key;
            $settingModel->value = ($value);

            if (!$settingModel->save()) {
                throw new Exception('Failed to update qds terminal setting');
            }
        }

        return true;
    }
 
}