<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\components\AppHelper;
use app\models\Branch;
use app\models\Brand;
use app\models\BrandSetting;
use app\models\CancelReason;
use app\models\CashMethod;
use app\models\forms\AutoSync;
use app\models\forms\FCM;
use app\models\forms\PrinterTest;
use app\models\forms\Settings;
use app\models\forms\UpdateKiosk;
use app\models\forms\UpdateOds;
use app\models\forms\UpdateTableSide;
use app\models\Gender;
use app\models\LkColor;
use app\models\LkExternalMemberShipType;
use app\models\MapBranchVisitPurpose;
use app\models\MapVisitPurposeGroup;
use app\models\Notes;
use app\models\PaymentMethod;
use app\models\PosVersion;
use app\models\PrinterConnection;
use app\models\PrinterType;
use app\models\PrintingMode;
use app\models\PromotionMemberType;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\Station;
use app\models\VisitorType;
use app\models\VisitPurpose;
use app\models\VisitPurposeGroup;
use app\models\Menu;
use app\models\MsPosCustomerDisplayDetail;
use app\models\MsReaderSettingTamanSafari;
use app\models\Terminal;
use app\models\TerminalSetting;
use app\models\BranchEvent;
use app\models\BranchMenuDetail;
use app\models\forms\UpdateQds;
use Yii;
use yii\db\Exception;
use yii\helpers\Url;
use yii\web\HttpException;

class SettingController extends BaseController {
    public $printResults;
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index', 'local', 'get-cancel-reason', 'get-cash-denom', 'get-gender', 'get-map-branch-visit-purpose',
            'get-notes', 'get-payment-method', 'get-pos-data', 'get-printer-connection',
            'get-printer-type', 'get-promotion', 'get-shift-out-settings', 'get-station',
            'get-visit-purpose', 'get-customer-display-image', 'ods-setting', 'save-ods-settings', 'get-customer-display-logo',
            'local-setting-end-shift', 'get-kiosk-visit-purpose', 'get-printing-mode', 'get-payment-edc', 'get-visitor-type', 'get-visit-purpose-group',
            'get-eso-setting', 'save-qds-settings', 'auto-sync', 'get-queue-display-color-setting', 'get-test-print-bill', 'get-test-print-menu', 'get-payment-method-other-cost-employee-limit',
            'get-terminal-list', 'get-visitor-type-qs', 'get-visitor-type-fs', 'init-mode', 'get-terminal-actived', 'update-setting-install',
            'check-upadate-kiosk', 'set-kiosk-version', 'set-table-side-version', 'set-ods-version', 'get-terminal-setting', 'save-terminal-setting', 'get-terminal-self-order', 'reset-flag-eso-deactivated', 'get-ods-terminal-setting' , 'save-ods-terminal-settings', 'get-kiosk-terminal-setting', 'save-kiosk-terminal-settings',
            'download-update-kiosk','download-update-ods'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        return Setting::find()
                ->andWhere(['<>', 'key1', 'Local Setting'])
                ->all();
    }

    public function actionLocal() {
        $settingModel = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->all();

        $result = [];
        foreach ($settingModel as $setting) {
            $key = lcfirst(str_replace(' ', '', $setting->key2));
            if ($setting->value2 == 'Enc') {
                $result[$key] = Yii::$app->security->decryptByKey(base64_decode($setting->value1),
                    Yii::$app->params['key']);
            } else {
                if ($setting->key1 == 'Local Setting' && $setting->key2 == 'POS Auto Sync') {
                    $result['autoSyncPOS'] = (int) $setting->value1;
                } else {
                    $result[$key] = $setting->value1;
                }
            }
        }

        return $result;
    }
    
    public function actionOdsSetting() {
        $settingModel = Setting::find()
            ->andWhere(['key1' => 'POS'])
            ->andWhere(['OR',
                ['key2' => 'ODS Mode'],
                ['key2' => 'Finish All Packages']               
            ])
            ->all();

        $result = [];
        foreach ($settingModel as $setting) {
            $key = lcfirst(str_replace(' ', '', $setting->key2));
            if ($key == 'oDSMode') {
                $key = 'mode';
            }

            $result[$key] = (float) $setting->value1;
        }
        
        if (!$result) {            
            $result['finishAllPackages'] = 0;
            $result['mode'] = 1;
        }

        return $result;
    }
    
    public function actionLocalSetting() {
        $query = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['IN', 'key2', [
                'Print Cancelled Menu',
                'Print Cancelled Menu Summary',
                'Print Closing Notes',
                'Print Custom Menu Sales',
                'Print Deposit Detail',
                'Print Deposit Summary',
                'Print Withdrawal Detail',
                'Print Withdrawal Summary',
                'Print Daily Member Summary',
                'Print Non Sales Bill Summary',
                'Print Non Sales By Menu',
                'Print Non Sales Menu Summary',
                'Print Non Sales Payment by Cashier',
                'Print Non Sales Payment Method Detail',
                'Print Non Sales Payment Method Summary',
                'Print Payment by Cashier',
                'Print Payment Method Detail',
                'Print Payment Method Summary',
                'Print Pending Sales',
                'Print Promotion Summary',
                'Print Quick Service Table Text',
                'Print Sales by Menu Category',
                'Print Sales by Menu Category Detail',
                'Print Sales By Menu Group',
                'Print Sales by Menu Qty',
                'Print Sales by Menu Qty Value',
                'Print Sales by Menu Value',
                'Print Sales by Mode',
                'Print Sales by Type',
                'Print Sales by Table Section',
                'Print Sales By Visit Purpose',
                'Print Sales Menu by Mode',
                'Print Sales Menu Package',
                'Print Sales Per Date',
                'Print Sales per Menu Category',
                'Print Sales Voucher Usage',
                'Print Shift Sales by Menu Value',
                'Print Shift Summary',
                'Print Special Price Summary',
                'Print Void Payment Detail',
                'Print Void Payment Summary',
                'Queue Number'
                ]
            ])
            ->all();

        $result = [];
        foreach ($query as $query) {
            $query->value1 = $query->value1 == '1' ? true : false;
            $result[] = $query;
        }

        return $result;
    }
    
    public function actionPrintEndShiftSetting() {
        $query = Setting::find()
            ->andWhere(['key1' => 'POS'])
            ->andWhere(['IN', 'key2', [
                    'Print Shift Summary',
                    'Print Sales by Type',
                    'Print Payment Method Summary',
                    'Print Non Sales Payment Method Summary',
                    'Print Payment by Cashier',
                    'Print Non Sales Payment by Cashier',
                    'Print Deposit Summary',
                    'Print Payment Method Detail',
                    'Print Non Sales Payment Method Detail',
                    'Print Deposit Detail',
                    'Print Custom Menu Sales',
                    'Print Withdrawal Detail',
                    'Print Withdrawal Summary',
                    'Print Daily Member Summary'
                ]
            ])
            ->all();

        $result = [];
        foreach ($query as $query) {
            $query->value1 = $query->value1 == '1' ? true : false;
            $result[] = $query;
        }

        return $result;
    }
    
    public function actionPrintShiftSetting() {
        $query = Setting::find()
            ->andWhere(['key1' => 'POS'])
            ->andWhere(['IN', 'key2', [
                'Print Stock Branch Menu',
                'Print Cancelled Menu',
                'Print Cancelled Menu Summary',
                'Print Closing Notes',
                'Print Custom Menu Sales',
                'Print Deposit Detail',
                'Print Deposit Summary',
                'Print Withdrawal Detail',
                'Print Withdrawal Summary',
                'Print Daily Member Summary',
                'Print Non Sales Bill Summary',
                'Print Non Sales By Menu',
                'Print Non Sales Menu Summary',
                'Print Non Sales Payment by Cashier',
                'Print Non Sales Payment Method Detail',
                'Print Non Sales Payment Method Summary',
                'Print Payment by Cashier',
                'Print Payment Method Detail',
                'Print Payment Method Summary',
                'Print Pending Sales',
                'Print Promotion Summary',
                'Print Sales by Menu Category',
                'Print Sales by Menu Category Detail',
                'Print Sales By Menu Group',
                'Print Sales by Menu Qty',
                'Print Sales by Menu Qty Value',
                'Print Sales by Menu Value',
                'Print Sales by Mode',
                'Print Sales by Type',
                'Print Sales by Table Section',
                'Print Sales By Visit Purpose',
                'Print Sales Menu by Mode',
                'Print Sales Menu Package',
                'Print Sales Per Date',
                'Print Sales per Menu Category',
                'Print Sales Voucher Usage',
                'Print Shift Sales by Menu Value',
                'Print Shift Summary',
                'Print Special Price Summary',
                'Print Void Payment Detail',
                'Print Void Payment Summary'
                ]
            ])
            ->all();

        $result = [];
        foreach ($query as $query) {
            $query->value1 = $query->value1 == '1' ? true : false;
            $result[] = $query;
        }

        return $result;
    }

    public function actionGetCancelReason() {
        return CancelReason::findActive()->all();
    }

    public function actionGetCashDenom() {
        return CashMethod::findActive()->all();
    }

    public function actionGetGender() {
        return Gender::find()->all();
    }

    public function actionGetMapBranchVisitPurpose() {
        return MapBranchVisitPurpose::find()->all();
    }

    public function actionGetNotes() {
        return Notes::findActiveAsArray();
    }

    public function actionGetPaymentMethod() {
        $visitPurposeID = isset($this->request->post()['visitPurposeID'])? $this->request->post()['visitPurposeID'] : null;
        return PaymentMethod::findActiveAsArray($visitPurposeID);
    }

    public function actionGetPaymentMethodOtherCostEmployeeLimit() {
        return PaymentMethod::findPaymentMethodEmployeeLimit();
    }

    public function actionGetPaymentQris() {
        return PaymentMethod::findActiveQrisAsArray();
    }

    public function actionGetPosData() {
        $branchID = Setting::getCurrentBranch();

        $branchModel = Branch::findOne(['branchID' => $branchID]);
        $brandModel = Brand::find()
                ->joinWith('branch')
                ->andWhere(['branchID' => $branchID])
                ->one();
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $ezoBrandSetting = BrandSetting::getEzoBrandSetting();
        $settingModel = Setting::find()->all();
        $shiftLogModel = ShiftLog::find()
            ->where(['branchID' => $branchID])
            ->orderBy('shiftID DESC')
            ->limit(1)
            ->asArray()
            ->one();

        if ($shiftLogModel) {
            $shiftLogModel['shiftInTime'] = str_replace("-", "/", $shiftLogModel['shiftInTime']);
        }

        $appVersion = PosVersion::getAppVersion();
        
        $settingData = [];
        foreach ($settingModel as $setting) {
            $key = lcfirst(str_replace(' ', '', $setting->key2));
            if ($setting->key1 == 'VAT' && $setting->key2 == 'Value') {
                $key = 'vat';
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'URL Dashboard Event') {
                $key = 'urlDashboardEvent';
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'URL Image Logo Event') {
                $key = 'urlImageLogoEvent';
                $newValue1 = Url::to('@web/images/event-logo/' . basename($setting->value1), true);
                $newValueActualPath = Yii::getAlias('@app/web/images/event-logo') . '/' . basename($setting->value1);
                $setting->value1 = file_exists($newValueActualPath) ? $newValue1 : null;
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'URL Icon Logo Event') {
                $key = 'urlIconLogoEvent';
                $newValue1 = Url::to('@web/images/event-logo/' . basename($setting->value1), true);
                $newValueActualPath = Yii::getAlias('@app/web/images/event-logo') . '/' . basename($setting->value1);
                $setting->value1 = file_exists($newValueActualPath) ? $newValue1 : null;
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'Show Tax & VAT Amount Detail') {
                $key = 'showTaxAndVATAmountDetail';
            }

            if ($key == "notificationTime(Minutes)") {
                $settingData['notificationTime'] = $setting->value1;
            } else if ($key <> '') {
                if ($setting->key1 == 'Local Setting' && $setting->key2 == 'POS Auto Sync') {
                    $settingData['autoSyncPOS'] = (int) $setting->value1;
                } else {
                    $settingData[$key] = $setting->value1;
                }
            }

            if ($setting->value2 == 'Enc') {
                $settingData[$key] = Yii::$app->security->decryptByKey(base64_decode($setting->value1),
                    Yii::$app->params['key']);
            }

            //validasi notification closing
            if ($setting->key1 == 'POS' && $setting->key2 == 'Shift Notification Closing') {
                $shift = ShiftLog::find()
                    ->where('shiftOutTime is null')
                    ->one();

                if ($shift) {
                    $checkShiftDate = date('Y-m-d H:i:s') > date('Y-m-d ',
                            strtotime($shift->shiftInTime . ' +1 day')) . $setting->value2;

                    if ($setting->value1 == 1 && $checkShiftDate == true) {
                        $settingData['flagNotificationClosing'] = 1;
                    } else {
                        $settingData['flagNotificationClosing'] = 0;
                    }
                } else {
                    $settingData['flagNotificationClosing'] = 0;
                }
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'Customer Order Time') {
                $settingData['flagCustomerOrderTime'] = (int) $setting->value1;
                $settingData['customerOrderTime'] = $setting->value2;
            }

            if ($setting->key1 == 'Local Setting' && $setting->key2 == 'Auto Sync POS') {
                $settingData['autoSyncPOS'] = (int)$setting->value1;
            }

            if ($setting->key1 == 'POS' && $setting->key2 == 'Kitchen Fire Management') {
                $settingData['kitchenFireManagement'] = (int) $setting->value1;
            }
            if ($setting->key1 == 'Local Setting' && $setting->key2 == 'Generate Aevitas') {
                $settingData['aevitasVoucher'] = (int) $setting->value1;
            }

            //@Notes: hardcode setting ui payment version
            if ($setting->key1 == 'POS' && $setting->key2 == 'Show New Payment Version') {
                $settingData['showNewPaymentVersion'] = filter_var($setting->value1, FILTER_VALIDATE_BOOLEAN);
            }

            //@Notes: hardcode setting ui order version
            if ($setting->key1 == 'POS' && $setting->key2 == 'Show New Ordering Layout Version') {
                $settingData['showNewOrderingLayoutVersion'] = filter_var($setting->value1, FILTER_VALIDATE_BOOLEAN);
            }
        }

        // @Notes: Give default value
        if (!isset($settingData['takeAwayPrintBill'])) {
            $settingData['takeAwayPrintBill'] = '1';
        }
        if (!isset($settingData['takeAwayPrintChecker'])) {
            $settingData['takeAwayPrintChecker'] = '1';
        }
        if (!isset($settingData['dineInPrintChecker'])) {
            $settingData['dineInPrintChecker'] = '1';
        }
        if (!isset($settingData['printEZOTAReceipt'])) {
            $settingData['printEZOTAReceipt'] = 0;
        }
        if (!isset($settingData['printEZOTableChecker'])) {
            $settingData['printEZOTableChecker'] = 0;
        }
        if (!isset($settingData['limitAccessODS'])) {
            $settingData['limitAccessODS'] = '0';
        }
        if(!isset($settingData['uvLoyaltyIdentifier'])){
            $settingData['uvLoyaltyIdentifier'] = 0;
        }
        
        // @Notes: Set app version value
        $settingData['appVersion'] = $appVersion['name'];

        if ($setting->key1 == 'Local Setting' && $setting->key2 == 'Generate Aevitas') {
            $settingData['voucherDir'];
            $settingData['redeemDir'];
        }

        $settingData['externalMember'] = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $settingData['flagInputPhoneNum'] = array_key_exists('Flag Input Phone Num', $externalMemberSetting) ? (int) $externalMemberSetting['Flag Input Phone Num'] : 0;
        $settingData['mandatoryMemberPhoneNumber'] = array_key_exists('Mandatory Member Phone Number (Subway)', $externalMemberSetting) ? (int) $externalMemberSetting['Mandatory Member Phone Number (Subway)'] : 0;
        
        // @Notes: External membership
        $settingData['membershipType'] = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $memberShipTypeName = LkExternalMemberShipType::find()->where(['externalMembershipTypeID' => $settingData['membershipType']])->one();
        $settingData['membershipTypeName'] = $memberShipTypeName ? $memberShipTypeName->externalMembershipTypeName : null;
        $settingData['showQrOnCustomerDisplay'] = array_key_exists('Show QR on Customer Display', $externalMemberSetting) ? (bool)$externalMemberSetting['Show QR on Customer Display'] : false;
        $settingData['uvLoyaltyIdentifier'] = array_key_exists('Ultra Voucher Loyalty Identifier', $externalMemberSetting) ? (int)$externalMemberSetting['Ultra Voucher Loyalty Identifier'] : 0;

        $settingData['brandColor'] = array_key_exists('TA Theme Color', $ezoBrandSetting) ? $ezoBrandSetting['TA Theme Color'] : null;
        $settingData['textColor'] = Menu::defineMenuTextColor($settingData['brandColor'] ? $settingData['brandColor'] : '#ffffff');
        $settingData['logoUrl'] = array_key_exists('Logo URL', $ezoBrandSetting) ? $ezoBrandSetting['Logo URL'] : null;
        $memberPromoPopUp = BrandSetting::getBrandPosSetting('Member Promo Pop Up');
        $settingData['memberPromoPopUp'] = array_key_exists('Member Promo Pop Up', $memberPromoPopUp) ? $memberPromoPopUp['Member Promo Pop Up'] : null;

        //@Notes: hardcode setting ui payment version
        $settingData['showNewPaymentVersion'] = $settingData['showNewPaymentVersion'] ?? false;        
        $settingData['showNewOrderingLayoutVersion'] = $settingData['showNewOrderingLayoutVersion'] ?? false;        

        $promotionMemberType = [];
        $promotionMemberTypeModel = PromotionMemberType::find()->all();
        if ($promotionMemberTypeModel) {
            foreach ($promotionMemberTypeModel as $promos) {
                if ($promos['promotionMemberTypeID'] == 0) {
                    $promotionMemberType[] = ['promotionMemberTypeID' => $promos['promotionMemberTypeID'], 'promotionMemberTypeName' => 'Public'];
                } else if ($promos['promotionMemberTypeID'] == 3) {
                    $promotionMemberType[] = ['promotionMemberTypeID' => $promos['promotionMemberTypeID'], 'promotionMemberTypeName' => 'Member'];
                } else if ($promos['promotionMemberTypeID'] == 2) {
                    $promotionMemberType[] = ['promotionMemberTypeID' => $promos['promotionMemberTypeID'], 'promotionMemberTypeName' => 'Employee'];
                } else {
                    $promotionMemberType[] = ['promotionMemberTypeID' => $promos['promotionMemberTypeID'], 'promotionMemberTypeName' => 'Member/Staf'];
                }
            }
        }
        $settingData['promotionMemberType'] = $promotionMemberType;

        // @Notes: QRIS BPD Bali
        $mpan = BrandSetting::getBrandPosSetting('BPD Merchant PAN');
        $settingData['mpan'] = array_key_exists('BPD Merchant PAN', $mpan) ? $mpan['BPD Merchant PAN'] : null;

        // @Notes: KIOSK Theme Color
        $settingData['colors'] = LkColor::findPosColors();
        
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');
        if ($trialMode) {
            $settingData['trialMode'] = (float) $trialMode->value1;
        }

        if ($brandModel) {
            $settingData['brandName'] = $brandModel->brandName;
        }

        // @Notes: Limit Point Usage
        $settingData['limitPointUsage'] = array_key_exists('Limit Point Usage', $externalMemberSetting) ? (int)$externalMemberSetting['Limit Point Usage'] : 0;
        $settingData['limitPointUsageApiUrl'] = array_key_exists('Limit Point Usage URL', $externalMemberSetting) ? $externalMemberSetting['Limit Point Usage URL'] : '';

        return array_merge(
            $branchModel->toArray(), $settingData, $shiftLogModel ? $shiftLogModel : []
        );
    }

    public function actionGetPrinterConnection() {
        return PrinterConnection::find()->all();
    }

    public function actionGetPrinterType() {
        return PrinterType::find()->all();
    }
    
    public function actionGetPrintingMode() {
        return PrintingMode::find()->all();
    }

    public function actionGetStation() {
        $branchID = Setting::getCurrentBranch();

        return Station::findActive()
                ->all();
    }

    public function actionGetVisitPurpose() {
        return VisitPurpose::findActive()
                ->innerJoinWith('mapBranchVisitPurpose')
                ->all();
    }

    public function actionGetVisitPurposeGroup() {
        return VisitPurposeGroup::findVisitPurposeGroups();
    }

    public function actionGetKioskVisitPurpose() {
        return VisitPurpose::findActive()
                ->joinWith('mapBranchVisitPurpose')
                ->andWhere([MapBranchVisitPurpose::tableName() . '.flagKiosk' => 1])
                ->all();
    }

    public function actionGetEsoSetting() {
        return Setting::getEZOSetting();
    }

    public function actionSubscribeToTopic() {
        if (!$this->request->post('token')) {
            throw new HttpException(400);
        }

        $fcmModel = new FCM([
            'attributes' => $this->request->post()
        ]);
        return $fcmModel->subscribe();
    }

    public function actionUnsubscribeToTopic() {
        if (!$this->request->post('token')) {
            throw new HttpException(400);
        }

        $fcmModel = new FCM([
            'attributes' => $this->request->post()
        ]);
        return $fcmModel->unsubscribe();
    }

    public function actionSave() {
        $settingModel = new Settings([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$settingModel->save()) {
                throw new Exception(json_encode($settingModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data ' .$ex->getMessage()));
        }
    }

    public function actionSaveShiftOutPrintingSettings() {
        $settingModel = new Settings();
        $settingModel->shiftOutPrintingSettings = $this->request->post();

        try {
            if (!$settingModel->saveShiftOutPrintingSettings()) {
                throw new Exception(json_encode($settingModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data '. $ex->getMessage()));
        }
    }
    
    public function actionSaveOdsSettings() {
        $settingModel = new Settings();
        $settingModel->odsSettings = $this->request->post();

        try {
            if (!$settingModel->saveOdsSettings()) {
                throw new Exception(json_encode($settingModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data '. $ex->getMessage()));
        }
    }

    public function actionTestPrint() {
        $printingModel = new PrinterTest([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrinterTest::SCENARIO_PRINT_TEST;
        $printingModel->runTest();
        
        if($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()
            ];
        }
    }

    public function actionOpenDrawer() {
        $printingModel = new PrinterTest([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrinterTest::SCENARIO_OPEN_DRAWER;
        $printingModel->runTest();
        return AndroidPrintConnector::getData();
    }

    public function actionTestPrintBill() {
        $printModel = new PrinterTest([
            'attributes' => $this->request->post()
        ]);
        $printModel->scenario = PrinterTest::SCENARIO_TEST_PRINT_BILL;
        $printModel->runTest();
        return AndroidPrintConnector::getData();
    }

    public function actionTestPrintMenu() {
        $printModel = new PrinterTest([
            'attributes' => $this->request->post()
        ]);
        $printModel->scenario = PrinterTest::SCENARIO_TEST_PRINT_MENU;
        $printModel->runTest();
        return AndroidPrintConnector::getData();
    }

    public function actionGetCustomerDisplayImage() {
        $applicationID = $this->request->post("applicationID");
        return MsPosCustomerDisplayDetail::getCustomerDisplayImage($applicationID);
    }
    
    public function actionGetCustomerDisplayLogo() {
        $brandSettingModel = BrandSetting::find()
            ->innerJoin(Branch::tableName(),
                'ms_branch.brandID = ms_brandsetting.brandID')
            ->where(['=','ms_brandsetting.brandSettingID',37])
            ->one();
        if($brandSettingModel && $brandSettingModel->value1) {
            return Url::to('@web/images/customer-display-logo/' . $brandSettingModel->value1, true);
        } else {
            return "assets/images/logo-customer-display.png";
        }
    }
    
    public function actionGetPaymentEdc() {
        return PaymentMethod::findActiveEdcPaymentAsArray();
    }
    
    public function actionGetVisitorTypeFs() {
        return VisitorType::findActive(1)->all();
    }
    
    public function actionGetVisitorTypeQs() {
        return VisitorType::findActive(null, 1)->all();
    }

    public function actionCheckUpdateOds() {
        $model = new UpdateOds([
            'attributes' => $this->request->post()
        ]);

        return $model->checkUpdate();
    }

    public function actionApplyUpdateOds() {
        $model = new UpdateOds([
            'attributes' => $this->request->post()
        ]);

        return $model->applyUpdate();
    }

    public function actionCheckUpdateKiosk() {
        $kioskModel = new UpdateKiosk([
            'attributes' => $this->request->post()
        ]);

        $checkUpdate = $kioskModel->checkUpdate();

        if (!$kioskModel->errorMessage) {
            return $checkUpdate;
        } else {
            return $kioskModel->errorMessage;
        }
    }

    public function actionApplyUpdateKiosk() {
        $model = new UpdateKiosk([
            'attributes' => $this->request->post()
        ]);

        return $model->applyUpdate();
    }

    public function actionDownloadUpdateKiosk() {
        $model = new UpdateKiosk([
            'attributes' => $this->request->post()
        ]);

        return $model->downloadUpdateKioskVersion();
    }

    public function actionDownloadUpdateOds() {
        $model = new UpdateOds([
            'attributes' => $this->request->post()
        ]);

        return $model->downloadUpdateOdsVersion();
    }

    public function actionCheckUpadateTableSide() {
        $tableSideModel = new UpdateTableSide([
            'attributes' => $this->request->post()
        ]);
        
        $checkUpdate = $tableSideModel->checkUpdate();

        if (!$tableSideModel->errorMessage) {
            return $checkUpdate;
        } else {
            return $tableSideModel->errorMessage;
        }
    }

    public function actionApplyUpdateTableSide() {
        $model = new UpdateTableSide ([
            'attributes' => $this->request->post()
        ]);

        return $model->applyUpdate();
    }

    public function actionSetKioskVersion() {
        $currentVersion = $this->request->post('currentVersion');

        UpdateKiosk::setKioskVersion($currentVersion, true, 'kiosk');
    }

    public function actionSetTableSideVersion() {
        $currentVersion = $this->request->post('currentVersion');

        UpdateTableSide::setTableSideVersion($currentVersion, true, 'tableSide');
    }

    public function actionSetOdsVersion() {
        $currentVersion = $this->request->post('currentVersion');

        UpdateOds::setOdsVersion($currentVersion, true, 'ods');
    }

    public function actionAutoSync(){
        try {
            $autoSync = new AutoSync();
            $autoSync->run();
            return true;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public function actionGetPaymentQrisSetting() {
        $posExternalPaymentID = $this->request->post('posExternalPaymentID');
        $externalPaymentSetting = Setting::getExternalPaymentSetting($posExternalPaymentID);
        if (in_array($posExternalPaymentID, ['qrisnobu', 'qrisotopay', 'qrisgpay', 'qrisbri'])) {
            if (!$externalPaymentSetting['status']) return $externalPaymentSetting['status'];
        }
        return true;
    }

    public function actionGetQueueDisplayColorSetting() {
       return Setting::getQueueDisplayColorSetting();      
    }

    public function actionGetTerminalList() {
        return Terminal::fetchTerminalList();
    }

    public function actionGetTerminalActived() {
        $terminalModel = Terminal::findOne([
            'terminalCode' => $this->request->post('terminalCode')
        ]);
        if ($terminalModel) {
            if ($terminalModel->statusID == 47) return $terminalModel;
        }
        return null;
    }

    public function actionGetLockTerminal() {
        $lockTerminal = false;
        $terminalModel = Terminal::findOne([
            'terminalCode' => $this->request->post('terminalCode'),
        ]);
        $tempActivatedDate = date('Y-m-d H:i:s', $this->request->post('activatedDate'));
        if ($terminalModel) {
            if ($terminalModel->activatedDate == $tempActivatedDate) $lockTerminal = true;
        }
        return $lockTerminal;
    }

    public function actionInitMode() {
        $status = $this->request->post('status');
        try {
            $settingsModel = new Settings();
            if (!$settingsModel->saveUpdateSetting($status, true)) {
                throw new Exception(json_encode($settingsModel->errors));
            }
            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveChangeMode() {
        $status = $this->request->post('status');
        try {
            $settingsModel = new Settings();
            if (!$settingsModel->saveUpdateSetting($status)) {
                throw new Exception(json_encode($settingsModel->errors));
            }
            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionUpdateSettingInstall() {
        try {
            $settingsModel = new Settings();
            if (!$settingsModel->saveUpdateSettingInstall()) {
                throw new Exception(json_encode($settingsModel->errors));
            }
            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionGetTerminalSetting()
    {
        $terminalID = $this->request->post('terminalID');
        try {
            return TerminalSetting::getTerminalSettings($terminalID);
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionGetOdsTerminalSetting()
    {
        $terminalID = $this->request->post('terminalID');
        try {
            return TerminalSetting::getOdsTerminalSettings($terminalID);
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionGetKioskTerminalSetting()
    {
        $terminalID = $this->request->post('terminalID');
        try {
            return TerminalSetting::getKioskTerminalSettings($terminalID);
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveTerminalSetting()
    {
        $terminalSettingModel = new Settings([
            'attributes' => $this->request->post()
        ]);
        
        try {
            //@defaultQS reset 0
            if($terminalSettingModel->visitPurposeQs && $terminalSettingModel->defaultVisitPurposeQs){
                $arrVisitPurposeQS = $terminalSettingModel->visitPurposeQs ? explode(',', $terminalSettingModel->visitPurposeQs) : [];
                if(!in_array($terminalSettingModel->defaultVisitPurposeQs, $arrVisitPurposeQS)){
                    $terminalSettingModel->defaultVisitPurposeQs = 0;
                }
            }
            if (!$terminalSettingModel->saveTerminalSetting()) {
                throw new Exception(json_encode($terminalSettingModel->errors));
            }

            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveOdsTerminalSettings()
    {
        $terminalSettingModel = new Settings([
            'attributes' => $this->request->post()
        ]);
        
        try {
            if (!$terminalSettingModel->saveOdsTerminalSettings()) {
                throw new Exception(json_encode($terminalSettingModel->errors));
            }

            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSaveKioskTerminalSettings()
    {
        $terminalSettingModel = new Settings([
            'attributes' => $this->request->post()
        ]);
        
        try {
            if (!$terminalSettingModel->saveKioskTerminalSettings()) {
                throw new Exception(json_encode($terminalSettingModel->errors));
            }

            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionGetTerminalSelfOrder() 
    {
        try {
            return TerminalSetting::getTerminalSelfOrder();
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionResetFlagEsoDeactivated()
    {
        try {
            $flagEsoDeactivate = Setting::findOne([
                'key2' => 'ESO FS Deactivated',
                'value1' => 1
            ]);

            if($flagEsoDeactivate) {
                $flagEsoDeactivate->value1 = 0;
                if(!$flagEsoDeactivate->save()) {
                    throw new Exception('Failed to update flag ESO FS Deactivated');
                }
            }

            return true;
        } catch (Exception $ex) {
            Yii::warning($ex->getMessage());
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionGetStiReaderSettings() 
    {   
        $terminalSettingModel = new MsReaderSettingTamanSafari([
            'attributes' => $this->request->post()
        ]);

        try {
            return $terminalSettingModel->getDataReaderSettings();
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }
    
    public function actionRemoveBranchEvent() {
        $tableShiftLog = ShiftLog::tableName();
        try {
            BranchEvent::deleteAll(
                'eventDate < (SELECT DATE_SUB(MAX(shiftOutTime), INTERVAL 6 MONTH) FROM ' . $tableShiftLog . ')'
            );
            return [
                'status' => 'success',
                'message' => 'Success deleted data'
            ];
        } catch (Exception $e) {
            Yii::error($e);
            return [
                'status' => 'error',
                'message' => 'An error occurred while deleting branch events: ' . $e->getMessage(),
            ];
        }
    }

    public function actionCheckNewMenu(){
        $newMenuExists = BranchMenuDetail::checkNewMenu();
        if($newMenuExists){
            return true;
        }
        return false;
    }

    public function actionUpdateBranchMenu(){
        $saveData = BranchMenuDetail::saveData();
        if($saveData){
            return true;
        }
        return false;
    }
    
    public function actionCheckUpdateQds() {
        $model = new UpdateQds([
            'attributes' => $this->request->post()
        ]);

        return $model->checkUpdate();
    }
    public function actionApplyUpdateQds()
    {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
        try {
            $model = new UpdateQds([
                'attributes' => $this->request->post()
            ]);
            $model->qdsBaseHref = "/esb-qds"; 
            $model->qdsVersion =  $this->request->post('currentVersion');
            if (!$model->applyUpdate()) {
                throw new HttpException(
                    400,
                    Yii::t('app', 'Failed to apply updates')
                );
            }
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionSetQdsVersion() {
        $currentVersion = $this->request->post('currentVersion');

        UpdateQds::setQdsVersion($currentVersion, true, 'qds');
    }
}
