<?php
use app\models\Branch;
use app\models\BranchEvent;
use app\models\BranchMenu;
use app\models\CancelReason;
use app\models\CashMethod;
use app\models\Day;
use app\models\DepositWithdrawalDetail;
use app\models\DepositWithdrawalHead;
use app\models\Gender;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\Menu;
use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\MenuExtra;
use app\models\MenuGroup;
use app\models\MenuPackage;
use app\models\MenuPromotion;
use app\models\MenuPromotionDay;
use app\models\MenuPromotionHead;
use app\models\Notes;
use app\models\NotesCategory;
use app\models\PaymentMethod;
use app\models\PaymentMethodType;
use app\models\PosAccessControl;
use app\models\PosFilterAccess;
use app\models\PosUser;
use app\models\PosUserAccess;
use app\models\PosUserRole;
use app\models\PosVersion;
use app\models\PrinterConnection;
use app\models\PrinterType;
use app\models\PromotionCategory;
use app\models\PromotionDay;
use app\models\PromotionDetail;
use app\models\PromotionHead;
use app\models\PromotionType;
use app\models\SalesDepositWithdrawal;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPayment;
use app\models\SalesVoucher;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\ShiftLogDetail;
use app\models\Station;
use app\models\Status;
use app\models\Table;
use app\models\TableSection;
use app\models\TableType;
use app\models\TableUsage;
use app\models\TransNumber;
use app\models\VisitPurpose;
use app\models\Voucher;
use yii\db\Migration;

/**
 * Class m191019_045741_init_db_v_2_1
 */
class m191019_045741_init_db_v_2_1 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Day::tableName(), true) === null) {
            $this->createTable(Day::tableName(),
                [
                    'dayID' => $this->primaryKey(),
                    'dayName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(Gender::tableName(), true) === null) {
            $this->createTable(Gender::tableName(),
                [
                    'genderID' => $this->primaryKey(),
                    'genderName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema('lk_membertype', true) === null) {
            $this->createTable('lk_membertype',
                [
                    'memberTypeID' => $this->primaryKey(),
                    'memberTypeName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(PaymentMethodType::tableName(), true) === null) {
            $this->createTable(PaymentMethodType::tableName(),
                [
                    'paymentMethodTypeID' => $this->primaryKey(),
                    'paymentMethodTypeName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(PosAccessControl::tableName(), true) === null) {
            $this->createTable(PosAccessControl::tableName(),
                [
                    'posAccessID' => $this->string(10)->notNull()->append('PRIMARY KEY'),
                    'description' => $this->string(50)->notNull(),
                    'node' => $this->string(50)->notNull(),
                    'icon' => $this->string(50)->notNull(),
                    'orderID' => $this->integer(),
            ]);
        }

        if ($this->db->getTableSchema(PosFilterAccess::tableName(), true) === null) {
            $this->createTable(PosFilterAccess::tableName(),
                [
                    'posAccessID' => $this->string(10)->notNull(),
                    'filterAccessID' => $this->string(10)->notNull(),
                    'description' => $this->string(50)->notNull(),
                    'subNodes' => $this->string(500)->notNull(),
                    'orderID' => $this->integer(),
            ]);

            $this->addPrimaryKey('PRIMARYKEY', PosFilterAccess::tableName(),
                ['posAccessID', 'filterAccessID']);
        }

        if ($this->db->getTableSchema(PrinterConnection::tableName(), true) === null) {
            $this->createTable(PrinterConnection::tableName(),
                [
                    'printerConnectionID' => $this->primaryKey(),
                    'printerConnectionName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(PrinterType::tableName(), true) === null) {
            $this->createTable(PrinterType::tableName(),
                [
                    'printerTypeID' => $this->primaryKey(),
                    'printerTypeName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(PromotionType::tableName(), true) === null) {
            $this->createTable(PromotionType::tableName(),
                [
                    'promotionTypeID' => $this->primaryKey(),
                    'promotionTypeDesc' => $this->string(50)->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(Status::tableName(), true) === null) {
            $this->createTable(Status::tableName(),
                [
                    'statusID' => $this->primaryKey(),
                    'statusName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(TableType::tablename(), true) === null) {
            $this->createTable(TableType::tablename(),
                [
                    'tableTypeID' => $this->primaryKey(),
                    'tableTypeName' => $this->string(50),
            ]);
        }

        if ($this->db->getTableSchema(Branch::tableName(), true) === null) {
            $this->createTable(Branch::tableName(),
                [
                    'branchID' => $this->primaryKey(),
                    'branchTypeID' => $this->integer()->notNull(),
                    'branchCode' => $this->string(20),
                    'branchName' => $this->string(50)->notNull(),
                    'address' => $this->string(200),
                    'phone' => $this->string(20),
                    'printingHeader' => $this->string(500)->notNull(),
                    'printingFooter' => $this->string(500)->notNull(),
                    'additionalTaxName' => $this->string(100)->notNull(),
                    'additionalTaxValue' => $this->decimal(18, 2)->notNull(),
                    'flagOtherTaxVat' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(BranchMenu::tableName(), true) === null) {
            $this->createTable(BranchMenu::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'branchID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'checkerStationID' => $this->integer()->notNull(),
                    'stationID' => $this->integer()->notNull(),
                    'qty' => $this->integer(),
                    'flagSoldOut' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(CancelReason::tableName(), true) === null) {
            $this->createTable(CancelReason::tableName(),
                [
                    'cancelReasonID' => $this->primaryKey(),
                    'cancelReasonDesc' => $this->string(50)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(CashMethod::tableName(), true) === null) {
            $this->createTable(CashMethod::tableName(),
                [
                    'cashMethodID' => $this->primaryKey(),
                    'cashMethodAmount' => $this->decimal(20, 4)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Member::tableName(), true) === null) {
            $this->createTable(Member::tableName(),
                [
                    'memberID' => $this->primaryKey(),
                    'memberName' => $this->string(100)->notNull(),
                    'memberTypeID' => $this->integer()->notNull(),
                    'memberCode' => $this->string(20)->notNull(),
                    'genderID' => $this->integer()->notNull(),
                    'memberBirthDate' => $this->date(),
                    'memberAddress' => $this->string(200),
                    'memberPhone' => $this->string(20),
                    'memberEmail' => $this->string(50),
                    'flagActive' => $this->boolean()->notNull()->defaultValue('0'),
                    'createdBy' => $this->string(100),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Menu::tableName(), true) === null) {
            $this->createTable(Menu::tableName(),
                [
                    'menuID' => $this->primaryKey(),
                    'menuCategoryDetailID' => $this->integer()->notNull(),
                    'bomID' => $this->integer(),
                    'menuName' => $this->string(100)->notNull(),
                    'menuShortName' => $this->string(50)->notNull(),
                    'estimatedCost' => $this->decimal(20, 4)->notNull(),
                    'price' => $this->decimal(20, 4)->notNull(),
                    'flagTax' => $this->tinyInteger(1)->notNull(),
                    'flagOtherTax' => $this->tinyInteger(1)->notNull(),
                    'zeroValueText' => $this->string(12)->notNull(),
                    'flagCustomerPrint' => $this->tinyInteger(1)->notNull(),
                    'salesCoaNo' => $this->string(20)->notNull(),
                    'cogsCoaNo' => $this->string(20)->notNull(),
                    'discountCoaNo' => $this->string(20)->notNull(),
                    'notes' => $this->string(100),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_menu_menuName', Menu::tableName(),
                'menuName');
            $this->createIndex('idx_menu_menuCategoryDetailID',
                Menu::tableName(), 'menuCategoryDetailID');
        }

        if ($this->db->getTableSchema(MenuCategory::tableName(), true) === null) {
            $this->createTable(MenuCategory::tableName(),
                [
                    'menuCategoryID' => $this->primaryKey(),
                    'menuCategoryDesc' => $this->string(50)->notNull(),
                    'salesCoaNo' => $this->string(20)->notNull(),
                    'cogsCoaNo' => $this->string(20)->notNull(),
                    'discountCoaNo' => $this->string(20)->notNull(),
                    'notes' => $this->string(100),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true) === null) {
            $this->createTable(MenuCategoryDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'menuCategoryID' => $this->integer()->notNull(),
                    'menuCategoryDetailDesc' => $this->string(100)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
            ]);

            $this->createIndex('idx_menucategorydetail_menuCategoryID',
                MenuCategoryDetail::tableName(), 'menuCategoryID');
        }

        if ($this->db->getTableSchema(MenuExtra::tableName(), true) === null) {
            $this->createTable(MenuExtra::tableName(),
                [
                    'menuExtraID' => $this->primaryKey(),
                    'menuID' => $this->integer()->notNull(),
                    'bomID' => $this->integer(),
                    'menuGroupID' => $this->integer()->notNull(),
                    'menuExtraName' => $this->string(100)->notNull(),
                    'menuExtraShortName' => $this->string(30)->notNull(),
                    'price' => $this->decimal(20, 4)->notNull(),
                    'flagMandatory' => $this->tinyInteger(1)->notNull(),
                    'minExtraQty' => $this->decimal(20, 4)->notNull(),
                    'maxExtraQty' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_menuextra_menuGroupID',
                MenuExtra::tableName(), 'menuGroupID');
            $this->createIndex('idx_menuextra_menuID', MenuExtra::tableName(),
                'menuID');
        }

        if ($this->db->getTableSchema(MenuGroup::tableName(), true) === null) {
            $this->createTable(MenuGroup::tableName(),
                [
                    'menuGroupID' => $this->primaryKey(),
                    'menuID' => $this->integer()->notNull(),
                    'menuGroup' => $this->string(50)->notNull(),
                    'minQty' => $this->decimal(20, 4)->notNull(),
                    'maxQty' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
            ]);

            $this->createIndex('idx_menugroup_menuID', MenuGroup::tableName(),
                'menuID');
        }

        if ($this->db->getTableSchema(MenuPackage::tableName(), true) === null) {
            $this->createTable(MenuPackage::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'menuGroupID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'price' => $this->decimal(20, 4)->notNull(),
                    'flagDefault' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_menupackage_menuGroupID',
                MenuPackage::tableName(), 'menuGroupID');
            $this->createIndex('idx_menupackage_menuID',
                MenuPackage::tableName(), 'menuID');
        }

        if ($this->db->getTableSchema(MenuPromotion::tableName(), true) === null) {
            $this->createTable(MenuPromotion::tableName(),
                [
                    'menuPromotionID' => $this->primaryKey(),
                    'headID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'promotionPrice' => $this->decimal(20, 4)->notNull(),
                    'flagActive' => $this->boolean()->notNull()->defaultValue('0'),
            ]);
        }

        if ($this->db->getTableSchema(MenuPromotionDay::tableName(), true) === null) {
            $this->createTable(MenuPromotionDay::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'headID' => $this->integer()->notNull(),
                    'dayID' => $this->integer()->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(MenuPromotionHead::tableName(), true) === null) {
            $this->createTable(MenuPromotionHead::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'startDate' => $this->dateTime()->notNull(),
                    'endDate' => $this->dateTime()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'notes' => $this->string(100)->notNull(),
                    'flagActive' => $this->boolean()->notNull()->defaultValue('0'),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Notes::tableName(), true) === null) {
            $this->createTable(Notes::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'notesCategoryID' => $this->integer()->notNull(),
                    'notes' => $this->string(100)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(NotesCategory::tableName(), true) === null) {
            $this->createTable(NotesCategory::tableName(),
                [
                    'notesCategoryID' => $this->primaryKey(),
                    'notesCategoryDesc' => $this->string(50)->notNull(),
                    'notes' => $this->string(100),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(PaymentMethod::tableName(), true) === null) {
            $this->createTable(PaymentMethod::tableName(),
                [
                    'paymentMethodID' => $this->primaryKey(),
                    'paymentMethodTypeID' => $this->integer()->notNull(),
                    'paymentMethodName' => $this->string(50)->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'coaNo' => $this->string(20)->notNull(),
                    'flagAuthorization' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_paymentmethod_paymentMethodTypeID',
                PaymentMethod::tableName(), 'paymentMethodTypeID');
        }

        if ($this->db->getTableSchema(PosUser::tableName(), true) === null) {
            $this->createTable(PosUser::tableName(),
                [
                    'username' => $this->string(100)->notNull()->append('PRIMARY KEY'),
                    'fullName' => $this->string(200)->notNull(),
                    'password' => $this->string()->notNull()->defaultValue(''),
                    'salt' => $this->string(45)->notNull(),
                    'posUserRoleID' => $this->integer()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'posAuthKey' => $this->string(50),
                    'posUserID' => $this->string(50)->notNull(),
                    'posPassword' => $this->string(50)->notNull(),
                    'posSalt' => $this->string(45)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(PosUserAccess::tableName(), true) === null) {
            $this->createTable(PosUserAccess::tableName(),
                [
                    'posUserRoleID' => $this->integer()->notNull(),
                    'filterAccessID' => $this->string(10)->notNull(),
                    'hasAccess' => $this->tinyInteger(1)->notNull(),
            ]);

            $this->addPrimaryKey('PRIMARYKEY', PosUserAccess::tableName(),
                ['posUserRoleID', 'filterAccessID']);
        }

        if ($this->db->getTableSchema(PosUserRole::tableName(), true) === null) {
            $this->createTable(PosUserRole::tableName(),
                [
                    'posUserRoleID' => $this->primaryKey(),
                    'posRoleDesc' => $this->string(100)->notNull()->defaultValue(''),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(PosVersion::tableName(), true) === null) {
            $this->createTable(PosVersion::tableName(),
                [
                    'posVersionID' => $this->primaryKey(),
                    'versionName' => $this->string(45)->notNull(),
                    'downloadUrl' => $this->string(300)->notNull(),
                    'downloadMd5' => $this->string(45)->notNull(),
                    'query' => $this->text(),
                    'deletedFiles' => $this->text(),
            ]);
        }

        if ($this->db->getTableSchema(PromotionCategory::tableName(), true) === null) {
            $this->createTable(PromotionCategory::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer()->notNull(),
                    'menuCategoryID' => $this->integer()->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(PromotionDay::tableName(), true) === null) {
            $this->createTable(PromotionDay::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer()->notNull(),
                    'dayID' => $this->integer()->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(PromotionDetail::tableName(), true) === null) {
            $this->createTable(PromotionDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'promotionID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'qty' => $this->decimal(20, 4)->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(PromotionHead::tableName(), true) === null) {
            $this->createTable(PromotionHead::tableName(),
                [
                    'promotionID' => $this->primaryKey(),
                    'startDate' => $this->dateTime()->notNull(),
                    'endDate' => $this->dateTime()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'promotionTypeID' => $this->integer()->notNull(),
                    'minSalesPrice' => $this->decimal(20, 4)->notNull(),
                    'flagMultiplication' => $this->tinyInteger(1)->notNull(),
                    'maxSalesPrice' => $this->decimal(20, 4)->notNull(),
                    'paymentMethodTypeID' => $this->integer(),
                    'discount' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Setting::tableName(), true) === null) {
            $this->createTable(Setting::tableName(),
                [
                    'key1' => $this->string(100)->notNull(),
                    'key2' => $this->string(100)->notNull(),
                    'value1' => $this->string(500),
                    'value2' => $this->string(500),
            ]);

            $this->addPrimaryKey('PRIMARYKEY', Setting::tableName(),
                ['key1', 'key2']);
        }

        if ($this->db->getTableSchema(Station::tableName(), true) === null) {
            $this->createTable(Station::tableName(),
                [
                    'stationID' => $this->primaryKey(),
                    'stationName' => $this->string(50)->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'printerConnectionID' => $this->integer()->notNull(),
                    'printerTypeID' => $this->integer()->notNull()->defaultValue('1'),
                    'printerName' => $this->string(100),
                    'printerPort' => $this->string(50),
                    'characterPerLine' => $this->integer(),
                    'flagSingleMenuPrint' => $this->tinyInteger(1)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Table::tableName(), true) === null) {
            $this->createTable(Table::tableName(),
                [
                    'tableID' => $this->primaryKey(),
                    'tableTypeID' => $this->integer()->notNull(),
                    'tableName' => $this->string(50)->notNull(),
                    'tableSeat' => $this->string(50)->notNull(),
                    'tableSectionID' => $this->integer()->notNull(),
                    'tableMinimumBilling' => $this->decimal(20, 4)->notNull(),
                    'tableChargeFee' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100),
                    'posX' => $this->decimal(20, 4)->notNull(),
                    'posY' => $this->decimal(20, 4)->notNull(),
                    'widthRes' => $this->integer()->notNull(),
                    'heightRes' => $this->integer()->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(TableSection::tableName(), true) === null) {
            $this->createTable(TableSection::tableName(),
                [
                    'tableSectionID' => $this->primaryKey(),
                    'tableSectionName' => $this->string(50)->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(TransNumber::tableName(), true) === null) {
            $this->createTable(TransNumber::tableName(),
                [
                    'transNumberID' => $this->primaryKey(),
                    'transType' => $this->string(50)->notNull(),
                    'transAbbreviation' => $this->string(3)->notNull(),
            ]);
        }

        if ($this->db->getTableSchema(VisitPurpose::tableName(), true) === null) {
            $this->createTable(VisitPurpose::tableName(),
                [
                    'visitPurposeID' => $this->primaryKey(),
                    'visitPurposeName' => $this->string(50)->notNull(),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(Voucher::tableName(), true) === null) {
            $this->createTable(Voucher::tableName(),
                [
                    'voucherID' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'voucherSortID' => $this->string(10)->notNull(),
                    'voucherTypeID' => $this->integer()->notNull(),
                    'voucherLength' => $this->integer()->notNull(),
                    'voucherStartDate' => $this->date(),
                    'voucherEndDate' => $this->date(),
                    'createdBranchID' => $this->integer()->notNull(),
                    'usedBranchID' => $this->integer(),
                    'usedDate' => $this->dateTime(),
                    'salesNum' => $this->string(20),
                    'minimumSalesAmount' => $this->decimal(20, 4)->notNull(),
                    'voucherAmount' => $this->decimal(20, 4)->notNull(),
                    'voucherPercentage' => $this->decimal(20, 4)->notNull(),
                    'voucherSalesPrice' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100),
                    'flagActive' => $this->tinyInteger(1)->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'createdDate' => $this->dateTime()->notNull(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(BranchEvent::tableName(), true) === null) {
            $this->createTable(BranchEvent::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'branchID' => $this->integer()->notNull(),
                    'eventDate' => $this->dateTime()->notNull(),
                    'refNum' => $this->string(20)->notNull(),
                    'eventSubject' => $this->string(50)->notNull(),
                    'eventDescription' => $this->text()->notNull(),
                    'createdBy' => $this->string(100)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(DepositWithdrawalDetail::tableName(), true) === null) {
            $this->createTable(DepositWithdrawalDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'depositWithdrawalNum' => $this->string(20)->notNull(),
                    'memberDepositNum' => $this->string(20)->notNull(),
                    'withdrawalTotal' => $this->decimal(20, 4)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(DepositWithdrawalHead::tableName(), true) === null) {
            $this->createTable(DepositWithdrawalHead::tableName(),
                [
                    'depositWithdrawalNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'depositWithdrawalDate' => $this->date()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'memberID' => $this->integer()->notNull(),
                    'currencyID' => $this->integer()->notNull(),
                    'rate' => $this->decimal(20, 4)->notNull(),
                    'paymentMethodID' => $this->integer()->notNull(),
                    'withdrawalTotal' => $this->decimal(20, 4)->notNull(),
                    'additionalInfo' => $this->string(100),
                    'statusID' => $this->tinyInteger(2)->notNull(),
                    'createdBy' => $this->string(100),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'authorizedBy' => $this->string(100),
                    'authorizedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(MemberDeposit::tableName(), true) === null) {
            $this->createTable(MemberDeposit::tableName(),
                [
                    'memberDepositNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'memberDepositDate' => $this->date()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'memberID' => $this->integer()->notNull(),
                    'paymentMethodID' => $this->integer()->notNull(),
                    'currencyID' => $this->integer()->notNull(),
                    'rate' => $this->decimal(20, 4)->notNull(),
                    'depositTotal' => $this->decimal(20, 4)->notNull(),
                    'usedDepositTotal' => $this->decimal(20, 4)->notNull(),
                    'additionalInfo' => $this->string(100),
                    'statusID' => $this->tinyInteger(2)->notNull(),
                    'createdBy' => $this->string(100),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'authorizedBy' => $this->string(100),
                    'authorizedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(SalesDepositWithdrawal::tableName(), true) === null) {
            $this->createTable(SalesDepositWithdrawal::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(20)->notNull(),
                    'memberDepositNum' => $this->string(20)->notNull(),
                    'paymentTotal' => $this->decimal(20, 4)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true) === null) {
            $this->createTable(SalesHead::tableName(),
                [
                    'salesNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'salesDate' => $this->date()->notNull(),
                    'salesDateIn' => $this->dateTime()->notNull(),
                    'salesDateOut' => $this->dateTime(),
                    'branchID' => $this->integer()->notNull(),
                    'memberID' => $this->integer(),
                    'tableID' => $this->integer()->notNull(),
                    'visitPurposeID' => $this->integer()->notNull(),
                    'paxTotal' => $this->integer()->notNull(),
                    'subtotal' => $this->decimal(20, 4)->notNull(),
                    'discountTotal' => $this->decimal(20, 4)->notNull(),
                    'menuDiscountTotal' => $this->decimal(20, 4)->notNull(),
                    'promotionDiscount' => $this->decimal(20, 4)->notNull(),
                    'otherTaxTotal' => $this->decimal(20, 4)->notNull(),
                    'vatTotal' => $this->decimal(20, 4)->notNull(),
                    'grandTotal' => $this->decimal(20, 4)->notNull(),
                    'voucherTotal' => $this->decimal(20, 4)->notNull(),
                    'roundingTotal' => $this->decimal(20, 4),
                    'paymentTotal' => $this->decimal(20, 4)->notNull(),
                    'billingPrintCount' => $this->integer()->notNull(),
                    'paymentPrintCount' => $this->integer()->notNull(),
                    'additionalInfo' => $this->string(200),
                    'promotionID' => $this->integer(),
                    'statusID' => $this->tinyInteger(2)->notNull(),
                    'createdBy' => $this->string(100),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_saleshead_statusID', SalesHead::tableName(),
                'statusID');
            $this->createIndex('idx_saleshead_salesDateIn',
                SalesHead::tableName(), 'salesDateIn');
            $this->createIndex('idx_saleshead_salesDateOut',
                SalesHead::tableName(), 'salesDateOut');
        }

        if ($this->db->getTableSchema(SalesLink::tableName(), true) === null) {
            $this->createTable(SalesLink::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(50)->notNull(),
                    'linkSalesNum' => $this->string(50)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_saleslink_linkSalesNum',
                SalesLink::tableName(), 'linkSalesNum');
        }

        if ($this->db->getTableSchema(SalesMenu::tableName(), true) === null) {
            $this->createTable(SalesMenu::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(50)->notNull(),
                    'batchID' => $this->integer()->notNull(),
                    'menuRefID' => $this->integer()->notNull(),
                    'menuGroupID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'qty' => $this->decimal(20, 4)->notNull(),
                    'price' => $this->decimal(20, 4)->notNull(),
                    'discount' => $this->decimal(20, 4)->notNull(),
                    'otherTax' => $this->decimal(20, 4)->notNull(),
                    'vat' => $this->decimal(20, 4)->notNull(),
                    'otherTaxOnVat' => $this->tinyInteger(1)->notNull(),
                    'total' => $this->decimal(20, 4)->notNull(),
                    'notes' => $this->string(100),
                    'statusID' => $this->tinyInteger(2)->notNull(),
                    'promotionDetailID' => $this->integer()->notNull(),
                    'menuPromotionID' => $this->integer()->notNull(),
                    'cancelNotes' => $this->string(100),
                    'createdBy' => $this->string(100),
                    'createdDate' => $this->dateTime(),
                    'editedBy' => $this->string(100),
                    'editedDate' => $this->dateTime(),
                    'syncDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_salesmenu_menuID', SalesMenu::tableName(),
                'menuID');
            $this->createIndex('idx_salesmenu_salesNum', SalesMenu::tableName(),
                'salesNum');
            $this->createIndex('idx_salesmenu_statusID', SalesMenu::tableName(),
                'statusID');
            $this->createIndex('idx_salesmenu_menuGroupID',
                SalesMenu::tableName(), 'menuGroupID');
            $this->createIndex('idx_salesmenu_menuRefID',
                SalesMenu::tableName(), 'menuRefID');
        }

        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true) === null) {
            $this->createTable(SalesMenuExtra::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(20)->notNull(),
                    'menuDetailID' => $this->integer()->notNull(),
                    'menuExtraID' => $this->integer()->notNull(),
                    'qty' => $this->decimal(20, 4)->notNull(),
                    'price' => $this->decimal(20, 4)->notNull(),
                    'discount' => $this->decimal(20, 4)->notNull(),
                    'otherTax' => $this->decimal(20, 4)->notNull(),
                    'vat' => $this->decimal(20, 4)->notNull(),
                    'otherTaxOnVat' => $this->tinyInteger(1)->notNull(),
                    'total' => $this->decimal(20, 4)->notNull(),
                    'statusID' => $this->tinyInteger(2)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_salesmenuextra_menuExtraID',
                SalesMenuExtra::tableName(), 'menuExtraID');
            $this->createIndex('idx_salesmenuextra_menuDetailID',
                SalesMenuExtra::tableName(), 'menuDetailID');
        }

        if ($this->db->getTableSchema(SalesMergeTable::tableName(), true) === null) {
            $this->createTable(SalesMergeTable::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(50)->notNull(),
                    'tableID' => $this->integer()->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(SalesPayment::tableName(), true) === null) {
            $this->createTable(SalesPayment::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(50)->notNull(),
                    'paymentMethodID' => $this->integer()->notNull(),
                    'voucherCode' => $this->string(20)->notNull(),
                    'notes' => $this->string(100)->notNull(),
                    'cardNumber' => $this->string(20)->notNull(),
                    'bankName' => $this->string(100)->notNull(),
                    'accountName' => $this->string(50)->notNull(),
                    'verificationCode' => $this->string(100)->notNull(),
                    'coaNo' => $this->string(20)->notNull(),
                    'paymentAmount' => $this->decimal(20, 4)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_salespayment_salesNum',
                SalesPayment::tableName(), 'salesNum');
            $this->createIndex('idx_salespayment_paymentMethodID',
                SalesPayment::tableName(), 'paymentMethodID');
        }

        if ($this->db->getTableSchema(SalesVoucher::tableName(), true) === null) {
            $this->createTable(SalesVoucher::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(),
                    'salesNum' => $this->string(50)->notNull(),
                    'voucherID' => $this->string(20)->notNull(),
                    'voucherSalesPrice' => $this->decimal(20, 4)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(ShiftLog::tableName(), true) === null) {
            $this->createTable(ShiftLog::tableName(),
                [
                    'shiftID' => $this->primaryKey(),
                    'branchID' => $this->integer()->notNull(),
                    'shiftInTime' => $this->dateTime()->notNull(),
                    'shiftOutTime' => $this->dateTime(),
                    'shiftInTotal' => $this->decimal(20, 4)->notNull(),
                    'systemCashReceivedTotal' => $this->decimal(20, 4),
                    'shiftOutTotal' => $this->decimal(20, 4),
                    'shiftInUsername' => $this->string(50)->notNull(),
                    'shiftOutUsername' => $this->string(50),
                    'shiftOutNotes' => $this->string(200),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(ShiftLogDetail::tableName(), true) === null) {
            $this->createTable(ShiftLogDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'shiftID' => $this->integer()->notNull(),
                    'shiftTime' => $this->dateTime()->notNull(),
                    'shiftUsername' => $this->string(50)->notNull(),
                    'syncDate' => $this->dateTime(),
            ]);
        }

        if ($this->db->getTableSchema(TableUsage::tableName(), true) === null) {
            $this->createTable(TableUsage::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'referenceID' => $this->string(20)->notNull(),
                    'expiredTime' => $this->dateTime()->notNull(),
                    'username' => $this->string(100)->notNull(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        echo 'm191019_045741_init_db_v_2_1 cannot be reverted.\n';

        return false;
    }

}
