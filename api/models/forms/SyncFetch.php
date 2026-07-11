<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BranchMenuTransaction;
use app\models\Employee;
use app\models\Member;
use app\models\ProductDetailMenu;
use app\models\SalesMenu;
use app\models\Setting;
use app\models\Voucher;
use app\models\forms\SyncOptimize;
use app\models\MsReaderSettingTamanSafari;
use app\models\PosUser;
use app\models\Table;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\httpclient\Client;

/**
 * @property string $syncType
 * 
 * PRIVATE
 * @property string $apiKey
 * @property string $apiUrl
 * @property int $branchID
 */
class SyncFetch extends Model {
    const API_VERSION = 'esb_apiv11';
    const FETCH_MASTER_SETTINGS = 'fetchMasterSettings';
    const FETCH_BRANCH_SETTINGS = 'fetchBranchSettings';
    const FETCH_MEMBER = 'fetchMember';
    const FETCH_MENU = 'fetchMenu';
    const FETCH_PROMOTION = 'fetchPromotion';
    const FETCH_TABLE = 'fetchTable';
    const FETCH_USER = 'fetchUser';
    const FETCH_POS_VERSION = 'fetchPosVersion';
    const FETCH_VOUCHER = 'fetchVoucher';
    const FETCH_SALES = 'fetchSales';
    const FETCH_BRANCH_MENU = 'fetchBranchMenu';
    const FETCH_POS_NOTIFICATION = 'fetchPosNotification';
    const FETCH_STI_READER = 'fetchStiReaderSettings';

    // Sync Optimize Keys
    const MEMBER = 'MEMBER';
    const MEMBER_DEPOSIT = 'MEMBER DEPOSIT';
    const MEMBER_WITHDRAWAL = 'MEMBER WITHDRAWAL';
    const VOUCHER = 'VOUCHER';

    public $syncType;
    public $apiKey;
    public $apiUrl;
    public $branchID;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiKey = Setting::getApiKey();
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['syncType'], 'required'],
            [['syncType'], 'string', 'max' => 100]
        ];
    }

    public function doSync() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            switch ($this->syncType) {
                case self::FETCH_MASTER_SETTINGS:
                    $this->fetchMasterSettings();
                    break;
                case self::FETCH_BRANCH_SETTINGS:
                    $this->fetchBranchSettings();
                    break;
                case self::FETCH_MEMBER:
                    $this->fetchMember();
                    break;
                case self::FETCH_MENU:
                    $this->fetchMenu();
                    break;
                case self::FETCH_PROMOTION:
                    $this->fetchPromotion();
                    break;
                case self::FETCH_TABLE:
                    $this->fetchTable();
                    break;
                case self::FETCH_USER:
                    $this->fetchUser();
                    break;
                case self::FETCH_POS_VERSION:
                    $this->fetchPosVersion();
                    break;
                case self::FETCH_VOUCHER:
                    $this->fetchVoucher();
                    break;
                case self::FETCH_SALES:
                    $this->fetchSales();
                    break;
                case self::FETCH_BRANCH_MENU:
                    $this->fetchBranchMenu();
                    break;
                case self::FETCH_POS_NOTIFICATION:
                    $this->fetchPosNotification();
                    break;
                case self::FETCH_STI_READER:
                    $this->fetchStiReaderSettings();
                    break;
                default:
                    break;
            }

            Logging::save('-', Logging::SYNC_FETCH, $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());

            if (Yii::$app->controller->id == 'sync')
                Logging::save('-', Logging::FAILED_SYNC_ERROR, $this->getAttributes(), $ex->getMessage());

            return false;
        }
    }

    private function fetchMasterSettings() {
  
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-master-settings';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $data =   ['branchID' => $this->branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $data, $options);
        
        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $day = $responseData['day'];
            $gender = $responseData['gender'];
            $paymentMethodType = $responseData['paymentMethodType'];
            $posAccessControl = $responseData['posAccessControl'];
            $printerConnection = $responseData['printerConnection'];
            $printerType = $responseData['printerType'];
            $promotionType = $responseData['promotionType'];
            $status = $responseData['status'];
            $tableType = $responseData['tableType'];
            $posCalculation = $responseData['posCalculation'];
            $cardNumberValidationType = $responseData['cardNumberValidationType'];
            $lkColor = $responseData['lkColor'];
            $lkColorDetail = $responseData['lkColorDetail'];
            
            $this->saveModel('app\models\Day', $day);
            $this->saveModel('app\models\Gender', $gender);
            $this->saveModel('app\models\PaymentMethodType', $paymentMethodType);
            $this->saveModel('app\models\PosAccessControl', $posAccessControl);
            $this->saveModel('app\models\PrinterConnection', $printerConnection);
            $this->saveModel('app\models\PrinterType', $printerType);
            $this->saveModel('app\models\PromotionType', $promotionType);
            $this->saveModel('app\models\Status', $status);
            $this->saveModel('app\models\TableType', $tableType);
            $this->saveModel('app\models\PosCalculation', $posCalculation);
            $this->saveModel('app\models\LkCardNumberValidationType', $cardNumberValidationType);
            $this->saveModel('app\models\LkColor', $lkColor);
            $this->saveModel('app\models\LkColorDetail', $lkColorDetail);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchBranchSettings() {
    
        $lastSycnDate = null;
        $syncOptModel = SyncOptimize::find()
            ->where(['syncType' => 'VOUCHER'])->one();

        if($syncOptModel) {
            $lastSycnDate = $syncOptModel->pushDateTime;
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-branch-settings';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $data =   [
            'branchID' => $this->branchID,
            'voucherLastSyncDate' => $lastSycnDate
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $data, $options);

    
        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $branch = $responseData['branch'];
            $cancelReason = $responseData['cancelReason'];
            $cashMethod = $responseData['cashMethod'];
            $mapSelfOrderPaymentMethod = $responseData['mapSelfOrderPaymentMethod'];
            $notes = $responseData['notes'];
            $notesCategory = $responseData['notesCategory'];
            $paymentMethod = $responseData['paymentMethod'];
            $paymentMethodExternalVoucher = $responseData['paymentMethodExternalVoucher'];
            $mapVisitPurposePaymentMethod = $responseData['mapVisitPurposePaymentMethod'];
            $setting = $responseData['setting'];
            $station = $responseData['station'];
            $transNumber = $responseData['transNumber'];
            $visitPurpose = $responseData['visitPurpose'];
            $mapVisitPurposeGroup = $responseData['mapVisitPurposeGroup'];
            $visitPurposeGroup = $responseData['visitPurposeGroup'];
            $visitorType = $responseData['visitorType'];
            $voucher = $responseData['voucher'];
            $brand = $responseData['brand'];
            $brandApiContent = $responseData['brandApiContent'];
            $lkBrandSetting = $responseData['lkBrandSetting'];
            $brandSetting = $responseData['brandSetting'];
            $questionnaire = isset($responseData['questionnaire']) ? $responseData['questionnaire'] : [];
            $questionOption = isset($responseData['questionOption']) ? $responseData['questionOption'] : [];
            $posExternalPayment = $responseData['posExternalPayment'];
            $msBranchBusinessHour = $responseData['msBranchBusinessHour'];
            $mapBranchPosCustomerDisplay = $responseData['mapBranchPosCustomerDisplay'];
            $msPosCustomerDisplayHead = $responseData['msPosCustomerDisplayHead'];
            $msPosCustomerDisplayDetail = $responseData['msPosCustomerDisplayDetail'];
            $mapStationPosCustomerDisplay = $responseData['mapStationPosCustomerDisplay'];
            $voucherTemplate = $responseData['voucherTemplate'];
            $voucherTemplateDetail = $responseData['voucherTemplateDetail'];
            $lkExternalMemberShipType = $responseData['lkExternalMemberShipType'];
            $mapNotesMenuCategory = $responseData['mapNotesMenuCategory'];
            $mapNotesMenuCategoryDetail = $responseData['mapNotesMenuCategoryDetail'];
            $msPosCustomerDisplayApplication = $responseData['msPosCustomerDisplayApplication'];
            $terminalCode = $responseData['terminalCode'];

            $this->saveModel('app\models\Branch', $branch);
            $this->saveModel('app\models\CancelReason', $cancelReason);
            $this->saveModel('app\models\CashMethod', $cashMethod);
            $this->saveModel('app\models\MapSelfOrderPaymentMethod', $mapSelfOrderPaymentMethod);
            $this->saveModel('app\models\Notes', $notes);
            $this->saveModel('app\models\NotesCategory', $notesCategory);
            $this->saveModel('app\models\PaymentMethod', $paymentMethod);
            $this->saveModel('app\models\PaymentMethodExternalVoucher', $paymentMethodExternalVoucher);
            $this->saveModel('app\models\MapVisitPurposePaymentMethod', $mapVisitPurposePaymentMethod);
            $this->saveModel('app\models\Setting', $setting);
            $this->saveModel('app\models\Station', $station);
            $this->saveModel('app\models\TransNumber', $transNumber);
            $this->saveModel('app\models\VisitPurpose', $visitPurpose);
            $this->saveModel('app\models\MapVisitPurposeGroup', $mapVisitPurposeGroup);
            $this->saveModel('app\models\VisitPurposeGroup', $visitPurposeGroup);
            $this->saveModel('app\models\VisitorType', $visitorType);
            $this->saveModel('app\models\Voucher', $voucher);
            $this->saveModel('app\models\Brand', $brand);
            $this->saveModel('app\models\LkBrandSetting', $lkBrandSetting);
            $this->saveModel('app\models\BrandSetting', $brandSetting);
            $this->saveModel('app\models\BrandApiContent', $brandApiContent);
            $this->saveModel('app\models\Questionnaire', $questionnaire);
            $this->saveModel('app\models\QuestionOption', $questionOption);
            $this->saveModel('app\models\PosExternalPayment', $posExternalPayment);
            $this->saveModel('app\models\MsBranchBusinessHour', $msBranchBusinessHour);
            $this->saveModel('app\models\MapBranchPosCustomerDisplay', $mapBranchPosCustomerDisplay);
            $this->saveModel('app\models\MsPosCustomerDisplayHead', $msPosCustomerDisplayHead);
            $this->saveModel('app\models\MsPosCustomerDisplayDetail', $msPosCustomerDisplayDetail);
            $this->saveModel('app\models\MapStationPosCustomerDisplay', $mapStationPosCustomerDisplay);
            $this->saveModel('app\models\VoucherTemplate', $voucherTemplate);
            $this->saveModel('app\models\VoucherTemplateDetail', $voucherTemplateDetail);
            $this->saveModel('app\models\LkExternalMemberShipType', $lkExternalMemberShipType);
            $this->saveModel('app\models\MapNotesMenuCategory', $mapNotesMenuCategory);
            $this->saveModel('app\models\MapNotesMenuCategoryDetail', $mapNotesMenuCategoryDetail);
            $this->saveModel('app\models\PosCustomerDisplayApplication', $msPosCustomerDisplayApplication);
            $this->saveModel('app\models\Terminal', $terminalCode);
            
            $this->saveBranchLogo();
            $this->saveBranchFooterLogo();
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchMember() {
        $syncDateMember = null;
        $syncDateMemberDeposit = null;
        $syncDateMemberWithdrawal = null;

        $syncOptModel = SyncOptimize::find()
        ->where(['IN', 'syncType', ['MEMBER', 'MEMBER DEPOSIT', 'MEMBER WITHDRAWAL']]);
        foreach ($syncOptModel->all() as $value) {
            if ($value['syncType'] === 'MEMBER') { 
                $syncDateMember = $value['pushDateTime']; 
            }
            if ($value['syncType'] === 'MEMBER DEPOSIT') { 
                $syncDateMemberDeposit = $value['pushDateTime']; 
            }
            if ($value['syncType'] === 'MEMBER WITHDRAWAL') { 
                $syncDateMemberWithdrawal = $value['pushDateTime']; 
            }
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-member';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $options = ['timeOut' => 300];
        $datas = ['branchID' => $this->branchID];
        if($syncOptModel) {
            $datas = [
                'branchID' => $this->branchID,
                'lastSyncDateMember' => $syncDateMember,
                'lastSyncDateMemberDeposit' => $syncDateMemberDeposit,
                'lastSyncDateMemberWithdrawal' => $syncDateMemberWithdrawal
            ];
        }
        $response = $httpService->post($url, $headers, $datas, $options);
    
        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }
            
            $member = $responseData['member'];
            $memberDeposit = $responseData['memberDeposit'];
            $depositWithdrawalHead = $responseData['depositWithdrawalHead'];
            $employee = $responseData['employee'];
            $employeeGroup = $responseData['employeeGroup'];
            $employeeGroupDetail = $responseData['employeeGroupDetail'];

            $this->saveModel('app\models\Member', $member);
            $this->saveModel('app\models\MemberDeposit', $memberDeposit);
            $this->saveModel('app\models\DepositWithdrawalHead', $depositWithdrawalHead);
            $this->saveModel('app\models\MsEmployee', $employee);
            $this->saveModel('app\models\EmployeeGroup', $employeeGroup);
            $this->saveModel('app\models\EmployeeGroupDetail', $employeeGroupDetail);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchMenu() {

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-menu';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $data =   ['branchID' => $this->branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $data, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $branchMenu = $responseData['branchMenu'];
            $mapBranchVisitPurpose = $responseData['mapBranchVisitPurpose'];
            $menu = $responseData['menu'];
            $menuCategory = $responseData['menuCategory'];
            $menuCategoryDetail = $responseData['menuCategoryDetail'];
            $menuExtra = $responseData['menuExtra'];
            $menuGroup = $responseData['menuGroup'];
            $menuPackage = $responseData['menuPackage'];
            $menuTemplateDetail = $responseData['menuTemplateDetail'];
            $menuTemplateDetailDay = $responseData['menuTemplateDetailDay'];
            $menuTemplateHead = $responseData['menuTemplateHead'];
            $menuTemplateLayout = $responseData['menuTemplateLayout'];
            $menuTemplateCategory = $responseData['menuTemplateCategory'];
            $menuTemplateCategoryDetail = $responseData['menuTemplateCategoryDetail'];
            $mapMenuTemplatePackage = $responseData['mapMenuTemplatePackage'];
            $menuSize = $responseData['menuSize'];
            $hubMenu = $responseData['hubMenu'];
            $hubHost = $responseData['hubHost'];
            $menuIcon = $responseData['menuIcon'];
            $mapMenuIcon = $responseData['mapMenuIcon'];
            $productDetailMenu = $responseData['productDetailMenu'];
            $maxOrder = $responseData['maxOrder'];
            $maxOrderDetail = $responseData['maxOrderDetail'];
            $menuRecommendationHead = $responseData['menuRecommendationHead'];
            $menuRecommendationDetail = $responseData['menuRecommendationDetail'];
            $menuRecommendationGroup = $responseData['menuRecommendationGroup'];

            $this->saveModel('app\models\BranchMenu', $branchMenu);
            $this->saveModel('app\models\BranchMenuDetail', $branchMenu);
            $this->saveModel('app\models\MapBranchVisitPurpose', $mapBranchVisitPurpose);
            $this->saveModel('app\models\Menu', $menu);
            $this->saveModel('app\models\MenuCategory', $menuCategory);
            $this->saveModel('app\models\MenuCategoryDetail', $menuCategoryDetail);
            $this->saveModel('app\models\MenuExtra', $menuExtra);
            $this->saveModel('app\models\MenuGroup', $menuGroup);
            $this->saveModel('app\models\MenuPackage', $menuPackage);
            $this->saveModel('app\models\MenuTemplateDetail', $menuTemplateDetail);
            $this->saveModel('app\models\MenuTemplateDetailDay', $menuTemplateDetailDay);
            $this->saveModel('app\models\MenuTemplateHead', $menuTemplateHead);
            $this->saveModel('app\models\MenuTemplateCategory', $menuTemplateCategory);
            $this->saveModel('app\models\MenuTemplateCategoryDetail', $menuTemplateCategoryDetail);
            $this->saveModel('app\models\MapMenuTemplatePackage', $mapMenuTemplatePackage);
            $this->saveModel('app\models\HubMenu', $hubMenu);
            $this->saveModel('app\models\HubHost', $hubHost);
            $this->saveModel('app\models\MenuTemplateLayout', $menuTemplateLayout);
            $this->saveModel('app\models\LkMenuSize', $menuSize);
            $this->saveModel('app\models\MenuIcon', $menuIcon);
            $this->saveModel('app\models\MapMenuIcon', $mapMenuIcon);
            $this->saveModel('app\models\ProductDetailMenu', $productDetailMenu);
            $this->saveModel('app\models\MaxOrder', $maxOrder);
            $this->saveModel('app\models\MaxOrderDetail', $maxOrderDetail);
            $this->saveModel('app\models\MenuRecommendationHead', $menuRecommendationHead);
            $this->saveModel('app\models\MenuRecommendationDetail', $menuRecommendationDetail);
            $this->saveModel('app\models\MenuRecommendationGroup', $menuRecommendationGroup);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchPromotion() {
          // @refactor http_helper
          $httpService = new HttpHelperService();
          $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-promotion';
          $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
          $datas =   ['branchID' => $this->branchID];
          $options = ['timeOut' => 300];
          $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $menuPromotion = $responseData['menuPromotion'];
            $menuPromotionHead = $responseData['menuPromotionHead'];
            $menuPromotionDay = $responseData['menuPromotionDay'];
            $promotionCategory = $responseData['promotionCategory'];
            $promotionDay = $responseData['promotionDay'];
            $promotionTime = $responseData['promotionTime'];
            $promotionBin = $responseData['promotionBin'];
            $promotionDetail = $responseData['promotionDetail'];
            $promotionHead = $responseData['promotionHead'];
            $promotionPrefix = $responseData['promotionPrefix'];
            $promotionPackageSub = $responseData['promotionPackageSub'];
            $promotionEmployeeGroup = $responseData['promotionEmployeeGroup'];
            $promotionRequirement = $responseData['promotionRequirement'];
            $promotionReward = $responseData['promotionReward'];
            $specialPriceHead = $responseData['specialPriceHead'];
            $specialPriceMenu = $responseData['specialPriceMenu'];
            $specialPriceDay = $responseData['specialPriceDay'];
            $specialPriceTime = $responseData['specialPriceTime'];
            $selfOrderCampaignHead = $responseData['selfOrderCampaignHead'];
            $selfOrderCampaignItem = $responseData['selfOrderCampaignItem'];
            $selfOrderCampaignBranch = $responseData['selfOrderCampaignBranch'];
            $selfOrderCampaignBranchDetail = $responseData['selfOrderCampaignBranchDetail'];
            $tentCard = $responseData['tentCard'];
            $promotionVisitPurpose = $responseData['promotionVisitPurpose'];

            $this->saveModel('app\models\MenuPromotion', $menuPromotion);
            $this->saveModel('app\models\MenuPromotionHead', $menuPromotionHead);
            $this->saveModel('app\models\MenuPromotionDay', $menuPromotionDay);
            $this->saveModel('app\models\PromotionCategory', $promotionCategory);
            $this->saveModel('app\models\PromotionDay', $promotionDay);
            $this->saveModel('app\models\PromotionTime', $promotionTime);
            $this->saveModel('app\models\PromotionBin', $promotionBin);
            $this->saveModel('app\models\PromotionDetail', $promotionDetail);
            $this->saveModel('app\models\PromotionHead', $promotionHead);
            $this->saveModel('app\models\PromotionPrefix', $promotionPrefix);
            $this->saveModel('app\models\PromotionPackageSub', $promotionPackageSub);
            $this->saveModel('app\models\PromotionEmployeeGroup', $promotionEmployeeGroup);
            $this->saveModel('app\models\PromotionRequirement', $promotionRequirement);
            $this->saveModel('app\models\PromotionReward', $promotionReward);
            $this->saveModel('app\models\SpecialPriceHead', $specialPriceHead);
            $this->saveModel('app\models\SpecialPriceMenu', $specialPriceMenu);
            $this->saveModel('app\models\SpecialPriceDay', $specialPriceDay);
            $this->saveModel('app\models\SpecialPriceTime', $specialPriceTime);
            $this->saveModel('app\models\MsSelfOrderCampaignHead', $selfOrderCampaignHead);
            $this->saveModel('app\models\MsSelfOrderCampaignItem', $selfOrderCampaignItem);
            $this->saveModel('app\models\MapSelfOrderCampaignBranch', $selfOrderCampaignBranch);
            $this->saveModel('app\models\MapSelfOrderCampaignBranchDetail', $selfOrderCampaignBranchDetail);
            $this->saveModel('app\models\MapPromotionVisitPurpose', $promotionVisitPurpose);
            $this->saveModel('app\models\TentCard', $tentCard);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchPosNotification() {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-pos-notification';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   ['branchID' => $this->branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $notificationHead = $responseData['notificationHead'];
            $notificationDetail = $responseData['notificationDetail'];

            $this->saveModel('app\models\MsNotificationHead', $notificationHead);
            $this->saveModel('app\models\MsNotificationDetail', $notificationDetail);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchTable() {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-table';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   ['branchID' => $this->branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $table = $responseData['table'];
            $tableSection = $responseData['tableSection'];
            $tableSectionStation = $responseData['tableSectionStation'];

            $validateTable = json_decode(Table::onCheckFetchTableValidate($table));
            if(!$validateTable->status){
                throw new Exception($validateTable->message);
            }

            $this->saveModel('app\models\Table', $table);
            $this->saveModel('app\models\TableSection', $tableSection);
            $this->saveModel('app\models\TableSectionStation', $tableSectionStation);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));

        }
    }

    private function fetchUser() {
         // @refactor http_helper
         $httpService = new HttpHelperService();
         $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-user';
         $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
         $datas =   ['branchID' => $this->branchID];
         $options = ['timeOut' => 300];
         $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $posFilterAccess = $responseData['posFilterAccess'];
            $posUser = $responseData['posUser'];
            $posUserAccess = $responseData['posUserAccess'];
            $posUserRole = $responseData['posUserRole'];

            $usernames = array_column($posUser, 'username');
            foreach ($usernames as $index=>$username) {
                $userSearch = PosUser::find()
                ->where(['username' => $username])
                ->one();
                if($userSearch){
                    $posUser[$index] = array_merge($posUser[$index], ['posAuthKey' => $userSearch->posAuthKey]);
                }
            }

        
            $this->saveModel('app\models\PosFilterAccess', $posFilterAccess);
            $this->saveModel('app\models\PosUser', $posUser);
            $this->saveModel('app\models\PosUserAccess', $posUserAccess);
            $this->saveModel('app\models\PosUserRole', $posUserRole);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchPosVersion() {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-pos-version';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   ['branchID' => $this->branchID];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $posVersion = $response->getData()['posVersion'];
            $this->saveModel('app\models\PosVersion', $posVersion);
        } else {
            throw new Exception(json_encode(['statusCode'=> 500, 'message' => 'Failed To fetch Data']));
        }
    }

    private function fetchStiReaderSettings() {
      
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-reader-setting-tid';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $data =   [
            'branchID' => $this->branchID
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $data, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $readerSettingHeader = $responseData['readerSettingHeader'];
            $readerSettingDetail = $responseData['readerSettingDetail'];
            $this->saveModel('app\models\MsReaderSettingTamanSafari', $readerSettingHeader);
            $this->saveModel('app\models\MsReaderSettingTamanSafariDetail', $readerSettingDetail);
        } else {
           return true;
        }
    }

    private function saveModel($className, $source) {
        if (isset($source)) {
            $model = new $className;
            if ($className == 'app\models\SalesMenu' || $className == 'app\models\ShiftLog') {
                $model->scenario = 'NEW_INSTALL';
            }

            $helperMaps = $className == 'app\models\BranchMenu' ? $model::getBranchMenuMapQty() : [];
            $fields = [];
            $values = [];
            $index = 0;
            $indexMenu = 0;
            $indexMcd = 0;
            $indexTentCard = 0;
            $indexMenuIcon = 0;
            $indexCustomerDisplay = 0;
            $indexCustomerDisplayLogo = 0;
            $indexEventLogo = 0;

            $dirName = Yii::$app->basePath . "/web/images";
            $dirDetailName = Yii::$app->basePath . "/web/images/menu-category-detail";
            $dirMenuName = Yii::$app->basePath . "/web/images/menu";
            $dirTentCard = Yii::$app->basePath . "/web/images/tent-card";
            $dirMenuIcon = Yii::$app->basePath . "/web/images/menu-icon";
            $dirCustomerDisplay = Yii::$app->basePath . "/web/images/customer-display";
            $dirCustomerDisplayLogo = Yii::$app->basePath . "/web/images/customer-display-logo";
            $dirEventLogo = Yii::$app->basePath . "/web/images/event-logo";
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777);
            }

            if ($className == 'app\models\MsPosCustomerDisplayDetail') {
                $customerDisplayImageFiles = [];
                foreach (glob(Yii::$app->basePath . '/web/images/customer-display/*.*') as $filename) {
                    $customerDisplayImageFiles[] = basename($filename);
                }

                $currentCustomerDisplayImages = [];
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['imageUrl']) && ($data['imageUrl'] != '')) {
                            if (!is_dir($dirCustomerDisplay)) {
                                mkdir($dirCustomerDisplay, 0777);
                            }
                            $checkedID = 'CSD_' . $data['ID'];
                            $url = $data['imageUrl'];
                            $nameImage = basename($data['imageUrl']);
                            $img = Yii::$app->basePath . '/web/images/customer-display/' . $nameImage;
                            if (!in_array($nameImage, $customerDisplayImageFiles)) {
                                file_put_contents($img, file_get_contents($url));
                            } else {
                                $currentCustomerDisplayImages[] = $nameImage;
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexCustomerDisplay == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\MsPosCustomerDisplayDetail' && $field == 'imageUrl') {
                            $values[$indexCustomerDisplay][] = isset($data['imageUrl']) ? $nameImage : null;
                        } else {
                            $values[$indexCustomerDisplay][] = $value;
                        }
                    }
                    $indexCustomerDisplay++;
                }

                foreach (array_diff($customerDisplayImageFiles, $currentCustomerDisplayImages) as $filename) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/customer-display/' . basename($filename);
                    unlink($oldImageUrl);
                }
            }
            
            if ($className == 'app\models\MenuIcon') {
                $menuIconImageFiles = [];
                foreach (glob(Yii::$app->basePath . '/web/images/menu-icon/*.*') as $filename) {
                    $menuIconImageFiles[] = basename($filename);
                }

                $currentMenuIconImages = [];
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['menuIconUrl']) && ($data['menuIconUrl'] != '')) {
                            if (!is_dir($dirMenuIcon)) {
                                mkdir($dirMenuIcon, 0777);
                            }
                            $checkedID = 'TNC_' . $data['menuIconID'];
                            $url = $data['menuIconUrl'];
                            $nameImage = basename($data['menuIconUrl']);
                            $img = Yii::$app->basePath . '/web/images/menu-icon/' . $nameImage;
                            if (!in_array($nameImage, $menuIconImageFiles)) {
                                file_put_contents($img, file_get_contents($url));
                            } else {
                                $currentMenuIconImages[] = $nameImage;
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexMenuIcon == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\MenuIcon' && $field == 'menuIconUrl') {
                            $values[$indexMenuIcon][] = isset($data['menuIconUrl']) ? $nameImage : null;
                        } else {
                            $values[$indexMenuIcon][] = $value;
                        }
                    }
                    $indexMenuIcon++;
                }

                foreach (array_diff($menuIconImageFiles, $currentMenuIconImages) as $filename) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/menu-icon/' . basename($filename);
                    unlink($oldImageUrl);
                }
            }
            
            if ($className == 'app\models\TentCard') {
                $tentCardImageFiles = [];
                foreach (glob(Yii::$app->basePath . '/web/images/tent-card/*.*') as $filename) {
                    $tentCardImageFiles[] = basename($filename);
                }

                $currentTentCardImages = [];
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['image']) && ($data['image'] != '')) {
                            if (!is_dir($dirTentCard)) {
                                mkdir($dirTentCard, 0777);
                            }
                            $checkedID = 'TNC_' . $data['tentCardID'];
                            $url = $data['image'];
                            $nameImage = basename($data['image']);
                            $img = Yii::$app->basePath . '/web/images/tent-card/' . $nameImage;

                            if (!in_array($nameImage, $tentCardImageFiles)) {
                                file_put_contents($img, file_get_contents($url));
                            } else {
                                $currentTentCardImages[] = $nameImage;
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexTentCard == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\TentCard' && $field == 'image') {
                            $values[$indexTentCard][] = isset($data['image']) ? $nameImage : null;
                        } else {
                            $values[$indexTentCard][] = $value;
                        }
                    }
                    $indexTentCard++;
                }

                foreach (array_diff($tentCardImageFiles, $currentTentCardImages) as $filename) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/tent-card/' . basename($filename);
                    unlink($oldImageUrl);
                }
            }
            
            if ($className == 'app\models\BrandSetting') {
                $customerDisplayLogoImageFile = '';
                $existingCustomerDisplayLogoImage = glob(Yii::$app->basePath . '/web/images/customer-display-logo/*.*');
                if ($existingCustomerDisplayLogoImage) {
                    foreach ($existingCustomerDisplayLogoImage as $filename) {
                        $customerDisplayLogoImageFile = basename($filename);
                    }
                }

                $currentCustomerDisplayLogoImage = '';
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['brandSettingID']) && ($data['brandSettingID'] == '37')) {
                            if (isset($data['value1']) && ($data['value1'] != '')) {
                                if (!is_dir($dirCustomerDisplayLogo)) {
                                    mkdir($dirCustomerDisplayLogo, 0777);
                                }
                                $url = $data['value1'];
                                $nameImage = basename($data['value1']);
                                $img = Yii::$app->basePath . '/web/images/customer-display-logo/' . $nameImage;
    
                                if ($nameImage != $customerDisplayLogoImageFile) {
                                    file_put_contents($img, file_get_contents($url));
                                } else {
                                    $currentCustomerDisplayLogoImage = $nameImage;
                                }
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexCustomerDisplayLogo == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\BrandSetting' && $field == 'value1' && $data['brandSettingID'] == '37') {
                            $values[$indexCustomerDisplayLogo][] = isset($data['value1']) ? $nameImage : null;
                        } else {
                            $values[$indexCustomerDisplayLogo][] = $value;
                        }
                    }
                    $indexCustomerDisplayLogo++;
                    
                }
                if($customerDisplayLogoImageFile != $currentCustomerDisplayLogoImage) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/customer-display-logo/' . basename($customerDisplayLogoImageFile);
                    unlink($oldImageUrl);
                }
            }
            
            if ($className == 'app\models\MenuCategoryDetail') {
                $menuCategoryDetailImageFiles = [];
                foreach (glob(Yii::$app->basePath . '/web/images/menu-category-detail/*.*') as $filename) {
                    $menuCategoryDetailImageFiles[] = basename($filename);
                }

                $currentMenuCategoryImages = [];
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['imageUrl']) && ($data['imageUrl'] != '')) {
                            if (!is_dir($dirDetailName)) {
                                mkdir($dirDetailName, 0777);
                            }
                            $checkedID = 'MCD_' . $data['ID'];
                            $url = $data['imageUrl'];
                            $nameImage = basename($data['imageUrl']);
                            $img = Yii::$app->basePath . '/web/images/menu-category-detail/' . $nameImage;

                            if (!in_array($nameImage, $menuCategoryDetailImageFiles)) {
                                file_put_contents($img, file_get_contents($url));
                            } else {
                                $currentMenuCategoryImages[] = $nameImage;
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }
                    $model->load($data, '');
                    foreach ($model as $field => $value) {

                        if ($indexMcd == 0) {
                            $fields[] = $field;
                        }

                        if ($className == 'app\models\MenuCategoryDetail' && $field == 'imageUrl') {
                            $values[$indexMcd][] = isset($data['imageUrl']) ? $nameImage : null;
                        } else {
                            $values[$indexMcd][] = $value;
                        }
                    }
                    $indexMcd++;
                }

                foreach (array_diff($menuCategoryDetailImageFiles, $currentMenuCategoryImages) as $filename) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/menu-category-detail/' . basename($filename);
                    unlink($oldImageUrl);
                }
            } else if ($className == 'app\models\Menu') {
                $menuImageFiles = [];
                foreach (glob(Yii::$app->basePath . '/web/images/menu/*.*') as $filename) {
                    $menuImageFiles[] = basename($filename);
                }

                $currentMenuImages = [];
                foreach ($source as $data) {
                    $nameImage = '';
                    try {
                        if (isset($data['imageUrl']) && ($data['imageUrl'] != '')) {
                            if (!is_dir($dirMenuName)) {
                                mkdir($dirMenuName, 0777);
                            }
                            $checkedID = 'MNU_' . $data['menuID'];
                            $url = $data['imageUrl'];
                            $nameImage = basename($data['imageUrl']);
                            $img = Yii::$app->basePath . '/web/images/menu/' . $nameImage;

                            if (!in_array($nameImage, $menuImageFiles)) {
                                file_put_contents($img, file_get_contents($url));
                            } else {
                                $currentMenuImages[] = $nameImage;
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexMenu == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\Menu' && $field == 'imageUrl') {
                            $values[$indexMenu][] = isset($data['imageUrl']) ? $nameImage : null;
                        } else {
                            $values[$indexMenu][] = $value;
                        }
                    }
                    $indexMenu++;
                }

                foreach (array_diff($menuImageFiles, $currentMenuImages) as $filename) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/menu/' . basename($filename);
                    unlink($oldImageUrl);
                }
            }

            if ($className == 'app\models\Setting') {
                $eventLogoImageFile = '';
                $eventLogoIconFile = '';
                $existingEventLogoImage = glob(Yii::$app->basePath . '/web/images/event-logo/*.*');
                if ($existingEventLogoImage) {
                    foreach ($existingEventLogoImage as $filename) {
                        if (substr(basename($filename), 0, 6 ) === "image_") {
                            $eventLogoImageFile = basename($filename);
                        } else if (substr(basename($filename), 0, 5 ) === "icon_") {
                            $eventLogoIconFile = basename($filename);
                        }
                        
                    }
                }

                $currentEventLogoImage = '';
                $currentEventLogoIcon = '';
                foreach ($source as $data) {
                    $nameImage = '';
                    $nameIcon = '';
                    try {
                        if (isset($data['key2']) && ($data['key2'] == 'URL Image Logo Event')) {
                            if (isset($data['value1']) && ($data['value1'] != '')) {
                                if (!is_dir($dirEventLogo)) {
                                    mkdir($dirEventLogo, 0777);
                                }
                                $url = $data['value1'];
                                $nameImage = "image_" . basename($data['value1']);
                                $img = Yii::$app->basePath . '/web/images/event-logo/' . $nameImage;
    
                                if ($nameImage != $eventLogoImageFile) {
                                    file_put_contents($img, file_get_contents($url));
                                } else {
                                    $currentEventLogoImage = $nameImage;
                                }
                            }
                        }
                        if (isset($data['key2']) && ($data['key2'] == 'URL Icon Logo Event')) {
                            if (isset($data['value1']) && ($data['value1'] != '')) {
                                if (!is_dir($dirEventLogo)) {
                                    mkdir($dirEventLogo, 0777);
                                }
                                $url = $data['value1'];
                                $nameIcon = "icon_" . basename($data['value1']);
                                $img = Yii::$app->basePath . '/web/images/event-logo/' . $nameIcon;
    
                                if ($nameIcon != $eventLogoIconFile) {
                                    file_put_contents($img, file_get_contents($url));
                                } else {
                                    $currentEventLogoIcon = $nameIcon;
                                }
                            }
                        }
                    } catch (Exception $ex) {
                        Yii::error($ex);
                    }

                    if (isset($data['key2']) && ($data['key2'] == 'Terminal ID')) {
                        if (isset($data['value1'])) {
                            $data['value1'] = 1;
                        }
                    }

                    $model->load($data, '');
                    foreach ($model as $field => $value) {
                        if ($indexEventLogo == 0) {
                            $fields[] = $field;
                        }
                        if ($className == 'app\models\Setting' && $data['key2'] == 'URL Image Logo Event' && $field == 'value1') {
                            $values[$indexEventLogo][] = isset($data['value1']) ? $nameImage : null;
                        } else if ($className == 'app\models\Setting' && $data['key2'] == 'URL Icon Logo Event' && $field == 'value1') {
                            $values[$indexEventLogo][] = isset($data['value1']) ? $nameIcon : null;
                        } else {
                            $values[$indexEventLogo][] = $value;
                        }
                    }
                    $indexEventLogo++;
                }
                if($eventLogoImageFile != $currentEventLogoImage) {
                    $oldImageUrl = Yii::$app->basePath . '/web/images/event-logo/' . basename($eventLogoImageFile);
                    unlink($oldImageUrl);
                }
                if($eventLogoIconFile != $currentEventLogoIcon) {
                    $oldIconUrl = Yii::$app->basePath . '/web/images/event-logo/' . basename($eventLogoIconFile);
                    unlink($oldIconUrl);
                }
            }
            
              
            if($className == 'app\models\BranchMenuDetail'){
                foreach ($source as $key => $data) {
                    $existsRecord = $model->checkMenu($data);
                    if ($existsRecord) {
                        unset($source[$key]);
                    }
                }
            }

            //@notes : isi id uniq untuk delete data spesifik (selain menu & menuCatDetail)
            $referenceID = null;
            $referenceDeletedIDs = [];
            if ($className == 'app\models\Member'){
                $referenceID = 'memberCode';
            }
            if ($className == 'app\models\MemberDeposit'){
                $referenceID = 'memberDepositNum';
            }

            if ($className == 'app\models\DepositWithdrawalHead'){
                $referenceID = 'depositWithdrawalNum';
            }
            if($className == 'app\models\Voucher') {
                $referenceID = 'voucherID';
            }

            if ($className !== 'app\models\MenuCategoryDetail' && 
                $className !== 'app\models\Menu' && 
                $className !== 'app\models\TentCard' && 
                $className !== 'app\models\MenuIcon' && 
                $className !== 'app\models\BrandSetting' && 
                $className !== 'app\models\MsPosCustomerDisplayDetail' &&
                $className !== 'app\models\Setting') {
                
                if ($className == 'app\models\BranchMenu') {
                    $branchMenuTransactionMenuIDs = BranchMenuTransaction::find()
                        ->select(['menuID'])
                        ->distinct()
                        ->where('syncDate IS NULL')
                        ->column();

                    $productDetailMenuIDs = ProductDetailMenu::find()
                        ->select(['menuID'])
                        ->distinct()
                        ->column();
                }

                foreach ($source as $data) {
                    if ($className == 'app\models\BranchMenu') {
                        if (in_array($data['menuID'], $branchMenuTransactionMenuIDs)) {
                            $branchMenuTransactionModel = BranchMenuTransaction::find()
                            ->select(['qty' => new Expression('SUM(qty)')])
                            ->where(['menuID' => $data['menuID'], 'branchID' => $data['branchID']])
                            ->andWhere('syncDate IS NULL')
                            ->one();
                            if ($branchMenuTransactionModel) {
                                $data['qty'] = $data['qty'] - $branchMenuTransactionModel->qty;
                            }
                        }

                        if (in_array($data['menuID'], $productDetailMenuIDs)) {
                            if ($data['qty'] <= 0) {
                                $data['flagSoldOut'] = true;
                            }
                        }
                    }

                    if ($className == 'app\models\SalesMenu') {
                        $data['ID'] = $data['localID'];
                    }

                    $referenceDeletedIDs[] = ($referenceID && array_key_exists($referenceID, $data)) ? $data[$referenceID] : null;
                    $model->load($data, '');

                    foreach ($model as $field => $value) {
                        if ($index == 0) {
                            $fields[] = $field;
                        }
                        
                        $values[$index][] = $value;
                    }

                    $index++;
                }
            }

            try {
                // @Notes: before insert
                if ($className == 'app\models\Setting') {
                    Yii::$app->db->createCommand()
                            ->delete($model->tableName(), 'key1 <> "Local Setting"')
                            ->execute();
                } else if($referenceID) {
                    //@notes delete spesifik ids jika ada
                    Yii::$app->db->createCommand()
                            ->delete($model->tableName(), ['in', $referenceID, $referenceDeletedIDs])
                            ->execute();
                } else if($className != 'app\models\BranchMenuDetail'){
                    Yii::$app->db->createCommand()
                            ->delete($model->tableName())
                            ->execute();
                }

                // @notes: pecah array menjadi beberapa bagian.
                $chunk = array_chunk($values, 2500);
                foreach($chunk as $val) {
                    Yii::$app->db->createCommand()
                    ->batchInsert($model->tableName(), $fields, $val)
                    ->execute();
                }

                // @Notes: after insert
                if ($className == 'app\models\PosUser') {
                    Yii::$app->db->createCommand()
                            ->update($model->tableName(),
                                    ['syncDate' => new Expression('NOW()')])
                            ->execute();
                }
                if ($className == 'app\models\Member') {
                    $syncOptModel = SyncOptimize::findOne(self::MEMBER);
                    if ($syncOptModel) {
                        $syncOptModel->pullDateTime = date('Y-m-d H:i:s');
                        $syncOptModel->save();
                    }
                }
                if ($className == 'app\models\MemberDeposit') {
                    $syncOptModel = SyncOptimize::findOne(self::MEMBER_DEPOSIT);
                    if ($syncOptModel) {
                        $syncOptModel->pullDateTime = date('Y-m-d H:i:s');
                        $syncOptModel->save();
                    }
                }
                if ($className == 'app\models\DepositWithdrawalHead') {
                    $syncOptModel = SyncOptimize::findOne(self::MEMBER_WITHDRAWAL);
                    if ($syncOptModel) {
                        $syncOptModel->pullDateTime = date('Y-m-d H:i:s');
                        $syncOptModel->save();
                    }
                }
                if ($className == 'app\models\Voucher') {
                    $syncOptModel = SyncOptimize::findOne(self::VOUCHER);
                    if ($syncOptModel) {
                        $syncOptModel->pullDateTime = date('Y-m-d H:i:s');
                        $syncOptModel->save();
                    }
                }
                if ($className == 'app\models\MsReaderSettingTamanSafari') {
                    Yii::$app->db->createCommand()
                            ->update($model->tableName(),
                                    ['syncDate' => new Expression('NOW()')])
                            ->execute();
                }

            } catch (\Exception $ex) {
                Yii::error($ex);
                throw $ex;
            }

        }
    }

    private function saveBranchLogo() {
        $model = Branch::find()->andWhere(['branchID' => $this->branchID])->one();
        if ($model) {
            $mimeType = NULL;
            $imgEncode = $model->image;
            $dirName = Yii::$app->basePath . "/web/images";
            $filename = 'pic-' . $model->branchCode . ".png";
            $filePath = $dirName . "/" . $filename;
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777);
            }
            if (!$imgEncode) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            } else {
                // Decode Image
                $binary = base64_decode($imgEncode);
                $f = finfo_open();
                $mimeType = finfo_buffer($f, $binary, FILEINFO_MIME_TYPE);
                header("Content-type: $mimeType");
                file_put_contents($filePath, $binary);
            }
        }
        return true;
    }

    private function saveBranchFooterLogo() {
        $model = Branch::find()->andWhere(['branchID' => $this->branchID])->one();
        if ($model) {
            $mimeType = NULL;
            $imgEncode = $model->imageFooter;
            $dirName = Yii::$app->basePath . "/web/images";
            $filename = 'picfoot-' . $model->branchCode . ".png";
            $filePath = $dirName . "/" . $filename;
            if (!is_dir($dirName)) {
                mkdir($dirName, 0777);
            }
            if (!$imgEncode) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            } else {
                // Decode Image
                $binary = base64_decode($imgEncode);
                $f = finfo_open();
                $mimeType = finfo_buffer($f, $binary, FILEINFO_MIME_TYPE);
                header("Content-type: $mimeType");
                file_put_contents($filePath, $binary);
            }
        }
        return true;
    }

    private function fetchVoucher() {
        $syncOptModel = SyncOptimize::find()
            ->where(['syncType' => 'VOUCHER'])->one();
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-voucher';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $options = ['timeOut' => 300];
        $datas =   ['branchID' => $this->branchID];
        if($syncOptModel) {
            $datas =   [
                'branchID' => $this->branchID,
                'voucherLastSyncDate' => $syncOptModel->pushDateTime
            ];
        }
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $voucher = $responseData['voucher'];

            $this->saveModel('app\models\Voucher', $voucher);
        } else {
            Yii::warning($response->getData());
            throw new Exception('Failed to fetch data');
        }
    }

    private function fetchSales() {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-sales';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $options = ['timeOut' => 300];
        $datas =   ['branchID' => $this->branchID];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $salesHead = $responseData['salesHead'];
            $salesMenu = $responseData['salesMenu'];
            $salesMenuExtra = $responseData['salesMenuExtra'];
            $salesMergeTable = $responseData['salesMergeTable'];
            $salesPayment = $responseData['salesPayment'];
            $salesVoucher = $responseData['salesVoucher'];
            $salesVoucherOnline = $responseData['salesVoucherOnline'];
            $salesVoucherUsage = $responseData['salesVoucherUsage'];
            $salesDepositWithdrawal = $responseData['salesDepositWithdrawal'];
            $salesLink = $responseData['salesLink'];
            $salesMenuCompletion = $responseData['salesMenuCompletion'];
            $salesMenuRecommendation = $responseData['salesMenuRecommendation'];
            $salesMenuRelated = $responseData['salesMenuRelated'];
            $salesPlatformFee = $responseData['salesPlatformFee'];
            $shiftLog = $responseData['shiftLog'];
            $shiftLogDetail = $responseData['shiftLogDetail'];

            $this->saveModel('app\models\SalesHead', $salesHead);
            $this->saveModel('app\models\SalesMenu', $salesMenu);
            $this->saveModel('app\models\SalesMenuExtra', $salesMenuExtra);
            $this->saveModel('app\models\SalesMergeTable', $salesMergeTable);
            $this->saveModel('app\models\SalesPayment', $salesPayment);
            $this->saveModel('app\models\SalesVoucher', $salesVoucher);
            $this->saveModel('app\models\SalesVoucherOnline', $salesVoucherOnline);
            $this->saveModel('app\models\SalesVoucherUsage', $salesVoucherUsage);
            $this->saveModel('app\models\SalesDepositWithdrawal', $salesDepositWithdrawal);
            $this->saveModel('app\models\SalesLink', $salesLink);
            $this->saveModel('app\models\SalesMenuCompletion', $salesMenuCompletion);
            $this->saveModel('app\models\SalesMenuRecommendation', $salesMenuRecommendation);
            $this->saveModel('app\models\SalesMenuRelated', $salesMenuRelated);
            $this->saveModel('app\models\SalesPlatformFee', $salesPlatformFee);
            $this->saveModel('app\models\ShiftLog', $shiftLog);
            $this->saveModel('app\models\ShiftLogDetail', $shiftLogDetail);
        } else {
            Yii::warning($response->getData());
            throw new Exception('Failed to fetch data');
        }
    }
    
    private function fetchBranchMenu() {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/' . self::API_VERSION . '/main/get-branch-menu';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $options = ['timeOut' => 300];
        $datas =   ['branchID' => $this->branchID];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            $responseData = AppHelper::unzipSyncData($response->getData());
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            $branchMenu = $responseData['branchMenu'];
            $this->saveModel('app\models\BranchMenu', $branchMenu);
            $this->saveModel('app\models\BranchMenuDetail', $branchMenu);
        } else {
            Yii::warning($response->getData());
            throw new Exception('Failed to fetch data');
        }
    }

}
