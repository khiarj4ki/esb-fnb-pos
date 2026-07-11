<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\PaymentMethod;
use app\models\PosUser;
use app\models\Setting;
use app\models\Station;
use app\models\TerminalSetting;
use app\models\VisitPurpose;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property boolean $printAllBills
 * @property boolean $printPaymentMethod
 * @property boolean $printMenu
 */
class Settings extends Model {

    const SETTING_TIMER_DANGER = 'Timer Danger';
    const SETTING_TIMER_WARNING = 'Timer Warning';
    const SETTING_FINISH_ALL_PACKAGES = 'Finish All Packages';
    const SETTING_MODE = 'Mode';
    const SETTING_STATION = 'Station Ids';
    const SETTING_VIEW_MODE = 'View Mode';
    const SETTING_AUTO_SYNC_POS = 'POS Auto Sync';
    const SETTING_ESO_PRINTER_STATION = 'ESO Printer Station';
    const TERMINAL_CUSTOMER_DISPLAY = 'Customer Display';
    const TERMINAL_DEFAULT_STATION = 'Default Station';
    const TERMINAL_VISIT_PURPOSE_FS = 'Visit Purpose FS';
    const TERMINAL_VISIT_PURPOSE_QS = 'Visit Purpose QS';
    const TERMINAL_QR_CODE_MODE = 'Qr Code Mode';
    const TERMINAL_DIRECT_SERVING = 'Direct Serving';
    const TERMINAL_PENDING_NOTES = 'Pending Notes';
    const TERMINAL_SELF_ORDER_SERVER = 'Self Order Server';
    const TERMINAL_ODS_PRINTING_STATION = 'Printing Station ID';
    const TERMINAL_ODS_STATION = 'Station ID';
    const TERMINAL_ODS_VISIT_PURPOSE = 'Visit Purpose ID';
    const TERMINAL_ODS_TIMER_DANGER = 'Timer Danger';
    const TERMINAL_ODS_TIMER_WARNING = 'Timer Warning';
    const TERMINAL_KIOSK_STATION = 'Station ID';
    const TERMINAL_KIOSK_PAY_AT_CASHIER = 'Pay At Cashier';
    const TERMINAL_KIOSK_POS_EXTERNAL_PAYMENT = 'POS External Payment';
    const TERMINAL_INTEGRATED_SCALE = 'Integrated Scale';
    const TERMINAL_ODS_BARCODE = 'ODS Barcode';
    const TERMINAL_DEFAULT_VISIT_PURPOSE_QS = 'Default Visit Purpose QS';
    const TERMINAL_POLE_DISPLAY_LENGTH = 'Pole Display Length';
    const TERMINAL_POLE_DISPLAY_PORT = 'Pole Display Port';


    public $timeDanger;
    public $timeWarning;    
    public $shiftOutPrintingSettings;
    public $odsSettings;
    public $autoSyncPOS;
    public $selfOrderServer;
    public $userName;
    public $flagSelfOrderServer;
    public $terminalID;
    public $customerDisplay;
    public $defaultStation;
    public $visitPurposeFs;
    public $visitPurposeQs;
    public $qrCodeMode;
    public $directServing;
    public $pendingNotes;
    public $timerDanger;
    public $timerWarning;
    public $stationIds;
    public $printingStationIds;    
    public $visitPurposeIds;
    public $viewMode;
    public $kioskStationID;
    public $payAtCashier;
    public $posExternalPaymentID;
    public $esoPrinterStation;
    public $lastEditedBy;
    public $flagIntegratedWeightScale;
    public $odsBarcode;
    public $defaultVisitPurposeQs;
    public $poleDisplayLength;
    public $poleDisplayPort;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['timeDanger', 'timeWarning'], 'required'],
            [['shiftOutPrintingSettings', 'odsSettings', 'autoSyncPOS', 'selfOrderServer', 'userName', 'flagSelfOrderServer',
                'terminalID', 
                'customerDisplay', 'defaultStation', 'visitPurposeFs', 'visitPurposeQs', 'qrCodeMode', 'directServing',
                'pendingNotes', 
                'timerDanger', 'timerWarning', 'stationIds', 'printingStationIds', 'visitPurposeIds', 'viewMode',
                'esoPrinterStation', 
                'odsBarcode', 'kioskStationID', 'payAtCashier', 'posExternalPaymentID', 'lastEditedBy', 'flagIntegratedWeightScale',
                'defaultVisitPurposeQs', 'poleDisplayLength', 'poleDisplayPort'], 'safe']
        ];
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$this->saveSetting(self::SETTING_TIMER_DANGER, $this->timeDanger)) {
                throw new Exception('Failed to save timer danger');
            }

            if (!$this->saveSetting(self::SETTING_TIMER_WARNING, $this->timeWarning)) {
                throw new Exception('Failed to save timer warning');
            }

            if (!$this->saveSetting(self::SETTING_AUTO_SYNC_POS, $this->autoSyncPOS)) {
                throw new Exception('Failed to save Auto Sync POS');
            }
            if (!$this->saveSetting(self::SETTING_ESO_PRINTER_STATION, $this->esoPrinterStation)) {
                throw new Exception('Failed to save ESO Printer Station');
            };
            
            Logging::save('-', Logging::EDIT_SETTINGS, $this->getAttributes());
            
            if (isset($this->flagSelfOrderServer) && $this->flagSelfOrderServer == true) {
                $dataLogging = [
                    'action' => $this->selfOrderServer == 1 ? 'Turn ON' : 'Turn OFF',
                    'dateTime' => date('y-m-d h:i:s'),
                    'userName' => $this->userName
                ];
                
                Logging::save($this->selfOrderServer, Logging::EDIT_SELF_ORDER_SERVER, $dataLogging);
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

    public function saveShiftOutPrintingSettings() {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->shiftOutPrintingSettings as $setting) {
                $settingModel = Setting::find()
                        ->where(['key1' => 'Local Setting'])
                        ->andWhere(['key2' => $setting['key2']])
                        ->one();
                if ($settingModel) {
                    $settingModel->value1 = $setting['value1'] == true ? '1' : '0';
                    if (!$settingModel->save()) {
                        
                        throw new Exception('Failed to update shift out printing');
                    }
                }
            }

            Logging::save('-', Logging::EDIT_SETTINGS, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('setting', $ex->getMessage());
            return false;
        }
    }
    
    public function saveOdsSettings() {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $kitchenCheck = 0;
            $checkerCheck = 0;
            $viewMode = $this->odsSettings['viewMode'];
            foreach ($this->odsSettings as $key => $value) {
                if (in_array($key, ['finishAllPackages'])) {
                    $key2 = $this->findKey($key);
                    $settingModel = Setting::find()
                        ->where(['key1' => 'ODS'])
                        ->andWhere(['key2' => $key2])
                        ->one();
                    
                    if ($settingModel) {
                        if ($key == 'stationIds') {
                            if ($viewMode == 1) {
                                $value = implode(",", $value);
                            } else {
                                $value = $settingModel->value1;
                            }                            
                        }
                        $settingModel->value1 = strval($value);
                        if (!$settingModel->save()) {
                           
                            throw new Exception('Failed to update ods setting');
                        }
                    } else {
                        $settingModel = new Setting();
                        $settingModel->key1 = 'ODS';
                        $settingModel->key2 = $key2;
                        if ($key == 'stationIds') {
                            if ($viewMode == 1) {
                                $value = implode(",", $value);
                            } else {
                                $value = $settingModel->value1;
                            }                            
                        }
                        $settingModel->value1 = strval($value);
                        if (!$settingModel->save()) {
                            throw new Exception('Failed to update ods setting');
                        }
                    }
                } else {
                    if ($key == 'kitchenCheck') {
                        $kitchenCheck = $value;
                    } else if ($key == 'checkerCheck') {
                        $checkerCheck = $value;
                    }
                }               
            }
            
            $mode = 1;

            $key2 = $this->findKey('mode');
            $settingModel = Setting::find()
                ->where(['key1' => 'ODS'])
                ->andWhere(['key2' => $key2])
                ->one();
                    
            if ($settingModel) {
                $settingModel->value1 = strval($mode);
                if (!$settingModel->save()) {
                  
                    throw new Exception('Failed to update shift out printing');
                }
            } else {
                $settingModel = new Setting();
                $settingModel->key1 = 'ODS';
                $settingModel->key2 = $key2;
                $settingModel->value1 = strval($mode);
                if (!$settingModel->save()) {
                    throw new Exception('Failed to update ods setting');
                }
            }

            Logging::save('-', Logging::EDIT_SETTINGS, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('setting', $ex->getMessage());
            return false;
        }
    }
    
    private function findKey($key) {
        if ($key == 'finishAllPackages') {
            $key2 = self::SETTING_FINISH_ALL_PACKAGES;
        } else if ($key == 'mode') {
            $key2 = self::SETTING_MODE;
        } else if ($key == 'stationIds') {
            $key2 = self::SETTING_STATION;
        } else if ($key == 'timerWarning') {
            $key2 = self::SETTING_TIMER_WARNING;
        } else if ($key == 'timerDanger') {
            $key2 = self::SETTING_TIMER_DANGER;
        } else {
            $key2 = self::SETTING_VIEW_MODE;
        }
        
        return $key2;
    }

    private function findSetting($key2) {
        return Setting::find()
                        ->andWhere(['key1' => 'Local Setting'])
                        ->andWhere(['key2' => $key2])
                        ->one();
    }

    private function saveSetting($key2, $value1) {
        $settingModel = $this->findSetting($key2);
        if (!$settingModel) {
            $newModel = new Setting();
            $newModel->key1 = 'Local Setting';
            $newModel->key2 = $key2;
            //$newModel->value1 = $value1 ? '1' : '0';
            if ($key2 == self::SETTING_TIMER_DANGER || $key2 == self::SETTING_TIMER_WARNING) {
                $newModel->value1 = strval($value1);
            } else if ($key2 == self::SETTING_ESO_PRINTER_STATION) {
                if ($this->selfOrderServer === 1) {
                    $stationModel = Station::find()
                        ->where(['flagActive' => 1])
                        ->orderBy('stationID ASC')
                        ->one();
                    
                    $newModel->value1 = $value1 != '0' ? $value1 : ($this->defaultStation ? $this->defaultStation : ($stationModel ? $stationModel->stationID : '-1'));
                }
            } else {
                $newModel->value1 = $value1 ? '1' : '0';
            }
            if (!$newModel->save()) {
                Yii::error($newModel->errors);
                return false;
            }
        } else {
            //$settingModel->value1 = $value1 ? '1' : '0';
            if ($key2 == self::SETTING_TIMER_DANGER || $key2 == self::SETTING_TIMER_WARNING) {
                $settingModel->value1 = strval($value1);
            } else if ($key2 == self::SETTING_ESO_PRINTER_STATION) {
                if ($this->selfOrderServer === 1) {
                    $stationModel = Station::find()
                        ->where(['flagActive' => 1])
                        ->orderBy('stationID ASC')
                        ->one();
                    $settingModel->value1 = $value1 != '0' ? $value1 : ($this->defaultStation ? $this->defaultStation : ($stationModel ? $stationModel->stationID : '-1'));
                }
            } else {
                $settingModel->value1 = $value1 ? '1' : '0';
            }
            if (!$settingModel->save()) {
                Yii::error($settingModel->errors);
                return false;
            }
        }
        return true;
    }

    public function saveUpdateSetting($status, $init = false) {
        $currentStatus = Setting::getSetting('Local Setting', 'Trial Mode');
        $username = null;
        if ($init) {
            $username = 'SYSTEM';
        } else {
            $user = PosUser::findIdentityByAccessToken(Yii::$app->user->identity->posAuthKey);
            $username = $user ? $user->username : null;
        }
        try {
            $db = Yii::$app->db;
            $connectionArray = AppHelper::getConnectionArray();
            foreach ($connectionArray->connection as $name) {
                $db->close();
                $db->dsn = "mysql:host=$connectionArray->host;dbname=$name";
                $db->open();
                
                $db->createCommand()->update(
                    Setting::tableName(),
                    [ 'value1' =>  $status ],
                    ['key1' => 'Local Setting', 'key2' => 'Trial Mode']
                )->execute();
                
                self::setAutoSync($db, $init, $status);
                
                $eventSubject = [
                    'date' => date('Ymd H:i:s'),
                    'mode' => (object) array(
                        'before' => (float) $currentStatus->value1 == 1 ? 'trial' : 'live',
                        'after' => $status == 1 ? 'trial' : 'live'
                    ),
                ];

                Logging::save($username, Logging::CHANGE_MODE, $eventSubject);
            }
            return true;
        } catch (Exception $ex) {
            $this->addError('setting', $ex->getMessage());
            return false;
        }
    }

    public function saveUpdateSettingInstall() {
        try {
            $db = Yii::$app->db;
            $currentDbName = AppHelper::getDsnAttribute('dbname', $db->dsn);
            $currentDbHost = AppHelper::getDsnAttribute('host', $db->dsn);

            $installSetting = null;
            $connectionArray = AppHelper::getConnectionArray();
            foreach ($connectionArray->connection as $name) {
                $db->close();
                $db->dsn = "mysql:host=$connectionArray->host;dbname=$name";
                $db->open();

                $apiUrl = Setting::getApiUrl();
                $apiKey = Setting::getApiKey();
                $branchID = Setting::getCurrentBranch();
            
                if ($apiUrl != null && $apiKey != null && $branchID != null) {
                    $installSetting = (object) array(
                        'apiUrl' => $apiUrl,
                        'apiKey' => $apiKey,
                        'branchID' => $branchID,
                    );
                }
            }

            if ($installSetting) {
                $db->close();
                $db->dsn = "mysql:host=$currentDbHost;dbname=$currentDbName";
                $db->open();

                $transaction = $db->beginTransaction();
                if (!Setting::saveLocalSetting('Api Url', $installSetting->apiUrl, true)) {
                    throw new Exception('Failed to save api url');
                }
                if (!Setting::saveLocalSetting('Api Key', $installSetting->apiKey, true)) {
                    throw new Exception('Failed to save api key');
                }
                if (!Setting::saveLocalSetting('Branch ID', $installSetting->branchID, false)) {
                    throw new Exception('Failed to save branch id');
                }
                $transaction->commit();
            }
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('setting', $ex->getMessage());
            return false;
        }
    }


    public function saveTerminalSetting()
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {

            self::onCheckSelfOrderServer($this->selfOrderServer, $this->terminalID);

            if (!$this->runSaveTerminalSetting(self::TERMINAL_CUSTOMER_DISPLAY, $this->customerDisplay)) {
                throw new Exception('Failed to save customer display');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_DEFAULT_STATION, $this->defaultStation)) {
                throw new Exception('Failed to save default station');
            }
            
            if (!$this->runSaveTerminalSetting(self::TERMINAL_DIRECT_SERVING, $this->directServing)) {
                throw new Exception('Failed to save direct serving');
            }
            
            if (!$this->runSaveTerminalSetting(self::TERMINAL_PENDING_NOTES, $this->pendingNotes)) {
                throw new Exception('Failed to save pending notes');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_QR_CODE_MODE, $this->qrCodeMode)) {
                throw new Exception('Failed to save qr code mode');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_SELF_ORDER_SERVER, $this->selfOrderServer)) {
                throw new Exception('Failed to save pending notes');
            }
            
            if (!$this->runSaveTerminalSetting(self::TERMINAL_VISIT_PURPOSE_FS, $this->visitPurposeFs)) {
                throw new Exception('Failed to save visit purpose fs');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_VISIT_PURPOSE_QS, $this->visitPurposeQs)) {
                throw new Exception('Failed to save visit purpose qs');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_INTEGRATED_SCALE, $this->flagIntegratedWeightScale)) {
                throw new Exception('Failed to save integrated scale');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_DEFAULT_VISIT_PURPOSE_QS, $this->defaultVisitPurposeQs)) {
                throw new Exception('Failed to save default station');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_POLE_DISPLAY_LENGTH, $this->poleDisplayLength)) {
                throw new Exception('Failed to save pole display length');
            }

            if (!$this->runSaveTerminalSetting(self::TERMINAL_POLE_DISPLAY_PORT, $this->poleDisplayPort)) {
                throw new Exception('Failed to save pole display port');
            }

            Logging::save($this->terminalID, Logging::TERMINAL_SETTING_CHANGE, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            $transaction->rollBack();
            return false;
        }
    }

    private function runSaveTerminalSetting($key, $value)
    {
        $terminalModel = TerminalSetting::findTerminalSetting($this->terminalID, $key);
        if (!$terminalModel) {
            $terminalModel = new TerminalSetting();
            $terminalModel->key = $key;
            $terminalModel->value = $value;

            if (self::getCondtionSaveTerminal($key)) {
                $terminalModel->terminalID = $this->terminalID;
                $terminalModel->value = strval($value);
            }

            if (!$terminalModel->save()) {
                Yii::error($terminalModel->errors);
                return false;
            }
        } else {
            if (self::getCondtionSaveTerminal($key)) {
                $terminalModel->value = strval($value);
            }
            
            if (!$terminalModel->save()) {
                Yii::error($terminalModel->errors);
                return false;
            }
        }
        return true;
    }

    private function getCondtionSaveTerminal($key)
    {
        if (
            $key == self::TERMINAL_CUSTOMER_DISPLAY || 
            $key == self::TERMINAL_DEFAULT_STATION ||
            $key == self::TERMINAL_DIRECT_SERVING || 
            $key == self::TERMINAL_PENDING_NOTES ||
            $key == self::TERMINAL_QR_CODE_MODE ||
            $key == self::TERMINAL_SELF_ORDER_SERVER ||
            $key == self::TERMINAL_VISIT_PURPOSE_FS || 
            $key == self::TERMINAL_VISIT_PURPOSE_QS || 
            $key == self::TERMINAL_INTEGRATED_SCALE ||
            $key == self::TERMINAL_DEFAULT_VISIT_PURPOSE_QS ||
            $key == self::TERMINAL_POLE_DISPLAY_LENGTH ||
            $key == self::TERMINAL_POLE_DISPLAY_PORT
        ) {
            return true;
        }

        return false;
    }

    private static function onCheckSelfOrderServer($selfOrderServer, $terminalID)
    {
        if ($selfOrderServer !== 0) {
            $terminalModel = TerminalSetting::find()
                ->where(['<>', 'terminalID', $terminalID])
                ->andWhere(['key' => 'Self Order Server'])
                ->all();
            
            $terminalIDs = [];
            foreach ($terminalModel as $terminal) {
                if ($terminal['value'] != 0) {
                    array_push($terminalIDs, $terminal['ID']);
                }
            }

            if (count($terminalIDs) > 0) {
                Yii::$app->db->createCommand()->update(
                    TerminalSetting::tableName(),
                    [ 'value' =>  0 ],
                    ['IN', 'ID', $terminalIDs]
                )->execute();
            }
        }
    }

    private static function setAutoSync($db, $init, $status) {
        if (!$init) {
            $autoSyncPOS = Setting::getSetting('Local Setting', 'POS Auto Sync');
            if ($autoSyncPOS) {
                $status = $status == 0 ? 1 : 0;
                $db->createCommand()->update(
                    Setting::tableName(),
                    ['value1' =>  $status],
                    ['key1' => 'Local Setting', 'key2' => 'POS Auto Sync']
                )->execute();
            } else {
                $db->createCommand()->insert(
                    Setting::tableName(),
                    [
                        'key1' => 'Local Setting',
                        'key2' => 'POS Auto Sync',
                        'value1' => 1,
                        'value2' => null,
                    ]
                )->execute();
            }
        }
    }

    public function saveOdsTerminalSettings()
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $odsSettings = [
                self::TERMINAL_ODS_PRINTING_STATION => $this->printingStationIds,
                self::TERMINAL_ODS_STATION => $this->stationIds,
                self::TERMINAL_ODS_VISIT_PURPOSE => $this->visitPurposeIds,
                self::TERMINAL_ODS_TIMER_DANGER => $this->timerDanger,
                self::TERMINAL_ODS_TIMER_WARNING => $this->timerWarning,
                self::SETTING_VIEW_MODE => $this->viewMode,
                self::TERMINAL_ODS_BARCODE => $this->odsBarcode
            ];

            foreach ($odsSettings as $key => $value) {
                if($key == self::TERMINAL_ODS_STATION || $key == self::TERMINAL_ODS_PRINTING_STATION) {
                    $stationArray = explode(',', $value);
                    $stationList = Station::find()
                            ->select('stationID')
                            ->where(['IN', 'stationID', $stationArray])
                            ->andWhere(['flagActive' => 1])
                            ->column();
    
                    $value = $stationList ? implode(',',$stationList) : null;
                    if($key == self::TERMINAL_ODS_STATION) {
                        $this->stationIds = $value;
                    } else if($key == self::TERMINAL_ODS_PRINTING_STATION) {
                        $this->printingStationIds = $value;
                    }
                }
                if($key == self::TERMINAL_ODS_VISIT_PURPOSE) {
                    $vpArray = explode(',', $value);
                    $vpList = VisitPurpose::find()
                            ->select('visitPurposeID')
                            ->where(['IN', 'visitPurposeID', $vpArray])
                            ->andWhere(['flagActive' => 1])
                            ->column();
    
                    $this->visitPurposeIds = $vpList ? implode(',',$vpList) : null;
                }
                if (!$this->runSaveOdsTerminalSetting($key, $value)) {
                    throw new Exception("Failed to save $key");
                }
            }

            Logging::save($this->terminalID, Logging::ODS_TERMINAL_SETTING_CHANGE, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            $transaction->rollBack();
            return false;
        }
    }

    private function runSaveOdsTerminalSetting($key, $value)
    {
        $settingModel = TerminalSetting::findTerminalSetting($this->terminalID, $key);
        if ($settingModel) {
            $settingModel->value = ($value);
            if (!$settingModel->save()) {
                Yii::error($settingModel->errors);
                throw new Exception('Failed to update ods terminal setting');
            }
        } else {
            $settingModel = new TerminalSetting();
            $settingModel->terminalID = $this->terminalID;
            $settingModel->key = $key;
            $settingModel->value = ($value);

            if (!$settingModel->save()) {
                throw new Exception('Failed to update ods terminal setting');
            }
        }

        return true;
    }

    public function saveKioskTerminalSettings()
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $kioskSettings = [
                self::TERMINAL_KIOSK_PAY_AT_CASHIER => $this->payAtCashier,
                self::TERMINAL_KIOSK_STATION => $this->kioskStationID,
                self::TERMINAL_KIOSK_POS_EXTERNAL_PAYMENT => $this->posExternalPaymentID
            ];

            foreach ($kioskSettings as $key => $value) {
                if($key === self::TERMINAL_KIOSK_STATION) {
                    $stationId = Station::find()
                        ->select('stationID')
                        ->where(['=', 'stationID', $value])
                        ->andWhere(['flagActive' => 1])
                        ->scalar();
                    $value = $stationId ? $stationId : null;
                }
                if($key === self::TERMINAL_KIOSK_POS_EXTERNAL_PAYMENT) {
                    $paymentMethodModel = PaymentMethod::find()
                        ->select('posExternalPaymentID')
                        ->where(['=', 'posExternalPaymentID', $value])
                        ->andWhere(['flagActive' => 1])
                        ->scalar();
                    $value = $paymentMethodModel ? $paymentMethodModel : null;
                }
                if (!$this->runSaveKioskTerminalSetting($key, $value)) {
                    throw new Exception("Failed to save $key");
                }
            }

            Logging::save($this->terminalID, Logging::KIOSK_TERMINAL_SETTING_CHANGE, $this->getAttributes(), null, null, $this->lastEditedBy);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            $transaction->rollBack();
            return false;
        }
    }

    private function runSaveKioskTerminalSetting($key, $value)
    {
        $settingModel = TerminalSetting::findTerminalSetting($this->terminalID, $key);
        if ($settingModel) {
            $settingModel->value = ($value);
            if (!$settingModel->save()) {
                Yii::error($settingModel->errors);
                throw new Exception('Failed to update kiosk terminal setting');
            }
        } else {
            $settingModel = new TerminalSetting();
            $settingModel->terminalID = $this->terminalID;
            $settingModel->key = $key;
            $settingModel->value = ($value);

            if (!$settingModel->save()) {
                throw new Exception('Failed to update kiosk terminal setting');
            }
        }

        return true;
    }
}
