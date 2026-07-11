<?php
namespace app\models\forms;

use app\models\BranchEvent;
use app\models\BranchMenu;
use app\models\BranchMenuTransaction;
use app\models\CancelMenu;
use app\models\CustomNumber;
use app\models\DepositWithdrawalDetail;
use app\models\DepositWithdrawalHead;
use app\models\DeviceTransaction;
use app\models\EsoLogEvent;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\PaymentMethod;
use app\models\PosUser;
use app\models\PosVersion;
use app\models\ProductDetailMenu;
use app\models\SalesDepositWithdrawal;
use app\models\SalesHead;
use app\models\SalesHeadVat;
use app\models\SalesInfo;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuVat;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPayment;
use app\models\SalesVoucher;
use app\models\SalesVoucherOnline;
use app\models\SalesVoucherUsage;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\ShiftLogDetail;
use app\models\ShiftLogCash;
use app\models\ShiftLogMode;
use app\models\Station;
use app\models\Voucher;
use app\models\forms\SyncOptimize;
use app\models\PaymentOnlineTrackingLog;
use app\models\SalesMenuRecommendation;
use app\models\SalesMenuRelated;
use app\models\QuestionAnswer;
use app\models\SalesContactInfo;
use app\models\SalesShiftPaymentDenom;
use app\models\SalesShiftPaymentDetail;
use app\models\SalesShiftPaymentHead;
use app\models\SalesOrderCampaign;
use app\models\SalesProcessMenu;
use app\models\SalesRewardHead;
use app\models\SalesRewardMenu;
use app\models\SalesPlatformFee;
use app\models\Terminal;
use app\models\TrCustomerTransaction;
use app\services\http_helper\HttpHelperService;
use Exception;
use Underscore\Types\Arrays;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * @property string $syncType
 * 
 * PRIVATE
 * @property string $apiKey
 * @property string $apiUrl
 * @property int $branchID
 */
class SyncPush extends Model {
    const API_VERSION = 'esb_api';
    const PUSH_CANCEL_MENU = 'pushCancelMenu';
    const PUSH_BRANCH_EVENT = 'pushBranchEvent';
    const PUSH_BRANCH_MENU_TRANSACTION = 'pushBranchMenuTransaction';
    const PUSH_BRANCH_MENU_TRANSACTION_END_DAY = 'pushBranchMenuTransactionEndDay';
    const PUSH_BRANCH_MENU = 'pushBranchMenu';
    const PUSH_DEPOSIT_WITHDRAWAL = 'pushDepositWithdrawal';
    const PUSH_MEMBER = 'pushMember';
    const PUSH_MEMBER_DEPOSIT = 'pushMemberDeposit';
    const PUSH_POS_USER = 'pushPosUser';
    const PUSH_SALES = 'pushSales';
    const PUSH_SHIFT = 'pushShift';
    const PUSH_STATION = 'pushStation';
    const PUSH_VOUCHER = 'pushVoucher';
    const PUSH_PAYMENT_METHOD = 'pushPaymentMethod';
    const PUSH_DEVICE_TRANSACTION = 'pushDeviceTransaction';
    const PUSH_POS_VERSION = 'pushPosVersion';
    const PUSH_KIOSK_VERSION = 'pushKioskVersion';
    const PUSH_ODS_VERSION = 'pushOdsVersion';
    const PUSH_QDS_VERSION = 'pushQdsVersion';
    const PUSH_TABLESIDE_VERSION = 'pushTableSideVersion';
    const PUSH_SELF_ORDER_CAMPAIGN_DETAIL = 'pushSelfOrderCampaignDetail';
    const PUSH_QUESTION_ANSWER = 'pushQuestionAnswer';
    const PUSH_TERMINAL_DATA = 'pushTerminalData';
    const PUSH_PAYMENT_ONLINE_TRACKING_LOG = 'pushTrackingPaymentOnlineLog';
    const PUSH_ESO_LOG_EVENT = 'pushEsoLogEvent';

    // Sync Optimize Keys
    const MEMBER = 'MEMBER';
    const MEMBER_DEPOSIT = 'MEMBER DEPOSIT';
    const MEMBER_WITHDRAWAL = 'MEMBER WITHDRAWAL';
    const VOUCHER = 'VOUCHER';

    public $syncType;
    public $apiKey;
    public $apiUrl;
    public $branchID;
    public $shiftId;

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
            [['shiftId'], 'safe'],
            [['syncType'], 'string', 'max' => 100]
        ];
    }

    public function doSync() {
        if (!$this->validate()) {
            return false;
        }

        switch ($this->syncType) {
            case self::PUSH_CANCEL_MENU:
                $this->pushCancelMenu();
                break;
            case self::PUSH_BRANCH_EVENT:
                $this->pushBranchEvent();
                break;
            case self::PUSH_BRANCH_MENU:
                $this->pushBranchMenu();
                break;
            case self::PUSH_BRANCH_MENU_TRANSACTION:
                $this->pushBranchMenuTransaction(true);
                break;
            case self::PUSH_BRANCH_MENU_TRANSACTION_END_DAY:
                $this->pushBranchMenuTransaction(false);
                break;
            case self::PUSH_DEPOSIT_WITHDRAWAL:
                $this->pushDepositWithdrawal();
                break;
            case self::PUSH_MEMBER:
                $this->pushMember();
                break;
            case self::PUSH_MEMBER_DEPOSIT:
                $this->pushMemberDeposit();
                break;
            case self::PUSH_POS_USER:
                $this->pushPosUser();
                break;
            case self::PUSH_SALES:
                $this->pushSales();
                break;
            case self::PUSH_SHIFT:
                $this->pushShift();
                break;
            case self::PUSH_STATION:
                $this->pushStation();
                break;
            case self::PUSH_VOUCHER:
                $this->pushVoucher();
                break;
            case self::PUSH_PAYMENT_METHOD:
                $this->pushPaymentMethod();
                break;
            case self::PUSH_DEVICE_TRANSACTION:
                $this->pushDeviceTransaction();
                break;
            case self::PUSH_POS_VERSION:
                $this->pushPosVersion();
                break;
            case self::PUSH_KIOSK_VERSION:
                $this->pushKioskVersion();
                break;
            case self::PUSH_ODS_VERSION:
                $this->pushOdsVersion();
                break;
            case self::PUSH_QDS_VERSION:
                $this->pushQdsVersion();
                break;
            case self::PUSH_TABLESIDE_VERSION:
                $this->pushTableSideVersion();
                break;
            case self::PUSH_SELF_ORDER_CAMPAIGN_DETAIL:
                $this->pushSelfOrderCampaignDetail();
                break;
            case self::PUSH_TERMINAL_DATA:
                $this->pushTerminalData();
                break;
            case self::PUSH_PAYMENT_ONLINE_TRACKING_LOG:
                $this->pushTrackingOnlinePaymentLog();
                break;
            case self::PUSH_ESO_LOG_EVENT:
                $this->pushEsoLogEvent();
                break;
            default:
                break;
        }
//        $func = $this->syncType;
//        $this->$func();

        Logging::save('-', Logging::SYNC_PUSH, $this->getAttributes());

        return true;
    }

    public static function getUnsyncCount() {
        $branchID = Setting::getCurrentBranch();
        $branchEventCount = BranchEvent::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $branchMenuCount = BranchMenu::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $depositWithdrawalCount = DepositWithdrawalHead::find()
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $memberCount = Member::find()
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $memberDepositCount = MemberDeposit::find()
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $salesCount = SalesHead::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $shiftCount = ShiftLog::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS NOT', 'shiftOutTime', null])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $stationCount = Station::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $userCount = PosUser::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        $voucherCount = Voucher::find()
            ->andWhere(['IS', 'syncDate', null])
            ->count();
        
         $paymentMethodCount = PaymentMethod::find()
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->count();

        return [
            'branchSettingsCount' => (int) ($branchEventCount + $stationCount + $voucherCount + $paymentMethodCount),
            'memberCount' => (int) ($memberCount + $memberDepositCount + $depositWithdrawalCount),
            'menuCount' => (int) $branchMenuCount,
            'salesCount' => (int) ($salesCount + $shiftCount),
            'userCount' => (int) $userCount,
        ];
    }

    private function getHttpClient($pullAction) {
        $client = new Client();
        return $client->post($this->apiUrl . '/' . self::API_VERSION . '/pull/' . $pullAction)
                ->addHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->apiKey
        ]);
    }

    private function pushCancelMenu() {
        $cancelMenu = (new Query())
            ->select('*')
            ->from(CancelMenu::tableName())
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($cancelMenu as $event) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-cancel-menu-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $options = ['timeOut' => 300];
                $datas =   [
                    'cancelMenuData' => [$event]
                ];
                $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    CancelMenu::syncUpdate($event['ID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushEsoLogEvent() {

        $esoLogEvent = (new Query())
            ->select('*')
            ->from(EsoLogEvent::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        $batchSize = 100;
        $batches = array_chunk($esoLogEvent, $batchSize);
        foreach ($batches as $batch) {
            foreach ($batch as $event) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    // @refactor http_helper
                    $httpService = new HttpHelperService();
                    $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-eso-log-data';
                    $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                    $options = ['timeOut' => 300];
                    $datas =   [
                        'esoLogEventData' => [$event]
                    ];
                    $response = $httpService->post($url, $headers, $datas, $options);
                    if ($response->getData()['status'] == '00') {
                            EsoLogEvent::syncUpdate($event['ID'],
                                $response->getData()['syncDate']);
                    } else {
                        Yii::warning($response->getData());
                        throw new Exception('Failed to push data');
                    }
                    $transaction->commit();
                } catch (Exception $ex) {
                    $transaction->rollBack();
                    $this->addError('syncType', $ex->getMessage());
                    return false;
                }
            }
        }
    }
    
    private function pushBranchEvent() {
        $branchEvent = (new Query())
            ->select('*')
            ->from(BranchEvent::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($branchEvent as $event) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                 // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-branch-event-data';
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $options = ['timeOut' => 300];
                 $datas =   [
                    'branchEventData' => [$event]
                 ];
                 $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    BranchEvent::syncUpdate($event['ID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushBranchMenu() {
        $branchMenu = (new Query())
            ->select('*')
            ->from(BranchMenu::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        $maxDataCount = count($branchMenu);
        $i = 1;
        foreach ($branchMenu as $menu) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                //@notes: add lastSyncProcess for last data
                $menu['lastSyncProcess'] = false;
                if ($i == $maxDataCount) {
                    $menu['lastSyncProcess'] = true;
                }
   
                // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-branch-menu-data';
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $options = ['timeOut' => 300];
                 $datas =   [
                    'branchMenuData' => [$menu]
                 ];
                 $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    BranchMenu::syncUpdate($menu['ID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $i++;
                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushBranchMenuTransaction($status) {
        $shiftTime = [];
        if (!$status) {
            $branchMenuTransaction = [];
            if ($this->shiftId && $this->shiftId > 0) {
                $shiftTime = (new Query())
                    ->select('*')
                    ->from('tr_shiftlog')
                    ->andWhere(['=', 'tr_shiftlog.shiftID', $this->shiftId])
                    ->one();
    
                if ($shiftTime) {
                    $shiftDate = date('Y-m-d', strtotime($shiftTime["shiftInTime"]));
                    $now = date('Y-m-d', strtotime("+1 days"));

                    $branchMenuTransaction = (new Query())
                    ->select([
                        'tr_branchmenutransaction.salesNum',
                        'tr_branchmenutransaction.menuID',
                        'tr_branchmenutransaction.branchID',
                        'ms_productdetailmenu.productID',
                        'tr_saleshead.salesDate',
                        'SUM(tr_branchmenutransaction.qty) AS qty',
                        'tr_branchmenutransaction.syncDate'
                    ])
                    ->from(BranchMenuTransaction::tableName())
                    ->join("INNER JOIN", ProductDetailMenu::tableName(),
                        BranchMenuTransaction::tableName(). ".menuID = ".ProductDetailMenu::tableName().".menuID")
                    ->join("INNER JOIN", SalesHead::tableName(),
                        SalesHead::tableName(). ".salesNum = ".BranchMenuTransaction::tableName().".salesNum")
                    ->andWhere(['OR',
                        BranchMenuTransaction::tableName().".syncDate BETWEEN '$shiftDate' AND '$now'",
                        ['IS', BranchMenuTransaction::tableName().'.syncDate', null]
                    ])
                    ->andWhere([BranchMenuTransaction::tableName().'.branchID' => $this->branchID])
                    ->groupBy([
                        'tr_branchmenutransaction.salesNum',
                        'tr_branchmenutransaction.menuID',
                        'tr_branchmenutransaction.syncDate',
                        'ms_productdetailmenu.productID',
                        'tr_saleshead.salesDate'
                    ])
                    ->all();
                }
            }
        } else {

            $branchMenuTransaction = (new Query())
                ->select([
                    'tr_branchmenutransaction.salesNum',
                    'tr_branchmenutransaction.menuID',
                    'tr_branchmenutransaction.branchID',
                    'ms_productdetailmenu.productID',
                    'tr_saleshead.salesDate',
                    'SUM(tr_branchmenutransaction.qty) AS qty',
                    'tr_branchmenutransaction.syncDate'
                ])
                ->from(BranchMenuTransaction::tableName())
                ->innerJoin(ProductDetailMenu::tableName(), BranchMenuTransaction::tableName() . '.menuID = ' . ProductDetailMenu::tableName() . '.menuID')
                ->innerJoin(SalesHead::tableName(), SalesHead::tableName() . '.salesNum = ' . BranchMenuTransaction::tableName() . '.salesNum')
                ->where(['IS', BranchMenuTransaction::tableName() . '.syncDate', null])
                ->andWhere([BranchMenuTransaction::tableName() . '.branchID' => $this->branchID])
                ->groupBy([
                    'tr_branchmenutransaction.salesNum',
                    'tr_branchmenutransaction.menuID',
                    'tr_branchmenutransaction.syncDate',
                    'ms_productdetailmenu.productID',
                    'tr_saleshead.salesDate'
                ])
                ->all();
        }
        $maxDataCount = count($branchMenuTransaction);
        $i = 1;
        foreach ($branchMenuTransaction as $transactionMenu) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($transactionMenu['syncDate'] == null) {
                    $transactionMenu['isUpdate'] = true;
                }

                //@notes: add lastSyncProcess for last data
                $transactionMenu['lastSyncProcess'] = false;
                if ($i == $maxDataCount) {
                    $transactionMenu['lastSyncProcess'] = true;
                }
     
                 // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $action  = $status ? 'pull-branch-menu-transaction': 'pull-branch-menu-transaction-end-day';
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/'. $action;
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $options = ['timeOut' => 300];
                 $datas = [
                     'branchMenuTransaction' => [$transactionMenu]
                 ];
                 $response = $httpService->post($url, $headers, $datas, $options);
               
                if ($transactionMenu['syncDate'] == null) {
                    if ($response->getData()['status'] == '00') {
                 
                        BranchMenuTransaction::syncUpdate($transactionMenu['salesNum'],$response->getData()['syncDate']);
                    } else {
                        Yii::warning($response->getData());
                        throw new Exception('Failed to push data');
                    }
                }
                
                Logging::save($transactionMenu['salesNum'], Logging::LOGGIN_SYNC_MENU_RTS, $datas);
                $i++;
                $transaction->commit();
            } catch (Exception $ex) {
                Yii::error($ex->getMessage());
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushDepositWithdrawal() {
        // Check to sync type table
        if (!$this->optimizeSync(self::MEMBER_WITHDRAWAL)) {
            throw new Exception("Failed to sync optimize");
        }
        
        $withdrawalQuery = DepositWithdrawalHead::find()
            ->select('depositWithdrawalNum')
            ->andWhere(['IS', 'syncDate', null]);

        $witdrawalHead = (new Query())
            ->select('*')
            ->from(DepositWithdrawalHead::tableName())
            ->andWhere(['IN', 'depositWithdrawalNum', $withdrawalQuery])
            ->all();

        $witdrawalDetail = (new Query())
            ->select('*')
            ->from(DepositWithdrawalDetail::tableName())
            ->andWhere(['IN', 'depositWithdrawalNum', $withdrawalQuery])
            ->all();

        foreach ($witdrawalHead as $withdrawal) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $witdrawalDetails = Arrays::filter($witdrawalDetail,
                        function ($witdrawalDetail) use ($withdrawal) {
                        return $witdrawalDetail['depositWithdrawalNum'] == $withdrawal['depositWithdrawalNum'];
                    });

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-deposit-withdrawal-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $data =   [
                    'depositWithdrawalData' => [$withdrawal],
                    'depositWithdrawalDetailData' => $witdrawalDetails
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);

                if ($response->getData()['status'] == '00') {
                    DepositWithdrawalHead::syncUpdate($withdrawal['depositWithdrawalNum'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushMember() {
        // Check to sync type table
        if (!$this->optimizeSync(self::MEMBER)) {
            throw new Exception("Failed to sync optimize");
        }

        $branchID = Setting::getCurrentBranch();
        $member = (new Query())
            ->select('*')
            ->from(Member::tableName())
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        $transaction = Yii::$app->db->beginTransaction();
        try {

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-member-data';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $data =   [
            'memberData' => $member,
            'branchID' => $this->branchID
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $data, $options);

            if ($response->getData()['status'] == '00') {
                if (count($response->getData()['newMembers']) > 0) {
                    // @Notes: the return newMembers array has been reversed from the webservice.
                    // No need to reverse on the local site.
                    $newMembers = $response->getData()['newMembers'];
                    foreach ($newMembers as $member) {
                        Member::syncUpdate($member['oldMemberID'],
                            $response->getData()['syncDate']);
                        if ($member['oldMemberID'] != $member['newMemberID']) {
                            SalesHead::updateAll(['memberID' => $member['newMemberID']],
                                ['memberID' => $member['oldMemberID'], 'branchID' => $branchID]);
                            MemberDeposit::updateAll(['memberID' => $member['newMemberID']],
                                ['memberID' => $member['oldMemberID'], 'branchID' => $branchID]);
                            DepositWithdrawalHead::updateAll(['memberID' => $member['newMemberID']],
                                ['memberID' => $member['oldMemberID'], 'branchID' => $branchID]);
                        }
                    }
                }
            } else {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }

    private function pushMemberDeposit() {
        // Check to sync type table
        if (!$this->optimizeSync(self::MEMBER_DEPOSIT)) {
            throw new Exception("Failed to sync optimize");
        }

        $memberDeposit = (new Query())
            ->select('*')
            ->from(MemberDeposit::tableName())
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($memberDeposit as $deposit) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
              
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-member-deposit-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $data =   [
                    'memberDepositData' => [$deposit]
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);
      
                if ($response->getData()['status'] == '00') {
                    MemberDeposit::syncUpdate($deposit['memberDepositNum'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushPosUser() {
        $posUser = (new Query())
            ->select('*')
            ->from(PosUser::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($posUser as $user) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-pos-user-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $data =   [
                    'posUserData' => [$user]
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);

                if ($response->getData()['status'] == '00') {
                    PosUser::syncUpdate($user['username'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushSales() {
        $salesQuery = SalesHead::find()
            ->select('salesNum')
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['OR',
                ['IS', 'syncDate', null],
                "DATE_SUB(syncDate, INTERVAL 5 SECOND) < editedDate",
            ])
            ->all();
    

        foreach ($salesQuery as $sales) {
            try {
                $salesHead = (new Query())
                    ->select('*')
                    ->from(SalesHead::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->one();

                $salesMenu = (new Query())
                    ->select('*')
                    ->from(SalesMenu::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenuExtra = (new Query())
                    ->select('*')
                    ->from(SalesMenuExtra::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMergeTable = (new Query())
                    ->select('*')
                    ->from(SalesMergeTable::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesPayment = (new Query())
                    ->select('*')
                    ->from(SalesPayment::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesVoucher = (new Query())
                    ->select('*')
                    ->from(SalesVoucher::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $salesVoucherUsage = (new Query())
                    ->select('*')
                    ->from(SalesVoucherUsage::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesVoucherOnline = (new Query())
                    ->select('*')
                    ->from(SalesVoucherOnline::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesDepositWithdrawal = (new Query())
                    ->select('*')
                    ->from(SalesDepositWithdrawal::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesLink = (new Query())
                    ->select('*')
                    ->from(SalesLink::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenuCompletion = (new Query())
                    ->select('*')
                    ->from(SalesMenuCompletion::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $salesInfo = (new Query())
                    ->select('*')
                    ->from(SalesInfo::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesOrderCampaign = (new Query())
                    ->select('*')
                    ->from(SalesOrderCampaign::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesCustomerTransaction = (new Query())
                    ->select('*')
                    ->from(TrCustomerTransaction::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenuRecommendation = (new Query())
                    ->select('*')
                    ->from(SalesMenuRecommendation::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenuRelated = (new Query())
                    ->select('*')
                    ->from(SalesMenuRelated::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                    
                $questionAnswer = (new Query())
                    ->select('*')
                    ->from(QuestionAnswer::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesRewardHead = (new Query())
                    ->select('*')
                    ->from(SalesRewardHead::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $salesRewardMenu = (new Query())
                    ->select('*')
                    ->from(SalesRewardMenu::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesContactInfo = (new Query())
                    ->select(['*'])
                    ->from(SalesContactInfo::tableName())
                    ->where(['=', 'salesNum',$sales['salesNum']])
                    ->all();

                $salesProcessMenu = (new Query())
                    ->select(['*'])
                    ->from(SalesProcessMenu::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesPlatformFee = (new Query())
                    ->select('*')
                    ->from(SalesPlatformFee::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesHeadVat = (new Query())
                    ->select('*')
                    ->from(SalesHeadVat::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenuVat = (new Query())
                    ->select('*')
                    ->from(SalesMenuVat::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $customNumber = (new Query())
                    ->select(['*'])
                    ->from(CustomNumber::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $salesMenus = Arrays::filter($salesMenu,
                        function ($salesMenu) use ($sales) {
                        return $salesMenu['salesNum'] == $sales['salesNum'];
                    });

                $salesMenuExtras = Arrays::filter($salesMenuExtra,
                        function ($salesMenuExtra) use ($sales) {
                        return $salesMenuExtra['salesNum'] == $sales['salesNum'];
                    });

                $salesMergeTables = Arrays::filter($salesMergeTable,
                        function ($salesMergeTable) use ($sales) {
                        return $salesMergeTable['salesNum'] == $sales['salesNum'];
                    });

                $salesPayments = Arrays::filter($salesPayment,
                        function ($salesPayment) use ($sales) {
                        return $salesPayment['salesNum'] == $sales['salesNum'];
                    });

                $salesVouchers = Arrays::filter($salesVoucher,
                        function ($salesVoucher) use ($sales) {
                        return $salesVoucher['salesNum'] == $sales['salesNum'];
                    });
                    
                $salesVoucherUsages = Arrays::filter($salesVoucherUsage,
                        function ($salesVoucherUsage) use ($sales) {
                        return $salesVoucherUsage['salesNum'] == $sales['salesNum'];
                    });

                $salesVouchersOnline = Arrays::filter($salesVoucherOnline,
                        function ($salesVoucherOnline) use ($sales) {
                        return $salesVoucherOnline['salesNum'] == $sales['salesNum'];
                    });

                $salesDepositWithdrawals = Arrays::filter($salesDepositWithdrawal,
                        function ($salesDepositWithdrawal) use ($sales) {
                        return $salesDepositWithdrawal['salesNum'] == $sales['salesNum'];
                    });

                $salesLinks = Arrays::filter($salesLink,
                        function ($salesLink) use ($sales) {
                        return $salesLink['salesNum'] == $sales['salesNum'];
                    });

                $salesMenuCompletions = Arrays::filter($salesMenuCompletion,
                        function ($salesMenuCompletion) use ($sales) {
                        return $salesMenuCompletion['salesNum'] == $sales['salesNum'];
                    });
                
                $salesInfos = Arrays::filter($salesInfo,
                    function ($salesInfo) use ($sales) {
                    return $salesInfo['salesNum'] == $sales['salesNum'];
                });

                $salesOrderCampaigns = Arrays::filter($salesOrderCampaign,
                    function ($salesOrderCampaign) use ($sales) {
                    return $salesOrderCampaign['salesNum'] == $sales['salesNum'];
                });

                $salesCustomerTransactions = Arrays::filter($salesCustomerTransaction,
                    function ($salesCustomerTransaction) use ($sales) {
                    return $salesCustomerTransaction['salesNum'] == $sales['salesNum'];
                });

                $salesMenuRecommendations = Arrays::filter($salesMenuRecommendation,
                    function ($salesMenuRecommendation) use ($sales) {
                    return $salesMenuRecommendation['salesNum'] == $sales['salesNum'];
                });

                $salesMenuRelateds = Arrays::filter($salesMenuRelated,
                    function ($salesMenuRelated) use ($sales) {
                    return $salesMenuRelated['salesNum'] == $sales['salesNum'];
                });
                    
                $questionAnswers = Arrays::filter($questionAnswer,
                    function ($questionAnswer) use ($sales) {
                    return $questionAnswer['salesNum'] == $sales['salesNum'];
                });
                
                $salesRewardHeads = Arrays::filter($salesRewardHead,
                    function ($salesRewardHead) use ($sales) {
                    return $salesRewardHead['salesNum'] == $sales['salesNum'];
                });
                
                $salesRewardMenus = Arrays::filter($salesRewardMenu,
                    function ($salesRewardMenu) use ($sales) {
                    return $salesRewardMenu['salesNum'] == $sales['salesNum'];
                });

                $salesContactInfos = Arrays::filter($salesContactInfo,
                    function ($salesContactInfo) use ($sales) {
                        return $salesContactInfo['salesNum'] == $sales['salesNum'];
                });

                $salesProcessMenus = Arrays::filter($salesProcessMenu,
                    function ($salesProcessMenu) use ($sales) {
                        return $salesProcessMenu['salesNum'] == $sales['salesNum'];
                });

                $salesPlatformFees = Arrays::filter($salesPlatformFee,
                    function ($salesPlatformFee) use ($sales) {
                    return $salesPlatformFee['salesNum'] == $sales['salesNum'];
                });

                $customNumbers = Arrays::filter($customNumber,
                    function ($customNumber) use ($sales) {
                        return $customNumber['salesNum'] == $sales['salesNum'];
                });

                $salesHeadVats = Arrays::filter($salesHeadVat,
                    function ($salesHeadVat) use ($sales) {
                    return $salesHeadVat['salesNum'] == $sales['salesNum'];
                });

                $salesMenuVats = Arrays::filter($salesMenuVat,
                    function ($salesMenuVat) use ($sales) {
                    return $salesMenuVat['salesNum'] == $sales['salesNum'];
                });
                
                $beforePushData = Json::encode([
                        'salesHead' => [$salesHead],
                        'salesMenu' => $salesMenus,
                        'salesMenuExtra' => $salesMenuExtras,
                        'salesMergeTable' => $salesMergeTables,
                        'salesPayment' => $salesPayments,
                        'salesVoucher' => $salesVouchers,
                        'salesVoucherUsage' => $salesVoucherUsages,
                        'salesVoucherOnline' => $salesVouchersOnline,
                        'salesDepositWithdrawal' => $salesDepositWithdrawals,
                        'salesLink' => $salesLinks,
                        'salesMenuCompletions' => $salesMenuCompletions,
                        'salesInfo' => $salesInfos,
                        'salesOrderCampaign' => $salesOrderCampaigns,
                        'salesCustomerTransactions' => $salesCustomerTransactions,
                        'salesMenuRecommendation' => $salesMenuRecommendations,
                        'salesMenuRelated' => $salesMenuRelateds,
                        'questionAnswers' => $questionAnswers,
                        'salesRewardHead' => $salesRewardHeads,
                        'salesRewardMenu' => $salesRewardMenus,
                        'salesContactInfo' => $salesContactInfos,
                        'salesProcessMenu' => $salesProcessMenus,
                        'salesPlatformFee' => $salesPlatformFees,
                        'customNumber' => $customNumbers,
                        'salesHeadVat' => $salesHeadVats,
                        'salesMenuVat' => $salesMenuVats
                ]);

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-sales-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $data =   [
                    'salesHead' => [$salesHead],
                    'salesMenu' => $salesMenus,
                    'salesMenuExtra' => $salesMenuExtras,
                    'salesMergeTable' => $salesMergeTables,
                    'salesPayment' => $salesPayments,
                    'salesVoucher' => $salesVouchers,
                    'salesVoucherUsage' => $salesVoucherUsages,
                    'salesVoucherOnline' => $salesVouchersOnline,
                    'salesDepositWithdrawal' => $salesDepositWithdrawals,
                    'salesLink' => $salesLinks,
                    'salesMenuCompletions' => $salesMenuCompletions,
                    'salesInfo' => $salesInfo,
                    'salesOrderCampaign' => $salesOrderCampaigns,
                    'salesCustomerTransactions' => $salesCustomerTransactions,
                    'salesMenuRecommendation' => $salesMenuRecommendations,
                    'salesMenuRelated' => $salesMenuRelateds,
                    'questionAnswers' => $questionAnswers,
                    'salesRewardHead' => $salesRewardHeads,
                    'salesRewardMenu' => $salesRewardMenus,
                    'salesContactInfo' => $salesContactInfos,
                    'salesProcessMenu' => $salesProcessMenus,
                    'salesPlatformFee' => $salesPlatformFees,
                    'customNumber' => $customNumbers,
                    'salesHeadVat' => $salesHeadVats,
                    'salesMenuVat' => $salesMenuVats
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);

                if ($response->getData()['status'] != '00') {
                    throw new Exception('Failed to push data');
                }
                
                // Compare before and after push data
                $currentSalesHead = (new Query())
                    ->select('*')
                    ->from(SalesHead::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->one();

                $currentSalesMenu = (new Query())
                    ->select('*')
                    ->from(SalesMenu::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenuExtra = (new Query())
                    ->select('*')
                    ->from(SalesMenuExtra::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMergeTable = (new Query())
                    ->select('*')
                    ->from(SalesMergeTable::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesPayment = (new Query())
                    ->select('*')
                    ->from(SalesPayment::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesVoucher = (new Query())
                    ->select('*')
                    ->from(SalesVoucher::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $currentSalesVoucherUsage = (new Query())
                    ->select('*')
                    ->from(SalesVoucherUsage::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesVoucherOnline = (new Query())
                    ->select('*')
                    ->from(SalesVoucherOnline::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesDepositWithdrawal = (new Query())
                    ->select('*')
                    ->from(SalesDepositWithdrawal::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesLink = (new Query())
                    ->select('*')
                    ->from(SalesLink::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenuCompletion = (new Query())
                    ->select('*')
                    ->from(SalesMenuCompletion::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $currentSalesInfo = (new Query())
                    ->select('*')
                    ->from(SalesInfo::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesOrderCampaign = (new Query())
                    ->select('*')
                    ->from(SalesOrderCampaign::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentCustomerTransaction = (new Query())
                    ->select('*')
                    ->from(TrCustomerTransaction::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenuRecommendation = (new Query())
                    ->select('*')
                    ->from(SalesMenuRecommendation::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenuRelated = (new Query())
                    ->select('*')
                    ->from(SalesMenuRelated::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentQuestionAnswer = (new Query())
                    ->select('*')
                    ->from(QuestionAnswer::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $currentSalesRewardHead = (new Query())
                    ->select('*')
                    ->from(SalesRewardHead::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();
                
                $currentSalesRewardMenu = (new Query())
                    ->select('*')
                    ->from(SalesRewardMenu::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesContactInfo = (new Query())
                    ->select(['*'])
                    ->from(SalesContactInfo::tableName())
                    ->where(['=', 'salesNum',$sales['salesNum']])
                    ->all();

                $currentSalesProcessMenu = (new Query())
                    ->select(['*'])
                    ->from(SalesProcessMenu::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesPlatformFee = (new Query())
                    ->select('*')
                    ->from(SalesPlatformFee::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentCustomNumber = (new Query())
                    ->select(['*'])
                    ->from(CustomNumber::tableName())
                    ->where(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesHeadVat = (new Query())
                    ->select('*')
                    ->from(SalesHeadVat::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenuVat = (new Query())
                    ->select('*')
                    ->from(SalesMenuVat::tableName())
                    ->andWhere(['=', 'salesNum', $sales['salesNum']])
                    ->all();

                $currentSalesMenus = Arrays::filter($currentSalesMenu,
                        function ($currentSalesMenu) use ($sales) {
                        return $currentSalesMenu['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesMenuExtras = Arrays::filter($currentSalesMenuExtra,
                        function ($currentSalesMenuExtra) use ($sales) {
                        return $currentSalesMenuExtra['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesMergeTables = Arrays::filter($currentSalesMergeTable,
                        function ($currentSalesMergeTable) use ($sales) {
                        return $currentSalesMergeTable['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesPayments = Arrays::filter($currentSalesPayment,
                        function ($currentSalesPayment) use ($sales) {
                        return $currentSalesPayment['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesVouchers = Arrays::filter($currentSalesVoucher,
                        function ($currentSalesVoucher) use ($sales) {
                        return $currentSalesVoucher['salesNum'] == $sales['salesNum'];
                    });
                    
                $currentSalesVoucherUsages = Arrays::filter($currentSalesVoucherUsage,
                        function ($currentSalesVoucherUsage) use ($sales) {
                        return $currentSalesVoucherUsage['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesVouchersOnline = Arrays::filter($currentSalesVoucherOnline,
                        function ($currentSalesVoucherOnline) use ($sales) {
                        return $currentSalesVoucherOnline['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesDepositWithdrawals = Arrays::filter($currentSalesDepositWithdrawal,
                        function ($currentSalesDepositWithdrawal) use ($sales) {
                        return $currentSalesDepositWithdrawal['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesLinks = Arrays::filter($currentSalesLink,
                        function ($currentSalesLink) use ($sales) {
                        return $currentSalesLink['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesMenuCompletions = Arrays::filter($currentSalesMenuCompletion,
                        function ($currentSalesMenuCompletion) use ($sales) {
                        return $currentSalesMenuCompletion['salesNum'] == $sales['salesNum'];
                    });
                    
                $currentSalesInfos = Arrays::filter($currentSalesInfo,
                        function ($currentSalesInfo) use ($sales) {
                        return $currentSalesInfo['salesNum'] == $sales['salesNum'];
                    });

                $currentSalesOrderCampaigs = Arrays::filter($currentSalesOrderCampaign,
                    function ($currentSalesOrderCampaign) use ($sales) {
                    return $currentSalesOrderCampaign['salesNum'] == $sales['salesNum'];
                });
                
                $currentCustomerTransactions = Arrays::filter($currentCustomerTransaction,
                    function ($currentCustomerTransaction) use ($sales) {
                    return $currentCustomerTransaction['salesNum'] == $sales['salesNum'];
                });

                $currentSalesMenuRecommendations = Arrays::filter($currentSalesMenuRecommendation,
                    function ($currentSalesMenuRecommendation) use ($sales) {
                    return $currentSalesMenuRecommendation['salesNum'] == $sales['salesNum'];
                });

                $currentSalesMenuRelateds = Arrays::filter($currentSalesMenuRelated,
                    function ($currentSalesMenuRelated) use ($sales) {
                    return $currentSalesMenuRelated['salesNum'] == $sales['salesNum'];
                });
                
                $currentQuestionAnswers = Arrays::filter($currentQuestionAnswer,
                    function ($currentQuestionAnswer) use ($sales) {
                    return $currentQuestionAnswer['salesNum'] == $sales['salesNum'];
                });

                $currentSalesRewardHeads = Arrays::filter($currentSalesRewardHead,
                    function ($salesRewardHead) use ($sales) {
                    return $salesRewardHead['salesNum'] == $sales['salesNum'];
                });
                
                $currentSalesRewardMenus = Arrays::filter($currentSalesRewardMenu,
                    function ($salesRewardMenu) use ($sales) {
                    return $salesRewardMenu['salesNum'] == $sales['salesNum'];
                });

                $currentSalesContactInfos = Arrays::filter($currentSalesContactInfo,
                    function ($currentSalesContactInfo) use ($sales) {
                        return $currentSalesContactInfo['salesNum'] == $sales['salesNum'];
                });

                $currentSalesProcessMenus = Arrays::filter($currentSalesProcessMenu,
                    function ($currentSalesProcessMenu) use ($sales) {
                        return $currentSalesProcessMenu['salesNum'] == $sales['salesNum'];
                });

                $currentSalesPlatformFees = Arrays::filter($currentSalesPlatformFee,
                    function ($currentSalesPlatformFee) use ($sales) {
                    return $currentSalesPlatformFee['salesNum'] == $sales['salesNum'];
                });

                $currentCustomNumbers = Arrays::filter($currentCustomNumber,
                    function ($currentCustomNumber) use ($sales) {
                        return $currentCustomNumber['salesNum'] == $sales['salesNum'];
                });

                $currentSalesHeadVats = Arrays::filter($currentSalesHeadVat,
                    function ($currentSalesHeadVat) use ($sales) {
                    return $currentSalesHeadVat['salesNum'] == $sales['salesNum'];
                });

                $currentSalesMenuVats = Arrays::filter($currentSalesMenuVat,
                    function ($currentSalesMenuVat) use ($sales) {
                    return $currentSalesMenuVat['salesNum'] == $sales['salesNum'];
                });
                
                $afterPushData = Json::encode([
                        'salesHead' => [$currentSalesHead],
                        'salesMenu' => $currentSalesMenus,
                        'salesMenuExtra' => $currentSalesMenuExtras,
                        'salesMergeTable' => $currentSalesMergeTables,
                        'salesPayment' => $currentSalesPayments,
                        'salesVoucher' => $currentSalesVouchers,
                        'salesVoucherUsage' => $currentSalesVoucherUsages,
                        'salesVoucherOnline' => $currentSalesVouchersOnline,
                        'salesDepositWithdrawal' => $currentSalesDepositWithdrawals,
                        'salesLink' => $currentSalesLinks,
                        'salesMenuCompletions' => $currentSalesMenuCompletions,
                        'salesInfo' => $currentSalesInfos,
                        'salesOrderCampaign' => $currentSalesOrderCampaigs,
                        'salesCustomerTransactions' => $currentCustomerTransactions,
                        'salesMenuRecommendation' => $currentSalesMenuRecommendations,
                        'salesMenuRelated' => $currentSalesMenuRelateds,
                        'questionAnswers' => $currentQuestionAnswers,
                        'salesRewardHead' => $currentSalesRewardHeads,
                        'salesRewardMenu' => $currentSalesRewardMenus,
                        'salesContactInfo' => $currentSalesContactInfos,
                        'salesProcessMenu' => $currentSalesProcessMenus,
                        'salesPlatformFee' => $currentSalesPlatformFees,
                        'customNumber' => $currentCustomNumbers,
                        'salesHeadVat' => $currentSalesHeadVats,
                        'salesMenuVat' => $currentSalesMenuVats
                ]);
                
                if ($beforePushData === $afterPushData) {
                    SalesHead::syncUpdate($sales['salesNum'], date('Y-m-d H:i:s'));
                }
            } catch (Exception $ex) {
                $this->addError('syncType', $ex->getMessage());
            }
        }
    }

    private function pushShift() {
        $shiftQuery = ShiftLog::find()
            ->select('shiftID')
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS NOT', 'shiftOutTime', null])
            ->andWhere(['IS', 'syncDate', null]);
        $unsyncCountQuery = (new Query())
            ->select(['a.shiftID', 'unsyncCount' => 'COUNT(*)'])
            ->from(ShiftLog::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b',
                'b.salesDateIn <= a.shiftOutTime AND b.syncDate IS NULL')
            ->andWhere(['IN', 'shiftID', $shiftQuery])
            ->groupBy('a.shiftID');

        $shiftLogHead = (new Query())
            ->select(['a.*', 'unsyncCount' => 'COALESCE(b.unsyncCount, 0)'])
            ->from(ShiftLog::tableName() . ' a')
            ->leftJoin(['b' => $unsyncCountQuery], 'a.shiftID = b.shiftID')
            ->andWhere(['IN', 'a.shiftID', $shiftQuery])
            ->all();

        $shiftLogDetail = (new Query())
            ->select('*')
            ->from(ShiftLogDetail::tableName())
            ->andWhere(['IN', 'shiftID', $shiftQuery])
            ->all();

        $shiftLogCash = (new Query())
            ->select('*')
            ->from(ShiftLogCash::tableName())
            ->andWhere(['IN', 'shiftID', $shiftQuery])
            ->all();

        $shiftLogMode = (new Query())
            ->select('*')
            ->from(ShiftLogMode::tableName())
            ->andWhere(['IN', 'shiftID', $shiftQuery])
            ->all();
        
        $shiftLogDetaildIDs = (new Query())
            ->select('ID')
            ->from(ShiftLogDetail::tableName())
            ->andWhere(['IN', 'shiftID', $shiftQuery])
            ->column();
        
        $salesShiftPaymentHeadIDs = (new Query())
            ->select('salesShiftPaymentHeadID')
            ->from(SalesShiftPaymentHead::tableName())
            ->where(['IN', 'shiftLogDetailID', $shiftLogDetaildIDs])
            ->andWhere(['IS', 'syncDate', NULL])
            ->andWhere(['IS NOT', 'submittedBy', NULL])
            ->column();
        
        $salesShiftPaymentHead = SalesShiftPaymentHead::find()
            ->where(['IN', 'salesShiftPaymentHeadID', $salesShiftPaymentHeadIDs])
            ->andWhere(['IS', 'syncDate', NULL])
            ->andWhere(['IS NOT', 'submittedBy', NULL])
            ->all();

        $salesShiftPaymentDetail = SalesShiftPaymentDetail::find()
            ->where(['IN', 'salesShiftPaymentHeadID', $salesShiftPaymentHeadIDs])
            ->andWhere(['IS', 'syncDate', NULL])
            ->all();

        $salesShiftPaymentDenom = SalesShiftPaymentDenom::find()
            ->where(['IN', 'salesShiftPaymentHeadID', $salesShiftPaymentHeadIDs])
            ->all();

        foreach ($shiftLogHead as $shiftLog) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($shiftLog['unsyncCount'] == '0') {
                    $shiftLogDetails = Arrays::filter($shiftLogDetail,
                            function ($shiftLogDetail) use ($shiftLog) {
                            return $shiftLogDetail['shiftID'] == $shiftLog['shiftID'];
                        });

                    $detail = [];
                    foreach ($shiftLogDetails as $logDetail) {
                        $salesShiftHeads = [];
                        $salesShiftPaymentHeads = Arrays::filter($salesShiftPaymentHead,
                            function ($salesShiftHead) use ($logDetail) {
                            return $salesShiftHead['shiftLogDetailID'] == $logDetail['ID'];
                        });

                        foreach ($salesShiftPaymentHeads as $salesShiftHead) {
                            $salesShiftDetails = [];
                            $salesShiftDenoms = [];
                            $salesShiftPaymentDetails = Arrays::filter($salesShiftPaymentDetail,
                                function ($salesShiftDetail) use ($salesShiftHead) {
                                return $salesShiftDetail['salesShiftPaymentHeadID'] == $salesShiftHead['salesShiftPaymentHeadID'];
                            });

                            foreach ($salesShiftPaymentDetails as $salesShiftDetail) {
                                $salesShiftDetails[] = [
                                    'salesShiftPaymentHeadID' => $salesShiftDetail['salesShiftPaymentHeadID'],
                                    'salesShiftDetailID' => $salesShiftDetail['salesShiftDetailID'],
                                    'paymentMethodID' => $salesShiftDetail['paymentMethodID'],
                                    'actualPaymentAmount' => $salesShiftDetail['actualPaymentAmount'],
                                    'expectedPaymentAmount' => $salesShiftDetail['expectedPaymentAmount']
                                ];
                            }

                            $salesShiftPaymentDenoms = Arrays::filter($salesShiftPaymentDenom,
                                function ($salesShiftDenom) use ($salesShiftHead) {
                                return $salesShiftDenom['salesShiftPaymentHeadID'] == $salesShiftHead['salesShiftPaymentHeadID'];
                            });
                            foreach ($salesShiftPaymentDenoms as $salesShiftDenom) {
                                $salesShiftDenoms[] = [
                                    'salesShiftPaymentHeadID' => $salesShiftDenom['salesShiftPaymentHeadID'],
                                    'ID' => $salesShiftDenom['ID'],
                                    'localID' => $salesShiftDenom['localID'],
                                    'denomAmount' => $salesShiftDenom['denomAmount'],
                                    'denomQty' => $salesShiftDenom['denomQty'],
                                    'denomTotal' => $salesShiftDenom['denomTotal']
                                ];
                            }

                            $salesShiftHeads[] = [
                                'salesShiftPaymentHeadID' => $salesShiftHead['salesShiftPaymentHeadID'],
                                'shiftID' => $salesShiftHead['shiftID'],
                                'shiftLogDetailID' => $salesShiftHead['shiftLogDetailID'],
                                'branchID' => $shiftLog['branchID'],
                                'actualTotalPaymentNonCash' => $salesShiftHead['actualTotalPaymentNonCash'],
                                'expectedTotalPaymentNonCash' => $salesShiftHead['expectedTotalPaymentNonCash'],
                                'actualTotalPaymentCash' => $salesShiftHead['actualTotalPaymentCash'],
                                'expectedTotalPaymentCash' => $salesShiftHead['expectedTotalPaymentCash'],
                                'description' => $salesShiftHead['description'],
                                'createdBy' => $salesShiftHead['createdBy'],
                                'submittedBy' => $salesShiftHead['submittedBy'],
                                'salesShiftDetails' => $salesShiftDetails,
                                'salesShiftDenoms' => $salesShiftDenoms
                            ];

                        }

                        $detail[] = [
                            'ID' => $logDetail['ID'],
                            'shiftID' => $logDetail['shiftID'],
                            'shiftTime' => $logDetail['shiftTime'],
                            'shiftUsername' => $logDetail['shiftUsername'],
                            'branchID' => $shiftLog['branchID'],
                            'shiftInTime' => $shiftLog['shiftInTime'],
                            'shiftOutTime' => $shiftLog['shiftOutTime'],
                            'salesShiftHeads' => $salesShiftHeads
                        ];
                    }

                    $shiftLogCashDatas = Arrays::filter($shiftLogCash,
                            function ($shiftLogCash) use ($shiftLog) {
                            return $shiftLogCash['shiftID'] == $shiftLog['shiftID'];
                        });

                    $shiftCash = [];
                    foreach ($shiftLogCashDatas as $logCash) {
                        $shiftCash[] = [
                            'ID' => $logCash['ID'],
                            'branchID' => $shiftLog['branchID'],
                            'shiftInTime' => $shiftLog['shiftInTime'],
                            'shiftOutTime' => $shiftLog['shiftOutTime'],
                            'shiftID' => $logCash['shiftID'],
                            'shiftNumber' => $logCash['shiftNumber'],
                            'shiftInTimeCash' => $logCash['shiftInTime'],
                            'shiftOutTimeCash' => $logCash['shiftOutTime'],
                            'startingCash' => $logCash['startingCash'],
                            'systemCashReceivedTotal' => $logCash['systemCashReceivedTotal'],
                            'endingCash' => $logCash['endingCash'],
                            'shiftInUsername' => $logCash['shiftInUsername'],
                            'shiftOutUsername' => $logCash['shiftOutUsername'],
                            'closingNotes' => $logCash['closingNotes']
                        ];
                    }

                    $shiftLogModeDatas = Arrays::filter($shiftLogMode,
                            function ($shiftLogMode) use ($shiftLog) {
                            return $shiftLogMode['shiftID'] == $shiftLog['shiftID'];
                        });

                    $shiftMode = [];
                    foreach ($shiftLogModeDatas as $logMode) {
                        $shiftMode[] = [
                            'ID' => $logMode['ID'],
                            'branchID' => $shiftLog['branchID'],
                            'shiftInTime' => $shiftLog['shiftInTime'],
                            'shiftOutTime' => $shiftLog['shiftOutTime'],
                            'shiftID' => $logMode['shiftID'],
                            'shiftMode' => $logMode['shiftMode']
                        ];
                    }

                     // @refactor http_helper
                    $httpService = new HttpHelperService();
                    $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-shift-data';
                    $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                    $data =   [
                        'shiftLog' => [$shiftLog],
                        'shiftLogDetail' => $detail,
                        'shiftLogCash' => $shiftCash,
                        'shiftLogMode' => $shiftMode
                    ];
                    $options = ['timeOut' => 300];
                    $response = $httpService->post($url, $headers, $data, $options);

                    if ($response->getData()['status'] == '00') {
                        ShiftLog::syncUpdate($shiftLog['shiftID'],
                            $response->getData()['syncDate'], $salesShiftPaymentHeadIDs);
                    } else {
                        Yii::warning($response->getData());
                        throw new Exception('Failed to push data');
                    }
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushStation() {
        $station = (new Query())
            ->select('*')
            ->from(Station::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($station as $data) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-station-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $datas =   [
                    'stationData' => [$data]
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    Station::syncUpdate($data['stationID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushTrackingOnlinePaymentLog() {
        $station = (new Query())
            ->select('*')
            ->from(PaymentOnlineTrackingLog::tableName())
            ->where(['branchID' => $this->branchID])
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($station as $data) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
             
                 // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-payment-online-tracking';
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $datas =   [
                     'trackingPaymentData' => [$data]
                 ];
                 $options = ['timeOut' => 300];
                 $response = $httpService->post($url, $headers, $datas, $options);
  
                if ($response->getData()['status'] == '00') {
                    PaymentOnlineTrackingLog::syncUpdate($data['salesNum'], $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushVoucher() {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Check to sync type table
            if (!$this->optimizeSync(self::VOUCHER)) {
                throw new Exception("Failed to sync optimize");
            }

            $voucher = (new Query())
                ->select('*')
                ->from(Voucher::tableName())
                ->andWhere(['IS', 'syncDate', null])
                ->all();

            foreach ($voucher as $data) {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-voucher-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $datas =   [
                    'voucherData' => [$data]
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);
                if ($response->getData()['status'] == '00') {
                    Voucher::syncUpdate($data['voucherID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }
            }
            
            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }
    
    private function pushPaymentMethod() {
        $paymentMethod = (new Query())
            ->select('*')
            ->from(PaymentMethod::tableName())
            ->andWhere(['IS', 'syncDate', null])
            ->andWhere(['IN', 'posExternalPaymentID', ['edcbca', 'wirecard', 'edccimb', 'edcbni', 'ecrbcaqris', 'ecrbcaflaz', 'ecrcimbqr', 'edcmdryoke', 'edcbri', 'edccimband', 'ecrbriqr', 'emoney']])
            ->all();
        
        foreach ($paymentMethod as $data) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                 // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-payment-method-data';
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $datas =   [
                    'paymentMethodData' => [$data]
                 ];
                 $options = ['timeOut' => 300];
                 $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    PaymentMethod::syncUpdate($data['paymentMethodID'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                Yii::error($ex->getMessage());
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }
    
    private function pushDeviceTransaction() {
        $deviceTransaction = (new Query())
            ->select('*')
            ->from(DeviceTransaction::tableName())
            ->andWhere(['IS', 'syncDate', null])
            ->all();

        foreach ($deviceTransaction as $data) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
               
                // @refactor http_helper
                 $httpService = new HttpHelperService();
                 $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-device-transaction-data';
                 $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                 $datas = [
                    'deviceTransactionData' => [$data],
                    'branchID' => Setting::getCurrentBranch(),
                 ];
                 $options = ['timeOut' => 300];
                 $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                    DeviceTransaction::syncUpdate($data['transactionDate'], $data['deviceMacAddress'],
                        $response->getData()['syncDate']);
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function pushPosVersion() {
        $posVersion = PosVersion::getRawAppVersion();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-pos-version';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'posVersion' => $posVersion,
                'branchID' => Setting::getCurrentBranch(),
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
 
            if ($response->getData()['status'] != '00') {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }

    private function pushKioskVersion() {
        $key1 = 'Local Setting';
        $key2 = 'Kiosk Version';
        $kioskVersion = self::getNewVersion($key1, $key2);
        $transaction = Yii::$app->db->beginTransaction();
        try {
        
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-kiosk-version';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'kioskVersion' => $kioskVersion,
                'branchID' => Setting::getCurrentBranch(),
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
 
            if ($response->getData()['status'] != '00') {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }

    private function pushOdsVersion() {
        $key1 = 'Local Setting';
        $key2 = 'Ods Version';
        $odsVersion = self::getNewVersion($key1, $key2);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-ods-version';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'odsVersion' => $odsVersion,
                'branchID' => Setting::getCurrentBranch(),
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getData()['status'] != '00') {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }

    private function pushQdsVersion() {
        $key1 = 'Local Setting';
        $key2 = 'Qds Version';
        $qdsVersion = self::getNewVersion($key1, $key2);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-qds-version';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'qdsVersion' => $qdsVersion,
                'branchID' => Setting::getCurrentBranch(),
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getData()['status'] != '00') {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }

    private function pushTableSideVersion() {
        $key1 = 'Local Setting';
        $key2 = 'Tableside Version';
        $tableSideVersion = self::getNewVersion($key1, $key2);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-table-side-version';
            $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
            $datas =   [
                'tableSideVersion' => $tableSideVersion,
                'branchID' => Setting::getCurrentBranch()
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            if ($response->getData()['status'] != '00') {
                Yii::warning($response->getData());
                throw new Exception('Failed to push data');
            }

            $transaction->commit();
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('syncType', $ex->getMessage());
            return false;
        }
    }
    
    private function pushSelfOrderCampaignDetail() {
        $selfOrderCampaignDetail = (new Query())
            ->select('*')
            ->from(\app\models\MapSelfOrderCampaignBranchDetail::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->all();
        
        foreach ($selfOrderCampaignDetail as $data) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-self-order-campaign-detail';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $datas =   [
                    'selfOrderCampaignDetail' => [$data],
                    'branchID' => Setting::getCurrentBranch(),
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $datas, $options);

                if ($response->getData()['status'] == '00') {
                } else {
                    Yii::warning($response->getData());
                    throw new Exception('Failed to push data');
                }

                $transaction->commit();
            } catch (Exception $ex) {
                Yii::error($ex->getMessage());
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }

    private function optimizeSync($key) {
        // Check to sync type table 
        if (!$key) {
            return false;
        }
        if ($key === 'MEMBER') {
            $model = Member::find()->select([
                'syncDate' => new Expression('MAX(syncDate)')
            ])->one();
        }else if($key === 'MEMBER DEPOSIT'){
            $model = MemberDeposit::find()->select([
                'syncDate' => new Expression('MAX(syncDate)')
            ])->one();
        }else if($key === 'MEMBER WITHDRAWAL') {
            $model = DepositWithdrawalHead::find()->select([
                'syncDate' => new Expression('MAX(syncDate)')
            ])->one();
        }else if($key === 'VOUCHER') {
            $model = Voucher::find()->select([
                'syncDate' => new Expression('MAX(syncDate)')
            ])->one();
        }

        $syncOptModel = SyncOptimize::findOne($key);
        if (!$syncOptModel) {
            $syncOptModel = new SyncOptimize();
            $syncOptModel->syncType = $key;
            if (!$model->syncDate) {
                return true;
            }
            $syncOptModel->pushDateTime = $model->syncDate;
            if (!$syncOptModel->save()) {
                return false;
            }
            return true;
        }else{
            if ($syncOptModel->pullDateTime) {
                if (!$model->syncDate) {
                    return true;
                }
                $syncOptModel->pushDateTime = $model->syncDate;
                $syncOptModel->pullDateTime = null;
                if (!$syncOptModel->save()) {
                    return false;
                }
            }
            return true;
        }
    }

    public static function getNewVersion($key1, $key2) {
        $setting = Setting::getSetting($key1, $key2);

        return $setting ? $setting->value1 : null;
    }
    
    private function pushTerminalData() {
        $terminalData = (new Query())
            ->select('*')
            ->from(Terminal::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['IS NOT', 'activatedDate', null])
            ->all();

        foreach ($terminalData as $terminal) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                 // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $this->apiUrl . '/' . self::API_VERSION . '/pull/pull-terminal-data';
                $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                $datas =   [
                   'terminalData' => [$terminal]
                ];
                $options = ['timeOut' => 300];
                $httpService->post($url, $headers, $datas, $options);

                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('syncType', $ex->getMessage());
                return false;
            }
        }
    }
}
