<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\BranchEvent;
use app\models\Menu;
use app\models\MenuGroup;
use app\models\MenuPackage;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\Setting;
use app\models\Station;
use Underscore\Types\Arrays;
use Yii;
use yii\console\Application;
use yii\db\Expression;
use yii\db\Query;

class Logging {
    const BOOK_TABLE = 'Book Table';
    const EDIT_TABLE = 'Edit Table';
    const CREATE_TAKE_AWAY = 'Create Take Away';
    const EDIT_TAKE_AWAY = 'Edit Take Away';
    const LINK_TABLE = 'Link Table';
    const MERGE_TABLE = 'Merge Table';
    const MOVE_ITEM = 'Move Item';
    const MOVE_ITEM_DESTINATION = 'Move Item Destination';
    const MOVE_TABLE = 'Move Table';
    const CANCEL_TABLE = 'Cancel Table';
    const SAVE_ORDER = 'Save Order';
    const SAVE_ORDER_ESO_FS = 'Save Order ESO FS';
    const SAVE_ORDER_ESO_QS = 'Save Order ESO QS';
    const SAVE_ORDER_TABLESIDE = 'Save Order Tableside';
    const SAVE_ORDER_KIOSK = 'Save Order Kiosk';
    const PRINT_BILL = 'Print Bill';
    const PRINT_CHECKER = 'Print Checker';
    const PRINT_CHECKER_SELFORDER = 'Print Checker Self Order';
    const ADD_BILL_PROMO = 'Add Bill Promo';
    const REMOVE_BILL_PROMO = 'Remove Bill Promo';
    const SAVE_PAYMENT = 'Save Payment';
    const SAVE_PAYMENT_ESO = 'Save Payment ESO';
    const SAVE_PAYMENT_KIOSK = 'Save Payment KIOSK';
    const EDIT_PAYMENT = 'Edit Payment';
    const EDIT_PAYMENT_BEFORE = 'Edit Payment Before';
    const REPRINT_PAYMENT = 'Reprint Payment';
    const VOID_SALES = 'Void Sales';
    const VOID_SALES_ESO = 'Void Sales ESO';
    const VOID_MENU_SALES = 'Void Menu Sales';
    const CREATE_MEMBER = 'Create Member';
    const EDIT_MEMBER = 'Edit Member';
    const CREATE_DEPOSIT = 'Create Deposit';
    const CREATE_WITHDRAWAL = 'Create Withdrawal';
    const SHIFT_IN = 'Shift In';
    const SHIFT_OUT = 'Shift Out';
    const END_SHIFT = 'End Shift';
    const REPRINT_END_SHIFT = 'Reprint End Shift';
    const REPRINT_SHIFT_OUT = 'Reprint Shift Out';
    const EDIT_BRANCH_MENU = 'Edit Branch Menu';
    const EDIT_STATION = 'Edit Station';
    const EDIT_SETTINGS = 'Edit Settings';
    const CHANGE_PASSWORD = 'Change Password';
    const PRINT_TEST = 'Print Test';
    const OPEN_DRAWER = 'Open Drawer';
    const SYNC_FETCH = 'Fetch Data';
    const SYNC_PUSH = 'Push Data';
    const OPEN_PRINTER = 'Open Printer';
    const OPEN_PRINTER_SELFORDER = 'Open Printer Self Order';
    const FAIL_OPEN_PRINTER = 'Fail Open Printer';
    const FAIL_OPEN_PRINTER_SELFORDER = 'Fail Open Printer Self Order';
    const ADD_ORDER_PROMO = 'Add Order Promo';
    const REMOVE_ORDER_PROMO = 'Remove Order Promo';
    const ADD_SALES_CHILD = 'Add Sales Child';
    const UPDATE_MENU_SPLIT = 'Update Menu Split';
    const DELETE_SALES_CHILD = 'Delete Sales Child';
    const DELETE_MENU_SPLIT = 'Delete Menu Split';
    const EDIT_REMARKS = 'Edit Remarks';
    const PRINT_REPORTING = 'Print Reporting';
    const EDIT_PAYMENT_EDC = 'Edit Payment EDC';
    const EDIT_EDC_SETTING_KIOSK = 'Edit EDC Setting KIOSK';
    const CHANGE_VISIT_PURPOSE = 'Change Visit Purpose';
    const ADD_MEMBER_EZO = 'Add Member EZO';
    const REMOVE_MEMBER_EZO = 'Remove Member EZO';
    const SIGNIN = 'SIGN IN';
    const SIGNOUT = 'SIGN OUT';
    const MAP_EMPLOYEE = 'Call API MAP Employee';
    const MAP_CLUB_TOKEN = 'Call API MAP Club Token';
    const MAP_CLUB_MEMBER = 'Call API MAP Club Member';
    const MAP_CLUB_TRANSACTION = 'Call API MAP Club Transaction';
    const MAP_UPDATE_EMPLOYEE_LIMIT = 'Call API MAP Update Employee Limit';
    const MAP_VALIDATE_VOUCHER = 'Call API Validate Online Voucher';
    const BURN_EXTERNAL_VOUCHER = 'Call API Burn Online Voucher';
    const UNBURN_VOUCHER_API_URL = 'Unburn Voucher API Url';
    const ADD_BA_ONLINE = 'Add Ba Online';
    const UPDATE_SUBMITTED_BY_BA_ONLINE = 'Update submitted by Ba Online';
    const CLAIM_ESB_ONLINE_VOUCHER = 'Call API Claim ESB Online Voucher';
    const GENERATE_VOUCHER_CASHBACK = 'Generate Voucher Cashback';
    const LOG_CHECK_MEMBER = 'Log Check Member';
    const APPLY_PROMOTION_WITH_PIN = 'Apply Promotion With PIN';
    const APPLY_PAYMENT_WITH_PIN = 'Apply Payment With PIN';
    const MEMBER_DEPOSIT_WITH_PIN = 'Member Deposit With PIN';
    const MEMBER_WITHDRAWAL_WITH_PIN = 'Member Withdrawal With PIN';
    const EDC_PAYMENT = 'EDC Payment';
    const EDC_QRIS_PAYMENT = 'EDC QRIS Payment';
    const EDC_TEST_CONNECTION = 'EDC Test Connection';
    const API_SAVE_MENU_ESO_FS = 'API Save Menu ESO FS';
    const CREATE_PAYMENT_QRIS = 'Create Payment QRIS';
    const SETTLEMENT_PAYMENT_QRIS = 'Settlement Payment QRIS';
    const UPDATE_POS_VERSION = 'Update POS Version';
    const FAIL_UPDATE_POS_VERSION = 'Fail Update POS Version';
    const FAIL_DOWNLOAD_POS_UPDATE = 'Fail Download POS Update';
    const UPDATE_ODS_VERSION = 'Update ODS Version';
    const UPDATE_QDS_VERSION = 'Update QDS Version';
    const FAIL_UPDATE_ODS_VERSION = 'Fail Update ODS Version';
    const FAIL_UPDATE_QDS_VERSION = 'Fail Update QDS Version';
    const UPDATE_KIOSK_VERSION = 'Update KIOSK Version';
    const FAIL_UPDATE_KIOSK_VERSION = 'Fail Update KIOSK Version';
    const UPDATE_TABLESIDE_VERSION = 'Update TABLESIDE Version';
    const FAIL_UPDATE_TABLESIDE_VERSION = 'Fail Update TABLESIDE Version';
    const SAVE_TERMINAL = 'Save Terminal';
    const EDIT_SELF_ORDER_SERVER = 'Edit Self Order Server';
    const CHANGE_MODE = 'Change Mode';
    const TERMINAL_SETTING_CHANGE = 'Terminal Setting Change';
    const ODS_TERMINAL_SETTING_CHANGE = 'ODS Terminal Setting Change';
    const KIOSK_TERMINAL_SETTING_CHANGE = 'KIOSK Terminal Setting Change';
    const FINISH_PICKUP_ORDER = 'Finish Pickup Order';
    const ADD_PICKUP_ORDER = 'Add Pickup Order';
    const DELETE_PICKUP_LIST = 'Delete Pickup List';
    const FAIL_GENERATE_PARKING_VOUCHER = 'Fail Generate Parking Voucher';
    const FAIL_GENERATE_PARKING_VOUCHER_SELFORDER = 'Fail Generate Parking Voucher Self Order';
    const FAIL_GENERATE_PARKING_VOUCHER_KIOSK = 'Fail Generate Parking Voucher KIOSK';
    const PRINT_KITCHEN = 'Print Kitchen';
    const ULTRA_VOUCHER_GET_MEMBER = 'Ultra Voucher Get Member';
    const REPRINT_MEMBER_DEPOSIT = 'Reprint Member Deposit';
    const REPRINT_WITHDRAWAL_DEPOSIT = 'Reprint Withdrawal Deposit';
    const POS_INSTALLATION = 'Pos Installation';
    const VALIDATE_PLUXEE_VOUCHER = 'Call API Validate Pluxee Voucher';
    const BURN_PLUXEE_VOUCHER = 'Call API Burn Pluxee Voucher';
    const UNBURN_PLUXEE_VOUCHER = 'Call API Unburn Pluxee Voucher';
    const VALIDATE_ULTRA_VOUCHER = 'Call API Validate Ultra Voucher';
    const BURN_ULTRA_VOUCHER = 'Call API Burn Ultra Voucher';
    const REMOVE_ULTRA_VOUCHER = 'Remove Ultra Voucher';
    const FAILED_SYNC_ERROR = 'Failed to Sync Master Setting';
    const CREATE_PAYMENT_UVL = 'Create Transaction UVL';
    const VALIDATE_PAYMENT_UVL = 'Validate Transaction UVL';
    const START_DAY_OTP = 'Start Day via OTP';
    const FAILED_GET_MEMBER = 'Failed to Get Member';
    const UPDATE_SALES_DATE_OUT = 'Update Sales Date Out';
    const ESO_FS_PROCESS_QUEUE = 'ESB Order Full Service Process Queue';
    const ESO_PROCESS_QUEUE = 'ESB Order Process Queue';
    const LOGGIN_SYNC_MENU_RTS = 'Sync Logging Menu RTS';
    const ONLINE_PAYMENT_TUTOR = 'Online Payment Tutor';
    const CALL_API_REGISTER_MEMBER = 'Api Register Member LOOP';
    const GENERATE_INVOICE = 'Generate e-Invoice';
    const CHECK_INQUIRY_INVOICE = 'Check Inquiry e-Invoice';
    const SAVE_ORDER_EXCEPTION = 'Error Recheck Order';
    const QDS_TERMINAL_SETTING_CHANGE = 'QDS Terminal Setting Change';

    public static function save($refNum, $eventSubject, $modelAttr, $menuPackages = null, $menuExtras = null, $lastEditedBy = null) {
        $eventDescription = '';
        switch ($eventSubject) {
            case self::SIGNIN:
                $eventDescription = json_encode([
                    'username' => $modelAttr['username'],
                    'fullName' => $modelAttr['fullName'],
                    'branchID' => $modelAttr['branchID'],
                ]);
                break;
            case self::SIGNOUT:
                $eventDescription = json_encode([
                    'username' => $modelAttr['username'],
                    'fullName' => $modelAttr['fullName'],
                    'branchID' => $modelAttr['branchID'],
                ]);
                break;
            case self::BOOK_TABLE:
            case self::CREATE_TAKE_AWAY:
                $eventDescription = Logging::getLogCreateSales($modelAttr);
                break;
            case self::EDIT_TABLE:
            case self::EDIT_TAKE_AWAY:
                $eventDescription = Logging::getLogEditSales($modelAttr);
                break;
            case self::LINK_TABLE:
                $eventDescription = Logging::getLogLinkTable($modelAttr);
                break;
            case self::MERGE_TABLE:
                $eventDescription = Logging::getLogMergeTable($modelAttr);
                break;
            case self::MOVE_ITEM:
                $eventDescription = Logging::getLogMoveItem($modelAttr);
                break;
            case self::MOVE_ITEM_DESTINATION:
                $eventDescription = Logging::getLogMoveItemDes($modelAttr);
                break;
            case self::MOVE_TABLE:
                $eventDescription = Logging::getLogMoveTable($modelAttr);
                break;
            case self::CANCEL_TABLE:
                $eventDescription = Logging::getLogCancelTable($modelAttr);
                break;
            case self::SAVE_ORDER:
            case self::SAVE_ORDER_ESO_FS:
            case self::SAVE_ORDER_ESO_QS:
            case self::SAVE_ORDER_TABLESIDE:
            case self::SAVE_ORDER_KIOSK:
                $eventDescription = Logging::getLogSaveOrder($modelAttr);
                break;
            case self::APPLY_PROMOTION_WITH_PIN:
                $eventDescription = Logging::getLogSaveOrderWithAuthPromotion($modelAttr);
                break;
            case self::PRINT_CHECKER:
            case self::PRINT_CHECKER_SELFORDER:
                $eventDescription = Logging::getLogPrintChecker($modelAttr);
                break;
            case self::ADD_BILL_PROMO:
                $eventDescription = Logging::getLogAddBill($modelAttr);
                break;
            case self::REMOVE_BILL_PROMO:
                $eventDescription = Logging::getLogRemoveBill($modelAttr);
                break;
            case self::SAVE_PAYMENT:
            case self::SAVE_PAYMENT_ESO:
            case self::SAVE_PAYMENT_KIOSK:
            case self::EDIT_PAYMENT:
                $eventDescription = Logging::getLogSavePayment($modelAttr);
                break;
            case self::EDIT_PAYMENT_BEFORE:
                $eventDescription = Logging::getLogEditSavePayment($modelAttr);
                break;
            case self::APPLY_PAYMENT_WITH_PIN:
                $eventDescription = Logging::getLogSavePaymentWithAuthPaymentMethod($modelAttr);
                break;
            case self::VOID_SALES:
            case self::VOID_SALES_ESO:
                $eventDescription = Logging::getLogVoidSales($modelAttr);
                break;
            case self::VOID_MENU_SALES:
                $eventDescription = Logging::getLogVoidMenuSales($modelAttr, $menuPackages, $menuExtras);
                break;
            case self::CREATE_MEMBER:
            case self::EDIT_MEMBER:
                $eventDescription = Logging::getLogSaveMember($modelAttr);
                break;
            case self::CREATE_DEPOSIT:
                $eventDescription = Logging::getLogCreateDeposit($modelAttr);
                break;
            case self::MEMBER_DEPOSIT_WITH_PIN:
                $eventDescription = Logging::getLogCreateDepositWithAuth($modelAttr);
                break;
            case self::CREATE_WITHDRAWAL:
                $eventDescription = Logging::getLogCreateWithdrawal($modelAttr);
                break;
            case self::MEMBER_WITHDRAWAL_WITH_PIN:
                $eventDescription = Logging::getLogCreateWithdrawalAuth($modelAttr);
                break;
            case self::SHIFT_IN:
                $eventDescription = Logging::getLogShiftIn($modelAttr);
                break;
            case self::SHIFT_OUT:
                $eventDescription = Logging::getLogShiftOut($modelAttr);
                break;
            case self::END_SHIFT:
                $eventDescription = Logging::getLogEndShift($modelAttr);
                break;
            case self::REPRINT_END_SHIFT:
                $eventDescription = Logging::getLogReprintEndShift($modelAttr);
                break;
            case self::EDIT_BRANCH_MENU:
                $eventDescription = Logging::getLogEditBranchMenu($modelAttr, $menuPackages );
                break;
            case self::EDIT_STATION:
                $eventDescription = Logging::getLogEditStation($modelAttr);
                break;
            case self::EDIT_SETTINGS:
                $eventDescription = Logging::getLogEditSettings($modelAttr);
                break;
            case self::TERMINAL_SETTING_CHANGE:
                $eventDescription = Logging::getLogChangeTerminalSetting($modelAttr);
                break;
            case self::ODS_TERMINAL_SETTING_CHANGE:
                $eventDescription = Logging::getLogChangeOdsTerminalSetting($modelAttr);
                break;
            case self::QDS_TERMINAL_SETTING_CHANGE:
                $eventDescription = Logging::getLogChangeQdsTerminalSetting($modelAttr);
                break;
            case self::KIOSK_TERMINAL_SETTING_CHANGE:
                $eventDescription = Logging::getLogChangeKioskTerminalSetting($modelAttr);
                break;
            case self::EDIT_SELF_ORDER_SERVER:
                $eventDescription = Logging::getLogEditSelfOrderServer($modelAttr);
                break;
            case self::ADD_PICKUP_ORDER:
                $eventDescription = Logging::getLogAddPickupOrder($modelAttr);
                break;
            case self::FINISH_PICKUP_ORDER:
                $eventDescription = Logging::getLogFinishPickupOrder($modelAttr);
                break;
            case self::DELETE_PICKUP_LIST:
                $eventDescription = Logging::getLogDeletePickupList($modelAttr);
                break;
            case self::CHANGE_PASSWORD:
                $eventDescription = Logging::getLogChangePassword($modelAttr);
                break;
            case self::SYNC_FETCH:
            case self::SYNC_PUSH:
                $eventDescription = Logging::getLogSync($modelAttr);
                break;
            case self::EDIT_PAYMENT_EDC:
                $eventDescription = Logging::getLogEditPaymentEdc($modelAttr);
                break;
            case self::EDIT_EDC_SETTING_KIOSK:
                $eventDescription = Logging::getLogEditEdcSettings($modelAttr);
                break;
            case self::CHANGE_VISIT_PURPOSE:
                $eventDescription = Logging::getLogChangeVisitPurpose($modelAttr);
                break;
            case self::OPEN_PRINTER:
            case self::OPEN_PRINTER_SELFORDER:
            case self::FAIL_OPEN_PRINTER:
            case self::FAIL_OPEN_PRINTER_SELFORDER:
            case self::PRINT_BILL:
            case self::REPRINT_PAYMENT:
            case self::REPRINT_SHIFT_OUT:
            case self::PRINT_TEST:
            case self::OPEN_DRAWER:
                $eventDescription = Logging::getLogOpenDrawer($modelAttr);
                break;
            case self::PRINT_REPORTING:
                /* @var $modelAttr Station */
                $eventDescription = json_encode([
                    'stationID' => $modelAttr->stationID,
                    'stationName' => $modelAttr->stationName
                ]);
                break;
            case self::ADD_SALES_CHILD:
                $eventDescription = Logging::getLogAddSalesChild($modelAttr);
                break;
            case self::UPDATE_MENU_SPLIT:
                $eventDescription = Logging::getLogUpdateMenuSplit($modelAttr);
                break;
            case self::DELETE_MENU_SPLIT:
                $eventDescription = Logging::getLogDeleteMenuSplit($modelAttr, $menuPackages, $menuExtras);
                // $eventDescription = json_encode($modelAttr);
                break;
            case self::DELETE_SALES_CHILD:
                $eventDescription = Logging::getLogDeleteSalesChild($modelAttr, $menuPackages, $menuExtras);
                break;
            case self::MAP_EMPLOYEE:
            case self::MAP_CLUB_TOKEN:
            case self::MAP_CLUB_MEMBER:
            case self::MAP_CLUB_TRANSACTION:
            case self::MAP_UPDATE_EMPLOYEE_LIMIT:
            case self::MAP_VALIDATE_VOUCHER:
            case self::BURN_EXTERNAL_VOUCHER:
            case self::UNBURN_VOUCHER_API_URL:
            case self::CLAIM_ESB_ONLINE_VOUCHER:
                $eventDescription = Logging::getLogMap($modelAttr);
                break;
            case self::GENERATE_VOUCHER_CASHBACK:
                $eventDescription = Logging::getLogGenerateVoucherTemplate($modelAttr);
                break;
            case self::ADD_BA_ONLINE:
                $eventDescription = Logging::getLogHeadBaOnline($modelAttr);
                break;
            case self::UPDATE_SUBMITTED_BY_BA_ONLINE:
                $eventDescription = Logging::getLogSubmittedByBaOnline($modelAttr);
                break;
            case self::LOG_CHECK_MEMBER:
                $eventDescription = Logging::getLogCheckMember($modelAttr);
                break;
            case self::EDC_PAYMENT:
            case self::EDC_QRIS_PAYMENT:
            case self::EDC_TEST_CONNECTION:
            case self::API_SAVE_MENU_ESO_FS:
            case self::CREATE_PAYMENT_QRIS:    
            case self::CREATE_PAYMENT_UVL:
            case self::VALIDATE_PAYMENT_UVL:
            case self::SETTLEMENT_PAYMENT_QRIS:
            case self::SAVE_TERMINAL:
            case self::CHANGE_MODE:
                $eventDescription = json_encode($modelAttr);
                break;
            case self::UPDATE_POS_VERSION:
                $eventDescription = Logging::getLogUpdatePosVersion($modelAttr);
                break;
            case self::FAIL_UPDATE_POS_VERSION:
            case self::FAIL_DOWNLOAD_POS_UPDATE:
                $eventDescription = Logging::getLogFailUpdatePosVersion($modelAttr);
                break;
            case self::UPDATE_ODS_VERSION:
                $eventDescription = Logging::getLogUpdateOdsVersion($modelAttr);
                break;
            case self::FAIL_UPDATE_ODS_VERSION:
                $eventDescription = Logging::getLogFailUpdateOdsVersion($modelAttr);
                break;
            case self::UPDATE_QDS_VERSION:
                $eventDescription = Logging::getLogUpdateOdsVersion($modelAttr);
                break;
            case self::FAIL_UPDATE_QDS_VERSION:
                $eventDescription = Logging::getLogFailUpdateOdsVersion($modelAttr);
                break;
            case self::UPDATE_KIOSK_VERSION:
                $eventDescription = Logging::getLogUpdateKioskVersion($modelAttr);
                break;
            case self::FAIL_UPDATE_KIOSK_VERSION:
                $eventDescription = Logging::getLogFailUpdateKioskVersion($modelAttr);
                break;
            case self::UPDATE_TABLESIDE_VERSION:
                $eventDescription = Logging::getLogUpdateTablesideVersion($modelAttr);
                break;
            case self::FAIL_UPDATE_TABLESIDE_VERSION:
                $eventDescription = Logging::getLogFailUpdateTablesideVersion($modelAttr);
                break;
            case self::FAIL_GENERATE_PARKING_VOUCHER:
            case self::FAIL_GENERATE_PARKING_VOUCHER_SELFORDER:
            case self::FAIL_GENERATE_PARKING_VOUCHER_KIOSK:
                $eventDescription = Logging::getLogFailGenerateParkingVoucher($modelAttr);
                break;
            case self::PRINT_KITCHEN:
                $eventDescription = Logging::getLogPrintKitchen($modelAttr);
                break;
            case self::POS_INSTALLATION:
                $eventDescription = Logging::getLogPosInstallation($modelAttr);
                break;
            case self::ULTRA_VOUCHER_GET_MEMBER:
                $eventDescription = Logging::getLogUvlLog($modelAttr);
                break;
            case self::REPRINT_MEMBER_DEPOSIT:
                $eventDescription = Logging::getLogRePrintMemberDeposit($modelAttr, $menuPackages);
                break;
            case self::REPRINT_WITHDRAWAL_DEPOSIT:
                $eventDescription = Logging::getLogRePrintWithdrawalDeposit($modelAttr, $menuPackages);
                break;
            case self::VALIDATE_PLUXEE_VOUCHER:
                $eventDescription = Logging::getLogPluxee($modelAttr);
                break;
            case self::BURN_PLUXEE_VOUCHER:
                $eventDescription = Logging::getLogPluxee($modelAttr);
                break;
            case self::UNBURN_PLUXEE_VOUCHER:
                $eventDescription = Logging::getLogPluxee($modelAttr);
                break;
            case self::VALIDATE_ULTRA_VOUCHER:
            case self::BURN_ULTRA_VOUCHER:
            case self::FAILED_GET_MEMBER:
            case self::REMOVE_ULTRA_VOUCHER:
                $eventDescription = Logging::getLogUv($modelAttr);
                break;
            case self::FAILED_SYNC_ERROR:
                $eventDescription = Logging::getLogFailedSyncPos($modelAttr, $menuPackages);
                break;
            case self::START_DAY_OTP:
                $eventDescription = Logging::getLogStartDayOTP($modelAttr);
                break;
            case self::UPDATE_SALES_DATE_OUT:
                $eventDescription = Logging::getLogUpdateSalesDateOut($modelAttr);
                break;
            case self::ESO_FS_PROCESS_QUEUE:
                $eventDescription = Logging::getLogEsoQueueProcess($modelAttr);
                break;
            case self::ESO_PROCESS_QUEUE:
                $eventDescription = Logging::getLogEsoQueueProcess($modelAttr);
                break;
            case self::CALL_API_REGISTER_MEMBER:
                $eventDescription = json_encode($modelAttr);
                break;
            case self::ONLINE_PAYMENT_TUTOR:
                $eventDescription = json_encode($modelAttr);
                break;
            case self::GENERATE_INVOICE:
                $eventDescription = json_encode($modelAttr);
                break;
            case self::CHECK_INQUIRY_INVOICE:
                $eventDescription = json_encode($modelAttr);
                break;
            case self::SAVE_ORDER_EXCEPTION:
                $eventDescription = Logging::getLogEsoFailedSaveOrder($modelAttr);
                break;
            case self::LOGGIN_SYNC_MENU_RTS:
                $eventDescription = json_encode($modelAttr);
                break;
            default:
                return;
        }
        
        Logging::insertLog($refNum, $eventSubject, $eventDescription, $lastEditedBy);
    }

    private static function getLogSubmittedByBaOnline($modelAttr) {
        return json_encode([
            'submittedBy' => $modelAttr['submittedBy'],
            'salesShiftPaymentHeadID' => $modelAttr['salesShiftPaymentHeadID']
        ]);
    }

    private static function getLogHeadBaOnline($modelAttr) {
        return json_encode([
            'actualEndingCash' => $modelAttr['actualEndingCash'],
            'description' => $modelAttr['description'],
            'shiftLogDetailID' => $modelAttr['shiftLogDetailID'],
            'salesShiftPaymentHeadID' => $modelAttr['salesShiftPaymentHeadID'],
            'isEndShift' => $modelAttr['isEndShift']
        ]);
    }

    private static function getLogMap($modelAttr) {
        return json_encode([
            'body' => $modelAttr['body'],
            'response' => $modelAttr['response'],
            'data' => isset($modelAttr['data']) ? $modelAttr['data'] : null
        ]);
    }

    private static function getLogGenerateVoucherTemplate($modelAttr) {
        return json_encode([
            'requestData' => isset($modelAttr['requestBody']) ? $modelAttr['requestBody'] : '',
            'responseData' => isset($modelAttr['responseBody']) ? $modelAttr['responseBody'] : ''
        ]);
    }

    private static function getLogCreateSales($modelAttr) {
        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'visitPurposeID' => $modelAttr['visitPurposeID'],
            'visitPurposeName' => $modelAttr['visitModel']->visitPurposeName,
            'paxTotal' => $modelAttr['paxTotal']
        ]);
    }

    private static function getLogEditSales($modelAttr) {
        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'memberID' => $modelAttr['memberID'],
            'memberName' => $modelAttr['memberID'] != 0 ? $modelAttr['salesModel']->member->memberName : '',
            'visitPurposeID' => $modelAttr['visitPurposeID'],
            'visitPurposeName' => $modelAttr['visitModel']->visitPurposeName,
            'paxTotal' => $modelAttr['paxTotal']
        ]);
    }

    private static function getLogLinkTable($modelAttr) {
        $salesLink = [];
        foreach ($modelAttr['salesLink'] as $link) {
            $salesLink[] = [
                'tableID' => $link['tableID'],
                'tableName' => $link['tableName'],
                'salesNum' => $link['salesNum']
            ];
        }

        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesLink' => $salesLink,
            'mainSales' => [
                'tableID' => $modelAttr['mainSalesModel']->tableID,
                'tableName' => $modelAttr['mainSalesModel']->table->tableName,
                'salesNum' => $modelAttr['mainSalesModel']->salesNum
            ]
        ]);
    }

    private static function getLogMergeTable($modelAttr) {
        $salesMerge = [];
        foreach ($modelAttr['salesMerge'] as $merge) {
            $salesMerge[] = [
                'tableID' => $merge['tableID'],
                'tableName' => $merge['tableName'],
                'salesNum' => $merge['salesNum']
            ];
        }

        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesMerge' => $salesMerge,
            'mainSales' => [
                'tableID' => $modelAttr['salesModel']->tableID,
                'tableName' => $modelAttr['salesModel']->table->tableName,
                'salesNum' => $modelAttr['salesModel']->salesNum
            ]
        ]);
    }
    
    private static function getLogAddSalesChild($modelAttr) {
        return json_encode([
            'source' => [
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'target' => [
                'salesNum' => $modelAttr['salesNumTarget']
            ]
        ]);
    }
    
    private static function getLogUpdateMenuSplit($modelAttr) {
        $salesMenu = [];
        foreach ($modelAttr['targetSalesMenuModel'] as $key => $value) {
            $salesMenu[$key] = $value;
        }

        $packages = SalesMenu::getMenuPackages($modelAttr['targetSalesMenuModel']['salesNum'], $modelAttr['targetSalesMenuModel']['localID']);
        if (!is_null($packages)) {
            $salesMenu['packages'] = $packages;
        }
        
        
        $extras = SalesMenuExtra::getMenuExtras($modelAttr['menuID'], $modelAttr['targetSalesMenuModel']['localID']);
        if (!is_null($extras)) {
            $salesMenu['extras'] = $extras;
        }

        return json_encode([
            'source' => [
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'target' => [
                'salesNum' => $modelAttr['salesNumTarget']
            ],
            'salesMenu' => $salesMenu
        ]);
    }
    
    private static function getLogDeleteSalesChild($modelAttr, $menuPackages, $menuExtras) {
        $salesMenu = [];
        $i = 0;

        foreach ($modelAttr['targetSalesMenuModel'] as $detail) {
            $itemSalesMenu = [];

            foreach ($detail as $key => $value) {
                $itemSalesMenu[$key] = $value;
            }
            foreach ($menuPackages as $packages) {
                if ( $itemSalesMenu['ID'] === $packages['menuRefID']) {
                    $itemSalesMenu['packages'][] = $packages;
                }
            }
            foreach ($menuExtras as $extras) {
                if ( $itemSalesMenu['ID'] === $extras['menuDetailID']) {
                    $itemSalesMenu['extras'][] = $extras;
                }
            }

            $i++;
            $salesMenu[] = $itemSalesMenu;
        }
        return json_encode([
            'source' => [
                'salesNum' => $modelAttr['salesNumHead']
            ],
            'target' => [
                'salesNum' => $modelAttr['salesNum']
            ],
            'salesMenu' => $salesMenu
        ]);
    }
    
    private static function getLogDeleteMenuSplit($modelAttr, $menuPackages, $menuExtras) {
        $salesMenu = [];
        foreach ($modelAttr['targetSalesMenuModel'] as $key => $value) {
            $salesMenu[$key] = $value;
        }
        $salesMenu['packages'] = $menuPackages;
        $salesMenu['extras'] = $menuExtras;
        return json_encode([
            'source' => [
                'salesNum' => $modelAttr['salesNum']
            ],
            'target' => [
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'salesMenu' => $salesMenu
        ]);
    }

    private static function getLogMoveItem($modelAttr) {
        $salesMove = [];
        foreach ($modelAttr['salesMove'] as $menu) {
            $salesMove[] = Logging::getSalesMenu($menu);
        }

        return json_encode([
            'source' => [
                'tableID' => $modelAttr['sourceTableID'],
                'tableName' => $modelAttr['sourceSalesModel']->table ? $modelAttr['sourceSalesModel']->table->tableName : '',
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'target' => [
                'tableID' => $modelAttr['tableID'],
                'tableName' => $modelAttr['salesModel']->table ? $modelAttr['salesModel']->table->tableName : '',
                'salesNum' => $modelAttr['salesModel']->salesNum,
                'newTable' => $modelAttr['salesNum'] == null
            ],
            'salesMove' => $salesMove
        ]);
    }

    private static function getLogMoveItemDes($modelAttr) {
        $salesMove = [];
        foreach ($modelAttr['salesMove'] as $menu) {
            $salesMove[] = Logging::getSalesMenu($menu);
        }

        return json_encode([
            'source' => [
                'tableID' => $modelAttr['sourceTableID'],
                'tableName' => $modelAttr['sourceSalesModel']->table ? $modelAttr['sourceSalesModel']->table->tableName : '',
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'target' => [
                'tableID' => $modelAttr['tableID'],
                'tableName' => $modelAttr['salesModel']->table ? $modelAttr['salesModel']->table->tableName : '',
                'salesNum' => $modelAttr['salesModel']->salesNum,
                'newTable' => $modelAttr['salesNum'] == null
            ],
            'salesMove' => $salesMove
        ]);
    }

    private static function getLogMoveTable($modelAttr) {
        return json_encode([
            'source' => [
                'tableID' => $modelAttr['sourceTableID'],
                'tableName' => $modelAttr['sourceSalesModel']->table ? $modelAttr['sourceSalesModel']->table->tableName : '',
                'salesNum' => $modelAttr['sourceSalesNum']
            ],
            'target' => [
                'tableID' => $modelAttr['tableID'],
                'tableName' => $modelAttr['tableModel']->tableName
            ],
        ]);
    }

    private static function getLogSaveOrder($modelAttr) {
        $salesMenu = [];
        if (isset($modelAttr['salesMenu'])) {
            foreach ($modelAttr['salesMenu'] as $menu) {
                $salesMenu[] = Logging::getSalesMenu($menu);
            }
        }

        $employeeDetail = [];
        if (isset($modelAttr['employeeName']) && !empty($modelAttr['employeeName'])) {
            $employeeDetail = [
                'employeeName' => $modelAttr['employeeName'],
                'employeeCode' => '********',
                'employeeType' => $modelAttr['employeeType']
            ];
        }

        $memberDetail = [];
        if (isset($modelAttr['externalMemberName']) && !empty($modelAttr['externalMemberName'])) {
            $memberDetail = [
                'memberName' => $modelAttr['externalMemberName'],
                'memberCode' => '********',
                'memberType' => $modelAttr['externalMembershipTypeID']
            ];
        } elseif (isset($modelAttr['internalMemberName']) && !empty($modelAttr['internalMemberName'])) {
            $memberDetail = [
                'memberName' => $modelAttr['internalMemberName'],
                'memberCode' => '********',
                'memberType' => 'Internal Member'
            ];
        }

        $descriptionLog = [
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'additionalInfo' => $modelAttr['additionalInfo'],
            'batchID' => $modelAttr['batchID'],
            'promotionID' => $modelAttr['promotionID'],
            'salesHead' => self::getBeforeUpdateValue($modelAttr['salesHead']),
            'salesMenu' => $salesMenu,
            'employeeInfo' => $employeeDetail,
            'memberInfo' => $memberDetail
        ];

        if (isset($modelAttr['webSocketID'])) {
            $descriptionLog['webSocketID'] = $modelAttr['webSocketID'];
        }

        return json_encode($descriptionLog);

    }

    private static function getLogSaveOrderWithAuthPromotion($modelAttr) {
        return json_encode([
            'authUserName' => isset($modelAttr['authUserName']) ? $modelAttr['authUserName'] : null,
            'branchID' => isset($modelAttr['branchID']) ? $modelAttr['branchID'] : $modelAttr['salesModel']->branchID,
            'promotionID' => isset($modelAttr['promotionID']) ? $modelAttr['promotionID'] : 0,
            'promotionName' => isset($modelAttr['promotionName']) ? $modelAttr['promotionName'] : $modelAttr['promotionModel']->notes,
            'tableID' => isset($modelAttr['tableID']) ? $modelAttr['tableID'] : 0,
        ]);
    }

    private static function getLogCancelTable($modelAttr) {
        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'cancelNotes' => $modelAttr['cancelNotes']
        ]);
    }

    private static function getLogPrintChecker($modelAttr) {
        return json_encode([
            'batchID' => $modelAttr['batchID'],
            'stationID' => $modelAttr['stationID'],
            'stationName' => $modelAttr['stationModel']->stationName
        ]);
    }

    private static function getLogPrintKitchen($modelAttr) {
        return json_encode([
            'batchID' => $modelAttr['batchID'],
            'stationID' => $modelAttr['stationModel']->stationID,
            'stationName' => $modelAttr['stationModel']->stationName
        ]);
    }

    private static function getLogPosInstallation($modelAttr) {
        return json_encode([
            'username' => $modelAttr['username'],
            'branchID' => $modelAttr['branchID'],
            'apiUrl' => $modelAttr['apiUrl']
        ]);
    }

    private static function getLogRePrintMemberDeposit($modelAttr, $currentDeposit) {
        return json_encode([
            'memberDepositNum' => $modelAttr['memberDepositNum'],
            'depositTotal' => $modelAttr['depositTotal'],
            'currentDeposit' => $currentDeposit
        ]);
    }

    private static function getLogRePrintWithdrawalDeposit($modelAttr, $currentDeposit) {
        return json_encode([
            'depositWithdrawalNum' => $modelAttr['depositWithdrawalNum'],
            'withdrawalTotal' => $modelAttr['withdrawalTotal'],
            'currentDeposit' => $currentDeposit
        ]);
    }

    private static function getLogFailedSyncPos($modelAttr, $modelAttr2) {
        if (strlen($modelAttr2) > 200) {
            $modelAttr2 = substr($modelAttr2, 0, 200);
        }

        if($modelAttr['syncType'] == 'fetchTable') {
            $tables = explode('|', $modelAttr2);
            $tableName = $tables ? 'Table ' . $tables[0] : '';
            $tableID = $tables ? $tables[1] : '';

            return json_encode([
                'syncType' => $modelAttr['syncType'],
                'apiUrl' => $modelAttr['apiUrl'],
                'branchID' => $modelAttr['branchID'],
                'tableID' => $tableID,
                'errorMessage' => $tableName,
                'errorTime' => date('Y-m-d H:i:s')
            ]);
        }

        return json_encode([
            'syncType' => $modelAttr['syncType'],
            'apiUrl' => $modelAttr['apiUrl'],
            'branchID' => $modelAttr['branchID'],
            'errorMessage' => $modelAttr2,
            'errorTime' => date('Y-m-d H:i:s')
        ]);
    }

    private static function getLogAddBill($modelAttr) {
        $headData = self::getMemberAndEmployeeData($modelAttr);

        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'promotionID' => $modelAttr['promotionID'],
            'headBeforeUpdate' =>  self::getBeforeUpdateValue($modelAttr['headBeforeUpdate']),
            'headAfterUpdate' => self::getAfterUpdateValue($modelAttr['headAfterUpdate']),
            'employeeInfo' => $headData['employeeInfo'],
            'memberInfo' => $headData['memberInfo']
        ]);
    }

    private static function getLogRemoveBill($modelAttr) {
        $headBeforeUpdate = [];
        $headAfterUpdate = [];
        $headData = self::getMemberAndEmployeeData($modelAttr);

        if (isset($modelAttr['headBeforeUpdate']) || isset($modelAttr['headAfterUpdate'])) {
            $headBeforeUpdate = self::getBeforeUpdateValue($modelAttr['headBeforeUpdate']);
            $headAfterUpdate = self::getAfterUpdateValue($modelAttr['headAfterUpdate']);
        }

        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'salesNum' => $modelAttr['salesNum'],
            'headBeforeUpdate' => $headBeforeUpdate,
            'headAfterUpdate' => $headAfterUpdate,
            'employeeInfo' => $headData['employeeInfo'],
            'memberInfo' => $headData['memberInfo']
        ]);
    }

    private static function getLogSavePayment($modelAttr) {
        $salesVoucher = [];
        if ($modelAttr['salesVoucher']) {
            foreach ($modelAttr['salesVoucher'] as $voucher) {
                unset($voucher['createdBranchID']);
                unset($voucher['usedBranchID']);
                unset($voucher['salesNum']);
                unset($voucher['voucherPercentage']);
                unset($voucher['createdBy']);
                unset($voucher['createdDate']);
                unset($voucher['editedBy']);
                unset($voucher['editedDate']);
                unset($voucher['syncDate']);
                if(isset($voucher['paymentMethodWithAuth'])) unset($voucher['paymentMethodWithAuth']);
                $salesVoucher[] = $voucher;
            }
        }

        $salesPayment = [];
        if ($modelAttr['salesPayment']) {
            foreach ($modelAttr['salesPayment'] as $payment) {
                unset($payment['salesNum']);
                unset($payment['branchID']);
                unset($payment['createdBy']);
                unset($payment['createdDate']);
                unset($payment['editedBy']);
                unset($payment['editedDate']);
                if(isset($payment['authUserName'])) unset($payment['authUserName']);
                $salesPayment[] = $payment;
            }
        }

        return json_encode([
            'tableID' => $modelAttr['tableID'],
            'tableName' => $modelAttr['salesModel']->table ? $modelAttr['salesModel']->table->tableName : '',
            'salesNum' => $modelAttr['salesNum'],
            'salesVoucher' => $salesVoucher,
            'salesPayment' => $salesPayment
        ]);
    }

    private static function getLogEditSavePayment($modelAttr) {
        $eventDescription = $modelAttr['eventDescription'];
        if($eventDescription) {
            $eventData = json_decode($eventDescription, TRUE);
            $salesPayment = [];
            $salesVoucher = [];

            if ($eventData['salesPayment']) {
                foreach ($eventData['salesPayment'] as $payment) {
                    $salesPayment[] = $payment;
                }
            }

            if ($eventData['salesVoucher']) {
                foreach ($eventData['salesVoucher'] as $voucher) {
                    $salesVoucher[] = $voucher;
                }
            }
            
            return json_encode([
                'tableID' => $eventData['tableID'],
                'tableName' => $eventData['tableName'] ? $eventData['tableName'] : '',
                'salesNum' => $eventData['salesNum'],
                'salesVoucher' => $salesVoucher,
                'salesPayment' => $salesPayment
            ]);
        }
    }

    private static function getLogSavePaymentWithAuthPaymentMethod($modelAttr) {
        return json_encode([
            'authUserName' => $modelAttr['authUserName'],
            'branchID' => $modelAttr['branchID'],
            'paymentMethodID' => $modelAttr['paymentMethodID'],
            'paymentMethodName' => $modelAttr['paymentMethodName'],
            'tableID' => $modelAttr['tableID'],
        ]);
    }

    private static function getLogVoidSales($modelAttr) {
        return json_encode([
            'tableID' => $modelAttr['salesModel']->tableID,
            'tableName' => $modelAttr['salesModel']->table ? $modelAttr['salesModel']->table->tableName : '',
            'salesNum' => $modelAttr['salesNum'],
            'voidNotes' => $modelAttr['voidNotes']
        ]);
    }

    private static function getLogVoidMenuSales($modelAttr, $menuPackages, $menuExtras) {
        return json_encode([
            'tableID' => $modelAttr['salesModel']->tableID,
            'tableName' => $modelAttr['salesModel']->tableID != 0 ? $modelAttr['salesModel']->table->tableName : 'Quick Service',
            'salesNum' => $modelAttr['salesMenuModel']->salesNum,
            'salesMenuID' => $modelAttr['salesMenuID'],
            'menuID' => $modelAttr['salesMenuModel']->menuID,
            'menuName' => Menu::findOne($modelAttr['salesMenuModel']->menuID)->menuName,
            'subTotal' => $modelAttr['subTotal'],
            'grandTotal' => $modelAttr['grandTotal'],
            'inclusivePrice' => $modelAttr['inclusivePrice'],
            'menuDiscount' => $modelAttr['discountValue'],
            'serviceCharge' => $modelAttr['serviceCharge'],
            'taxTotal' => $modelAttr['taxTotal'],
            'voidQty' => $modelAttr['voidQty'],
            'packages' => $menuPackages,
            'extras' => $menuExtras
        ]);
    }

    private static function getLogSaveMember($modelAttr) {
        return json_encode([
            'memberID' => $modelAttr['memberID'],
            'memberName' => $modelAttr['memberName'],
            'memberCode' => $modelAttr['memberCode'],
            'genderID' => $modelAttr['genderID'],
            'memberBirthDate' => $modelAttr['memberBirthDate'],
            'memberAddress' => $modelAttr['memberAddress'],
            'memberPhone' => $modelAttr['memberPhone'],
            'memberEmail' => $modelAttr['memberEmail']
        ]);
    }

    private static function getLogCreateDeposit($modelAttr) {
        return json_encode([
            'memberID' => $modelAttr['memberID'],
            'memberName' => $modelAttr['memberModel']->memberName,
            'memberCode' => $modelAttr['memberModel']->memberCode,
            'paymentMethodID' => $modelAttr['paymentMethodID'],
            'paymentMethodName' => $modelAttr['paymentMethodModel']->paymentMethodName,
            'depositTotal' => $modelAttr['depositTotal'],
            'additionalInfo' => $modelAttr['additionalInfo']
        ]);
    }

    private static function getLogCreateDepositWithAuth($modelAttr) {
        return json_encode([
            'branchID' => $modelAttr['paymentMethodModel']->branchID,
            'paymentMethodID' => $modelAttr['paymentMethodID'],
            'paymentMethodName' => $modelAttr['paymentMethodModel']->paymentMethodName,
            'authUserName' => isset($modelAttr['authUserName']) ? $modelAttr['authUserName'] : null
        ]);
    }

    private static function getLogCreateWithdrawal($modelAttr) {
        return json_encode([
            'memberID' => $modelAttr['memberID'],
            'memberName' => $modelAttr['memberModel']->memberName,
            'memberCode' => $modelAttr['memberModel']->memberCode,
            'paymentMethodID' => $modelAttr['paymentMethodID'],
            'paymentMethodName' => $modelAttr['paymentMethodModel']->paymentMethodName,
            'withdrawalTotal' => $modelAttr['withdrawalTotal'],
            'additionalInfo' => $modelAttr['additionalInfo']
        ]);
    }

    private static function getLogCreateWithdrawalAuth($modelAttr) {
        return json_encode([
            'branchID' => $modelAttr['paymentMethodModel']->branchID,
            'paymentMethodID' => $modelAttr['paymentMethodID'],
            'paymentMethodName' => $modelAttr['paymentMethodModel']->paymentMethodName,
            'authUserName' => isset($modelAttr['authUserName']) ? $modelAttr['authUserName'] : null
        ]);
    }

    private static function getLogShiftIn($modelAttr) {
        return json_encode([
            'shiftInTotal' => $modelAttr['shiftInTotal'],
            'shiftInUsername' => $modelAttr['shiftLogModel']->shiftInUsername
        ]);
    }

    private static function getLogShiftOut($modelAttr) {
        return json_encode([
            'systemCashReceivedTotal' => $modelAttr['shiftLogModel']->systemCashReceivedTotal,
            'shiftOutTotal' => $modelAttr['shiftOutTotal'],
            'shiftOutNotes' => $modelAttr['shiftOutNotes'],
            'shiftOutUsername' => $modelAttr['shiftLogModel']->shiftOutUsername,
            'documentPrinted' => $modelAttr['labelPrinted']
        ]);
    }

    private static function getLogEndShift($modelAttr) {
        return json_encode([
            'shiftDetailID' => $modelAttr['shiftLogDetailModel']->ID,
            'shiftUsername' => $modelAttr['shiftLogDetailModel']->shiftUsername
        ]);
    }

    private static function getLogReprintEndShift($modelAttr) {
        return json_encode([
            'shiftDetailID' => $modelAttr['shiftDetailID'],
            'stationID' => $modelAttr['stationID'],
            'stationName' => $modelAttr['stationName']
        ]);
    }

    private static function getLogEditBranchMenu($modelAttr, $beforeChanges ) {
        $branchMenu = [];
        foreach ($modelAttr['branchMenu'] as $menus) {
            $updatedBranchMenu = Arrays::filter($menus['menus'],
                    function ($menu) {
                    return array_key_exists('updated', $menu);
                });
            
            $menu = [];
            foreach ($updatedBranchMenu as $updated) {
                $menuID = $updated['menuID'];
                $menu[] = [
                    'afterChanges' => [
                        'menuID' => $updated['menuID'],
                        'menuName' => $updated['menuName'],
                        'menuShortName' => $updated['menuShortName'],
                        'checkerStationID' => implode(',',$updated['checkerStationID']),
                        'checkerStationName' => implode(',',$updated['checkerStationName']),
                        'stationID' => implode(',',$updated['stationID']),
                        'stationName' => implode(',',$updated['stationName']),
                        'qty' => $updated['qty'],
                        'flagSoldOut' => $updated['flagSoldOut']
                    ],
                    'beforeChanges' => [
                        'menuID' => $updated['menuID'],
                        'menuName' => $beforeChanges[$menuID]['menuName'],
                        'menuShortName' => $beforeChanges[$menuID]['menuShortName'],
                        'checkerStationID' => $beforeChanges[$menuID]['checkerStationID'],
                        'checkerStationName' => $beforeChanges[$menuID]['checkerStationName'],
                        'stationID' => $beforeChanges[$menuID]['stationID'],
                        'stationName' => $beforeChanges[$menuID]['stationName'],
                        'qty' => $beforeChanges[$menuID]['qty'],
                        'flagSoldOut' => $beforeChanges[$menuID]['flagSoldOut']
                    ]
                ];
            }
            $branchMenu = array_merge($branchMenu, $menu);
        }

        return json_encode([
            'branchMenu' => $branchMenu
        ]);
    }

    private static function getLogEditStation($modelAttr) {
        $updatedStation = Arrays::filter($modelAttr['station'],
                function ($station) {
                return array_key_exists('updated', $station);
            });

        $station = [];
        foreach ($updatedStation as $updated) {
            $station[] = [
                'stationID' => $updated['stationID'],
                'stationName' => $updated['stationName'],
                'printerConnectionID' => $updated['printerConnectionID'],
                'printerConnectionName' => $updated['printerConnectionName'],
                'printerTypeID' => $updated['printerTypeID'],
                'printerTypeName' => $updated['printerTypeName'],
                'printerName' => $updated['printerName'],
                'printerPort' => $updated['printerPort'],
                'characterPerLine' => $updated['characterPerLine'],
                'printingModeID' => $updated['printingModeID'],
                'printingModeName' => $updated['printingModeName'],
            ];
        }

        return json_encode([
            'station' => $updatedStation
        ]);
    }

    private static function getLogEditSettings($modelAttr) {
        return json_encode([
            // 'printAllBills' => $modelAttr['printAllBills'],
            // 'printMenu' => $modelAttr['printMenu']
            // 'printPaymentMethod' => $modelAttr['printPaymentMethod'],
            'timeWarning' => $modelAttr['timeWarning'],
            'timeDanger' => $modelAttr['timeDanger']
        ]);
    }

    private static function getLogChangeTerminalSetting($modelAttr) {
        return json_encode([
            "terminalID" => $modelAttr['terminalID'],
            "customerDisplay" => $modelAttr['customerDisplay'],
            "defaultStation" => $modelAttr['defaultStation'],
            "visitPurposeFs" => $modelAttr['visitPurposeFs'],
            "visitPurposeQs" => $modelAttr['visitPurposeQs'],
            "qrCodeMode" => $modelAttr['qrCodeMode'],
            "selfOrderServer" => $modelAttr['selfOrderServer'] != 0 ? 'ON' : 'OFF',
            "directServing" => $modelAttr['directServing'],
            "pendingNotes" => $modelAttr['pendingNotes']
        ]);
    }

    private static function getLogChangeOdsTerminalSetting($modelAttr) {
        return json_encode([
            "terminalID" => $modelAttr['terminalID'],
            "visitPurposeIds" => $modelAttr['visitPurposeIds'],
            "printingStationIds" => $modelAttr['printingStationIds'],
            "stationIds" => $modelAttr['stationIds'],
            "timerDanger" => $modelAttr['timerDanger'],
            "timerWarning" => $modelAttr['timerWarning'],
            "viewMode" => $modelAttr['viewMode'],
            "userName" => $modelAttr['userName']
        ]);
    }

    private static function getLogChangeQdsTerminalSetting($modelAttr) {
        return json_encode([
            "visitPurposeIds" => $modelAttr['visitPurposeIds'],
            "additionalInfo" => $modelAttr['additionalInfo'],
            "showTableInfo" => $modelAttr['showTableInfo'],
            "stationID" => $modelAttr['stationID'],
            "activeStation" => $modelAttr['activeStation'],
            "selectedLanguage" => $modelAttr['selectedLanguage']
        ]);
    }

    private static function getLogChangeKioskTerminalSetting($modelAttr) {
        return json_encode([
            "terminalID" => $modelAttr['terminalID'],
            "stationID" => $modelAttr['kioskStationID'],
            "posExternalPaymentID" => $modelAttr['posExternalPaymentID'],
            "payAtCashier" => $modelAttr['payAtCashier']
        ]);
    }

    private static function getLogEditSelfOrderServer($modelAttr) {
        return json_encode([
            'action' => $modelAttr['action'],
            'dateTime' => $modelAttr['dateTime'],
            'userName' => $modelAttr['userName']
        ]);
    }

    private static function getLogAddPickupOrder($modelAttr) {
        return json_encode([
            'salesNum' => $modelAttr['salesNum'],
            'orderID' => $modelAttr['orderID']
        ]);
    }

    private static function getLogFinishPickupOrder($modelAttr) {
        return json_encode([
            'salesNum' => $modelAttr['salesNum'],
            'orderID' => $modelAttr['orderID'],
            'finishedBy' => $modelAttr['finishedBy']
        ]);
    }

    private static function getLogDeletePickupList($modelAttr) {
        return json_encode([
            'salesNum' => $modelAttr
        ]);
    }

    private static function getLogChangePassword($modelAttr) {
        return json_encode([
            'username' => $modelAttr['userModel']->username,
            'posUserID' => $modelAttr['userModel']->posUserID,
            'fullName' => $modelAttr['userModel']->fullName
        ]);
    }

    private static function getLogSync($modelAttr) {
        return json_encode([
            'syncType' => $modelAttr['syncType'],
            'apiUrl' => $modelAttr['apiUrl'],
            'branchID' => $modelAttr['branchID'],
        ]);
    }

    private static function getLogUpdateSalesDateOut($modelAttr) {
        return json_encode([
            'salesNum' => $modelAttr['salesNum'],
            'value SalesDateOut before update' => $modelAttr['prevSalesDateOut'],
            'value SalesDateOut after update' => $modelAttr['newSalesDateOut'],
            'updateTime' => $modelAttr['updateTime']
        ]);
    }

    private static function getSalesMenu($salesMenu) {
        $salesMenuPackages = [];
        if (isset($salesMenu['packages'])) {
            foreach ($salesMenu['packages'] as $package) {
                if (isset($package['flagHoldOrder']) && $package['flagHoldOrder'] == true) {
                    $package['statusID'] = 46;
                    $package['statusName'] = 'Hold';
                }
                
                if (isset($package['flagFireOrder']) && isset($package['flagFireOrder']) == true) {
                    $package['statusID'] = 13;
                    $package['statusName'] = 'Preparing';
                }
                $salesMenuPackages[] = Logging::returnSalesMenu($package);
            }
        }

        $salesMenuExtras = [];
        if (isset($salesMenu['extras'])) {
            foreach ($salesMenu['extras'] as $extras) {
                if (isset($extras['flagHoldOrder']) && $extras['flagHoldOrder'] == true) {
                    $extras['statusID'] = 46;
                    $extras['statusName'] = 'Hold';
                }
                
                if (isset($extras['flagFireOrder']) && isset($extras['flagFireOrder']) == true) {
                    $extras['statusID'] = 13;
                    $extras['statusName'] = 'Preparing';
                }
                $salesMenuExtras[] = Logging::returnSalesMenu($extras);
            }
        }
        
        if (isset($salesMenu['flagHoldOrder']) && $salesMenu['flagHoldOrder'] == true) {
            $salesMenu['statusID'] = 46;
            $salesMenu['statusName'] = 'Hold';
        }
        
        if (isset($salesMenu['flagFireOrder']) && $salesMenu['flagFireOrder'] == true) {
            $salesMenu['statusID'] = 13;
            $salesMenu['statusName'] = 'Preparing';
        }

        $currentSalesMenu = Logging::returnSalesMenu($salesMenu);
        $currentSalesMenu['packages'] = $salesMenuPackages;
        $currentSalesMenu['extras'] = $salesMenuExtras;

        return $currentSalesMenu;
    }
    
    private static function getLogEditPaymentEdc($modelAttr) {
        $updatedPaymentEdc = Arrays::filter($modelAttr['paymentEdc'],
                function ($paymentEdc) {
                return array_key_exists('updated', $paymentEdc);
            });

        $station = [];
        foreach ($updatedPaymentEdc as $updated) {
            $station[] = [
                'paymentMethodID' => $updated['paymentMethodID'],
                'paymentMethodTypeID' => $updated['paymentMethodTypeID'],
                'paymentMethodName' => $updated['paymentMethodName'],
                'posExternalPaymentID' => $updated['posExternalPaymentID'],
                'posExternalPaymentName' => $updated['posExternalPaymentName'],
                'edcWssUrl' => $updated['edcWssUrl'],
                'edcPort' => $updated['edcPort']
            ];
        }

        return json_encode([
            'paymentEdc' => $updatedPaymentEdc
        ]);
    }

    private static function getLogEditEdcSettings($modelAttr) {
        $updatedData = $modelAttr['paymentEdc'];

        $data = [];
        if ($modelAttr['edcActive'] == 1) {
            $data = [
                'paymentMethodID' => $updatedData['paymentMethodID'],
                'paymentMethodTypeID' => $updatedData['paymentMethodTypeID'],
                'paymentMethodName' => $updatedData['paymentMethodName'],
                'posExternalPaymentID' => $updatedData['posExternalPaymentID'],
                'posExternalPaymentName' => $updatedData['posExternalPaymentName'],
                'edcWssUrl' => $updatedData['edcWssUrl'],
                'edcPort' => $updatedData['edcPort'],
                'edcActive' => $modelAttr['edcActive']
            ];
        } else {
            $data = [
                'edcActive' => $modelAttr['edcActive']
            ];
        }
        return json_encode($data);
    }
    
    private static function getLogChangeVisitPurpose($modelAttr) {
        return json_encode([
            'salesNum' => $modelAttr['salesNum'],
            'visitPurposeID' => $modelAttr['visitPurposeID']
        ]);
    }

    private static function getLogOpenDrawer($modelAttr) {
        return json_encode([
            'stationID' => $modelAttr['stationID'],
            'stationName' => $modelAttr['stationName']
        ]);
    }

    private static function getLogCheckMember($modelAttr) {
        return json_encode([
            'message' => $modelAttr['message'],
            'error' => isset($modelAttr['error']) ? $modelAttr['error'] : $modelAttr['statusCode']
        ]);
    }

    private static function returnSalesMenu($salesMenu) {
        return [
            'ID' => $salesMenu['ID'],
            'localID' => array_key_exists('localID', $salesMenu) ? $salesMenu['localID'] : 0,
            'batchID' => array_key_exists('batchID', $salesMenu) ? $salesMenu['batchID'] : 0,
            'menuRefID' => array_key_exists('menuRefID', $salesMenu) ? $salesMenu['menuRefID'] : 0,
            'menuGroupID' => array_key_exists('menuGroupID', $salesMenu) ? $salesMenu['menuGroupID'] : 0,
            'menuID' => array_key_exists('menuID', $salesMenu) ? $salesMenu['menuID'] : '',
            'menuName' => array_key_exists('menuName', $salesMenu) ? $salesMenu['menuName'] : (isset($salesMenu['menuID']) ? $salesMenu['menuID'] : 0),
            'menuShortName' => array_key_exists('menuShortName', $salesMenu) ? $salesMenu['menuShortName'] : (isset($salesMenu['menuID']) ? $salesMenu['menuID'] : 0), 
            'qty' => $salesMenu['qty'],
            'price' => $salesMenu['price'],
            'discount' => $salesMenu['discount'],
            'otherTax' => $salesMenu['otherTax'],
            'vat' => $salesMenu['vat'],
            'otherTaxOnVat' => $salesMenu['otherTaxOnVat'],
            'total' => $salesMenu['total'],
            'notes' => array_key_exists('notes', $salesMenu) ? $salesMenu['notes'] : '',
            'statusID' => $salesMenu['statusID'],
            'statusName' => array_key_exists('statusName', $salesMenu) ? $salesMenu['statusName'] : $salesMenu['statusID'],
            'promotionDetailID' => array_key_exists('promotionDetailID',
                $salesMenu) ? $salesMenu['promotionDetailID'] : 0,
            'promotionDetailName' => array_key_exists('promotionDetailName',
                $salesMenu) ? $salesMenu['promotionDetailName'] : '',
            'menuPromotionID' => array_key_exists('menuPromotionID', $salesMenu) ? $salesMenu['menuPromotionID'] : 0,
            'cancelNotes' => array_key_exists('cancelNotes', $salesMenu) ? $salesMenu['cancelNotes'] : '',
            'packages' => array_key_exists('packages', $salesMenu) ? $salesMenu['packages'] : [],
            'extras' => array_key_exists('extras', $salesMenu) ? $salesMenu['extras'] : []
        ];
    }

    private static function getLogUpdatePosVersion($modelAttr) {
        return json_encode([
            'date' => $modelAttr['date'],
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate']
        ]);
    }

    private static function getLogFailUpdatePosVersion($modelAttr) {
        return json_encode([
            'date' => $modelAttr['date'],
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate'],
            'errorMessage' => $modelAttr['errorMessage']
        ]);
    }

    private static function getLogUpdateOdsVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate']
        ]);
    }

    private static function getLogFailUpdateOdsVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate'],
            'errorMessage' => $modelAttr['errorMessage']
        ]);
    }

    private static function getLogUpdateKioskVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate']
        ]);
    }

    private static function getLogFailUpdateKioskVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate'],
            'errorMessage' => $modelAttr['errorMessage']
        ]);
    }

    private static function getLogUpdateTablesideVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate']
        ]);
    }

    private static function getLogFailUpdateTablesideVersion($modelAttr) {
        return json_encode([
            'beforeUpdate' => $modelAttr['beforeUpdate'],
            'afterUpdate' => $modelAttr['afterUpdate'],
            'errorMessage' => $modelAttr['errorMessage']
        ]);
    }

    private static function getBeforeUpdateValue($salesHead) 
    {
        return [
            'subTotal' => $salesHead['subtotal'],
            'discountTotal' => $salesHead['discountTotal'],
            'menuDiscountTotal' => $salesHead['menuDiscountTotal'],
            'promotionDiscount' => $salesHead['promotionDiscount'],
            'voucherDiscountTotal' => $salesHead['voucherDiscountTotal'],
            'otherTaxTotal' => $salesHead['otherTaxTotal'],
            'vatTotal' => $salesHead['vatTotal'],
            'otherVatTotal' => $salesHead['otherVatTotal'],
            'grandTotal' => $salesHead['grandTotal'],
            'voucherTotal' => $salesHead['voucherTotal'],
            'roundingTotal' => $salesHead['roundingTotal'],
            'paymentTotal' => $salesHead['paymentTotal'],
            'promotionID' => $salesHead['promotionID']
        ];
    }

    private static function getAfterUpdateValue($salesHead) 
    {
        return [
            'subTotal' => $salesHead['subtotal'],
            'discountTotal' => $salesHead['discountTotal'],
            'menuDiscountTotal' => $salesHead['menuDiscountTotal'],
            'promotionDiscount' => $salesHead['promotionDiscount'],
            'voucherDiscountTotal' => $salesHead['voucherDiscountTotal'],
            'otherTaxTotal' => $salesHead['otherTaxTotal'],
            'vatTotal' => $salesHead['vatTotal'],
            'otherVatTotal' => $salesHead['otherVatTotal'],
            'grandTotal' => $salesHead['grandTotal'],
            'voucherTotal' => $salesHead['voucherTotal'],
            'roundingTotal' => $salesHead['roundingTotal'],
            'paymentTotal' => $salesHead['paymentTotal'],
            'promotionID' => $salesHead['promotionID']
        ];
    }


    private static function getMemberAndEmployeeData($modelAttr) {
        $employeeDetail = [];
        if (isset($modelAttr['employeeName']) && !empty($modelAttr['employeeName'])) {
            $employeeDetail = [
                'employeeName' => $modelAttr['employeeName'],
                'employeeCode' => '********',
                'employeeType' => $modelAttr['employeeType']
            ];
        }

        $memberDetail = [];
        if (isset($modelAttr['externalMemberName']) && !empty($modelAttr['externalMemberName'])) {
            $memberDetail = [
                'memberName' => $modelAttr['externalMemberName'],
                'memberCode' => '********',
                'memberType' => $modelAttr['externalMembershipTypeID']
            ];
        } elseif (
            isset($modelAttr['memberID']) && !empty($modelAttr['memberID']) ||
            isset($modelAttr['internalMemberName']) && !empty($modelAttr['internalMemberName'])    
        ) {
            $memberDetail = [
                'memberID' => $modelAttr['memberID'],
                'memberCode' => '********',
                'memberType' => 'Internal Member'
            ];
        }

        return [
            'employeeInfo' => $employeeDetail,
            'memberInfo' => $memberDetail
        ];
    }

    private static function getLogFailGenerateParkingVoucher($modelAttr) 
    {
        return json_encode([
            'billNum' => $modelAttr['billNum'],
            'subtotal' => $modelAttr['subtotal'],
            'response' => $modelAttr['response']
        ]);
    }

    private static function getLogUvlLog($modelAttr) 
    {
        return json_encode($modelAttr);
    }

    private static function getLogPluxee($modelAttr) 
    {
        return json_encode($modelAttr);
    }

    private static function getLogUv($modelAttr) 
    {
        return json_encode($modelAttr);
    }
    
    private static function getLogStartDayOTP($modelAttr)
    {
        return json_encode([
            'otp' => $modelAttr['otp'],
            'startDayTime' => $modelAttr['startDayTime'],
            'user' => $modelAttr['user']
        ]);
    }

    private static function getLogEsoQueueProcess($modelAttr)
    {
        return json_encode([
            'orderID' => $modelAttr['orderID'],
            'message' => $modelAttr['message']
        ]);
    }
    private static function getLogEsoFailedSaveOrder($modelAttr)
    {
        return json_encode([
            'error' => $modelAttr
        ]);
    }

    private static function insertLog($refNum, $eventSubject, $eventDescription, $lastEditedBy) {
        $branchID = Setting::getCurrentBranch();
        $nonSyncTransactions = [
            self::SYNC_FETCH,
            self::SYNC_PUSH,
            self::OPEN_PRINTER,
            self::BOOK_TABLE,
            self::SAVE_ORDER,
            self::SAVE_PAYMENT,
            self::CREATE_TAKE_AWAY,
            self::PRINT_BILL,
            self::PRINT_CHECKER,
            self::PRINT_KITCHEN,
            self::ADD_MEMBER_EZO,
            self::REMOVE_MEMBER_EZO,
            self::ADD_SALES_CHILD,
            self::UPDATE_MENU_SPLIT,
            self::DELETE_MENU_SPLIT,
            self::DELETE_SALES_CHILD,
            self::LOG_CHECK_MEMBER
        ];

        $eventModel = new BranchEvent();
        $eventModel->branchID = $branchID;
        $eventModel->eventDate = new Expression('NOW()');
        $eventModel->refNum = strval($refNum);
        $eventModel->eventSubject = Logging::getEventSubject($eventSubject);
        $eventModel->eventDescription = substr($eventDescription, 0, 65535);
        $eventModel->createdBy = Logging::getCreatedBy($eventSubject, $refNum, $eventDescription, $lastEditedBy);
        $eventModel->syncDate = !in_array($eventSubject, $nonSyncTransactions) ? null : new Expression('NOW()');
        if (!$eventModel->save()) {
            Yii::warning($eventModel->errors);
        }
    }

    private static function getCreatedBy($eventSubject, $refNum, $eventDescription = null, $lastEditedBy) {
        $createdBy = (Yii::$app instanceof Application) ? 'Console App' : (!Yii::$app->user->isGuest ? Yii::$app->user->identity->username : strval($refNum));
        if ($eventSubject === Logging::SAVE_ORDER_ESO_FS ||
            $eventSubject === Logging::SAVE_ORDER_ESO_QS ||
            $eventSubject === Logging::SAVE_PAYMENT_ESO ||
            $eventSubject === Logging::VOID_SALES_ESO ||
            $eventSubject === Logging::PRINT_CHECKER_SELFORDER ||
            $eventSubject === Logging::OPEN_PRINTER_SELFORDER ||
            $eventSubject === Logging::FAIL_OPEN_PRINTER_SELFORDER ||
            $eventSubject === Logging::API_SAVE_MENU_ESO_FS ||
            $eventSubject === Logging::FINISH_PICKUP_ORDER ||
            $eventSubject === Logging::ADD_PICKUP_ORDER ||
            $eventSubject === Logging::FAIL_GENERATE_PARKING_VOUCHER_SELFORDER ||
            $eventSubject === Logging::ESO_FS_PROCESS_QUEUE ||
            $eventSubject === Logging::ESO_PROCESS_QUEUE) {
            $createdBy = 'ESBORDER';
        } else if (self::getEventUpdates($eventSubject)) {
            $createdBy = 'SYSTEM';
        }
        if ($eventSubject === Logging::ODS_TERMINAL_SETTING_CHANGE) {
            $createdBy = 'ODS';
            $decoded = json_decode($eventDescription);
            if ($decoded && $decoded->userName) {
                $createdBy = $decoded->userName;
            }
        }
        if ($eventSubject === Logging::QDS_TERMINAL_SETTING_CHANGE) {
            $createdBy = 'QDS';
            $decoded = json_decode($eventDescription);
        }
        if ($eventSubject === Logging::SAVE_PAYMENT_KIOSK ||
            $eventSubject === Logging::SAVE_ORDER_KIOSK ||
            $eventSubject === Logging::FAIL_GENERATE_PARKING_VOUCHER_KIOSK
        ) {
            $createdBy = 'KIOSK';
        }
        if ($eventSubject === Logging::KIOSK_TERMINAL_SETTING_CHANGE ||
            $eventSubject === Logging::EDIT_EDC_SETTING_KIOSK) {
                $createdBy = $lastEditedBy ? $lastEditedBy :  'KIOSK';
        }
        if ($eventSubject === Logging::SAVE_ORDER_TABLESIDE){
            $createdBy = 'TABLESIDE';
        }
        return $createdBy;
    }

    private static function getEventSubject($eventSubject) {
        if($eventSubject === Logging::PRINT_CHECKER_SELFORDER) {
            $eventSubject = Logging::PRINT_CHECKER;
        }
        if($eventSubject === Logging::OPEN_PRINTER_SELFORDER) {
            $eventSubject = Logging::OPEN_PRINTER;
        }
        if($eventSubject === Logging::FAIL_OPEN_PRINTER_SELFORDER) {
            $eventSubject = Logging::FAIL_OPEN_PRINTER;
        }
        if($eventSubject === Logging::FAIL_GENERATE_PARKING_VOUCHER_SELFORDER || 
        $eventSubject === Logging::FAIL_GENERATE_PARKING_VOUCHER_KIOSK) {
            $eventSubject = Logging::FAIL_GENERATE_PARKING_VOUCHER;
        }

        return $eventSubject;
    }

    private static function getEventUpdates($eventSubject) {
        return (
            $eventSubject === Logging::UPDATE_POS_VERSION ||
            $eventSubject === Logging::FAIL_UPDATE_POS_VERSION ||
            $eventSubject === Logging::FAIL_DOWNLOAD_POS_UPDATE ||
            $eventSubject === Logging::UPDATE_ODS_VERSION ||
            $eventSubject === Logging::FAIL_UPDATE_ODS_VERSION ||
            $eventSubject === Logging::UPDATE_QDS_VERSION ||
            $eventSubject === Logging::FAIL_UPDATE_QDS_VERSION ||
            $eventSubject === Logging::UPDATE_KIOSK_VERSION ||
            $eventSubject === Logging::FAIL_UPDATE_KIOSK_VERSION ||
            $eventSubject === Logging::UPDATE_TABLESIDE_VERSION ||
            $eventSubject === Logging::FAIL_UPDATE_TABLESIDE_VERSION ||
            $eventSubject === Logging::START_DAY_OTP ||
            $eventSubject === Logging::CALL_API_REGISTER_MEMBER ||
            $eventSubject === Logging::LOGGIN_SYNC_MENU_RTS 
        );
    }
}
