<?php
namespace app\models;

use app\components\AppHelper;
use app\models\forms\Aevitas;
use app\models\forms\ApplyBillPromo;
use app\models\forms\ApplyOrderPromo;
use app\models\forms\CalculateTotal;
use app\models\forms\MemberDepositWithdrawalOnline;
use app\models\forms\UpdateOrder;
use app\models\Setting;
use DateTime;
use Underscore\Types\Arrays;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\Json;

/**
 * This is the model class for table "tr_saleshead".
 *
 * @property string $salesNum
 * @property string $billNum
 * @property string $salesDate
 * @property string $salesDateIn
 * @property string $orderTimeOut
 * @property string $salesDateOut
 * @property int $branchID
 * @property int $memberID
 * @property string $memberCode
 * @property string $employeeCode
 * @property string $employeeName
 * @property string $employeeType
 * @property int $tableID
 * @property int $visitPurposeID
 * @property int $paxTotal
 * @property string $subtotal
 * @property string $discountTotal
 * @property string $menuDiscountTotal
 * @property string $promotionDiscount
 * @property string $otherTaxTotal
 * @property string $vatTotal
 * @property string $deliveryCost
 * @property string $grandTotal
 * @property string $voucherTotal
 * @property string $roundingTotal
 * @property string $paymentTotal
 * @property int $billingPrintCount
 * @property int $paymentPrintCount
 * @property string $additionalInfo
 * @property string $remarks
 * @property int $promotionID
 * @property string promotionVoucherCode
 * @property int $printEsoFsQr
 * @property int $statusID
 * @property int $flagInclusive
 * @property string $externalMembershipTypeID
 * @property int $flagExternalAPI
 * @property string $flagExternalMemberID
 * @property string $flagExternalMemberPhone
 * @property string $flagExternalCardID
 * @property string $externalMemberName
 * @property string $externalTransID
 * @property string $externalCancelTransID
 * @property string $createdBy
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * 
 * @property Branch $branch
 * @property Member $member
 * @property Table $table
 * @property VisitPurpose $visitPurpose
 * @property SalesMenu[] $salesMenus
 * @property SalesMergeTable[] $salesMergeTables
 * @property SalesLink[] $salesLinks
 * @property SalesLink[] $childSalesLinks
 * @property PromotionHead $promotionHead
 * @property Status $status
 * @property SalesMenu[] $activeMainSalesMenus
 * @property SalesMenu[] $mainSalesMenus
 * @property SalesVoucher[] $salesVouchers
 * @property SalesPayment[] $salesPayments
 * @property PosUser $creator
 * @property PosUser $editor
 * @property TableUsage $tableUsage
 */
class SalesHead extends ActiveRecord {
    const SCENARIO_NOT_CALCULATE = 'not re-calculate total transaction';
    const PRINT_BILL = 'bill';
    const PRINT_PAYMENT = 'payment';
    const NON_INCLUSIVE_BEFORE_DISCOUNT = 'NON INCLUSIVE BEFORE DISCOUNT';
    const NON_INCLUSIVE_AFTER_DISCOUNT = 'NON INCLUSIVE AFTER DISCOUNT';
    const INCLUSIVE_BEFORE_DISCOUNT = 'INCLUSIVE BEFORE DISCOUNT';
    const INCLUSIVE_AFTER_DISCOUNT = 'INCLUSIVE AFTER DISCOUNT';
    const EXTERNAL_TRANSCATION_MODE_ID = [5, 6, 7, 8, 9, 10];


    public $visitBillTotal;
    public $tempMenuSubtotal;
    public $allGrandTotal = 0;
    public $allOrderFee = 0;
    public $tempPromotionPaymentMethodID;
    public $inclusiveDiscountTotal;
    public $visitPurposeName;
    public $flagMaxOrder = 0;
    public $rewardType;
    public $flagAutoRemovePromotion = false;
    public $platformFee;
    public $selfOrderIdKiosk;
    public $selfOrderPaymentMethodID = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_saleshead';
    }

    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
                'value' => function() {
                    if (array_key_exists('paymentPrintCount',
                            $this->getDirtyAttributes()) && !is_null($this->editedBy)) {
                        return $this->editedBy;
                    } else {
                        return Yii::$app->user->identity->username;
                    }
                }
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['memberID', 'subtotal', 'discountTotal', 'menuDiscountTotal', 'promotionDiscount', 'otherTaxTotal', 'vatTotal', 'deliveryCost', 'grandTotal', 'voucherTotal', 'roundingTotal', 'paymentTotal', 'billingPrintCount', 'paymentPrintCount', 'promotionID', 'flagInclusive', 'voucherDiscountTotal', 'flagExternalAPI', 'otherVatTotal'], 'default', 'value' => 0],
            [['statusID'], 'default', 'value' => 1],
            [['orderFee'], 'default', 'value' => -1],
            [['salesNum', 'salesDate', 'salesDateIn', 'branchID', 'tableID', 'visitPurposeID', 'paxTotal', 'subtotal', 'discountTotal', 'menuDiscountTotal', 'promotionDiscount', 'otherTaxTotal', 'vatTotal', 'grandTotal', 'voucherTotal', 'paymentTotal', 'billingPrintCount', 'paymentPrintCount', 'statusID', 'voucherDiscountTotal'], 'required'],
            [['externalTransID', 'externalMembershipTypeID', 'salesDate', 'salesDateIn', 'salesDateOut', 'editedDate', 'syncDate', 'employeeCode', 'employeeName', 'employeeType', 'visitBillTotal', 'lockTable', 'orderTimeOut', 'queueNum', 'orderFee', 'memberCode', 'visitorTypeID', 'inclusiveDiscountTotal', 'promotionVoucherCode', 'bookNum', 'flagMaxOrder', 'deliveryTime', 'rewardType', 'platformFee', 'conditionalPromoID', 'selfOrderIdKiosk', 'selfOrderPaymentMethodID'], 'safe'],
            [['branchID', 'memberID', 'tableID', 'visitPurposeID', 'paxTotal', 'billingPrintCount', 'paymentPrintCount', 'promotionID', 'statusID', 'flagInclusive', 'transactionModeID', 'visitorTypeID'], 'integer'],
            [['subtotal', 'discountTotal', 'menuDiscountTotal', 'promotionDiscount', 'otherTaxTotal', 'vatTotal', 'deliveryCost', 'grandTotal', 'voucherTotal', 'roundingTotal', 'paymentTotal', 'voucherDiscountTotal', 'otherVatTotal'], 'number'],
            [['externalMembershipTypeID', 'salesNum', 'billNum', 'flagExternalMemberPhone'], 'string', 'max' => 20],
            [['flagExternalMemberID', 'flagExternalCardID', 'externalTransID', 'externalCancelTransID', 'terminalID', 'promotionVoucherCode'], 'string', 'max' => 50],
            [['additionalInfo', 'remarks'], 'string', 'max' => 200],
            [['additionalInfo', 'employeeCode', 'remarks'], 'default', 'value' => ''],
            [['createdBy', 'editedBy'], 'string', 'max' => 100],
            [['salesNum', 'billNum'], 'unique'],
            [['externalMemberName'], 'validateExternalMemberName']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Num',
            'billNum' => 'Bill Num',
            'salesDate' => 'Sales Date',
            'salesDateIn' => 'Sales Date In',
            'salesDateOut' => 'Sales Date Out',
            'branchID' => 'Branch ID',
            'memberID' => 'Member ID',
            'tableID' => 'Table ID',
            'visitPurposeID' => 'Visit Purpose ID',
            'paxTotal' => 'Pax Total',
            'subtotal' => 'Subtotal',
            'discountTotal' => 'Discount Total',
            'voucherDiscountTotal' => 'Voucher Discount Total',
            'menuDiscountTotal' => 'Menu Discount Total',
            'promotionDiscount' => 'Promotion Discount',
            'otherTaxTotal' => 'Other Tax Total',
            'vatTotal' => 'Vat Total',
            'deliveryCost' => 'Delivery Cost',
            'grandTotal' => 'Grand Total',
            'voucherTotal' => 'Voucher Total',
            'roundingTotal' => 'Rounding Total',
            'paymentTotal' => 'Payment Total',
            'billingPrintCount' => 'Billing Print Count',
            'paymentPrintCount' => 'Payment Print Count',
            'additionalInfo' => 'Additional Info',
            'promotionID' => 'Promotion ID',
            'statusID' => 'Status ID',
            'flagInclusive' => 'Flag Inclusive',
            'createdBy' => 'Created By',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date',
            'employeeCode' => 'Employe Code',
            'employeeName' => 'Employe Name',
            'employeeType' => 'Employe Type',
            'remarks' => 'Remarks',
            'transactionModeID' => 'Transaction Mode ID',
            'deliveryTime' => 'Delivery Time',
            'terminalID' => 'Terminal ID',
            'queueNum' => 'Queue Number',
            'bookNum' => 'Booking Number',
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['subtotal'] = function ($model) {
            return (float) $model->subtotal;
        };
        $fields['menuDiscountTotal'] = function ($model) {
            return (float) $model->menuDiscountTotal;
        };
        $fields['promotionDiscount'] = function ($model) {
            return (float) $model->promotionDiscount;
        };
        $fields['discountTotal'] = function ($model) {
            return (float) $model->discountTotal;
        };
        $fields['otherTaxTotal'] = function ($model) {
            return (float) $model->otherTaxTotal;
        };
        $fields['vatTotal'] = function ($model) {
            return (float) $model->vatTotal;
        };
        $fields['otherVatTotal'] = function ($model) {
            return (float) $model->otherVatTotal;
        };
        $fields['deliveryCost'] = function ($model) {
            return (float) $model->deliveryCost;
        };
        $fields['orderFee'] = function ($model) {
            return (float) $model->orderFee;
        };
        $fields['grandTotal'] = function ($model) {
            return (float) $model->grandTotal;
        };
        $fields['voucherTotal'] = function ($model) {
            return (float) $model->voucherTotal;
        };
        $fields['roundingTotal'] = function ($model) {
            return (float) $model->roundingTotal;
        };
        $fields['paymentTotal'] = function ($model) {
            return (float) $model->paymentTotal;
        };
        $fields['tableName'] = function ($model) { 
            if ($model->tableID > 0) {
                $tableName = $model->table->tableName;
            } else {
                $tableNameModel = SalesInfo::find()
                    ->where(['salesNum' => $model->salesNum])
                    ->andWhere(['key' => 'Table Name'])
                    ->one();
                if ($tableNameModel) {
                    $tableName = $tableNameModel->value;
                } else {
                    $tableName = 'Quick Service';
                }
            }

            return $tableName;
        };
        $fields['memberName'] = function ($model) {
            return $model->member ? $model->member->memberName : 'Non Member';
        };
        $fields['memberCode'] = function ($model) {
            return $model->member ? $model->member->memberCode : null;
        };
        $fields['memberAddress'] = function ($model) {
            return $model->member ? $model->member->memberAddress : 'No Address';
        };
        $fields['promotionTypeID'] = function ($model) {
            return $model->promotionID != 0 ? ($model->promotion ? $model->promotion->promotionTypeID : 0) : 0;
        };
        $fields['promotionName'] = function ($model) {
            return $model->promotionID != 0 ? ($model->promotion ? $model->promotion->notes : '') : '';
        };
        $fields['promotionDiscountText'] = function ($model) {
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
            $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
            $promotionDiscountText = '';
            if($model->promotion){
                $promotionDiscountText = $model->promotion->promotionTypeID == 11 || $model->promotion->promotionTypeID == 12 || $model->promotion->promotionTypeID == 14 || $model->promotion->promotionTypeID == 15 || $model->promotion->promotionTypeID == 16 ? $model->promotionDiscount : $model->promotion->discount;
                if($model->promotion->promotionTypeID == 12|| $model->promotion->promotionTypeID == 14 || $model->promotion->promotionTypeID == 15 || $model->promotion->promotionTypeID == 16){
                    $promotionDiscountText = $model->discountTotal;
                }
            }
            
            return $model->promotionID != 0 && $model->promotion ? (number_format($promotionDiscountText,
                    $salesDecimalSetting, "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator") . ($model->promotion->promotionTypeID == 1 || $model->promotion->promotionTypeID == 10 || $model->promotion->promotionTypeID == 11 ? '%' : '')) : '';
        };
        $fields['statusName'] = function ($model) {
            return $model->status ? $model->status->statusName : '-';
        };
        $fields['flagInclusive'] = function ($model) {
            return $model->flagInclusive;
        };
        $fields['creator'] = function ($model) {
            return $model->creator ? $model->creator->fullName : 'SELF ORDER';
        };
        $fields['editor'] = function ($model) {
            return $model->editor ? $model->editor->fullName : 'SELF ORDER';
        };
        $fields['inclusiveMenuTemplateID'] = function ($model) {
            return MapBranchVisitPurpose::getInclusiveMenuTemplateID($model->visitPurposeID);
        };
        $fields['modePromotion'] = function ($model) {
            return 0;
        };
        $fields['customerName'] = function ($model) {
            if ($model->customer) {
                return Self::afterFindCustomerData($model, 'fullName');
            }
        };
        $fields['customerPhone'] = function ($model) {
            if ($model->customer) {
                return Self::afterFindCustomerData($model, 'phoneNumber');
            }
        };
        $fields['customerEmail'] = function ($model) {
            if ($model->customer) {
                return Self::afterFindCustomerData($model, 'email');
            }
        };
        $fields['customerPhoneNum'] = function ($model) {
            if ($model->salesContactInfo) return $model->salesContactInfo->customerPhoneNum;
        };

        $this->tempPromotionPaymentMethodID = 0;
        if ($this->promotion) {
            if ($this->promotion->paymentMethodID) {
                $this->tempPromotionPaymentMethodID = $this->promotion->paymentMethodID;
            }
        }

        foreach ($this->activeMainSalesMenus as $salesMenu) {
            if (!$this->tempPromotionPaymentMethodID) {
                if ($salesMenu->promotion) {
                    if ($salesMenu->promotion->paymentMethodID) {
                        $this->tempPromotionPaymentMethodID = $salesMenu->promotion->paymentMethodID;
                    }
                }
            }
        }

        $fields['promotionPaymentMethodID'] = function ($model) {
            return $model->tempPromotionPaymentMethodID;
        };
        $fields['newPromotionPaymentMethodID'] = function($model) {
            return $model->tempPromotionPaymentMethodID;
        };

        // $fields['flagMaxOrder'] = function ($model) {
        //     return 1;
        // };
        
        $fields['rewardType'] = function ($model) {
            return $model->salesRewardHead ? $model->salesRewardHead->rewardType : null;
        };

        $fields['conditionalPromoID'] = function ($model) {
            return $model->salesConditionalPromo ? $model->salesConditionalPromo->conditionalPromoID : 0;
        };

        $fields['selfOrderIdKiosk'] = function ($model) {
            return $model->salesPaymentGateway ? $model->salesPaymentGateway->selfOrderIdKiosk : null;
        };

        return $fields;
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_NOT_CALCULATE] = [
            'salesNum',
            'billNum',
            'salesDate',
            'salesDateIn',
            'salesDateOut',
            'branchID',
            'memberID',
            'memberCode',
            'employeeCode',
            'employeeName',
            'employeeType',
            'tableID',
            'visitPurposeID',
            'visitorTypeID',
            'paxTotal',
            'subtotal',
            'discountTotal',
            'menuDiscountTotal',
            'promotionDiscount',
            'otherTaxTotal',
            'vatTotal',
            'grandTotal',
            'voucherTotal',
            'roundingTotal',
            'paymentTotal',
            'billingPrintCount',
            'paymentPrintCount',
            'additionalInfo',
            'remarks',
            'promotionID',
            'statusID',
            'createdBy',
            'editedBy',
            'editedDate',
            'syncDate'
        ];

        return $scenarios;
    }

    public function validateExternalMemberName($attribute) {
        if ($this->externalMemberName && strlen($this->externalMemberName) > 100) {
            $this->externalMemberName = substr($this->externalMemberName, 0, 100);
        }
    }

    public function getExternalMembership() {
        return $this->hasOne(LkExternalMemberShipType::class, ['externalMembershipTypeID' => 'externalMembershipTypeID']);
    }

    public function getBranch() {
        return $this->hasOne(Branch::class, ['branchID' => 'branchID']);
    }

    public function getMember() {
        return $this->hasOne(Member::class, ['memberCode' => 'memberCode']);
    }

    public function getCustomer() {
        return $this->hasOne(CustomerTransaction::class, ['salesNum' => 'salesNum']);
    }

    public function getTable() {
        return $this->hasOne(Table::class, ['tableID' => 'tableID']);
    }

    public function getVisitPurpose() {
        return $this->hasOne(VisitPurpose::class,
                ['visitPurposeID' => 'visitPurposeID']);
    }

    public function getSalesMenus() {
        return $this->hasMany(SalesMenu::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesMergeTables() {
        return $this->hasMany(SalesMergeTable::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesLinks() {
        return $this->hasMany(SalesLink::class, ['salesNum' => 'salesNum']);
    }

    public function getChildSalesLinks() {
        return $this->hasMany(SalesLink::class, ['linkSalesNum' => 'salesNum']);
    }

    public function getPromotion() {
        return $this->hasOne(PromotionHead::class,
                ['promotionID' => 'promotionID']);
    }

    public function getSalesPaymentGateway() {
        return $this->hasOne(SalesPaymentGateway::class,
                ['salesNum' => 'salesNum']);
    }

    public function getStatus() {
        return $this->hasOne(Status::class, ['statusID' => 'statusID']);
    }

    public function getActiveMainSalesMenus() {
        return $this->hasMany(SalesMenu::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ID'
                ])
                ->andWhere(['IN', SalesMenu::tableName() . '.statusID', [13, 14, 34, 46]])
                ->orderBy(SalesMenu::tableName() . '.batchID',
                    SalesMenu::tableName() . '.ID');
    }

    public function getActiveHoldSalesMenus() {
        return $this->hasMany(SalesMenu::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ID'
                ])
                ->andWhere([SalesMenu::tableName() . '.statusID' => 46])
                ->orderBy(SalesMenu::tableName() . '.batchID',
                    SalesMenu::tableName() . '.ID');
    }

    public function getEsoTable() {
        return $this->hasOne(SalesInfo::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['key' => 'Table Name']);
    }

    public function getMainSalesMenus() {
        return $this->hasMany(SalesMenu::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ID'
                ])
                ->orderBy(SalesMenu::tableName() . '.batchID',
                    SalesMenu::tableName() . '.ID');
    }

    public function getSalesVouchers() {
        return $this->hasMany(SalesVoucher::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesVouchersOnline() {
        return $this->hasMany(SalesVoucherOnline::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesPayments() {
        return $this->hasMany(SalesPayment::class, ['salesNum' => 'salesNum']);
    }

    public function getCreator() {
        return $this->hasOne(PosUser::class, ['username' => 'createdBy']);
    }

    public function getEditor() {
        return $this->hasOne(PosUser::class, ['username' => 'editedBy']);
    }

    public function getTableUsage() {
        return $this->hasOne(TableUsage::class, ['referenceID' => 'salesNum']);
    }

    public function getSalesInfoPickupTime() {
        return $this->hasOne(SalesInfo::class, ['salesNum' => 'salesNum'])
            ->andOnCondition(['IN', 'key', ['Pickup Time', 'Delivery Time']]);
    }
    
    public function getTableQuickService() {
        return $this->hasOne(SalesInfo::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['key' => 'Table Name']);
    }

    public function getSalesMenuCompletionKitchen() {
        return $this->hasMany(SalesMenuCompletion::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['typeID' => 1])
                ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesMenuCompletionChecker() {
        return $this->hasMany(SalesMenuCompletion::class, ['salesNum' => 'salesNum'])
                ->andOnCondition(['typeID' => 2])
                ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesRewardHead() {
        return $this->hasOne(SalesRewardHead::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesContactInfo() {
        return $this->hasOne(SalesContactInfo::class, ['salesNum' => 'salesNum']);
    }

    public function getSalesConditionalPromo() {
        return $this->hasOne(SalesConditionalPromo::class, ['salesNum' => 'salesNum']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;
        if ($this->scenario != self::SCENARIO_NOT_CALCULATE) {
            $this->calculateTotal();
        }

        return true;
    }

    public static function CheckSalesNotSync($shiftInTime) {
        $branchID = Setting::getCurrentBranch();
        $data = SalesHead::find()
            ->where(['branchID' => $branchID])
            ->andWhere(['>=', 'salesDateIn', $shiftInTime])
            ->andWhere(['IS', 'syncDate', null])
            ->one();
        return $data != null;
    }

    public static function findOutstanding() {
        $branchID = Setting::getCurrentBranch();

        return SalesHead::find()
                ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                ->andWhere(['IS', SalesHead::tableName() . '.salesDateOut', null])
                ->orderBy('salesDate, salesNum');
    }

    public static function findFinished() {
        $branchID = Setting::getCurrentBranch();

        return SalesHead::find()
                ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                ->andWhere(['IS NOT', SalesHead::tableName() . '.salesDateOut', null])
                ->orderBy('salesDate, salesNum');
    }

    public static function findOutstandingOrder() {
        $salesModel = SalesHead::findOutstanding();

        return SalesHead::findOrderDetails($salesModel);
    }

    public static function findOrder() {
        $branchID = Setting::getCurrentBranch();
        $salesModel = SalesHead::find()
            ->andWhere([SalesHead::tableName() . '.branchID' => $branchID]);

        return SalesHead::findOrderDetails($salesModel);
    }

    public static function findOrderDetails($salesModel) {
        return $salesModel->joinWith('salesMergeTables')
                ->with('table')
                ->with('creator')
                ->with('editor')
                ->with('mainSalesMenus.menu.menuCategoryDetail')
                ->with('mainSalesMenus.status')
                ->with('mainSalesMenus.childSalesMenus.menu')
                ->with('mainSalesMenus.childSalesMenus.status')
                ->with('mainSalesMenus.salesExtras.menuExtra')
                ->with('promotion');
    }

    public static function findMainSales($tableID, $salesNum = null) {
        if (isset($salesNum)) {
            $branchID = Setting::getCurrentBranch();
            $salesModel = SalesHead::find()
                ->andWhere([SalesHead::tableName() . '.branchID' => $branchID]);
        } else {
            $salesModel = SalesHead::findOutstanding();
        }

        return $salesModel->select('d.*')
                ->leftJoin(SalesMergeTable::tableName() . ' b',
                    SalesHead::tableName() . '.salesNum = b.salesNum')
                ->leftJoin(SalesLink::tableName() . ' c',
                    SalesHead::tableName() . '.salesNum = c.linkSalesNum')
                ->leftJoin(SalesHead::tableName() . ' d',
                    new Expression('COALESCE(c.salesNum, ' . SalesHead::tableName() . '.salesNum) = d.salesNum'))
                ->andFilterWhere(['OR',
                    [SalesHead::tableName() . '.tableID' => $tableID],
                    ['b.tableID' => $tableID]
                ])
                ->andFilterWhere([SalesHead::tableName() . '.salesNum' => $salesNum])
                ->one();
    }

    public static function findVisitPurposeHeads($salesNum) {
        $branchID = Setting::getCurrentBranch();
        $salesModel = SalesHead::find()
            ->andWhere([SalesHead::tableName() . '.branchID' => $branchID]);

       return $salesModel->select('a.visitPurposeName')
            ->leftJoin(VisitPurpose::tableName() . ' a',
                SalesHead::tableName() . '.visitPurposeID = a.visitPurposeID')
            ->andFilterWhere([SalesHead::tableName() . '.salesNum' => $salesNum])
            ->one();
    }

    public static function findLinkSalesHeads($salesNum) {
        $salesLinkArray = SalesLink::find()
            ->select('linkSalesNum')
            ->andWhere(['salesNum' => $salesNum]);

        return SalesHead::find()
                ->with('salesMenus')
                ->andWhere(['IN', 'salesNum', $salesLinkArray])
                ->all();
    }

    public static function findHoldOrder() {
        $salesModel = SalesHead::findOutstanding()
        ->with('table')
        ->with('activeHoldSalesMenus.childSalesMenus')
        ->with('activeHoldSalesMenus.salesExtras')
        ->where(['<>', SalesHead::tableName() . '.tableID', 0])
        ->all();
        $salesData = [];
        if ($salesModel !== null) {
            $i=0;
            foreach ($salesModel as $sales) {
                if (($sales->table && $sales->activeHoldSalesMenus)) {
                    $salesData[$i]['tableName'] = $sales->table->tableName;
                    $salesData[$i]['salesNum'] = $sales->salesNum;
                    $salesData[$i]['salesMenu'] = $sales->activeHoldSalesMenus;
                    $salesData[$i]['occupiedTime'] = Table::getOccupiedTime(date_create($sales->activeHoldSalesMenus[0]->createdDate), new DateTime());
                    $i++;
                }
            }
        }
        return $salesData;
    }

    public static function findPickupOrder() {
        $salesModel = EsoPickupOrder::find()
            ->with('salesHead')
            ->with('salesHead.customer')
            ->with('salesHead.salesInfoPickupTime')
            ->all();

        $salesList = [];
        foreach ($salesModel as $sales) {
            $salesArr['salesNum'] = $sales->salesNum;
            $salesArr['billNum'] = $sales->salesHead->billNum;
            $salesArr['orderTime'] = $sales->salesHead->salesDateIn;
            $salesArr['selfOrderID'] = $sales->orderID;
            $salesArr['customerName'] = $sales->salesHead->customer->fullName;
            $salesArr['phoneNum'] = $sales->salesHead->customer->phoneNumber;
            $salesArr['pickUpTime'] = $sales->salesHead->salesInfoPickupTime && $sales->salesHead->salesInfoPickupTime['key'] == 'Pickup Time' ? $sales->salesHead->salesInfoPickupTime['value'] : 'NOW';
            $salesArr['statusID'] = $sales->salesHead->statusID;
            $salesList[] = $salesArr;
        }

        return $salesList;
    }

    public static function findOrderPaymentAsArray($tableID, $salesNum = null, $payment = false, $bill = false) {
        $connection = Yii::$app->getDb();
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();
        
        $mainSalesModel = SalesHead::getMainSalesRawQuery($branchID, $tableID, $salesNum);
        if (!$mainSalesModel) {
            return null;
        }

        $salesNum = $mainSalesModel['salesNum'];
        $salesNumList[] = $salesNum;

        $salesModel = $connection->createCommand(SalesHead::getFindOutstandingOrderRawQuery($branchID . "
          AND tr_saleshead.salesNum = '$salesNum'", 'tr_saleshead'))->queryOne();

        $salesLinkModel = [];
        if ($salesModel['linkSalesNum']) {
          $salesLinkModel = $connection->createCommand(SalesHead::getFindOutstandingOrderRawQuery($branchID . "
            AND tr_saleshead.salesNum = '$salesNum'", 'headLinkSales'))->queryAll();
          
          $salesNumList = array_merge(array_column($salesLinkModel, 'salesNum'), $salesNumList);
        }

        $salesNumList = "'" . implode("', '", $salesNumList) . "'";

        $activeMainSalesMenusModel = $connection->createCommand(SalesMenu::getFindOutstandingSalesMainRawQuery($branchID) . "
          AND tr_saleshead.salesNum IN ($salesNumList)
          AND tr_salesmenu.statusID IN (13, 14, 34, 46)
        ORDER BY tr_salesmenu.batchID, tr_salesmenu.ID")->queryAll();

        $childSalesMenuModel = $connection->createCommand(SalesMenu::getFindOutstandingSalesChildRawQuery($branchID) . "
          AND tr_saleshead.salesNum IN ($salesNumList)
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesMenuExtraModel = $connection->createCommand(SalesMenuExtra::getFindOutstandingSalesExtrasRawQuery($branchID) . "
          AND tr_saleshead.salesNum IN ($salesNumList)
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesPaymentsModel = $connection->createCommand("SELECT
            tr_salespayment.*,
            ms_paymentmethod.paymentMethodTypeID,
            ms_paymentmethod.paymentMethodName,
            ms_paymentmethod.flagUseEmployeeLimit,
            ms_paymentmethod.posExternalPaymentID,
            ms_paymentmethod.depositSourceID,
            ms_paymentmethod.voucherSourceID,
            lk_paymentmethodtype.paymentMethodTypeName
          FROM
            tr_salespayment
          LEFT JOIN
            ms_paymentmethod ON tr_salespayment.paymentMethodID = ms_paymentmethod.paymentMethodID
          LEFT JOIN
            lk_paymentmethodtype ON ms_paymentmethod.paymentMethodTypeID = lk_paymentmethodtype.paymentMethodTypeID
          WHERE
            tr_salespayment.salesNum IN ($salesNumList)")->queryAll();

        $salesNumListArray = array_column($activeMainSalesMenusModel, 'salesNum');
        $salesNumList = "'" . implode("', '", $salesNumListArray) . "'";

        $salesMenuCompletionModel = $connection->createCommand("SELECT * FROM
            tr_salesmenucompletion
          WHERE
            tr_salesmenucompletion.salesNum IN ($salesNumList)")->queryAll();

        $salesProcessMenuModel = $connection->createCommand("SELECT * FROM
            tr_salesprocessmenu
          WHERE
            tr_salesprocessmenu.salesNum IN ($salesNumList)")->queryAll();
        
        $promotionID = $salesModel['promotionID'];
        $promotionCategoryModel = $connection->createCommand("SELECT * FROM ms_promotioncategory WHERE promotionID = $promotionID")->queryAll();
        
        // @notes : flagPackageContent for recalculate total
        $promotionHeadModel = $connection->createCommand("SELECT * FROM ms_promotionhead WHERE promotionID = $promotionID")->queryOne();
        $flagPackageContent = false;
        if($promotionHeadModel){
            $flagPackageContent = $promotionHeadModel['flagPackageContent'] ? true : false;
            $salesMenuDataModel = $connection->createCommand("SELECT * FROM tr_salesmenu WHERE salesNum = '$salesNum' AND menuGroupID = 0")->queryAll();
        }
  
        $newFormatSalesModel = AppHelper::reformatTypeDataHead($salesModel);
        $activeMainSalesMenusModelArray = AppHelper::reformatTypeDataMenu($activeMainSalesMenusModel, 'salesNum');
        $salesLinkModelArray = AppHelper::reformatTypeDataMenu($salesLinkModel, 'linkSalesNum');
        $childSalesMenuModelArray = AppHelper::reformatTypeDataMenu($childSalesMenuModel, 'menuRefID');
        $salesMenuExtraModelArray = AppHelper::reformatTypeDataMenu($salesMenuExtraModel, 'menuDetailID');
        $salesMenuCompletionModelArray = AppHelper::reformatTypeDataMenu($salesMenuCompletionModel, 'salesNum');
        $salesProcessMenuModelArray = AppHelper::reformatTypeDataMenu($salesProcessMenuModel, 'salesNum');
        $promotionCategoryModelArray = AppHelper::reformatTypeDataMenu($promotionCategoryModel, 'promotionID');
        $salesPaymentModelArray = AppHelper::reformatTypeDataMenu($salesPaymentsModel, 'salesNum');

        $salesModel = self::getOtherAttributeSalesHead($newFormatSalesModel, $activeMainSalesMenusModelArray, $settings, 'payment');
        $activeMainSalesMenus = self::assignSalesMenuPackageExtra($salesNum, $salesModel, $activeMainSalesMenusModelArray, $childSalesMenuModelArray,
                                $salesMenuExtraModelArray, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $salesProcessMenuModelArray);
        
        $newActiveMainSalesMenus = SalesHead::groupingOrderForBillingArray($salesModel, $activeMainSalesMenus, $settings, $salesModel['flagInclusive'], $branchID, $payment, $bill);
        $extraFieldsSalesMenu = self::reAssignSalesMenuPackageExtra($salesModel, $newActiveMainSalesMenus, $salesMenuCompletionModelArray, $salesProcessMenuModelArray);

        $menuDiscountTotal = 0;
        $grandTotal = 0;
        $taxInclusiveAfterDiscount = false;
        if ($salesModel['flagInclusive']) {
            if ($salesModel['posOtherTaxCalculationID'] == 2 && $salesModel['posTaxCalculationID'] == 2) {
                $taxInclusiveAfterDiscount = true;
            }
        }

        $stationID = $salesModel['tableStationID'] ? $salesModel['tableStationID'] : null;

        $externalMember = $mainSalesModel['externalMembershipTypeID'] ?
        LkExternalMemberShipType::find()->where(['externalMembershipTypeID' => $mainSalesModel['externalMembershipTypeID']])->asArray()->one() : null;

        $extraFields = [
            'menuCategory' => SalesHead::groupingCategoryOrderForBillingArray($salesModel, $activeMainSalesMenus, $menuDiscountTotal, $grandTotal),
            'salesMenu' => $extraFieldsSalesMenu,
            'queueNum' => $mainSalesModel['queueNum'],
            'mergeTableNames' => SalesMergeTable::getMergeTableNames($mainSalesModel['salesNum']),
            'customerEmail' => $salesModel['email'] ? $salesModel['email'] : '',
            'externalMember' => $externalMember,
            "menuCategoryGroup" => Saleshead::groupingOrderMenuByCategoryArray($activeMainSalesMenus),
            'platformFee' => SalesHead::getSalesPlatformFee($mainSalesModel['salesNum'])
        ];

        if ($taxInclusiveAfterDiscount) {
          if ($salesModel['masterPromoID']) {
              if ($salesModel['promotionTypeID'] == 1 || $salesModel['promotionTypeID'] == 5) {
                  if ($salesModel['discountTotal'] > 0) {
                      if (($salesModel['grandTotal'] - $salesModel['roundingTotal']) > 0) {
                          if ((100 - $salesModel['promotionDiscount']) > 0) {
                              $platformFee = 0;
                              $platformFeeList = SalesHead::getSalesPlatformFee($salesModel['salesNum']);
                              if ($platformFeeList) {
                                  foreach ($platformFeeList as $row) {
                                      if (isset($row['platformFeeTypeID'])) {
                                          if ($row['platformFeeTypeID'] == 1) {
                                              $platformFee += $row['amount'];
                                          }
                                      }
                                  }
                              }
                             // @notes : flagPackageContent recalculate grantotaltotal for menu parent
                                $grandTotalBeforeDiscount = SalesHead::recalculateGrandTotalMenus($salesMenuDataModel, $salesModel, $platformFee, $flagPackageContent);

                            } else {
                              $grandTotalBeforeDiscount = $grandTotal;
                          }

                          $discountTotal = $grandTotalBeforeDiscount * $salesModel['promotionDiscount'] / 100;
                          if ($discountTotal > $salesModel['maxSalesPrice']) {
                              $discountTotal = $salesModel['maxSalesPrice'];
                          }

                            //@notes: Komparasi discount total dengan hasil reverse calculation
                            $reverseDiscountTotal = CalculateTotal::getReverseDiscountTotal($salesModel);
                            if ($reverseDiscountTotal != (int)$discountTotal) {
                                $salesModel['discountTotal'] = $reverseDiscountTotal;
                            } else {
                                $salesModel['discountTotal'] = $discountTotal;
                            }
                      } else {
                          $salesModel['discountTotal'] = $grandTotal - $menuDiscountTotal;
                      }
                  }
              } else if ($salesModel['promotionTypeID'] == 11) {
                  $tempMenuGrandTotal = 0;
                  $promotionCategoryIDs = [];
                  $promotionCategoryDetailIDs = [];
                  $promotionMenuIDs = [];

                  if (isset($promotionCategoryModelArray[$salesModel['promotionID']])) {
                    foreach ($promotionCategoryModelArray[$salesModel['promotionID']] as $promotionCategory) {
                      $promotionCategoryIDs[] = $promotionCategory['menuCategoryID'];
                      $promotionCategoryDetailIDs[] = $promotionCategory['menuCategoryDetailID'];
                      $promotionMenuIDs[] = $promotionCategory['menuID'];
                    }
                  }

                  foreach ($activeMainSalesMenus as $salesMenu) {
                      $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesModel['promotionID'], $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                      if ($applyDiscountBill) {
                          $tempMenuGrandTotal += $salesMenu['qty'] * $salesMenu['inclusivePrice'] - $salesMenu['inclusiveDiscountValue'];
                      }

                      if ($salesMenu['packages']) {
                          foreach ($salesMenu['packages'] as $perPackage) {
                              if ($applyDiscountBill) {
                                  $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesModel['promotionID'], $perPackage, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                  if ($applyDiscountBill) {
                                      $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perPackage['qty'] * $perPackage['inclusivePrice'] - $perPackage['inclusiveDiscountValue']);
                                  }
                              }
                          }
                      }
      
                      if ($salesMenu['extras']) {
                          foreach ($salesMenu['extras'] as $perExtra) {
                              if ($applyDiscountBill) {
                                  $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice'] - $perExtra['inclusiveDiscountValue']);
                              }
                          }
                      }
                  }
                  
                  if ($salesModel['discountTotal'] > 0) {
                      $promotionDiscount = $salesModel['promotionDiscount'] > 100 ? 100 : $salesModel['promotionDiscount'];
                      $discountTotal = ($tempMenuGrandTotal) * $promotionDiscount / 100;
                      
                        //@notes: Komparasi discount total dengan hasil reverse calculation
                        $reverseDiscountTotal = CalculateTotal::getReverseDiscountTotal($salesModel);
                        if ($reverseDiscountTotal != (int)$discountTotal) {
                            $salesModel['discountTotal'] = $reverseDiscountTotal;
                        } else {
                            $salesModel['discountTotal'] = $discountTotal;
                        }
                  }
              } else if (in_array($salesModel['promotionTypeID'], [3, 6, 12, 14, 15, 16])) {
                  if ($salesModel['discountTotal'] > 0) {
                      $salesModel['discountTotal'] = $salesModel['promotionDiscount'];
                  }
              } else if ($salesModel['promotionTypeID'] == 10) {
                  $tempMenuGrandTotal = 0;
                  $applyBillDiscountToPackageContent = (int) $salesModel['flagPackageContent'];
                  $applyBillDiscountToExtra = (int) $salesModel['flagMenuExtra'];
                  $promotionCategoryIDs = [];
                  $promotionCategoryDetailIDs = [];
                  $promotionMenuIDs = [];
                  if (isset($promotionCategoryModelArray[$salesModel['promotionID']])) {
                    foreach ($promotionCategoryModelArray[$salesModel['promotionID']] as $promotionCategory) {
                      $promotionCategoryIDs[] = $promotionCategory['menuCategoryID'];
                      $promotionCategoryDetailIDs[] = $promotionCategory['menuCategoryDetailID'];
                      $promotionMenuIDs[] = $promotionCategory['menuID'];
                    }
                  }

                  foreach ($activeMainSalesMenus as $salesMenu) {
                      $applyDiscountBill = ApplyOrderPromo::checkAppliedPromoArray($salesModel, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                      if ($applyDiscountBill) {
                          $tempMenuGrandTotal += $salesMenu['qty'] * $salesMenu['inclusivePrice'] - $salesMenu['inclusiveDiscountValue'];
                      }

                      if ($salesMenu['packages']) {
                          foreach ($salesMenu['packages'] as $perPackage) {
                              if ($applyDiscountBill) {
                                  if ($applyBillDiscountToPackageContent) {
                                      $applyDiscountBill = ApplyOrderPromo::checkAppliedPromoArray($salesModel, $perPackage, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                      if ($applyDiscountBill) {
                                          $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perPackage['qty'] * $perPackage['inclusivePrice'] - $perPackage['inclusiveDiscountValue']);
                                      }
                                  }
                              }
                          }
                      }
      
                      if ($salesMenu['extras']) {
                          foreach ($salesMenu['extras'] as $perExtra) {
                              if ($applyDiscountBill) {
                                  if ($applyBillDiscountToExtra) {
                                      $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice'] - $perExtra['inclusiveDiscountValue']);
                                  }
                              }
                          }
                      }
                  }

                  $discountTotal = ($tempMenuGrandTotal) * $salesModel['promotionDiscount'] / 100;
                  $maxDiscount = $salesModel['maxSalesPrice'];
 
                  if ($discountTotal > $maxDiscount) {
                      $discountTotal = $maxDiscount;
                  }

                  //@notes: Komparasi discount total dengan hasil reverse calculation
                  $reverseDiscountTotal = CalculateTotal::getReverseDiscountTotal($salesModel);
                  if ($reverseDiscountTotal != (int)$discountTotal) {
                      $salesModel['discountTotal'] = $reverseDiscountTotal;
                  } else {
                      $salesModel['discountTotal'] = $discountTotal;
                  }

              }
          }        
          $salesModel['menuDiscountTotal'] = $menuDiscountTotal;
        }

        $salesPaymentArray = [];
        if (isset($salesPaymentModelArray[$salesModel['salesNum']])) {
          foreach ($salesPaymentModelArray[$salesModel['salesNum']] as $payment) {
            $salesPaymentArray[] = $payment;
          }
        }

        $salesLink = [];
        if (isset($salesLinkModelArray[$salesModel['salesNum']])) {
          foreach ($salesLinkModelArray[$salesModel['salesNum']] as $link) {
            $linkGrandTotal = 0;
            $linkMenuDiscountTotal = 0;
            $linkInclusiveAfterDiscount = false;
            if ($link['flagInclusive']) {
                if ($salesModel['posOtherTaxCalculationID'] == 2 && $salesModel['posTaxCalculationID'] == 2) {
                    $linkInclusiveAfterDiscount = true;
                }
            }

            $link = self::getOtherAttributeSalesHead(AppHelper::reformatTypeDataHead($link), $activeMainSalesMenusModelArray, $settings, 'payment');
            $activeLinkMainSalesMenus = self::assignSalesMenuPackageExtra($link['salesNum'], $link, $activeMainSalesMenusModelArray, $childSalesMenuModelArray,
                                        $salesMenuExtraModelArray, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $salesProcessMenuModelArray);

            $newActiveMainLinkSalesMenus = SalesHead::groupingOrderForBillingArray($link, $activeLinkMainSalesMenus, $settings, $link['flagInclusive'], $branchID);
            $extraFieldsLinkSalesMenu = self::reAssignSalesMenuPackageExtra($link, $newActiveMainLinkSalesMenus, $salesMenuCompletionModelArray, $salesProcessMenuModelArray);

            $externalMembershipLink = null;
            if ($link['externalMembershipTypeID'])
            {
                $externalMembershipLink = LkExternalMemberShipType::find()->where(['externalMembershipTypeID' => $link['externalMembershipTypeID']])->asArray()->one();
            }
            

            $linkExtraFields = [
                'salesMenu' => $extraFieldsLinkSalesMenu,
                'menuCategory' => SalesHead::groupingCategoryOrderForBillingArray($link, $activeLinkMainSalesMenus, $linkMenuDiscountTotal, $linkGrandTotal),
                'mergeTableNames' => SalesMergeTable::getMergeTableNames($link['salesNum']),
                'externalMember' => $externalMembershipLink,
                "menuCategoryGroup" => Saleshead::groupingOrderMenuByCategoryArray($activeMainSalesMenus)
            ];
            
            if ($linkInclusiveAfterDiscount) {
                if ($link['masterPromoID']) {
                    if ($link['promotionTypeID'] == 1 || $link['promotionTypeID'] == 5) {
                        if ($salesModel['discountTotal'] > 0) {
                            if ($link['grandTotal'] > 0) {
                                $grandTotalBeforeDiscount = 100 /(100 - $link['promotionDiscount']) * $link['grandTotal'];
                                $linkDiscountTotal = $grandTotalBeforeDiscount * $link['promotionDiscount'] / 100;
                                $link['discountTotal'] = $linkDiscountTotal;
                            } else {
                                $link['discountTotal'] = $linkGrandTotal - $linkMenuDiscountTotal;
                            }
                        }
                    } else if (in_array($link['promotionTypeID'], [3, 6, 12, 14, 15, 16])) {
                        if ($link['discountTotal'] > 0) {
                            $link['discountTotal'] = $link['promotionDiscount'];
                        }
                    } else if ($link['promotionTypeID'] == 10) {
                        $tempMenuGrandTotal = 0;
                        $applyBillDiscountToPackageContent = $link['flagPackageContent'];
                        $applyBillDiscountToExtra = $link['flagMenuExtra'];
                        $promotionCategoryIDs = [];
                        $promotionCategoryDetailIDs = [];
                        $promotionMenuIDs = [];
                        if (isset($promotionCategoryModelArray[$salesModel['promotionID']])) {
                          foreach ($promotionCategoryModelArray[$salesModel['promotionID']] as $promotionCategory) {
                            $promotionCategoryIDs[] = $promotionCategory['menuCategoryID'];
                            $promotionCategoryDetailIDs[] = $promotionCategory['menuCategoryDetailID'];
                            $promotionMenuIDs[] = $promotionCategory['menuID'];
                          }
                        }
                        
                        foreach ($activeLinkMainSalesMenus as $salesMenu) {         
                            $applyDiscountBill = ApplyOrderPromo::checkAppliedPromoArray($link, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                            if ($applyDiscountBill) {
                                $tempMenuGrandTotal += $salesMenu['qty'] * $salesMenu['inclusivePrice'] - $salesMenu['inclusiveDiscountValue'];
                            }
    
                            if ($salesMenu['packages']) {
                                foreach ($salesMenu['packages'] as $perPackage) {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToPackageContent) {
                                            $applyDiscountBill = ApplyOrderPromo::checkAppliedPromoArray($link, $perPackage, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            if ($applyDiscountBill) {
                                                $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perPackage['qty'] * $perPackage['inclusivePrice'] - $perPackage['inclusiveDiscountValue']);
                                            }
                                        }
                                    }
                                }
                            }
            
                            if ($salesMenu['extras']) {
                                foreach ($salesMenu['extras'] as $perExtra) {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToExtra) {
                                            $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice'] - $perExtra['inclusiveDiscountValue']);
                                        }
                                    }
                                }
                            }                       
                        }
    
                        $discountTotal = ($tempMenuGrandTotal) * $link['promotionDiscount'] / 100;
                        $maxDiscount = $link['maxSalesPrice'];
       
                        if ($discountTotal > $maxDiscount) {
                            $discountTotal = $maxDiscount;
                        }
    
                        $link['discountTotal'] = $discountTotal;
                    } else if ($link['promotionTypeID'] == 11) {
                        $tempMenuGrandTotal = 0;
                        $promotionCategoryIDs = [];
                        $promotionCategoryDetailIDs = [];
                        $promotionMenuIDs = [];
                        if (isset($promotionCategoryModelArray[$salesModel['promotionID']])) {
                          foreach ($promotionCategoryModelArray[$salesModel['promotionID']] as $promotionCategory) {
                            $promotionCategoryIDs[] = $promotionCategory['menuCategoryID'];
                            $promotionCategoryDetailIDs[] = $promotionCategory['menuCategoryDetailID'];
                            $promotionMenuIDs[] = $promotionCategory['menuID'];
                          }
                        }
  
                        foreach ($activeLinkMainSalesMenus as $salesMenu) {         
                            $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($link['promotionID'], $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                            if ($applyDiscountBill) {
                                $tempMenuGrandTotal += $salesMenu['qty'] * $salesMenu['inclusivePrice'] - $salesMenu['inclusiveDiscountValue'];
                            }
  
                            if ($salesMenu['packages']) {
                                foreach ($salesMenu['packages'] as $perPackage) {
                                    if ($applyDiscountBill) {
                                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($link['promotionID'], $perPackage, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        if ($applyDiscountBill) {
                                            $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perPackage['qty'] * $perPackage['inclusivePrice'] - $perPackage['inclusiveDiscountValue']);
                                        }
                                    }
                                }
                            }
  
                            if ($salesMenu['extras']) {
                                foreach ($salesMenu['extras'] as $perExtra) {
                                    if ($applyDiscountBill) {
                                        $tempMenuGrandTotal += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice'] - $perExtra['inclusiveDiscountValue']);                                  
                                    }
                                }
                            }                       
                        }
  
                        if ($link['discountTotal'] > 0) {
                            $promotionDiscount = $link['promotionDiscount'] > 100 ? 100 : $link['promotionDiscount'];
                            $discountTotal = ($tempMenuGrandTotal) * $promotionDiscount / 100;
  
                            $link['discountTotal'] = $discountTotal;
                        }
                    }
                }        
                $link['menuDiscountTotal'] = $linkMenuDiscountTotal;
            }
  
            $salesLink[] = array_merge($link, $linkExtraFields);
            if (isset($salesPaymentModelArray[$link['salesNum']])) {
              foreach ($salesPaymentModelArray[$link['salesNum']] as $linkPayment) {
                $salesPaymentArray[] = $linkPayment;
              }
            }
          }
        }

        $vouchers = [];
        $salesVoucherModel = $connection->createCommand("SELECT * FROM tr_salesvoucher WHERE salesNum = '$salesNum'")->queryAll();
        foreach ($salesVoucherModel as $salesVoucher) {
            $vouchers[] = $salesVoucher['voucher'];
        }

        // AEVITAS
        $localSettings = Setting::getLocalSettings();
        if (array_key_exists('Generate Aevitas', $localSettings)) {
            if ($localSettings['Generate Aevitas'] == 1) {
                Aevitas::generateAevitas(array_merge($salesModel, $extraFields));
            }
        }

        $removePromotion = false;
        $promotionType = $salesModel['promotionTypeID'];
        if ($salesModel && ($salesModel['promotionID'] > 0 && $salesModel['discountTotal'] == 0 && $salesModel['menuDiscountTotal'] == 0 && 
                ($promotionType > 0 && $promotionType != 8)))
        {
            $removePromotion = ApplyBillPromo::removeIneligiblePromotion($salesModel['salesNum']);
        }

        $outstandingMemberDeposit = 0;
        if ($salesModel['memberCode'])
        {

            $memberCode = $salesModel['memberCode'];
            $outstandingMemberDepositModel = $connection->createCommand("SELECT 
                COALESCE(SUM(depositTotal - usedDepositTotal), 0) as depositTotal 
            FROM
                tr_memberdeposit
            WHERE
                tr_memberdeposit.memberCode = '$memberCode'")->queryOne();

            $outstandingMemberDeposit = $outstandingMemberDepositModel ? $outstandingMemberDepositModel['depositTotal'] : $outstandingMemberDeposit;
            
        }

        $promotionBin = SalesPromotionBin::find()
            ->where([
                'promotionID' => $salesModel['promotionID'],
                'salesNum' => $salesModel['salesNum']
            ])
            ->one();
        if($promotionBin){
            $salesModel['promotionBin'] = $promotionBin['bankIdentificationNumber'];
        }

        return [
            'order' => array_merge($salesModel, $extraFields),
            'salesLink' => $salesLink,
            'voucher' => count($vouchers) > 0 ? $vouchers : null,
            'salesPayment' => $salesNum ? $salesPaymentArray : null,
            'salesParent' => $salesNum ? ($salesModel['tableID'] != $mainSalesModel['tableID'] ? $mainSalesModel : null) : null,
            'availableDepositTotal' => $salesModel['memberCode'] ? $outstandingMemberDeposit : 0,
            'stationID' => $stationID,
            'responseMessage' => $removePromotion ? $removePromotion : null
        ];
    }

    private static function assignSalesMenuPackageExtra(
        $salesNum,
        $salesModel,
        $activeMainSalesMenusModelArray,
        $childSalesMenuModelArray,
        $salesMenuExtraModelArray,
        $salesMenuCompletionModelArray,
        $salesProcessMenuModelArray
      )
    {
      $activeMainSalesMenus = [];
      if (isset($activeMainSalesMenusModelArray[$salesNum])) {
        foreach ($activeMainSalesMenusModelArray[$salesNum] as $mainSales) {

          $tempPackages = [];
          if (isset($childSalesMenuModelArray[$mainSales['ID']])) {
            foreach ($childSalesMenuModelArray[$mainSales['ID']] as $package) {
              $package['tempDiscountValue'] = $package['discountValue'];
              if((int) $salesModel['flagInclusive']){
                $package['discountValue'] = $package['inclusiveDiscountValue'];
              }
              $tempPackages[] = SalesMenu::getOtherAttributeSalesMenu($package, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $mainSales);
            }
          }
          $mainSales['packages'] = $tempPackages;

          $tempExtras = [];
          if (isset($salesMenuExtraModelArray[$mainSales['ID']])) {
            foreach ($salesMenuExtraModelArray[$mainSales['ID']] as $extra) {
              $extra['tempDiscountValue'] = $extra['discountValue'];
              if((int) $salesModel['flagInclusive']){
                $extra['discountValue'] = $extra['inclusiveDiscountValue'];
              }
              $tempExtras[] = $extra;
            }
          }
          $mainSales['extras'] = $tempExtras;

          $mainSales['tempDiscountValue'] = $mainSales['discountValue'];
          $activeMainSalesMenus[] = SalesMenu::getOtherAttributeSalesMenu($mainSales, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $mainSales);
        }
      }
      return $activeMainSalesMenus;
    }

    private static function reAssignSalesMenuPackageExtra($salesModel, $newActiveMainSalesMenus, $salesMenuCompletionModelArray, $salesProcessMenuModelArray) {
      $activeMainSalesMenus = [];
      foreach ($newActiveMainSalesMenus as $salesMenu) {
        $activeMainSalesMenus[] = SalesMenu::getOtherAttributeSalesMenu($salesMenu, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $salesMenu);

        $tempPackages = [];
        foreach ($salesMenu['packages'] as $package) {
          $tempPackages[] = SalesMenu::getOtherAttributeSalesMenu($package, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $salesMenu);
        }
        $salesMenu['packages'] = $tempPackages;
      }
      return $activeMainSalesMenus;
    }

    private static function recalculateGrandTotalMenus($salesMenuModel, $salesModel, $platformFee, $flagPackageContent) {
       
        $grandTotalBeforeDiscount = 0;
        if($flagPackageContent) {
            // notes: use existing
            $grandTotalBeforeDiscount = 100 / (100 - $salesModel['promotionDiscount']) * ($salesModel['grandTotal'] - $salesModel['deliveryCost'] - $salesModel['orderFee'] - $platformFee);
        } else {
            // notes: recalculate new grandtotal
            $newGrandTotal = 0;
            foreach ($salesMenuModel as $salesMenu) {
                $newGrandTotal += ($salesMenu['qty'] * $salesMenu['inclusivePrice']);
            }
            $grandTotalBeforeDiscount = $newGrandTotal;
        }

        return $grandTotalBeforeDiscount;
    }

    public static function findSalesAsArray($salesNum) {
        $connection = Yii::$app->getDb();
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();

        $mainSalesModel = SalesHead::getMainSalesRawQuery($branchID, NULL, $salesNum);
        if (!$mainSalesModel) {
            return null;
        }

        $salesPaymentsModel = $connection->createCommand("SELECT
            tr_salespayment.*,
            ms_paymentmethod.paymentMethodTypeID,
            ms_paymentmethod.paymentMethodName,
            ms_paymentmethod.flagUseEmployeeLimit,
            ms_paymentmethod.posExternalPaymentID,
            ms_paymentmethod.depositSourceID,
            ms_paymentmethod.voucherSourceID,
            lk_paymentmethodtype.paymentMethodTypeName
          FROM
            tr_salespayment
          LEFT JOIN
            ms_paymentmethod ON tr_salespayment.paymentMethodID = ms_paymentmethod.paymentMethodID
          LEFT JOIN
            lk_paymentmethodtype ON ms_paymentmethod.paymentMethodTypeID = lk_paymentmethodtype.paymentMethodTypeID
          WHERE
            tr_salespayment.salesNum = '$salesNum'")->queryAll();

        $salesQueryModel = SalesHead::getOrderRawQuery($salesNum, 'tr_saleshead');
        $command = $connection->createCommand($salesQueryModel);
        $salesModel = $command->queryOne();

        $salesLinkModel = [];
        if ($salesModel['linkSalesNum']) {
          $salesLinkModel = $connection->createCommand(SalesHead::getOrderRawQuery($salesNum, 'headLinkSales'))->queryAll();
        }

        if ($salesModel) {
            $salesModel['salesDateIn'] = str_replace("-", "/", $salesModel['salesDateIn']);
            $salesModel['salesDateOut'] = str_replace("-", "/", $salesModel['salesDateOut']);
            $salesModel['statusID'] = intval($salesModel['statusID']);
        }

        $activeMainSalesMenusModel = $connection->createCommand(SalesMenu::getSalesMenuMainRawQuery($salesNum) . "
          ORDER BY tr_salesmenu.batchID, tr_salesmenu.ID")->queryAll();

        $childSalesMenuModel = $connection->createCommand(SalesMenu::getSalesMenuChildRawQuery($salesNum) . "
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesMenuExtraModel = $connection->createCommand(SalesMenuExtra::getSalesExtrasRawQuery($salesNum) . "
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesLinkModelArray = AppHelper::reformatTypeDataMenu($salesLinkModel, 'linkSalesNum');
        $activeMainSalesMenusModelArray = AppHelper::reformatTypeDataMenu($activeMainSalesMenusModel, 'salesNum');
        $childSalesMenuModelArray = AppHelper::reformatTypeDataMenu($childSalesMenuModel, 'menuRefID');
        $salesMenuExtraModelArray = AppHelper::reformatTypeDataMenu($salesMenuExtraModel, 'menuDetailID');

        $activeMainSalesMenus = self::assignSalesMenuPackageExtra($salesNum, $salesModel, $activeMainSalesMenusModelArray, $childSalesMenuModelArray,
                                $salesMenuExtraModelArray, NULL, NULL);

        $extraFields = [
            'salesMenu' => $activeMainSalesMenus,
            'platformFee' => SalesHead::getSalesPlatformFee($salesModel['salesNum'])
        ];

        $salesLink = [];
        if (isset($salesLinkModelArray[$salesModel['salesNum']])) {
            foreach ($salesLinkModelArray[$salesModel['salesNum']] as $link) {
                $link = self::getOtherAttributeSalesHead(AppHelper::reformatTypeDataHead($link), $activeMainSalesMenusModelArray, $settings);
                $activeLinkMainSalesMenus = self::assignSalesMenuPackageExtra($link['salesNum'], $link, $activeMainSalesMenusModelArray, $childSalesMenuModelArray,
                                            $salesMenuExtraModelArray, NULL, NULL);
                $linkExtraFields = [
                    'salesMenu' => $activeLinkMainSalesMenus
                ];
                $salesLink[] = array_merge($link, $linkExtraFields);
            }
        }

        $salesPaymentModelArray = AppHelper::reformatTypeDataMenu($salesPaymentsModel, 'salesNum');

        $salesPaymentArray = [];
        if (isset($salesPaymentModelArray[$salesModel['salesNum']])) {
          foreach ($salesPaymentModelArray[$salesModel['salesNum']] as $payment) {
            $salesPaymentArray[] = $payment;
          }
        }

        return [
            'order' => array_merge($salesModel, $extraFields),
            'salesLink' => $salesLink,
            'salesPayment' => $salesPaymentArray,
            'salesParent' => $salesModel['salesNum'] != $mainSalesModel['salesNum'] ? $mainSalesModel : null,
            'visitPurpose' => $salesModel['visitPurposeName']
        ];
    }

    public static function findOutstandingOrderListAsArray($token) {

        $connection = Yii::$app->getDb();

        $branchID = Setting::getCurrentBranch();
        $userModel = null;
        if ($token) {
            $posUserTableName = PosUser::tableName();
            $userModel = "SELECT username 
                FROM $posUserTableName
                WHERE $posUserTableName.posAuthKey = :posAuthKey";

            $command = $connection->createCommand($userModel);
            $command->bindValue(':posAuthKey', $token);
            $userModel = $command->queryOne();
        }

        $salesHeadTableName = SalesHead::tableName();
        $msTableName = Table::tableName();
        $msTableSectionTableName = TableSection::tableName();
        $trTableUsageTableName = TableUsage::tableName();
        $msMemberTableName = Member::tableName();

        $salesOutstanding = "SELECT 
            $salesHeadTableName.salesNum,
            $trTableUsageTableName.expiredTime,
            $trTableUsageTableName.username,
            $msTableSectionTableName.tableSectionName,
            $msTableName.tableName,
            $salesHeadTableName.paxTotal,
            $salesHeadTableName.grandTotal,
            $salesHeadTableName.roundingTotal,
            $salesHeadTableName.additionalInfo,
            $salesHeadTableName.tableID,
            IF($salesHeadTableName.memberID <> 0, $msMemberTableName.memberName, 'Non Member') as memberName
            FROM $salesHeadTableName
            INNER JOIN $msTableName ON $salesHeadTableName.tableID = $msTableName.tableID
            LEFT JOIN $trTableUsageTableName ON $salesHeadTableName.salesNum = $trTableUsageTableName.referenceID
            LEFT JOIN $msTableSectionTableName ON $msTableName.tableSectionID = $msTableSectionTableName.tableSectionID
            LEFT JOIN $msMemberTableName ON $salesHeadTableName.memberCode = $msMemberTableName.memberCode
            WHERE $salesHeadTableName.branchID = :branchID 
            AND $salesHeadTableName.salesDateOut IS NULL";

        $command = $connection->createCommand($salesOutstanding);
        $command->bindValue(':branchID', $branchID);
        $salesModelOutstanding = $command->queryAll();

        $salesModelArr = [];
        $now = new DateTime();
        foreach ($salesModelOutstanding as $sales) {
            $salesArr = $sales;
            if ($sales['expiredTime'] == null) {
                $salesArr['lockStatus'] = false;
            } else {
                $timeDiff = strtotime($sales['expiredTime']) - strtotime($now->format('Y-m-d H:i:s'));
                if ($userModel == null) {
                    $salesArr['lockStatus'] = $timeDiff > 0;
                } else {
                    $salesArr['lockStatus'] = $timeDiff > 0 && $sales['username'] != $userModel['username'];
                }
            }

            if ($salesArr['lockStatus']) {
                $salesArr['lockUser'] = $sales['username'] !== null ? $sales['username'] : '';
            } else {
                $salesArr['lockUser'] = null;
            }
            $salesArr['tableSectionName'] = $sales['tableSectionName'] !== null ? $sales['tableSectionName'] : '';

            $salesModelArr[] = $salesArr;
        }

        return $salesModelArr;
    }

    public static function findOutstandingTakeAwayAsArray($token)
    {
        $connection = Yii::$app->getDb();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        $userModel = null;
        if ($token) {
            $posUserTableName = PosUser::tableName();
            $userModel = "
                SELECT username 
                FROM $posUserTableName
                WHERE $posUserTableName.posAuthKey = :posAuthKey
            ";
            $command = $connection->createCommand($userModel);
            $command->bindValue(':posAuthKey', $token);
            $userModel = $command->queryOne();
        }

        $posModeID = $branchModel ? $branchModel->posModeID : 1;

        $salesHeadTableName = SalesHead::tableName();
        $msTableName = Table::tableName();
        $memberTableName = Member::tableName();
        $promotionTableName = PromotionHead::tableName();
        $statusTableName = Status::tableName();
        $trTableUsageTableName = TableUsage::tableName();
        $salesMenuTableName = SalesMenu::tableName();

        $msTableSectionTableName = TableSection::tableName();


        $salesOutstanding = "
            SELECT DISTINCT
                $salesHeadTableName.*,
                $msTableName.tableName,
                $trTableUsageTableName.*,
                COALESCE($memberTableName.memberName, 'Non Member') AS memberName,
                $memberTableName.memberAddress,
                $promotionTableName.*,
                $statusTableName.*
            FROM 
                $salesHeadTableName
            LEFT JOIN
                $msTableName ON $salesHeadTableName.tableID = $msTableName.tableID
            LEFT JOIN 
                $trTableUsageTableName ON $salesHeadTableName.salesNum = $trTableUsageTableName.referenceID
            LEFT JOIN
                $memberTableName ON $salesHeadTableName.memberCode = $memberTableName.memberCode
            LEFT JOIN
                $statusTableName ON $salesHeadTableName.statusID = $statusTableName.statusID
            LEFT JOIN
                $promotionTableName ON $salesHeadTableName.promotionID = $promotionTableName.promotionID
            LEFT JOIN 
                $salesMenuTableName ON $salesHeadTableName.salesNum = $salesMenuTableName.salesNum
            WHERE 
                $salesHeadTableName.branchID = :branchID 
                    AND $salesHeadTableName.salesDateOut IS NULL
        ";

        if ($posModeID == 1) {
            $salesOutstanding .= " AND $salesHeadTableName.tableID = 0";
        }

        $command = $connection->createCommand($salesOutstanding);
        $command->bindValue(':branchID', $branchID);
        $salesModelOutstanding = $command->queryAll();

        $salesModelArr = [];
        $now = new DateTime();
        foreach ($salesModelOutstanding as $sales) {
            $salesArr = $sales;

            if ($sales['expiredTime'] == null) {
                $salesArr['lockStatus'] = false;
            } else {
                $timeDiff = strtotime($sales['expiredTime']) - strtotime($now->format('Y-m-d H:i:s'));
                if ($userModel == null) {
                    $salesArr['lockStatus'] = $timeDiff > 0;
                } else {
                    $salesArr['lockStatus'] = $timeDiff > 0 && $sales['username'] != $userModel['username'];
                }
            }

            if ($salesArr['lockStatus']) {
                $salesArr['lockUser'] = $sales !== null ? $sales['username'] : '';
            } else {
                $salesArr['lockUser'] = null;
            }

            if(isset($sales['flagInclusive'])) {
              $salesArr['flagInclusive'] = (int) $sales['flagInclusive'];
              $salesArr['tableID'] = (int) $sales['tableID'];
            }

            $salesModelArr[] = $salesArr;
        }

        return $salesModelArr;
    }


    public static function groupingOrderForBilling($salesMenusModel, $flagInclusive = 0, $branchID = 0, $printPayment = false, $printBill = false, $forceSeparateMenuNotes = false) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);        
        $newSalesMenus = [];
        $issetNonSalesPayment = false;
        if ($printPayment && $salesMenusModel) {
            $salesNum = $salesMenusModel[0]['salesNum'];
            $salesPaymentModel = SalesPayment::find()->select(['paymentMethodID'])->where(['salesNum' => $salesNum]);
            $nonSalesPaymentMethodModel = PaymentMethod::find()
                        ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                        ->andWhere(['=', 'paymentMethodTypeID', 7])
                        ->one();
            if ($nonSalesPaymentMethodModel) {
                $issetNonSalesPayment = true;
            }
        }

        $showPrintingMenuNotes = $settings['Show Printing Menu Notes'] == 1 ? true : false;
        foreach ($salesMenusModel as $salesMenu) {
            if ($salesMenu->price > 0 || $salesMenu->menu->flagCustomerPrint) {
                $arr = Arrays::find($newSalesMenus,
                        function ($nsm) use ($salesMenu, $showPrintingMenuNotes, $printPayment, $printBill, $forceSeparateMenuNotes) {
                        // @Notes: Compare all related fields. If the value same, then do the grouping
                        if ($showPrintingMenuNotes) {
                            return $nsm->salesNum == $salesMenu->salesNum &&
                                $nsm->menuID == $salesMenu->menuID && $nsm->price == $salesMenu->price &&
                                $nsm->discount == $salesMenu->discount && $nsm->otherTax == $salesMenu->otherTax &&
                                $nsm->vat == $salesMenu->vat && $nsm->otherTaxOnVat == $salesMenu->otherTaxOnVat &&
                                $nsm->statusID == $salesMenu->statusID && $nsm->promotionDetailID == $salesMenu->promotionDetailID &&
                                $nsm->promotionVoucherCode == $salesMenu->promotionVoucherCode &&
                                $nsm->menuPromotionID == $salesMenu->menuPromotionID &&
                                ($forceSeparateMenuNotes ? $nsm->notes == $salesMenu->notes : true) &&
                                SalesHead::compareMenuExtras($nsm->salesExtras,
                                    $salesMenu->salesExtras) &&
                                SalesHead::compareChildSalesMenus($nsm->childSalesMenus,
                                    $salesMenu->childSalesMenus, $printPayment, $printBill, $showPrintingMenuNotes) && trim(strtolower($nsm->notes)) == trim(strtolower($salesMenu->notes)) && $nsm->customMenuName == $salesMenu->customMenuName;
                        } else {
                            return $nsm->salesNum == $salesMenu->salesNum &&
                                $nsm->menuID == $salesMenu->menuID && $nsm->price == $salesMenu->price &&
                                $nsm->discount == $salesMenu->discount && $nsm->otherTax == $salesMenu->otherTax &&
                                $nsm->vat == $salesMenu->vat && $nsm->otherTaxOnVat == $salesMenu->otherTaxOnVat &&
                                $nsm->statusID == $salesMenu->statusID && $nsm->promotionDetailID == $salesMenu->promotionDetailID &&
                                $nsm->promotionVoucherCode == $salesMenu->promotionVoucherCode &&
                                $nsm->menuPromotionID == $salesMenu->menuPromotionID &&
                                ($forceSeparateMenuNotes ? $nsm->notes == $salesMenu->notes : true) &&
                                SalesHead::compareMenuExtras($nsm->salesExtras,
                                    $salesMenu->salesExtras) &&
                                SalesHead::compareChildSalesMenus($nsm->childSalesMenus,
                                    $salesMenu->childSalesMenus, $printPayment, $printBill, $showPrintingMenuNotes) && $nsm->customMenuName == $salesMenu->customMenuName;
                        }
                    });

                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $perPackage) {
                        if ($flagInclusive) {
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                if ($printPayment && $issetNonSalesPayment) {
                                    $perPackage->total = $perPackage->total;
                                } else {
                                    $perPackage->total = $perPackage->qty * $perPackage->inclusivePrice - $perPackage->inclusiveDiscountValue;                           
                                    $perPackage->discountValue = $perPackage->inclusiveDiscountValue;
                                }
                            }
                        }
                    }
                }

                if ($salesMenu->salesExtras) {
                    foreach ($salesMenu->salesExtras as $perExtra) {
                        if ($flagInclusive) {
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                if ($printPayment && $issetNonSalesPayment) {
                                    $perExtra->total = $perExtra->total;
                                } else {
                                    $perExtra->total = $perExtra->qty * $perExtra->inclusivePrice - $perExtra->inclusiveDiscountValue;                          
                                    $perExtra->discountValue = $perExtra->inclusiveDiscountValue;
                                }
                            }
                        }
                    }
                }
            
                if (!$arr) {
                    if ($flagInclusive) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($printPayment && $issetNonSalesPayment) {
                                $salesMenu->total = $salesMenu->total;
                            } else {
                                $salesMenu->total = $salesMenu->qty * $salesMenu->inclusivePrice - $salesMenu->inclusiveDiscountValue;
                                $salesMenu->discountValue = $salesMenu->inclusiveDiscountValue;
                            }                            
                        }
                    }
                    
                    $newSalesMenus[] = clone $salesMenu;
                } else {
                    if ($flagInclusive) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($printPayment && $issetNonSalesPayment) {
                                $salesMenu->total = $salesMenu->total;
                            } else {
                                $salesMenu->total = $salesMenu->qty * $salesMenu->inclusivePrice;
                            }                            
                        } else {
                            $salesMenu->total = $salesMenu->total + $salesMenu->discountValue;
                        }
                    }

                    //$salesMenu->total = $flagInclusive ? $salesMenu->total + $salesMenu->discountValue : $salesMenu->total;
                    $arr['qty'] = $arr['qty'] + $salesMenu->qty;
                    $arr['total'] = (float) $arr['total'] + (float) $salesMenu->total;
                    
                    if (!$flagInclusive) {
                        $arr['discountValue'] = (float) $arr['discountValue'] + $salesMenu->discountValue;
                    }
                }
            }
        }

        return $newSalesMenus;
    }

    public static function groupingOrderForBillingArray($salesHead, $salesMenusModel, $settings, $flagInclusive = 0, $branchID = 0, $printPayment = false, $printBill = false, $forceSeparateMenuNotes = false) {
      $connection = Yii::$app->getDb();
      
      $newSalesMenus = [];
      $issetNonSalesPayment = false;
      if ($printPayment && $salesMenusModel) {
          $salesNum = $salesHead['salesNum'];
          $nonSalesPaymentMethodModel = $connection->createCommand("SELECT DISTINCT
              ms_paymentmethod.paymentMethodID
            FROM
              ms_paymentmethod
            LEFT JOIN
              tr_salespayment ON ms_paymentmethod.paymentMethodID = tr_salespayment.paymentMethodID
            WHERE
              tr_salespayment.salesNum = '$salesNum'
              AND ms_paymentmethod.paymentMethodTypeID = 7")->queryScalar();
          
          if ($nonSalesPaymentMethodModel) {
              $issetNonSalesPayment = true;
          }
      }

      $tempNewSales = [];
      $showPrintingMenuNotes = $settings['Show Printing Menu Notes'] == 1 ? true : false;
      foreach ($salesMenusModel as $salesMenu) {
          if ($salesMenu['price'] > 0 || $salesMenu['flagCustomerPrint']) {
              $arr = Arrays::find($tempNewSales,
                  function ($nsm) use ($salesMenu, $showPrintingMenuNotes, $printPayment, $printBill, $forceSeparateMenuNotes) {
                    // @Notes: Compare all related fields. If the value same, then do the grouping
                    if ($showPrintingMenuNotes) {
                        return $nsm['salesNum'] == $salesMenu['salesNum'] &&
                            $nsm['menuID'] == $salesMenu['menuID'] && $nsm['price'] == $salesMenu['price'] &&
                            $nsm['discount'] == $salesMenu['discount'] && $nsm['otherTax'] == $salesMenu['otherTax'] &&
                            $nsm['vat'] == $salesMenu['vat'] && $nsm['otherTaxOnVat'] == $salesMenu['otherTaxOnVat'] &&
                            $nsm['statusID'] == $salesMenu['statusID'] && $nsm['promotionDetailID'] == $salesMenu['promotionDetailID'] &&
                            $nsm['promotionVoucherCode'] == $salesMenu['promotionVoucherCode'] &&
                            $nsm['menuPromotionID'] == $salesMenu['menuPromotionID'] &&
                            ($forceSeparateMenuNotes ? $nsm['notes'] == $salesMenu['notes'] : true) &&
                            SalesHead::compareMenuExtrasArray($nsm['extras'],
                                $salesMenu['extras']) &&
                            SalesHead::compareChildSalesMenusArray($nsm['packages'],
                                $salesMenu['packages'], $printPayment, $printBill, $showPrintingMenuNotes) && trim(strtolower($nsm['notes'])) == trim(strtolower($salesMenu['notes'])) && $nsm['customMenuName'] == $salesMenu['customMenuName'];
                    } else {
                        return $nsm['salesNum'] == $salesMenu['salesNum'] &&
                            $nsm['menuID'] == $salesMenu['menuID'] && $nsm['price'] == $salesMenu['price'] &&
                            $nsm['discount'] == $salesMenu['discount'] && $nsm['otherTax'] == $salesMenu['otherTax'] &&
                            $nsm['vat'] == $salesMenu['vat'] && $nsm['otherTaxOnVat'] == $salesMenu['otherTaxOnVat'] &&
                            $nsm['statusID'] == $salesMenu['statusID'] && $nsm['promotionDetailID'] == $salesMenu['promotionDetailID'] &&
                            $nsm['promotionVoucherCode'] == $salesMenu['promotionVoucherCode'] &&
                            $nsm['menuPromotionID'] == $salesMenu['menuPromotionID'] &&
                            ($forceSeparateMenuNotes ? $nsm['notes'] == $salesMenu['notes'] : true) &&
                            SalesHead::compareMenuExtrasArray($nsm['extras'],
                                $salesMenu['extras']) &&
                            SalesHead::compareChildSalesMenusArray($nsm['packages'],
                                $salesMenu['packages'], $printPayment, $printBill, $showPrintingMenuNotes) && $nsm['customMenuName'] == $salesMenu['customMenuName'];
                    }
                  });

              $tempPackages = [];
              if ($salesMenu['packages']) {
                  foreach ($salesMenu['packages'] as $perPackage) {
                      if ($flagInclusive) {
                          if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                              if ($printPayment && $issetNonSalesPayment) {
                                  $perPackage['total'] = $perPackage['total'];
                              } else {
                                  $perPackage['total'] = $perPackage['qty'] * $perPackage['inclusivePrice'] - $perPackage['inclusiveDiscountValue'];                           
                                  $perPackage['discountValue'] = $perPackage['inclusiveDiscountValue'];
                              }
                          }
                      }
                      $tempPackages[] = $perPackage;
                  }
              }
              $salesMenu['packages'] = $tempPackages;

              $tempExtras = [];
              if ($salesMenu['extras']) {
                  foreach ($salesMenu['extras'] as $perExtra) {
                      $perExtra['displayPriceValue'] = $perExtra['price'];
                      if ($flagInclusive) {
                          if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                              if ($printPayment && $issetNonSalesPayment) {
                                  $perExtra['total'] = $perExtra['total'];
                              } else {
                                  $perExtra['total'] = $perExtra['qty'] * $perExtra['inclusivePrice'] - $perExtra['inclusiveDiscountValue'];                          
                                  $perExtra['discountValue'] = $perExtra['inclusiveDiscountValue'];
                              }
                          }
                          $perExtra['displayPriceValue'] = ($perExtra['total'] + $perExtra['discountValue']) / $perExtra['qty'];
                      }
                      $tempExtras[] = $perExtra;
                  }
              }
              $salesMenu['extras'] = $tempExtras;

              if (!$arr) {
                  if ($flagInclusive) {
                      if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                          if ($printPayment && $issetNonSalesPayment) {
                              $salesMenu['total'] = $salesMenu['total'];
                          } else {
                              $salesMenu['total'] = $salesMenu['qty'] * $salesMenu['inclusivePrice'] - $salesMenu['inclusiveDiscountValue'];
                              $salesMenu['discountValue'] = $salesMenu['inclusiveDiscountValue'];
                          }                            
                      }
                  }

                  $tempNewSales[] = $salesMenu;
                  $newSalesMenus[$salesMenu['ID']] = $salesMenu;
              } else {
                  $newArr = $newSalesMenus[$arr['ID']];
                  
                  if ($flagInclusive) {
                      if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                          if ($printPayment && $issetNonSalesPayment) {
                              $salesMenu['total'] = $salesMenu['total'];
                          } else {
                              $salesMenu['total'] = $salesMenu['qty'] * $salesMenu['inclusivePrice'];
                          }                            
                      } else {
                          $salesMenu['total'] = $salesMenu['total'] + $salesMenu['discountValue'];
                      }
                  }

                  $newArr['qty'] = $newArr['qty'] + $salesMenu['qty'];
                  $newArr['total'] = (float) $newArr['total'] + (float) $salesMenu['total'];
                  
                  if (!$flagInclusive) {
                    $newArr['discountValue'] = (float) $newArr['discountValue'] + $salesMenu['discountValue'];
                  }

                  $newSalesMenus[$arr['ID']] = $newArr;
              }

          }
      }

      return array_values($newSalesMenus);
    }

    public static function compareMenuExtras($salesMenuExtras1, $salesMenuExtras2) {
        if (count($salesMenuExtras1) != count($salesMenuExtras2)) {
            return false;
        }

        foreach ($salesMenuExtras2 as $salesMenuExtra) {
            $arr = Arrays::find($salesMenuExtras1,
                    function ($sme) use ($salesMenuExtra) {
                    // @Notes: Compare all related fields. If the value same, then do the grouping
                    return $sme->salesNum == $salesMenuExtra->salesNum && $sme->menuExtraID == $salesMenuExtra->menuExtraID &&
                        $sme->price == $salesMenuExtra->price && $sme->qty == $salesMenuExtra->qty && $sme->discount == $salesMenuExtra->discount &&
                        $sme->otherTax == $salesMenuExtra->otherTax && $sme->vat == $salesMenuExtra->vat &&
                        $sme->otherTaxOnVat == $salesMenuExtra->otherTaxOnVat && $sme->statusID == $salesMenuExtra->statusID;
                });

            if (!$arr) {
                return false;
            }
        }

        return true;
    }

    public static function compareMenuExtrasArray($salesMenuExtras1, $salesMenuExtras2) {
      if (count($salesMenuExtras1) != count($salesMenuExtras2)) {
          return false;
      }

      foreach ($salesMenuExtras2 as $salesMenuExtra) {
          $arr = Arrays::find($salesMenuExtras1,
                  function ($sme) use ($salesMenuExtra) {
                  // @Notes: Compare all related fields. If the value same, then do the grouping
                  return $sme['salesNum'] == $salesMenuExtra['salesNum'] && $sme['menuExtraID'] == $salesMenuExtra['menuExtraID'] &&
                      $sme['price'] == $salesMenuExtra['price'] && $sme['qty'] == $salesMenuExtra['qty'] && $sme['discount'] == $salesMenuExtra['discount'] &&
                      $sme['otherTax'] == $salesMenuExtra['otherTax'] && $sme['vat'] == $salesMenuExtra['vat'] &&
                      $sme['otherTaxOnVat'] == $salesMenuExtra['otherTaxOnVat'] && $sme['statusID'] == $salesMenuExtra['statusID'];
              });

          if (!$arr) {
              return false;
          }
      }

      return true;
    }

    public static function compareChildSalesMenus($childSalesMenus1, $childSalesMenus2, $printPayment = false, $printBill = false, $showPrintingMenuNotes = false) {
        if (count($childSalesMenus1) != count($childSalesMenus2)) {
            return false;
        }

        foreach ($childSalesMenus2 as $childSalesMenu) {
            $arr = Arrays::find($childSalesMenus1,
                    function ($csm) use ($childSalesMenu, $printPayment, $printBill, $showPrintingMenuNotes) {
                    // @Notes: Compare all related fields. If the value same, then do the grouping
                    if (!$printPayment && !$printBill) {
                        return $csm->salesNum == $childSalesMenu->salesNum && $csm->menuID == $childSalesMenu->menuID &&
                            $csm->menuGroupID == $childSalesMenu->menuGroupID &&
                            $csm->price == $childSalesMenu->price && $csm->qty == $childSalesMenu->qty &&
                            $csm->discount == $childSalesMenu->discount &&
                            $csm->otherTax == $childSalesMenu->otherTax && $csm->vat == $childSalesMenu->vat &&
                            $csm->otherTaxOnVat == $childSalesMenu->otherTaxOnVat && $csm->statusID == $childSalesMenu->statusID && 
                            $csm->notes == $childSalesMenu->notes;
                    } else if ($printPayment || $printBill) {
                        if ($showPrintingMenuNotes) {
                            return $csm->salesNum == $childSalesMenu->salesNum && $csm->menuID == $childSalesMenu->menuID &&
                                $csm->menuGroupID == $childSalesMenu->menuGroupID &&
                                $csm->price == $childSalesMenu->price && $csm->qty == $childSalesMenu->qty &&
                                $csm->discount == $childSalesMenu->discount &&
                                $csm->otherTax == $childSalesMenu->otherTax && $csm->vat == $childSalesMenu->vat &&
                                $csm->otherTaxOnVat == $childSalesMenu->otherTaxOnVat && $csm->statusID == $childSalesMenu->statusID && 
                                $csm->notes == $childSalesMenu->notes;
                        } else {
                            return $csm->salesNum == $childSalesMenu->salesNum && $csm->menuID == $childSalesMenu->menuID &&
                                $csm->menuGroupID == $childSalesMenu->menuGroupID &&
                                $csm->price == $childSalesMenu->price && $csm->qty == $childSalesMenu->qty &&
                                $csm->discount == $childSalesMenu->discount &&
                                $csm->otherTax == $childSalesMenu->otherTax && $csm->vat == $childSalesMenu->vat &&
                                $csm->otherTaxOnVat == $childSalesMenu->otherTaxOnVat && $csm->statusID == $childSalesMenu->statusID;
                        }
                        
                    }
                    
                });

            if (!$arr) {
                return false;
            }
        }

        return true;
    }

    public static function compareChildSalesMenusArray($childSalesMenus1, $childSalesMenus2, $printPayment = false, $printBill = false, $showPrintingMenuNotes = false) {
      if (count($childSalesMenus1) != count($childSalesMenus2)) {
          return false;
      }

      foreach ($childSalesMenus2 as $childSalesMenu) {
          $arr = Arrays::find($childSalesMenus1,
                  function ($csm) use ($childSalesMenu, $printPayment, $printBill, $showPrintingMenuNotes) {
                  // @Notes: Compare all related fields. If the value same, then do the grouping
                  if (!$printPayment && !$printBill) {
                      return $csm['salesNum'] == $childSalesMenu['salesNum'] && $csm['menuID'] == $childSalesMenu['menuID'] &&
                          $csm['menuGroupID'] == $childSalesMenu['menuGroupID'] &&
                          $csm['price'] == $childSalesMenu['price'] && $csm['qty'] == $childSalesMenu['qty'] &&
                          $csm['discount'] == $childSalesMenu['discount'] &&
                          $csm['otherTax'] == $childSalesMenu['otherTax'] && $csm['vat'] == $childSalesMenu['vat'] &&
                          $csm['otherTaxOnVat'] == $childSalesMenu['otherTaxOnVat'] && $csm['statusID'] == $childSalesMenu['statusID'] && 
                          $csm['notes'] == $childSalesMenu['notes'];
                  } else if ($printPayment || $printBill) {
                      if ($showPrintingMenuNotes) {
                          return $csm['salesNum'] == $childSalesMenu['salesNum'] && $csm['menuID'] == $childSalesMenu['menuID'] &&
                              $csm['menuGroupID'] == $childSalesMenu['menuGroupID'] &&
                              $csm['price'] == $childSalesMenu['price'] && $csm['qty'] == $childSalesMenu['qty'] &&
                              $csm['discount'] == $childSalesMenu['discount'] &&
                              $csm['otherTax'] == $childSalesMenu['otherTax'] && $csm['vat'] == $childSalesMenu['vat'] &&
                              $csm['otherTaxOnVat'] == $childSalesMenu['otherTaxOnVat'] && $csm['statusID'] == $childSalesMenu['statusID'] && 
                              $csm['notes'] == $childSalesMenu['notes'];
                      } else {
                          return $csm['salesNum'] == $childSalesMenu['salesNum'] && $csm['menuID'] == $childSalesMenu['menuID'] &&
                              $csm['menuGroupID'] == $childSalesMenu['menuGroupID'] &&
                              $csm['price'] == $childSalesMenu['price'] && $csm['qty'] == $childSalesMenu['qty'] &&
                              $csm['discount'] == $childSalesMenu['discount'] &&
                              $csm['otherTax'] == $childSalesMenu['otherTax'] && $csm['vat'] == $childSalesMenu['vat'] &&
                              $csm['otherTaxOnVat'] == $childSalesMenu['otherTaxOnVat'] && $csm['statusID'] == $childSalesMenu['statusID'];
                      }
                      
                  }
                  
              });

          if (!$arr) {
              return false;
          }
      }

      return true;
    }

    public static function updatePrintCount($type, $tableID, $salesNum = null, $flagFirstPayment = false, $requestBill = false) {
        try {
            if ($tableID != 0) {
                $mainSalesModel = SalesHead::findMainSales($tableID, $salesNum);
            } else {
                $mainSalesModel = SalesHead::findMainSales(null, $salesNum);
            }
            if (!$mainSalesModel) {
                throw new Exception('Table not found');
            }

            $mainSalesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;

            $printingCount = 1;

            if ($flagFirstPayment) {
                $salesPaymentModel = SalesPayment::find()->select(['paymentMethodID'])->where(['salesNum' => $mainSalesModel->salesNum]);
                $printingCountModel = PaymentMethod::find()
                    ->select([
                        'printedCount' => new Expression('MAX(printedCount)')
                    ])
                    ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                    ->one();
    
                $printingCount = (int) $printingCountModel->printedCount;
            }
            
            if ($type == self::PRINT_BILL) {
                $mainSalesModel->billingPrintCount += 1;
            } else {
                if ($printingCount > 0) {
                    $mainSalesModel->paymentPrintCount += 1;
                }
            }
            if (!$mainSalesModel->save()) {
                throw new Exception('Failed to update sales head');
            }

            $linkedSalesModel = SalesHead::findLinkSalesHeads($mainSalesModel->salesNum);
            foreach ($linkedSalesModel as $salesModel) {
                $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                if ($type == self::PRINT_BILL) {
                    $salesModel->billingPrintCount += 1;
                } else {
                    if ($printingCount > 0) {  //from paymment method
                        $salesModel->paymentPrintCount += 1;
                    }
                }
                if (!$salesModel->save()) {
                    throw new Exception('Failed to update linked sales head');
                }
            }

            return $requestBill ? $mainSalesModel->billingPrintCount : true;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public static function updateBillingPrintCount($tableID, $salesNum = null) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($tableID != 0) {
                $mainSalesModel = SalesHead::findMainSales($tableID, $salesNum);
            } else {
                $mainSalesModel = SalesHead::findMainSales(null, $salesNum);
            }
            if (!$mainSalesModel) {
                throw new Exception('Table not found');
            }

            $mainSalesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
            $mainSalesModel->billingPrintCount += 1;
            if (!$mainSalesModel->save()) {
                throw new Exception('Failed to update sales head');
            }

            $linkedSalesModel = SalesHead::findLinkSalesHeads($mainSalesModel->salesNum);
            foreach ($linkedSalesModel as $salesModel) {
                $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                $salesModel->billingPrintCount += 1;
                if (!$salesModel->save()) {
                    throw new Exception('Failed to update linked sales head');
                }
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    public static function getTotal($salesNum, $sumField) {
        $mainSalesModel = SalesHead::findMainSales(null, $salesNum);
        if (!$mainSalesModel) {
            return 0;
        }

        $salesLinkQuery = (new Query())
            ->select('linkSalesNum')
            ->from(SalesLink::tableName())
            ->andWhere(['salesNum' => $mainSalesModel->salesNum]);
        $sumTotal = (new Query())
            ->select(['subtotal' => new Expression('SUM(' . $sumField . ')')])
            ->from(SalesHead::tableName())
            ->andWhere(['OR',
                ['salesNum' => $mainSalesModel->salesNum],
                ['IN', 'salesNum', $salesLinkQuery]
            ])
            ->andWhere(['IS', 'salesDateOut', null])
            ->scalar();

        return $sumTotal;
    }

    public static function syncUpdate($salesNum, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        try {
            SalesHead::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['branchID' => $branchID], ['salesNum' => $salesNum]
            ]);

            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    private function calculateTotal() {
        $settings = Setting::getPrintingSettings();
        $flagInclusive = self::getInclusiveFlag($this->branchID,
                $this->visitPurposeID);
        $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
                $this->visitPurposeID);
        $roundingMode = isset($settings['Rounding Mode']) ? $settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($settings['Rounding Nearest Value']) ? $settings['Rounding Nearest Value'] : 0;

        if (isset($this->transactionModeID) && in_array($this->transactionModeID, [5,7,8,9,10])) {
            //force set rounding to 0 (GoFood, Hubster, GrabFood)
            $roundingNearestValue = 0;
        }

        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

        $deliveryCostTaxSetting = Setting::getValue1('EZO', 'Delivery Cost Tax');
        $deliveryCostTax = !is_null($deliveryCostTaxSetting) ? $deliveryCostTaxSetting : 0;
        $taxValue = (float) $mapBranchModel->taxValue;
        $vatSubject = (float) $mapBranchModel->vatSubject;
        $otherTaxValue = (float) $mapBranchModel->additionalTaxValue;

        $rounding = $roundingNearestValue;

        $sumData = NULL;
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        $otherTaxOnVat = (float) $mapBranchModel->flagOtherTaxVat;
        $platformFee = 0;
        $platformFee = 0;

        $nonSalesPaymentMethod = (new Query())
            ->select(
                ['b.paymentMethodTypeID']
            )
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(PaymentMethod::tableName() . ' b',
                'b.paymentMethodID = a.paymentMethodID and b.paymentMethodTypeID = 7')
            ->andWhere(['a.salesNum' => $this->salesNum])
            ->one();

        $promotionTypeID = 0;
        $promotionModel = PromotionHead::findOne($this->promotionID);
        if ($promotionModel) {
            $promotionTypeID = $promotionModel->promotionTypeID;
        }

        if (isset($this->tempMenuSubtotal)) {
            $tempMenuSubtotal = $this->tempMenuSubtotal;
        } else {
            $tempMenuSubtotal = 0;
        }

        if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                $grandTotal = $nonSalesPaymentMethod ? 'SUM(qty * price)' : 'SUM(qty * inclusivePrice - inclusiveDiscountValue)';
                $sumMenu = (new Query())
                    ->select(['subtotal' => 'SUM(qty * price)',
                        'menuDiscountTotal' => 'SUM(CASE '
                        .'WHEN ms_promotionhead.promotionTypeID = 5 THEN tr_salesmenu.discountValue '
                        .'WHEN ms_promotionhead.promotionTypeID = 9 THEN tr_salesmenu.discountValue ELSE '
                        . 'discountValue END)',
                        'otherTaxTotal' => "SUM(otherTaxValue)",
                        'vatTotal' => "SUM(vatValue)",
                        'otherVatTotal' => "SUM(otherVatValue)",
                        'grandTotal' => "$grandTotal"])
                    ->from(SalesMenu::tableName())
                    ->leftJoin(PromotionHead::tableName(),
                        'tr_salesmenu.promotionDetailID = ms_promotionhead.promotionID')
                    ->andWhere(['salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'statusID', [13, 14, 34]])
                    ->andWhere(['OR',
                        ['menuRefID' => 0],
                        'menuRefID = ID'
                    ])
                    ->one();

                $packageGrandTotal = $nonSalesPaymentMethod ? 'SUM(a.qty * (b.qty * b.price))' : 'SUM(a.qty * (b.qty * b.inclusivePrice - b.inclusiveDiscountValue))';
                $sumPackage = (new Query())
                    ->select(['subtotal' => 'SUM(a.qty * b.qty * b.price)',
                        'menuDiscountTotal' => "SUM(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagPackageContent = 1 THEN a.qty * b.discountValue ELSE a.qty * b.discountValue END)",
                        'otherTaxTotal' => "SUM(a.qty * b.otherTaxValue)",
                        'vatTotal' => "SUM(a.qty * b.vatValue)",
                        'otherVatTotal' => "SUM(a.qty * b.otherVatValue)",
                        'grandTotal' => "$packageGrandTotal"])
                    ->from(SalesMenu::tableName() . ' a')
                    ->leftJoin(['promotion' => PromotionHead::tableName()], 'promotion.promotionID = a.promotionDetailID')
                    ->leftJoin(SalesMenu::tableName() . ' b',
                        'a.salesNum = b.salesNum AND b.menuRefID <> 0 AND a.ID = b.menuRefID')
                    ->andWhere(['a.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                    ->andWhere(['OR',
                        ['a.menuRefID' => 0],
                        'a.menuRefID = a.ID'
                    ])
                    ->andWhere('b.ID <> b.menuRefID')
                    ->one();

                $extraGrandTotal = $nonSalesPaymentMethod ? 'SUM(sm.qty * (sme.qty * sme.price))' : 'SUM(sm.qty * (sme.qty * sme.inclusivePrice - sme.inclusiveDiscountValue))';
                $sumExtra = (new Query())
                    ->select(['subtotal' => 'SUM(sm.qty * sme.qty * sme.price)',
                        'menuDiscountTotal' => "SUM(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagMenuExtra = 1 THEN sm.qty * sme.discountValue ELSE sm.qty * sme.discountValue END)",
                        'otherTaxTotal' => "SUM(sm.qty * sme.otherTaxValue)",
                        'vatTotal' => "SUM(sm.qty * sme.vatValue)",
                        'otherVatTotal' => "SUM(sm.qty * sme.otherVatValue)",
                        'grandTotal' => "$extraGrandTotal"])
                    ->from(SalesMenuExtra::tableName() . ' sme')
                    ->innerJoin(SalesMenu::tableName() . ' sm',
                        'sme.menuDetailID = sm.ID')
                    ->leftJoin(['promotion' => PromotionHead::tableName()], 'promotion.promotionID = sm.promotionDetailID')
                    ->andWhere(['sme.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'sm.statusID', [13, 14, 34]])
                    ->one();

                $deliveryCostTaxValue = $deliveryCostTax ? ($this->deliveryCost * $taxValue / 100) : 0;
                $sumData['subtotal'] = $sumMenu['subtotal'] + $sumPackage['subtotal'] + $sumExtra['subtotal'];
                $sumData['menuDiscountTotal'] = $sumMenu['menuDiscountTotal'] + $sumPackage['menuDiscountTotal'] + $sumExtra['menuDiscountTotal'];
                $sumData['otherTaxTotal'] = $sumMenu['otherTaxTotal'] + $sumPackage['otherTaxTotal'] + $sumExtra['otherTaxTotal'];
                $sumData['vatTotal'] = $sumMenu['vatTotal'] + $deliveryCostTaxValue + $sumPackage['vatTotal'] + $sumExtra['vatTotal'];
                $sumData['otherVatTotal'] = $sumMenu['otherVatTotal'] + $sumPackage['otherVatTotal'] + $sumExtra['otherVatTotal'];
                $sumData['grandTotal'] = $sumMenu['grandTotal'] + $sumPackage['grandTotal'] + $sumExtra['grandTotal'];

                if ($nonSalesPaymentMethod) {
                    $sumData['grandTotal'] = $sumMenu['grandTotal'] + $sumPackage['grandTotal'] + $sumExtra['grandTotal'];
                }

                $this->load(['SalesHead' => $sumData]);
                $this->calculateDiscountTotal($this, true, $tempMenuSubtotal);
                $ineligiblePromotion = UpdateOrder::ineligiblePromotion($this);
                if ($ineligiblePromotion && $this->flagAutoRemovePromotion)
                {
                    $this->promotionID = 0;
                    $this->promotionDiscount = 0;
                    $this->promotionVoucherCode = '';
                }

                $platformFee = SalesPlatformFee::getPlatformFeeTotal($this->salesNum, $this->platformFee, $this->subtotal, $this->selfOrderPaymentMethodID);

                $orderFee = $this->orderFee ?  $this->orderFee : 0;

                $this->grandTotal = $this->subtotal + $this->deliveryCost + $orderFee - $this->discountTotal - $this->menuDiscountTotal + $this->otherTaxTotal + $this->vatTotal + $this->otherVatTotal + $this->voucherTotal;
                $this->grandTotal = ROUND($this->grandTotal, 3);
                $finalGrandTotal = $this->grandTotal;
            } else {
                $sumMenu = (new Query())
                ->select([
                    'subtotal' => 'COALESCE(SUM(a.qty * a.price), 0)',
                    'grandTotal' => 'COALESCE(SUM(a.qty * a.inclusivePrice), 0)',
                    'menuDiscountTotal' => 'COALESCE(SUM(a.discountValue), 0)'
                ])
                ->from(SalesMenu::tableName() . ' a')
                ->leftJoin(PromotionHead::tableName() . ' c',
                    "a.promotionDetailID = c.promotionID ")
                ->andWhere(['salesNum' => $this->salesNum])
                ->andWhere(['statusID' => [13, 14, 34]])
                ->andWhere(['OR',
                    ['a.menuRefID' => 0],
                    'a.menuRefID = a.ID'
                ])
                ->one();

                $menuSubtotal = $sumMenu['subtotal'];
                $menuGrandtotalBeforeDiscount = $sumMenu['grandTotal'];
                $menuDiscount = $sumMenu['menuDiscountTotal'];
                $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;

                $sumMenu1 = (new Query())
                    ->select([
                        "otherTaxTotal" => "SUM(otherTaxValue)",
                        "vatTotal" => "SUM(vatValue)",
                        "otherVatTotal" => "SUM(otherVatValue)"
                    ])
                    ->from(SalesMenu::tableName())
                    ->andWhere(['salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'statusID', [13, 14, 34]])
                    ->andWhere(['OR',
                        ['menuRefID' => 0],
                        'menuRefID = ID'
                    ])
                    ->one();

                $menuSubtotalAfterDiscount = $menuGrandtotalAfterDiscount + $menuDiscount - $sumMenu1['vatTotal'] - $sumMenu1['otherTaxTotal'];

                $sumPackage = (new Query())
                    ->select([
                        'subtotal' => 'SUM(a.qty * (b.qty * b.price))',
                        'grandTotal' => 'SUM(a.qty * (b.qty * b.inclusivePrice))',
                        'menuDiscountTotal' => 'SUM(a.qty * b.discountValue)',
                    ])
                    ->from(SalesMenu::tableName() . ' a')
                    ->leftJoin(SalesMenu::tableName() . ' b',
                        'a.salesNum = b.salesNum AND b.menuRefID <> 0 AND a.ID = b.menuRefID')
                    ->andWhere(['a.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                    ->andWhere(['OR',
                        ['a.menuRefID' => 0],
                        'a.menuRefID = a.ID'
                    ])
                    ->andWhere('b.ID <> b.menuRefID')
                    ->one();

                $menuPackageSubtotal = $sumPackage['subtotal'] ? $sumPackage['subtotal'] : 0;
                $menuGrandtotalBeforeDiscountPackage = $sumPackage['grandTotal'] ? $sumPackage['grandTotal'] : 0;
                $menuDiscountPackage = $sumPackage['menuDiscountTotal'] ? $sumPackage['menuDiscountTotal'] : 0;
                $menuGrandtotalAfterDiscountPackage = $menuGrandtotalBeforeDiscountPackage - $menuDiscountPackage;

                $sumPackage1 = (new Query())
                    ->select([
                        "otherTaxTotal" => "SUM(a.qty * b.otherTaxValue)",
                        "vatTotal" => "SUM(a.qty * b.vatValue)",
                        "otherVatTotal" => "SUM(a.qty * b.otherVatValue)"
                    ])
                    ->from(SalesMenu::tableName() . ' a')
                    ->leftJoin(SalesMenu::tableName() . ' b',
                        'a.salesNum = b.salesNum AND b.menuRefID <> 0 AND a.ID = b.menuRefID')
                    ->andWhere(['a.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                    ->andWhere(['OR',
                        ['a.menuRefID' => 0],
                        'a.menuRefID = a.ID'
                    ])
                    ->andWhere('b.ID <> b.menuRefID')
                    ->one();

                $menuSubtotalAfterDiscountPackage = $menuGrandtotalAfterDiscountPackage + $menuDiscountPackage - $sumPackage1['vatTotal'] - $sumPackage1['otherTaxTotal'];

                $sumExtra = (new Query())
                    ->select([
                        'subtotal' => 'SUM((sme.qty * sme.price) * sm.qty)',
                        'grandTotal' => 'SUM((sme.qty * sme.inclusivePrice) * sm.qty)',
                        'menuDiscountTotal' => 'SUM(sm.qty * sme.discountValue)'])
                    ->from(SalesMenuExtra::tableName() . ' sme')
                    ->innerJoin(SalesMenu::tableName() . ' sm',
                        'sme.menuDetailID = sm.ID')
                    ->andWhere(['sme.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'sm.statusID', [13, 14, 34]])
                    ->one();

                $menuExtraSubtotal = $sumExtra['subtotal'] ? $sumExtra['subtotal'] : 0;
                $menuGrandtotalBeforeDiscountExtra = $sumExtra['grandTotal'] ? $sumExtra['grandTotal'] : 0;
                $menuDiscountExtra = $sumExtra['menuDiscountTotal'] ? $sumExtra['menuDiscountTotal'] : 0;
                $menuGrandtotalAfterDiscountExtra = $menuGrandtotalBeforeDiscountExtra - $menuDiscountExtra;

                $sumExtra1 = (new Query())
                    ->select([
                        "otherTaxTotal" => "SUM(sm.qty * sme.otherTaxValue)",
                        "vatTotal" => "SUM(sm.qty * sme.vatValue)",
                        "otherVatTotal" => "SUM(sm.qty * sme.otherVatValue)"
                    ])
                    ->from(SalesMenuExtra::tableName() . ' sme')
                    ->innerJoin(SalesMenu::tableName() . ' sm',
                        'sme.menuDetailID = sm.ID')
                    ->andWhere(['sme.salesNum' => $this->salesNum])
                    ->andWhere(['IN', 'sm.statusID', [13, 14, 34]])
                    ->one();

                $menuSubtotalAfterDiscountExtra = $menuGrandtotalAfterDiscountExtra + $menuDiscountExtra - $sumExtra1['vatTotal'] - $sumExtra1['otherTaxTotal'];

                $deliveryCostTaxValue = $deliveryCostTax ? ($this->deliveryCost * $taxValue / 100) : 0;

                // Final Calculate
                $tempGrandTotal = $menuGrandtotalBeforeDiscount + $menuGrandtotalBeforeDiscountPackage + $menuGrandtotalBeforeDiscountExtra;
                $sumVatTotal = $sumMenu1['vatTotal'] + $deliveryCostTaxValue + $sumPackage1['vatTotal'] + $sumExtra1['vatTotal'];
                $sumOtherVatTotal = $sumMenu1['otherVatTotal'] + $sumPackage1['otherVatTotal'] + $sumExtra1['otherVatTotal'];
                $sumOtherTaxTotal = $sumMenu1['otherTaxTotal'] + $sumPackage1['otherTaxTotal'] + $sumExtra1['otherTaxTotal'];
                $sumMenuDiscountTotal = $menuDiscount + $menuDiscountPackage + $menuDiscountExtra;
                // $sumSubTotal = $menuSubtotalAfterDiscount + $menuSubtotalAfterDiscountPackage + $menuSubtotalAfterDiscountExtra;
                $sumSubTotal = $menuSubtotal + $menuPackageSubtotal + $menuExtraSubtotal;
                $sumGrandTotal = $tempGrandTotal - $sumMenuDiscountTotal;


                $sumData['menuDiscountTotal'] = $sumMenuDiscountTotal;
                $sumData['otherTaxTotal'] = $sumOtherTaxTotal;
                $sumData['vatTotal'] = $sumVatTotal;
                $sumData['otherVatTotal'] = $sumOtherVatTotal;
                $sumData['grandTotal'] = $nonSalesPaymentMethod ? $this->subtotal : $sumGrandTotal;
                $sumData['subtotal'] = $nonSalesPaymentMethod ? $this->subtotal : $sumSubTotal;

                $this->load(['SalesHead' => $sumData]);
                $this->calculateDiscountTotal($this, false, $tempMenuSubtotal);
                $ineligiblePromotion = UpdateOrder::ineligiblePromotion($this);
                if ($ineligiblePromotion && $this->flagAutoRemovePromotion)
                {
                    $this->promotionID = 0;
                    $this->promotionDiscount = 0;
                    $this->promotionVoucherCode = '';
                }

                $platformFee = SalesPlatformFee::getPlatformFeeTotal($this->salesNum, $this->platformFee, $this->subtotal, $this->selfOrderPaymentMethodID);

                $orderFee = $this->orderFee ?  $this->orderFee : 0;
                
                if ($nonSalesPaymentMethod) {
                    $this->grandTotal = $this->grandTotal + $this->deliveryCost + $orderFee;
                } else {
                    $total = ($sumData['subtotal'] - $sumMenuDiscountTotal) + $sumData['otherTaxTotal'] + $sumData['vatTotal'] + $sumData['otherVatTotal'];
                    $this->grandTotal = ($total + $this->deliveryCost + $orderFee - $this->discountTotal);
                }

                $this->grandTotal = ROUND($this->grandTotal, 3);

                $finalGrandTotal = $this->grandTotal;
            }

            if ($rounding != 0) {
                if ($roundingMode == 'DOWN') {
                    $this->roundingTotal = $nonSalesPaymentMethod ? 0 : $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'UP') {
                    $this->roundingTotal = $nonSalesPaymentMethod ? 0 : $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'AUTO') {
                    $this->roundingTotal = $nonSalesPaymentMethod ? 0 : $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding;
                }
            }
            
            $difference = $this->grandTotal - ($this->subtotal - $this->discountTotal - $this->menuDiscountTotal + $this->otherTaxTotal + $this->vatTotal + $this->otherVatTotal + $this->deliveryCost + $orderFee);
            $difference = ROUND($difference, 3);
            if ($this->otherTaxTotal > 0) { 
                $this->otherTaxTotal = $this->otherTaxTotal + $difference;
            } else {
                $this->subtotal = $this->subtotal + $difference;
            }
        } else {
            $billTotal = SalesHead::totalBill($this->salesNum);
            $billDiscount = 0;
            // $dataSalesHead = SalesHead::findOne(['salesNum' => $this->salesNum]);
            // if ($dataSalesHead['discountTotal']) {
            //     $billDiscount = $dataSalesHead['discountTotal'];
            // }

            if ($promotionModel) {
                if ($promotionModel->promotionTypeID == 3) {
                    $billDiscount = $promotionModel->discount;
                }
            }

            $taxCalculatePromotionTotal = $taxCalculationType == 2 ? "(CASE "
                . "WHEN ms_promotionhead.promotionTypeID = 9 THEN (tr_salesmenu.discountValue) "
                . "ELSE (qty * price * (tr_salesmenu.discount / 100)) END) - "
                . "((qty * price - discountValue) * $this->promotionDiscount / 100) - (qty * price / $billTotal * $billDiscount)" : 0;
            $otherTaxCalculatePromotionTotal = $otherTaxCalculationType == 2 ? "(CASE "
                . "WHEN ms_promotionhead.promotionTypeID = 9 THEN (tr_salesmenu.discountValue) "
                . "ELSE (qty * price * (tr_salesmenu.discount / 100)) END) - "
                . "((qty * price - discountValue) * $this->promotionDiscount / 100) - (qty * price / $billTotal * $billDiscount)" : 0;
            $sumMenu = (new Query())
                ->select(['subtotal' => 'SUM(qty * price)',
                    'menuDiscountTotal' => 'SUM(CASE '
                    .'WHEN ms_promotionhead.promotionTypeID = 5 THEN tr_salesmenu.discountValue '
                    .'WHEN ms_promotionhead.promotionTypeID = 9 THEN tr_salesmenu.discountValue ELSE '
                    . 'qty * price * (tr_salesmenu.discount / 100) END)',
                    // 'otherTaxTotal' => "SUM(((qty * price) - $otherTaxCalculatePromotionTotal) * (otherTax / 100))",
                    // 'vatTotal' => "SUM(CASE WHEN otherTaxOnVat = 1 THEN (((qty * price) - $taxCalculatePromotionTotal) + "
                    // . "(((qty * price) - $otherTaxCalculatePromotionTotal) * (otherTax / 100))) * (vat / 100) "
                    // . "ELSE ((qty * price) - $taxCalculatePromotionTotal) * (vat / 100) END)"])
                    'otherTaxTotal' => "SUM(otherTaxValue)",
                    'vatTotal' => "SUM(vatValue)",
                    'otherVatTotal' => "SUM(otherVatValue)"])
                ->from(SalesMenu::tableName())
                ->leftJoin(PromotionHead::tableName(),
                    'tr_salesmenu.promotionDetailID = ms_promotionhead.promotionID')
                ->andWhere(['salesNum' => $this->salesNum])
                ->andWhere(['IN', 'statusID', [13, 14, 34]])
                ->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ID'
                ])
                ->one();

            $taxCalculatePackagePromotionTotal = $taxCalculationType == 2 ? "(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagPackageContent = 1 THEN a.qty * b.discountValue ELSE a.qty * b.qty * b.price * (b.discount / 100) END) - "
                . "(a.qty * (b.qty * b.price - b.discountValue) * $this->promotionDiscount / 100) - (b.qty * b.price / $billTotal * $billDiscount)" : 0;
            $otherTaxCalculatePackagePromotionTotal = $otherTaxCalculationType == 2 ? "(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagPackageContent = 1 THEN a.qty * b.discountValue ELSE a.qty * b.qty * b.price * (b.discount / 100) END) - "
                . "(a.qty * (b.qty * b.price - b.discountValue) * $this->promotionDiscount / 100) - (b.qty * b.price / $billTotal * $billDiscount)" : 0;

            $sumPackage = (new Query())
                ->select(['subtotal' => 'SUM(a.qty * b.qty * b.price)',
                    'menuDiscountTotal' => "SUM(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagPackageContent = 1 THEN a.qty * b.discountValue ELSE a.qty * b.qty * b.price * (b.discount / 100) END)",
                    // 'otherTaxTotal' => "SUM(((a.qty * b.qty * b.price) - $otherTaxCalculatePackagePromotionTotal) * (b.otherTax / 100))",
                    // 'vatTotal' => "SUM(CASE WHEN b.otherTaxOnVat = 1 THEN "
                    // . "(((a.qty * b.qty * b.price) - $taxCalculatePackagePromotionTotal) + "
                    // . "(((a.qty * b.qty * b.price) - $otherTaxCalculatePackagePromotionTotal) * "
                    // . "(b.otherTax / 100))) * (b.vat / 100) "
                    // . "ELSE ((a.qty * b.qty * b.price) - $taxCalculatePackagePromotionTotal) * (b.vat / 100) END)"])
                    'otherTaxTotal' => "SUM(a.qty * b.otherTaxValue)",
                    'vatTotal' => "SUM(a.qty * b.vatValue)",
                    'otherVatTotal' => "SUM(a.qty * b.otherVatValue)"])
                ->from(SalesMenu::tableName() . ' a')
                ->leftJoin(['promotion' => PromotionHead::tableName()], 'promotion.promotionID = a.promotionDetailID')
                ->leftJoin(SalesMenu::tableName() . ' b',
                    'a.salesNum = b.salesNum AND b.menuRefID <> 0 AND a.ID = b.menuRefID')
                ->andWhere(['a.salesNum' => $this->salesNum])
                ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                ->andWhere(['OR',
                    ['a.menuRefID' => 0],
                    'a.menuRefID = a.ID'
                ])
                ->andWhere('b.ID <> b.menuRefID')
                ->one();

            $taxCalculateExtraPromotionTotal = $taxCalculationType == 2 ? "(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagMenuExtra = 1 THEN sm.qty * sme.discountValue ELSE sm.qty * sme.qty * sme.price * (sme.discount / 100) END) - "
                . "(sm.qty * (sme.qty * sme.price - sme.discountValue) * $this->promotionDiscount / 100) - (sme.qty * sme.price / $billTotal * $billDiscount)" : 0;
            $otherTaxCalculateExtraPromotionTotal = $otherTaxCalculationType == 2 ? "(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagMenuExtra = 1 THEN sm.qty * sme.discountValue ELSE sm.qty * sme.qty * sme.price * (sme.discount / 100) END) - "
                . "(sm.qty * (sme.qty * sme.price - sme.discountValue) * $this->promotionDiscount / 100) - (sme.qty * sme.price / $billTotal * $billDiscount)" : 0;
            $sumExtra = (new Query())
                ->select(['subtotal' => 'SUM(sm.qty * sme.qty * sme.price)',
                    'menuDiscountTotal' => "SUM(CASE WHEN promotion.promotionTypeID = 9 AND promotion.flagMenuExtra = 1 THEN sm.qty * sme.discountValue ELSE sm.qty * sme.qty * sme.price * (sme.discount / 100) END)",
                    // 'otherTaxTotal' => "SUM(((sm.qty * sme.qty * sme.price) - $otherTaxCalculateExtraPromotionTotal) * (sme.otherTax / 100))",
                    // 'vatTotal' => "SUM(CASE WHEN sme.otherTaxOnVat = 1 THEN "
                    // . "(((sm.qty * sme.qty * sme.price) - $taxCalculateExtraPromotionTotal) + "
                    // . "(((sm.qty * sme.qty * sme.price) - $otherTaxCalculateExtraPromotionTotal) * "
                    // . "(sme.otherTax / 100))) * (sme.vat / 100) "
                    // . "ELSE ((sm.qty * sme.qty * sme.price) - $taxCalculateExtraPromotionTotal) * (sme.vat / 100) END)"])
                    'otherTaxTotal' => "SUM(sm.qty * sme.otherTaxValue)",
                    'vatTotal' => "SUM(sm.qty * sme.vatValue)",
                    'otherVatTotal' => "SUM(sm.qty * sme.otherVatValue)"])
                ->from(SalesMenuExtra::tableName() . ' sme')
                ->innerJoin(SalesMenu::tableName() . ' sm',
                    'sme.menuDetailID = sm.ID')
                ->leftJoin(['promotion' => PromotionHead::tableName()], 'promotion.promotionID = sm.promotionDetailID')
                ->andWhere(['sme.salesNum' => $this->salesNum])
                ->andWhere(['IN', 'sm.statusID', [13, 14, 34]])
                ->one();

            $deliveryCostTaxValue = $deliveryCostTax ? ($this->deliveryCost * $taxValue / 100) : 0;

            $sumData['subtotal'] = $sumMenu['subtotal'] + $sumPackage['subtotal'] + $sumExtra['subtotal'];
            $sumData['menuDiscountTotal'] = $sumMenu['menuDiscountTotal'] + $sumPackage['menuDiscountTotal'] + $sumExtra['menuDiscountTotal'];
            $sumData['otherTaxTotal'] = $sumMenu['otherTaxTotal'] + $sumPackage['otherTaxTotal'] + $sumExtra['otherTaxTotal'];
            $sumData['vatTotal'] = $sumMenu['vatTotal'] + $deliveryCostTaxValue + $sumPackage['vatTotal'] + $sumExtra['vatTotal'];
            $sumData['otherVatTotal'] = $sumMenu['otherVatTotal'] + $sumPackage['otherVatTotal'] + $sumExtra['otherVatTotal'];

            $this->load(['SalesHead' => $sumData]);
            $this->calculateDiscountTotal($this, true, $tempMenuSubtotal);
            $ineligiblePromotion = UpdateOrder::ineligiblePromotion($this);
            if ($ineligiblePromotion && $this->flagAutoRemovePromotion)
            {
                $this->promotionID = 0;
                $this->promotionDiscount = 0;
                $this->promotionVoucherCode = '';
            }

            $platformFee = SalesPlatformFee::getPlatformFeeTotal($this->salesNum, $this->platformFee, $this->subtotal, $this->selfOrderPaymentMethodID);

            $orderFee = $this->orderFee ?  $this->orderFee : 0;
            $this->grandTotal = $this->subtotal + $this->deliveryCost + $orderFee - $this->discountTotal - $this->menuDiscountTotal + $this->otherTaxTotal + $this->vatTotal + $this->otherVatTotal + $this->voucherTotal;
            
            $finalGrandTotal = $this->grandTotal;

            $paymentMethod = (new Query())
                ->select(
                    ['b.paymentMethodTypeID']
                )
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(PaymentMethod::tableName() . ' b',
                    'b.paymentMethodID = a.paymentMethodID')
                ->andWhere(['a.salesNum' => $this->salesNum])
                ->one();

            if ($paymentMethod) {
                if ($rounding != 0) {
                    if ($roundingMode == 'DOWN') {
                        $this->roundingTotal = $paymentMethod['paymentMethodTypeID'] != 7 ? $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding) : 0;
                    } elseif ($roundingMode == 'UP') {
                        $this->roundingTotal = $paymentMethod['paymentMethodTypeID'] != 7 ? $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding) : 0;
                    } elseif ($roundingMode == 'AUTO') {
                        $this->roundingTotal = $paymentMethod['paymentMethodTypeID'] != 7 ? $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding : 0;
                    }
                }
            } else {
                if ($rounding != 0) {
                    if ($roundingMode == 'DOWN') {
                        $this->roundingTotal = $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding);
                    } elseif ($roundingMode == 'UP') {
                        $this->roundingTotal = $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding);
                    } elseif ($roundingMode == 'AUTO') {
                        $this->roundingTotal = $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding;
                    }
                }
            }
        }
        if($this->allGrandTotal != 0 && $this->allOrderFee != 0 && $this->grandTotal != 0){
            $this->orderFee = $this->allOrderFee * ($this->grandTotal / $this->allGrandTotal);
        }

        // @notes: Kalkulasi platform fee
        $this->grandTotal = $this->grandTotal + $platformFee;
    }

    public static function calculateDiscountTotal(&$salesHead, $calculate = true, $menuSubtotal = 0) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        
        $otherTaxValue = 0;
        $otherTaxOnVat = 0;
        $vatValue = 0;
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $salesHead['visitPurposeID']])->one();
        $vatSubject = 0;
        if ($mapBranchModel) {
            $otherTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatValue = $mapBranchModel->taxValue;
            $vatSubject = $mapBranchModel->vatSubject;
        }

        if ($salesHead['promotionID'] != 0) {
            $branchID = Setting::getCurrentBranch();
            $flagInclusive = self::getInclusiveFlag($branchID,
                    $salesHead['visitPurposeID']);
            $promotionModel = PromotionHead::find()
                ->joinWith('promotionCategories')
                ->andWhere(['ms_promotionhead.promotionID' => $salesHead['promotionID']])
                ->one();
            if (!$promotionModel) {
                return;
            }

            $freeMenuExist = false;
            if (isset($salesHead['salesMenu'])) {
                foreach ($salesHead['salesMenu'] as $salesMenu) {
                    if (!in_array($salesMenu['statusID'], [12,19])) {
                        if ($salesMenu['originalPrice'] != $salesMenu['price']) $freeMenuExist = true;
                    }
                }
            }

            $inclusiveDiscountTotal = 0;
            // @Notes: 1 = Discount(%)
            if ($promotionModel->promotionTypeID == 1 || $promotionModel->promotionTypeID == 5) {
                if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $inclusiveDiscountTotal = $grandtotalBeforeDiscount * $promotionModel->discount / 100;
                        if (($promotionModel->maxSalesPrice && $promotionModel->maxSalesPrice > 0) && $inclusiveDiscountTotal > $promotionModel->maxSalesPrice) {
                            $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                        }

                        $menuSubtotalBeforeDiscount = $salesHead['subtotal'];
 
                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);
                        $finalBillDisc = $result['finalBillDisc'];
                        $menuSubtotalBeforeDiscount = $result['menuSubtotalBeforeDiscount'];

                        $salesMenuSpecialPrice = SalesMenu::findActive()
                            ->where(['salesNum' => $salesHead['salesNum']])
                            ->andWhere('originalPrice <> price')
                            ->andWhere('statusID <> 19')
                            ->one();

                        if ($salesMenuSpecialPrice) {
                            if (isset($salesHead['salesMenu'])) {
                                foreach ($salesHead['salesMenu'] as $salesMenu) {
                                    if (isset($salesMenu['localID'])) {
                                        if ($salesMenuSpecialPrice->localID == $salesMenu['localID'] && 
                                            $salesMenuSpecialPrice->ID == $salesMenu['ID'] && $salesMenu['statusID'] == 12) $salesMenuSpecialPrice = null;
                                    }
                                }
                            }
                        }

                        if (!$promotionModel->promotionCategories) {
                            $discountTotal = $finalBillDisc > $menuSubtotalBeforeDiscount ? $menuSubtotalBeforeDiscount : $finalBillDisc;

                            $salesHead['discountTotal'] = $discountTotal;    
                            if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                                $tempDiscount = $promotionModel->maxSalesPrice > $grandtotalBeforeDiscount ? $grandtotalBeforeDiscount : $promotionModel->maxSalesPrice;
                                $percentageDiscount = $tempDiscount / $grandtotalBeforeDiscount * 100;
                                $salesHead['discountTotal'] = ceil($menuSubtotalBeforeDiscount * $percentageDiscount / 100);
                                //$salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        } else {
                            $salesHead['discountTotal'] = 0;
                        }

                        if ($salesMenuSpecialPrice || $freeMenuExist) {
                            $salesHead['discountTotal'] = 0;
                        }

                        $salesMenuIsSubs = SalesMenu::findActive()
                            ->where(['salesNum' => $salesHead['salesNum']])
                            ->andWhere('menuPromotionID <> 0')
                            ->andWhere('statusID <> 19')
                            ->one();

                        if ($salesMenuIsSubs) {
                            if (isset($salesHead['salesMenu'])) {
                                foreach ($salesHead['salesMenu'] as $salesMenu) {
                                    if (isset($salesMenu['localID'])) {
                                        if ($salesMenuIsSubs->localID == $salesMenu['localID'] && 
                                            $salesMenu['statusID'] == 12) $salesMenuIsSubs = null;
                                    }
                                }
                            }
                        }

                        if ($salesMenuIsSubs) {
                            $salesHead['discountTotal'] = 0;
                        }

                    } else {
                        $salesMenuSpecialPrice = SalesMenu::findActive()
                            ->where(['salesNum' => $salesHead['salesNum']])
                            ->andWhere('originalPrice <> price')
                            ->andWhere('statusID <> 19')
                            ->one();

                        if ($salesMenuSpecialPrice) {
                            if (isset($salesHead['salesMenu'])) {
                                foreach ($salesHead['salesMenu'] as $salesMenu) {
                                    if (isset($salesMenu['localID'])) {
                                        if ($salesMenuSpecialPrice->localID == $salesMenu['localID'] && 
                                            $salesMenuSpecialPrice->ID == $salesMenu['ID'] && $salesMenu['statusID'] == 12) $salesMenuSpecialPrice = null;
                                    }
                                }
                            }
                        }

                        $grandtotalBeforeDiscount = $salesHead['grandTotal'] + $salesHead['menuDiscountTotal'];
                        $subtotalBeforeTax = $menuSubtotal > 0 ? ($menuSubtotal + $salesHead['otherTaxTotal'] + $salesHead['vatTotal']) : $menuSubtotal;
                        $tempDiscount = ($grandtotalBeforeDiscount) * $promotionModel->discount / 100;
                        $discountTotal = $subtotalBeforeTax > $tempDiscount ? $tempDiscount : $subtotalBeforeTax;

                        if (!$promotionModel->promotionCategories) {
                            $salesHead['discountTotal'] = $discountTotal;
                            if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                                $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        } else {
                            $salesHead['discountTotal'] = 0;
                        }

                        if ($salesMenuSpecialPrice || $freeMenuExist) {
                            $salesHead['discountTotal'] = 0;
                        }

                        $salesMenuIsSubs = SalesMenu::findActive()
                            ->where(['salesNum' => $salesHead['salesNum']])
                            ->andWhere('menuPromotionID <> 0')
                            ->andWhere('statusID <> 19')
                            ->one();

                        if ($salesMenuIsSubs) {
                            if (isset($salesHead['salesMenu'])) {
                                foreach ($salesHead['salesMenu'] as $salesMenu) {
                                    if (isset($salesMenu['localID'])) {
                                        if ($salesMenuIsSubs->localID == $salesMenu['localID'] && 
                                            $salesMenu['statusID'] == 12) $salesMenuIsSubs = null;
                                    }
                                }
                            }
                        }

                        if ($salesMenuIsSubs) {
                            $salesHead['discountTotal'] = 0;
                        }
                    }
                } else {
                    $salesMenuSpecialPrice = SalesMenu::findActive()
                            ->where(['salesNum' => $salesHead['salesNum']])
                            ->andWhere('originalPrice <> price')
                            ->andWhere('statusID <> 19')
                            ->one();
                    
                    if ($salesMenuSpecialPrice) {
                        if (isset($salesHead['salesMenu'])) {
                            foreach ($salesHead['salesMenu'] as $salesMenu) {
                                if (isset($salesMenu['localID'])) {
                                    if ($salesMenuSpecialPrice->localID == $salesMenu['localID'] && 
                                        $salesMenuSpecialPrice->ID == $salesMenu['ID'] && $salesMenu['statusID'] == 12) $salesMenuSpecialPrice = null;
                                }
                            }
                        }
                    }

                    if (!$promotionModel->promotionCategories) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesHead['discountTotal'] = ($menuSubtotal) * $salesHead['promotionDiscount'] / 100;
                            if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                                $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        } else {
                            $salesHead['discountTotal'] = ($menuSubtotal - $salesHead['menuDiscountTotal']) * $salesHead['promotionDiscount'] / 100;
                            if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                                $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        }
                    } else {
                        $salesHead['discountTotal'] = 0;
                    }

                    if ($salesMenuSpecialPrice || $freeMenuExist) {
                        $salesHead['discountTotal'] = 0;
                    }

                    $salesMenuIsSubs = SalesMenu::findActive()
                        ->where(['salesNum' => $salesHead['salesNum']])
                        ->andWhere('menuPromotionID <> 0')
                        ->andWhere('statusID <> 19')
                        ->one();

                    if ($salesMenuIsSubs) {
                        if (isset($salesHead['salesMenu'])) {
                            foreach ($salesHead['salesMenu'] as $salesMenu) {
                                if (isset($salesMenu['localID'])) {
                                    if ($salesMenuIsSubs->localID == $salesMenu['localID'] && 
                                        $salesMenu['statusID'] == 12) $salesMenuIsSubs = null;
                                }
                            }
                        }
                    }

                    if ($salesMenuIsSubs) {
                        $salesHead['discountTotal'] = 0;
                    }
                }
            } else if ($promotionModel->promotionTypeID == 3 || $promotionModel->promotionTypeID == 6) {
                if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                    $salesHead['promotionDiscount'] = $promotionModel->discount;
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $inclusiveDiscountTotal = $promotionModel->discount;
                        
                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);
                        $salesHead['discountTotal'] =  (float) $result['finalBillDisc'];
                    } else {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $subtotalBeforeTax = $salesHead['subtotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'];
                        $tempDiscountTotal = $subtotalBeforeTax - $salesHead['menuDiscountTotal'] >= $promotionModel->discount ? (float) $promotionModel->discount : (float) $subtotalBeforeTax - $salesHead['menuDiscountTotal'];
                        $discountTotal = $salesHead['subtotal'] > $promotionModel->discount ? (float) $tempDiscountTotal : (float) ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $salesHead['subtotal']);
                        $grandtotalAfterDiscount = $grandtotalBeforeDiscount - $discountTotal;
                        $salesHead['discountTotal'] = $discountTotal;
                    }
                } else {
                    $salesHead['promotionDiscount'] = $promotionModel->discount;
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $subtotalAfterDiscount = $salesHead['subtotal'] - $salesHead['menuDiscountTotal'];
                        if ($subtotalAfterDiscount > $promotionModel->discount) {
                            $salesHead['discountTotal'] = (float) $promotionModel->discount;
                        } else if ($subtotalAfterDiscount <= $promotionModel->discount) {
                            $salesHead['discountTotal'] = (float) $subtotalAfterDiscount;
                        }
                    } else {
                        $subtotalBeforeTax = $salesHead['subtotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'];
                        $tempDiscountTotal = $subtotalBeforeTax - $salesHead['menuDiscountTotal'] >= $promotionModel->discount ? (float) $promotionModel->discount : (float) $subtotalBeforeTax - $salesHead['menuDiscountTotal'];
                        $discountTotal = $salesHead['subtotal'] > $promotionModel->discount ? (float) $tempDiscountTotal : (float) ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $salesHead['subtotal']);
                        $salesHead['discountTotal'] = $discountTotal;
                    }
                }
            } else if ($promotionModel->promotionTypeID == 10) {
                if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $inclusiveDiscountTotal = $grandtotalBeforeDiscount * $promotionModel->discount / 100;
                        if (($promotionModel->maxSalesPrice && $promotionModel->maxSalesPrice > 0) && $inclusiveDiscountTotal > $promotionModel->maxSalesPrice) {
                            $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                        }

                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);
                        $finalBillDisc = $result['finalBillDisc'];
                        $subtotal = $result['menuSubtotalBeforeDiscount'];

                        if ($calculate) {
                            $discountTotal = $finalBillDisc > $subtotal ? $subtotal : $finalBillDisc;
                            $inclusiveDiscountBill = ($menuSubtotal * $salesHead['promotionDiscount'] / 100);
                                if ($inclusiveDiscountBill > $promotionModel->maxSalesPrice) {
                                    $inclusiveDiscountBill = $promotionModel->maxSalesPrice;
                                }
                                $salesHead['grandTotal'] = $grandtotalBeforeDiscount - $inclusiveDiscountBill;
                        } else {
                            $discountTotal = $finalBillDisc;
                        }
                        $salesHead['discountTotal'] = $discountTotal;
                        if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                            if ($calculate) {
                                $tempDiscount = $promotionModel->maxSalesPrice > $menuSubtotal ? $menuSubtotal : $promotionModel->maxSalesPrice;
                                $percentageDiscount = ($menuSubtotal > 0 && $tempDiscount > 0) ?  $tempDiscount / $menuSubtotal * 100 : 0;
                                $salesHead['discountTotal'] = ceil($subtotal * $percentageDiscount / 100);
                            } else {
                                $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        }
                    } else {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $inclusiveDiscountTotal = $grandtotalBeforeDiscount * $promotionModel->discount / 100;
                        if (($promotionModel->maxSalesPrice && $promotionModel->maxSalesPrice > 0) && $inclusiveDiscountTotal > $promotionModel->maxSalesPrice) {
                            $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                        }

                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);

                        $finalBillDisc = $result['finalBillDisc'];
                        $subtotal = $result['menuSubtotalBeforeDiscount'];

                        if ($calculate) {
                            $tempDiscountTotal = $menuSubtotal - $salesHead['menuDiscountTotal'] > $finalBillDisc ? $finalBillDisc : $menuSubtotal - $salesHead['menuDiscountTotal'];
                            $discountTotal = $finalBillDisc > $subtotal ? ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $subtotal) : $tempDiscountTotal;
                            $inclusiveDiscountBill = ($menuSubtotal * $salesHead['promotionDiscount'] / 100);
                            if ($inclusiveDiscountBill > $promotionModel->maxSalesPrice) {
                                $inclusiveDiscountBill = $promotionModel->maxSalesPrice;
                            }
                            $salesHead['grandTotal'] = $grandtotalBeforeDiscount - $inclusiveDiscountBill;
                        } else {
                            $tempDiscountTotal = $menuSubtotal - $salesHead['menuDiscountTotal'] > $finalBillDisc ? $finalBillDisc : $menuSubtotal  - $salesHead['menuDiscountTotal'];
                            $discountTotal = $menuSubtotal > $finalBillDisc ? $tempDiscountTotal : ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $menuSubtotal);
                        }
                        
                        $salesHead['discountTotal'] = $discountTotal;
                        if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                            if ($calculate) {
                                $tempDiscount = $promotionModel->maxSalesPrice > $menuSubtotal ? $menuSubtotal : $promotionModel->maxSalesPrice;
                                $percentageDiscount = ($menuSubtotal > 0 && $tempDiscount > 0) ?  $tempDiscount / $menuSubtotal * 100 : 0;
                                $salesHead['discountTotal'] = ceil($subtotal * $percentageDiscount / 100);
                            } else {
                                $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                            }
                        }
                    }
                } else {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $salesHead['discountTotal'] = ($menuSubtotal) * $salesHead['promotionDiscount'] / 100;
                        if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                            $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                        }
                    } else {
                        $salesHead['discountTotal'] = ($menuSubtotal - $salesHead['menuDiscountTotal']) * $salesHead['promotionDiscount'] / 100;
                        if ($salesHead['discountTotal'] > $promotionModel->maxSalesPrice) {
                            $salesHead['discountTotal'] = (float) $promotionModel->maxSalesPrice;
                        }
                    }
                }
            } else if ($promotionModel->promotionTypeID == 11) {
                if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $inclusiveDiscountTotal = $grandtotalBeforeDiscount * $salesHead['promotionDiscount'] / 100;
                        $menuSubtotalBeforeDiscount = $salesHead['subtotal'];

                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);
                        $finalBillDisc = $result['finalBillDisc'];
                        $menuSubtotalBeforeDiscount = $result['menuSubtotalBeforeDiscount'];
                        if (!$promotionModel->promotionCategories) {
                            $promotionDiscount = $salesHead['promotionDiscount'] > 100 ? 100 : $salesHead['promotionDiscount'];
                            if ($calculate) {
                                $discountTotal = $finalBillDisc;
                                
                                $inclusiveDiscountBill = ($grandtotalBeforeDiscount * $promotionDiscount / 100);
                                $salesHead['grandTotal'] = $grandtotalBeforeDiscount - $inclusiveDiscountBill;
                            } else {
                                $discountTotal = ($grandtotalBeforeDiscount) * $promotionDiscount / 100;
                            }

                            $salesHead['discountTotal'] = $finalBillDisc;
                        } else {
                            $salesHead['discountTotal'] = 0;
                        }
                    } else {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'] + $salesHead['menuDiscountTotal'];
                        $subtotalBeforeTax = $menuSubtotal + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'];
                        $tempDiscount = $menuSubtotal * $salesHead['promotionDiscount'] / 100;
                        $tempDiscountTotal = $subtotalBeforeTax - $salesHead['menuDiscountTotal'] > $tempDiscount ? $tempDiscount : $subtotalBeforeTax - $salesHead['menuDiscountTotal'];
                        $discountTotal = $menuSubtotal > $tempDiscount ? $tempDiscountTotal : ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $menuSubtotal);

                        if (!$promotionModel->promotionCategories) {
                            $salesHead['discountTotal'] = $discountTotal;
                            if ($salesHead['discountTotal'] > $salesHead['subtotal']) {
                                $salesHead['discountTotal'] = (float) $salesHead['subtotal'];
                            }
                        } else {
                            $salesHead['discountTotal'] = 0;
                        }
                    }
                } else {
                    if (!$promotionModel->promotionCategories) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesHead['discountTotal'] = ($menuSubtotal) * $salesHead['promotionDiscount'] / 100;
                            if ($salesHead['discountTotal'] > ($salesHead['subtotal'] - $salesHead['menuDiscountTotal'])) {
                                $salesHead['discountTotal'] = (float) $salesHead['subtotal'] - $salesHead['menuDiscountTotal'];
                            }
                        } else {
                            $salesHead['discountTotal'] = ($menuSubtotal - $salesHead['menuDiscountTotal']) * $salesHead['promotionDiscount'] / 100;
                            if ($salesHead['discountTotal'] > ($salesHead['subtotal'] - $salesHead['menuDiscountTotal'])) {
                                $salesHead['discountTotal'] = (float) $salesHead['subtotal'] - $salesHead['menuDiscountTotal'];
                            }
                        }
                    } else {
                        $salesHead['discountTotal'] = 0;
                    }
                }
            } else if ($promotionModel->promotionTypeID == 12 || $promotionModel->promotionTypeID == 14 || $promotionModel->promotionTypeID == 15 || $promotionModel->promotionTypeID == 16) {
                if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        
                        $inclusiveDiscountTotal = $salesHead['promotionDiscount'];
                        $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel);

                        $salesHead['promotionDiscount'] = $inclusiveDiscountTotal;
                        $salesHead['discountTotal'] =  (float) $result['finalBillDisc'];
                    } else {
                        $grandtotalBeforeDiscount = $salesHead['grandTotal'];
                        $subtotalBeforeTax = (float) ($salesHead['subtotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal']);
                        $tempDiscountTotal = $subtotalBeforeTax - $salesHead['menuDiscountTotal'] >= $salesHead['promotionDiscount'] ? (float) $salesHead['promotionDiscount'] : (float) $subtotalBeforeTax - $salesHead['menuDiscountTotal'];
                        $discountTotal = $salesHead['subtotal'] > $salesHead['promotionDiscount'] ? (float) $tempDiscountTotal : (float) ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $salesHead['subtotal']);
                        $grandtotalAfterDiscount = $grandtotalBeforeDiscount - $discountTotal;
                        $salesHead['discountTotal'] = $discountTotal;
                    }
                } else {
                    $subtotalBeforeTax = $salesHead['subtotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'];
                    $tempDiscountTotal = $subtotalBeforeTax - $salesHead['menuDiscountTotal'] >= $salesHead['promotionDiscount'] ? (float) $salesHead['promotionDiscount'] : (float) $subtotalBeforeTax - $salesHead['menuDiscountTotal'];
                    $discountTotal = $salesHead['subtotal'] > $salesHead['promotionDiscount'] ? (float) $tempDiscountTotal : (float) ($salesHead['menuDiscountTotal'] > 0 ? $tempDiscountTotal : $salesHead['subtotal']);
                    $salesHead['discountTotal'] = $discountTotal;
                }
            }
            $salesHead['inclusiveDiscountTotal'] = $salesHead['discountTotal'] == 0 ? 0 : $inclusiveDiscountTotal;
        } else {
            $salesHead['discountTotal'] = 0;
        }
    }

    public static function calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesHead, $vatSubject, $promotionModel, $lvlHead = true, $salesMenu = null, $menuType = 'Main', $menuDiscount = 0) {

        $branchID = Setting::getCurrentBranch();
        $flagInclusive = self::getInclusiveFlag($branchID, $salesHead['visitPurposeID']);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $inclusiveAfterDiscount = ($flagInclusive && $flagInclusive == 1) && ($taxCalculationType == 2 && $otherTaxCalculationType == 2);
        $inclusiveBeforeDiscount = ($flagInclusive && $flagInclusive == 1) && ($taxCalculationType == 1 && $otherTaxCalculationType == 1);
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($salesHead['visitPurposeID']);
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();
        
        
        $proratePromotionID = [1, 3, 5, 6, 10, 11, 12, 14, 15, 16];
        $promoPercentage = [1, 5, 10, 11];
        $promoRp = [3, 6, 12, 14, 15];
        $forceProRate = in_array($promotionModel->promotionTypeID, [3, 6, 11, 12, 14, 15]);

        if ($lvlHead) {
            $menuSubtotalAfterDiscount = 0;
            $menuSubtotalBeforeDiscount = 0;
            $finalBillDisc = 0;
            $newGrandTotalBeforeDiscount = 0;
            $totalAfterBillDiscount = 0;
            $discountBill = 0;
            // Promo eso % 5 ikut di prorate juga.

            if (!isset($salesHead['salesMenu'])) {
                $salesMenusModel = [];
                $q1 = SalesMenu::find()
                    ->with('menu')
                    ->with('childSalesMenus.menu')
                    ->with('salesExtras.menuExtra.menu')
                    ->where([ SalesMenu::tableName() . '.salesNum' => $salesHead['salesNum']])
                    ->andWhere(['IN', SalesMenu::tableName() . '.statusID' , [13, 14, 34, 46]])
                    ->andWhere(['OR',
                        ['menuRefID' => 0],
                        'menuRefID = ' . SalesMenu::tableName() . '.ID'
                    ]);

                $salesLinkArray = SalesLink::find()
                    ->select('linkSalesNum')
                    ->andWhere(['salesNum' => $salesHead['salesNum']]);

                $q2 = SalesMenu::find()
                    ->with('menu')
                    ->with('childSalesMenus.menu')
                    ->with('salesExtras.menuExtra.menu')
                    ->where(['IN', SalesMenu::tableName() . '.salesNum', $salesLinkArray])
                    ->andWhere(['IN', SalesMenu::tableName() . '.statusID' , [13, 14, 34, 46]])
                    ->andWhere(['OR',
                        ['menuRefID' => 0],
                        'menuRefID = ' . SalesMenu::tableName() . '.ID'
                    ]);
                
                $salesMenuModel = $q1->union($q2, true)->asArray()->all();

                if ($salesMenuModel) {
                    $salesMenusModel = Self::defineSalesMenu($salesMenuModel);
                }
            } else {
                $salesMenusModel = $salesHead['salesMenu'];
            }

            $menuIDPromotionCategory = [];
            $menuCategoryIDPromotionCategory = [];
            $menuCategoryDetailIDPromotionCategory = [];

            if (isset($promotionModel->promotionCategories) && $promotionModel->promotionTypeID == 1 || $promotionModel->promotionTypeID == 9 || $promotionModel->promotionTypeID == 10) {
                foreach ($promotionModel->promotionCategories as $promotionCategoriesIDs) {
                    if ($promotionCategoriesIDs['menuID'] > 0) {
                        $menuIDPromotionCategory[] = $promotionCategoriesIDs['menuID'];
                    }
                    if ($promotionCategoriesIDs['menuCategoryID'] > 0) {
                        $menuCategoryIDPromotionCategory[] = $promotionCategoriesIDs['menuCategoryID'];
                    }
                    if ($promotionCategoriesIDs['menuCategoryDetailID'] > 0) {
                        $menuCategoryDetailIDPromotionCategory[] = $promotionCategoriesIDs['menuCategoryDetailID'];
                    }
                }
            }

            if ($forceProRate) {
                $applyBillDiscountToPackageContent = true;
                $applyBillDiscountToExtra = true;
            } else {
                $applyBillDiscountToPackageContent = $promotionModel->flagPackageContent == 1;
                $applyBillDiscountToExtra = $promotionModel->flagMenuExtra == 1;
            }

            foreach ($salesMenusModel as $sm) {
                $isApplyOtherVat = ($vatSubject === 1 && (isset($sm['menuFlagTax']) && $sm['menuFlagTax'] == 2));
                $tempInclusivePrice = isset($sm['inclusivePrice']) ? $sm['inclusivePrice'] : (isset($menuTemplateDetailModel[$sm['menuID']]) ? $menuTemplateDetailModel[$sm['menuID']]->price : 0);
                $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $tempInclusivePrice : $sm['price'];
                $applyOnBill = ApplyOrderPromo::checkAppliedPromo($promotionModel->promotionID, $sm, $menuCategoryIDPromotionCategory, $menuCategoryDetailIDPromotionCategory, $menuIDPromotionCategory);
                $issetSpecialPrice = false;
                if ($applyOnBill) {
                    $newGrandTotalBeforeDiscount += ($applyPrice * $sm['qty']);
                    if ($inclusiveAfterDiscount) {
                        $inclusiveDiscountValue = isset($sm['inclusiveDiscountValue']) ? $sm['inclusiveDiscountValue'] : 0;
                        $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                    }
                }
                if (in_array($promotionModel->promotionTypeID, [10, 11])) {
                    if (isset($sm['promotionDetailID']) && $sm['promotionDetailID'] == 0 &&
                        $sm['originalPrice'] <> $sm['price']) {
                        $issetSpecialPrice = true;
                    }
                }
                if (count($sm['packages']) > 0) {
                    foreach ($sm['packages'] as $package) {
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $package['inclusivePrice'] : $package['price'];
                        $applyOnBillPck = ApplyOrderPromo::checkAppliedPromo($promotionModel->promotionID, $package, $menuCategoryIDPromotionCategory, $menuCategoryDetailIDPromotionCategory, $menuIDPromotionCategory);
                        if ($applyOnBillPck && $applyBillDiscountToPackageContent && !$issetSpecialPrice) {
                            $newGrandTotalBeforeDiscount += ($applyPrice * $package['qty'] * $sm['qty']);
                            if ($inclusiveAfterDiscount) {
                                $inclusiveDiscountValue = isset($package['inclusiveDiscountValue']) ? $package['inclusiveDiscountValue'] * $sm['qty'] : 0;
                                $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                            }
                        }
                    }
                }

                if (count($sm['extras']) > 0) {
                    foreach ($sm['extras'] as $extras) {
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $extras['inclusivePrice'] : $extras['price'];
                        if ($applyOnBill && $applyBillDiscountToExtra && !$issetSpecialPrice) {
                            $newGrandTotalBeforeDiscount += ($applyPrice * $extras['qty'] * $sm['qty']);
                            if ($inclusiveAfterDiscount) {
                                $inclusiveDiscountValue = isset($extras['inclusiveDiscountValue']) ? $extras['inclusiveDiscountValue'] * $sm['qty'] : 0;
                                $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                            }
                        }
                    }
                }
            }

            if (in_array($promotionModel->promotionTypeID, $promoPercentage) && !in_array($promotionModel->promotionTypeID, [11, 12])) {
                $inclusiveDiscountTotal = $newGrandTotalBeforeDiscount * $promotionModel->discount / 100;
                if (($promotionModel->maxSalesPrice && $promotionModel->maxSalesPrice > 0) && $inclusiveDiscountTotal > $promotionModel->maxSalesPrice) {
                    $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                }
            } else if ($promotionModel->promotionTypeID == 11) {
                $inclusiveDiscountTotal = $newGrandTotalBeforeDiscount * $salesHead['promotionDiscount'] / 100;
            }

            foreach ($salesMenusModel as $salesMenu) {

                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] == 2));
                $tempInclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : (isset($menuTemplateDetailModel[$salesMenu['menuID']]) ? $menuTemplateDetailModel[$salesMenu['menuID']]->price : 0);
                $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $tempInclusivePrice : $salesMenu['price'];

                $inclusiveDiscountValue = 0;
                $discountValue = isset($salesMenu['discountValue']) ? $salesMenu['discountValue'] : 0;
                if (($inclusiveAfterDiscount || $inclusiveBeforeDiscount) && isset($salesMenu['inclusiveDiscountValue']) && $salesMenu['inclusiveDiscountValue'] > 0) {
                    $inclusiveDiscountValue = $salesMenu['inclusiveDiscountValue'];
                }
                $menuSubtotal = $applyPrice * $salesMenu['qty'];
                if ($inclusiveAfterDiscount) {
                    $menuSubtotal = $applyPrice * $salesMenu['qty'] - $inclusiveDiscountValue;
                }

                if ($newGrandTotalBeforeDiscount === 0) {
                    continue;
                } else {
                    $discountBill = ($inclusiveDiscountTotal == 0 && $newGrandTotalBeforeDiscount == 0 || $menuSubtotal == 0) ? 0 : ($inclusiveDiscountTotal * $menuSubtotal) / $newGrandTotalBeforeDiscount;
                }

                $applyOnBill = ApplyOrderPromo::checkAppliedPromo($promotionModel->promotionID, $salesMenu, $menuCategoryIDPromotionCategory, $menuCategoryDetailIDPromotionCategory, $menuIDPromotionCategory);
                if (!$applyOnBill) {
                    $discountBill = 0;
                }

                $totalAfterBillDiscount = ($applyPrice * $salesMenu['qty']) - $discountBill - $inclusiveDiscountValue;
                $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;

                $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                if (isset($salesMenu['flagLuxuryItem'])) {
                    $taxValue = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenu['otherVat']) : $salesMenu['vat'];
                }

                if ($salesMenu['otherTaxOnVat']) {
                    $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                    $tempMenuSubtotalBeforeDiscount = ($applyPrice * $salesMenu['qty']) * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                } else {
                    $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                    $tempMenuSubtotalBeforeDiscount = ($applyPrice * $salesMenu['qty']) * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                }

                if (!in_array($salesMenu['statusID'], [12,19])) {
                    $finalBillDiscPerMenu = $inclusiveAfterDiscount ? ($tempMenuSubtotalBeforeDiscount - $discountValue - $tempMenuSubtotalAfterDiscount) : $discountBill;
                    $menuSubtotalAfterDiscount += $tempMenuSubtotalAfterDiscount;
                    $menuSubtotalBeforeDiscount += $tempMenuSubtotalBeforeDiscount;
                    $finalBillDisc += $finalBillDiscPerMenu;
                }

                $issetSpecialPrice = false;
                if (in_array($promotionModel->promotionTypeID, [10, 11])) {
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] == 0 &&
                        $salesMenu['originalPrice'] <> $salesMenu['price']) {
                        $issetSpecialPrice = true;
                    }
                }

                if ($forceProRate || ($promotionModel->flagPackageContent == 1 && (in_array($promotionModel->promotionTypeID, $promoPercentage) && count($salesMenu['packages']) > 0))) {
                    foreach ($salesMenu['packages'] as $packages) {
                        $isApplyPckOtherVat = ($vatSubject === 1 && (isset($packages['menuFlagTax']) && $packages['menuFlagTax'] == 2));
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $packages['inclusivePrice'] : $packages['price'];
                        $applyPrice = $applyPrice * $salesMenu['qty'];

                        $inclusiveDiscountValue = 0;
                        $discountValue = isset($packages['discountValue']) ? $packages['discountValue'] * $salesMenu['qty'] : 0;
                        if ($inclusiveAfterDiscount && isset($packages['inclusiveDiscountValue']) && $packages['inclusiveDiscountValue'] > 0) {
                            $inclusiveDiscountValue = $packages['inclusiveDiscountValue'] * $salesMenu['qty'];
                        }

                        $menuSubtotal = $applyPrice * $packages['qty'];
                        if ($inclusiveAfterDiscount) {
                            $menuSubtotal = $applyPrice * $packages['qty'] - $inclusiveDiscountValue;
                        }

                        $discountBill = ($inclusiveDiscountTotal == 0 &&  $newGrandTotalBeforeDiscount == 0 || $menuSubtotal == 0) ? 0 : ($inclusiveDiscountTotal * $menuSubtotal) / $newGrandTotalBeforeDiscount; 
                        $applyOnPck = ApplyOrderPromo::checkAppliedPromo($promotionModel->promotionID, $packages, $menuCategoryIDPromotionCategory, $menuCategoryDetailIDPromotionCategory, $menuIDPromotionCategory);
                        if (!$applyOnPck) {
                            $discountBill = 0;
                        }
                        if (!$applyOnBill && $promotionModel->promotionTypeID == 10) {
                            $discountBill = 0;
                        }

                        if ($issetSpecialPrice) $discountBill = 0;

                        $totalAfterBillDiscount = ($applyPrice * $packages['qty']) - $discountBill - $inclusiveDiscountValue;
                        $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                        $taxValue = $isApplyPckOtherVat ? $packages['otherVat'] : $packages['vat'];
                        if (isset($packages['flagLuxuryItem'])) {
                            $taxValue = $isApplyPckOtherVat ? CalculateTotal::getNotLuxuryVatValue($packages['flagLuxuryItem'], $packages['otherVat']) : $packages['vat'];
                        }

                        if ($packages['otherTaxOnVat']) {
                            $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $packages['otherTax']);
                            $tempMenuSubtotalBeforeDiscount = ($applyPrice * $packages['qty']) * 100 / (100 + $taxValue) * 100 / (100 + $packages['otherTax']);
                        } else {
                            $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $packages['otherTax']);
                            $tempMenuSubtotalBeforeDiscount = ($applyPrice * $packages['qty']) * 100 / (100 + $taxValue + $packages['otherTax']);
                        }

                        if (!in_array($packages['statusID'], [12,19])) {
                            $finalBillDiscPerMenu = $inclusiveAfterDiscount ? ($tempMenuSubtotalBeforeDiscount - $discountValue - $tempMenuSubtotalAfterDiscount) : $discountBill;
                            $menuSubtotalAfterDiscount += $tempMenuSubtotalAfterDiscount;
                            $menuSubtotalBeforeDiscount += $tempMenuSubtotalBeforeDiscount;
                            $finalBillDisc += $finalBillDiscPerMenu;
                        }
                    }
                }

                if ($forceProRate || ($promotionModel->flagMenuExtra == 1 && (in_array($promotionModel->promotionTypeID, $promoPercentage) && count($salesMenu['extras']) > 0))) {
                    foreach ($salesMenu['extras'] as $extra) {
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $extra['inclusivePrice'] : $extra['price'];
                        $applyPrice = $applyPrice * $salesMenu['qty'];

                        $inclusiveDiscountValue = 0;
                        $discountValue = isset($extra['discountValue']) ? ($extra['discountValue'] * $salesMenu['qty']) : 0;
                        if ($inclusiveAfterDiscount && isset($extra['inclusiveDiscountValue']) && $extra['inclusiveDiscountValue'] > 0) {
                            $inclusiveDiscountValue = $extra['inclusiveDiscountValue'] * $salesMenu['qty'];
                        }

                        $menuSubtotal = $applyPrice * $extra['qty'];
                        if ($inclusiveAfterDiscount) {
                            $menuSubtotal = $applyPrice * $extra['qty'] - $inclusiveDiscountValue;
                        }

                        if ($newGrandTotalBeforeDiscount === 0) {
                            continue;
                        } else {
                            $discountBill = ($inclusiveDiscountTotal == 0 &&  $grandtotalBeforeDiscount == 0 || $menuSubtotal == 0) ? 0 : ($inclusiveDiscountTotal * $menuSubtotal) / $newGrandTotalBeforeDiscount;
                        }

                        if (!$applyOnBill) {
                            $discountBill = 0;
                        }

                        if ($issetSpecialPrice) $discountBill = 0;

                        $totalAfterBillDiscount = ($applyPrice * $extra['qty']) - $discountBill - $inclusiveDiscountValue;
                        $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                        $taxValue = $isApplyOtherVat ? $extra['otherVat'] : $extra['vat'];
                        if (isset($extra['flagLuxuryItem'])) {
                            $taxValue = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($extra['flagLuxuryItem'], $extra['otherVat']) : $extra['vat'];
                        }

                        if ($extra['otherTaxOnVat']) {
                            $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $extra['otherTax']);
                            $tempMenuSubtotalBeforeDiscount = ($applyPrice * $extra['qty']) * 100 / (100 + $taxValue) * 100 / (100 + $extra['otherTax']);
                        } else {
                            $tempMenuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $extra['otherTax']);
                            $tempMenuSubtotalBeforeDiscount = ($applyPrice * $extra['qty']) * 100 / (100 + $taxValue + $extra['otherTax']);
                        }

                        if (!in_array($extra['statusID'], [12,19])) {
                            $finalBillDiscPerMenu = $inclusiveAfterDiscount ? ($tempMenuSubtotalBeforeDiscount - $discountValue - $tempMenuSubtotalAfterDiscount) : $discountBill;
                            $menuSubtotalAfterDiscount += $tempMenuSubtotalAfterDiscount;
                            $menuSubtotalBeforeDiscount += $tempMenuSubtotalBeforeDiscount;
                            $finalBillDisc += $finalBillDiscPerMenu;
                        }
                    }
                }
            }
        } else {
            $menuSubtotalAfterDiscount = 0;
            $menuSubtotalBeforeDiscount = 0;
            $finalBillDisc = 0;
            $newGrandTotalBeforeDiscount =  0;
            $extraTaxValue = 0;
            $menuTypeIsMain = ($menuType == 'Main');
            $qtyHead = 1;

            foreach ($salesHead['salesMenu'] as $sm) {
                $isApplyOtherVat = ($vatSubject === 1 && (isset($sm['menuFlagTax']) && $sm['menuFlagTax'] == 2));
                $tempInclusivePrice = isset($sm['inclusivePrice']) ? $sm['inclusivePrice'] : (isset($menuTemplateDetailModel[$sm['menuID']]) ? $menuTemplateDetailModel[$sm['menuID']]->price : 0);
                $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $tempInclusivePrice : $sm['price'];
                if (!$menuTypeIsMain && $salesMenu['salesNum'] == $sm['salesNum']) {
                    $qtyHead = $sm['qty'];
                }
                if (in_array($sm['statusID'], [1, 13, 14, 34])) {
                    $newGrandTotalBeforeDiscount += ($applyPrice * $sm['qty']);
                    if ($inclusiveAfterDiscount) {
                        $inclusiveDiscountValue = isset($sm['inclusiveDiscountValue']) ? $sm['inclusiveDiscountValue'] : 0;
                        $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                    }
                }

                if (isset($sm['packages']) && count($sm['packages']) > 0) {
                    foreach ($sm['packages'] as $package) {
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $package['inclusivePrice'] : $package['price'];
                        if (in_array($sm['statusID'], [1, 13, 14, 34])) {
                            $newGrandTotalBeforeDiscount += ($applyPrice * $package['qty'] * $sm['qty']);
                            if ($inclusiveAfterDiscount) {
                                $inclusiveDiscountValue = isset($package['inclusiveDiscountValue']) ? $package['inclusiveDiscountValue'] * $sm['qty'] : 0;
                                $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                            }
                        }
                    }
                }

                if (isset($sm['extras']) && count($sm['extras']) > 0) {
                    foreach ($sm['extras'] as $extras) {
                        $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $extras['inclusivePrice'] : $extras['price'];
                        if (in_array($sm['statusID'], [1, 13, 14, 34])) {
                            $newGrandTotalBeforeDiscount += ($applyPrice * $extras['qty'] * $sm['qty']);
                            if ($inclusiveAfterDiscount) {
                                $inclusiveDiscountValue = isset($extras['inclusiveDiscountValue']) ? $extras['inclusiveDiscountValue'] * $sm['qty'] : 0;
                                $newGrandTotalBeforeDiscount = $newGrandTotalBeforeDiscount - $inclusiveDiscountValue;
                            }
                            if ($menuType === 'Extra' && ($extras['ID'] === $salesMenu['ID'])) {
                                $extraTaxValue = $isApplyOtherVat ? $sm['otherVat'] : $sm['vat'];
                            }
                        }
                    }
                }
            }

            $byPassMaxSalesPrice = !in_array($promotionModel->promotionTypeID, [11, 12]);
            if ($byPassMaxSalesPrice) {
                if (in_array($promotionModel->promotionTypeID, $promoPercentage)) {
                    $inclusiveDiscountTotal = $newGrandTotalBeforeDiscount*$promotionModel->discount/100;
    
                    if ($inclusiveDiscountTotal > $promotionModel->maxSalesPrice && $byPassMaxSalesPrice) {
                        $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                    }
                } elseif (in_array($promotionModel->promotionTypeID, $promoRp)) {
                    $inclusiveDiscountTotal = $promotionModel->discount;
                    if ($inclusiveDiscountTotal > $promotionModel->maxSalesPrice && $byPassMaxSalesPrice) {
                        $inclusiveDiscountTotal = $promotionModel->maxSalesPrice;
                    }
                }
            }

            $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
            $applyPrice = ($flagInclusive && $flagInclusive == 1) ? $salesMenu['inclusivePrice'] : $salesMenu['price'];
            $applyPrice = $applyPrice * $salesMenu['qty'];
            
            $menuSubtotal = $applyPrice * $qtyHead;
            $inclusiveDiscountValue = 0;
            $discountValue = isset($salesMenu['discountValue']) ? $salesMenu['discountValue'] * $qtyHead : 0;
            if (($inclusiveAfterDiscount || $inclusiveBeforeDiscount) && isset($salesMenu['inclusiveDiscountValue']) && $salesMenu['inclusiveDiscountValue'] > 0) {
                $inclusiveDiscountValue = $salesMenu['inclusiveDiscountValue'] * $qtyHead;
            }
            if ($inclusiveAfterDiscount) {
                $menuSubtotal = ($applyPrice * $qtyHead) - $inclusiveDiscountValue;
            }

            $discountBill = ($inclusiveDiscountTotal == 0 &&  $newGrandTotalBeforeDiscount == 0 || $menuSubtotal == 0) ? 0 : ($inclusiveDiscountTotal * $menuSubtotal) / $newGrandTotalBeforeDiscount;
            $totalAfterBillDiscount = ($applyPrice * $qtyHead) - $discountBill - $inclusiveDiscountValue;
            $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;

            $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
            
            if ($menuType === 'Extra') {
                $taxValue = $extraTaxValue;
            }

            if ($salesMenu['otherTaxOnVat']) {
                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                $menuSubtotalBeforeDiscount = $applyPrice * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
            } else {
                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                $menuSubtotalBeforeDiscount = $applyPrice * 100 / (100 + $taxValue + $salesMenu['otherTax']);
            }
            $finalBillDisc = $menuSubtotalBeforeDiscount - $discountValue - $menuSubtotalAfterDiscount;
        }

        return [
            'menuSubtotalAfterDiscount' => $menuSubtotalAfterDiscount ,
            'menuSubtotalBeforeDiscount' => $menuSubtotalBeforeDiscount,
            'finalBillDisc' => $finalBillDisc,
            'totalAfterBillDiscount' => $totalAfterBillDiscount,
            'discountBill' => $discountBill
        ];
    }

    public static function applyPromotion(&$salesMenu, $applyToBill, $menuCategoryID, $menuCategoryDetailID, $menuID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionCategoryMenuIDs, $promotionModel, $visitPurposeID) {
        if ($applyToBill) {
            $salesMenu['discount'] = 0;
            $salesMenu['promotionDetailID'] = 0;
            if (isset($salesMenu['promotionDetailName'])) {
                $salesMenu['promotionDetailName'] = '';
            }

            // if (isset($salesMenu['packages'])) {
            //     foreach ($salesMenu['packages'] as $package) {
            //         $package['promotionDetailID'] = 0;
            //     }
            // }
        } else {
            $isInclusive = SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                    $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES ? true : false;
            $specialPriceApplied = false;
            $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
                    $visitPurposeID);
            $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID);
            
            if (array_key_exists($salesMenu['menuID'], $specialPriceArrModel)) {
                if (!$isInclusive) {
                    $specialPriceApplied = $specialPriceArrModel[$salesMenu['menuID']] != $salesMenu['originalPrice'];
                } else {
                    $specialPrice = Menu::getNetPriceSpecialPrice($visitPurposeID,
                            $specialPriceArrModel[$salesMenu['menuID']]);
                    $specialPriceApplied = $specialPrice != $salesMenu['originalPrice'];
                }
            }
            
            if (in_array($menuCategoryID, $promotionCategoryIDs) && !$specialPriceApplied) {
                if (SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                        $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {

                    $menuSubtotal = $salesMenu['price'] * $salesMenu['qty'];
                    $menuGrandTotal = $salesMenu['total'];
                    $salesMenu['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                            $menuGrandTotal, $promotionModel->discount);
                    if ($promotionModel->flagPackageContent == 1) {
                        if (isset($salesMenu['packages'])) {
                            foreach ($salesMenu['packages'] as $package) {
                                $menuPackageSubtotal = $package['price'] * $package['qty'] * $salesMenu['qty'];
                                $menuPackageGrandTotal = $package['total'] * $salesMenu['qty'];
                                $package['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuPackageSubtotal,
                                        $menuPackageGrandTotal,
                                        $promotionModel->discount);
                            }
                        }
                    }
                    if ($promotionModel->flagMenuExtra == 1) {
                        if (isset($salesMenu['extras'])) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $menuExtraSubtotal = $extra['price'] * $extra['qty'] * $salesMenu['qty'];
                                $menuExtraGrandTotal = $extra['total'] * $salesMenu['qty'];
                                $extra['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuExtraSubtotal,
                                        $menuExtraGrandTotal,
                                        $promotionModel->discount);
                            }
                        }
                    }
                } else {
                    if ($promotionModel->promotionTypeID != 9) {
                        $salesMenu['discount'] = $promotionModel->discount;
                        if ($promotionModel->flagPackageContent == 1) {
                            if (isset($salesMenu['packages'])) {
                                foreach ($salesMenu['packages'] as $package) {
                                    $package['discount'] = $promotionModel->discount;
                                }
                            }
                        }
                        if ($promotionModel->flagMenuExtra == 1) {
                            if (isset($salesMenu['extras'])) {
                                foreach ($salesMenu['extras'] as $extra) {
                                    $extra['discount'] = $promotionModel->discount;
                                }
                            }
                        }
                    }
                }

                $salesMenu['promotionDetailID'] = $promotionModel->promotionID;
                if ($promotionModel->flagPackageContent == 1) {
                    if (isset($salesMenu['packages'])) {
                        foreach ($salesMenu['packages'] as $package) {
                            $package['promotionDetailID'] = $promotionModel->promotionID;
                        }
                    }
                }
                if ($promotionModel->flagMenuExtra == 1) {
                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {      
                            if (!is_object($extra)) {
                                $extra['promotionDetailID'] = $promotionModel->promotionID;
                            }
                        }
                    }
                }
                if (isset($salesMenu['promotionDetailName'])) {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                } else {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                }
            } else if (in_array($menuCategoryDetailID,
                    $promotionCategoryDetailIDs) && !$specialPriceApplied) {
                if (SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                        $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {

                    $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
                            $visitPurposeID);
                    $menuTemplateDetailModel = MenuTemplateDetail::find()
                        ->andWhere([
                            'menuTemplateID' => $mapBranchModel->menuTemplateID,
                            'menuID' => $salesMenu['menuID']
                        ])
                        ->one();

                    $menuSubtotal = $salesMenu['price'] * $salesMenu['qty'];
                    $menuGrandTotal = $menuTemplateDetailModel->price * $salesMenu['qty'];
                    $salesMenu['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                            $menuGrandTotal, $promotionModel->discount);
                    if ($promotionModel->flagPackageContent == 1) {
                        if (isset($salesMenu['packages'])) {
                            foreach ($salesMenu['packages'] as $package) {
                                $package['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                    if ($promotionModel->flagMenuExtra == 1) {
                        if (isset($salesMenu['extras'])) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $extra['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                } else {
                    if ($promotionModel->promotionTypeID != 9) {
                        $salesMenu['discount'] = $promotionModel->discount;
                        if ($promotionModel->flagPackageContent == 1) {
                            foreach ($salesMenu['packages'] as $package) {
                                $package['discount'] = $promotionModel->discount;
                            }
                        }
                        if ($promotionModel->flagMenuExtra == 1) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $extra['discount'] = $promotionModel->discount;
                            }
                        }
                    }
                }

                $salesMenu['promotionDetailID'] = $promotionModel->promotionID;
                if ($promotionModel->flagPackageContent == 1) {
                    if (isset($salesMenu['packages'])) {
                        foreach ($salesMenu['packages'] as $package) {
                            $package['promotionDetailID'] = $promotionModel->promotionID;
                        }
                    }
                }
                if ($promotionModel->flagMenuExtra == 1) {
                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {
                            if (!is_object($extra)) {
                                $extra['promotionDetailID'] = $promotionModel->promotionID;
                            }
                        }
                    }
                }
                if (isset($salesMenu['promotionDetailName'])) {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                } else {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                }
            } else if (in_array($menuID, $promotionCategoryMenuIDs) && !$specialPriceApplied) {
                if (SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                        $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {

                    $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
                            $visitPurposeID);
                    $menuTemplateDetailModel = MenuTemplateDetail::find()
                        ->andWhere([
                            'menuTemplateID' => $mapBranchModel->menuTemplateID,
                            'menuID' => $salesMenu['menuID']
                        ])
                        ->one();

                    $menuSubtotal = $salesMenu['price'] * $salesMenu['qty'];
                    $menuGrandTotal = $menuTemplateDetailModel->price * $salesMenu['qty'];
                    $salesMenu['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                            $menuGrandTotal, $promotionModel->discount);
                    if ($promotionModel->flagPackageContent == 1) {
                        if (isset($salesMenu['packages'])) {
                            foreach ($salesMenu['packages'] as $package) {
                                $package['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                    if ($promotionModel->flagMenuExtra == 1) {
                        if (isset($salesMenu['extras'])) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $extra['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                } else {
                    if ($promotionModel->promotionTypeID != 9) {
                        $salesMenu['discount'] = $promotionModel->discount;
                        if ($promotionModel->flagPackageContent == 1) {
                            if (isset($salesMenu['packages'])) {
                                foreach ($salesMenu['packages'] as $package) {
                                    $package['discount'] = $promotionModel->discount;
                                }
                            }
                        }
                        if ($promotionModel->flagMenuExtra == 1) {
                            if (isset($salesMenu['extras'])) {
                                foreach ($salesMenu['extras'] as $extra) {
                                    $extra['discount'] = $promotionModel->discount;
                                }
                            }
                        }
                    }
                }

                $salesMenu['promotionDetailID'] = $promotionModel->promotionID;
                if ($promotionModel->flagPackageContent == 1) {
                    if (isset($salesMenu['packages'])) {
                        foreach ($salesMenu['packages'] as $package) {
                            $package['promotionDetailID'] = $promotionModel->promotionID;
                        }
                    }
                }
                if ($promotionModel->flagMenuExtra == 1) {
                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {
                            if (!is_object($extra)) {
                                $extra['promotionDetailID'] = $promotionModel->promotionID;
                            }
                        }
                    }
                }
                if (isset($salesMenu['promotionDetailName'])) {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                } else {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                }
            } else if (count($promotionModel->promotionCategories) == 0 && !$specialPriceApplied) {
                if (SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                        $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {
                    $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
                            $visitPurposeID);
                    $menuTemplateDetailModel = MenuTemplateDetail::find()
                        ->andWhere([
                            'menuTemplateID' => $mapBranchModel->menuTemplateID,
                            'menuID' => $salesMenu['menuID']
                        ])
                        ->one();

                    $menuSubtotal = $salesMenu['price'] * $salesMenu['qty'];
                    $menuGrandTotal = $menuTemplateDetailModel->price * $salesMenu['qty'];
                    $salesMenu['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                            $menuGrandTotal, $promotionModel->discount);
                    if ($promotionModel->flagPackageContent == 1) {
                        if (isset($salesMenu['packages'])) {
                            foreach ($salesMenu['packages'] as $package) {
                                $package['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                    if ($promotionModel->flagMenuExtra == 1) {
                        if (isset($salesMenu['extras'])) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $extra['discount'] = $promotionModel->promotionTypeID == 9 ? 0 : self::calculateInclusiveDiscountPercentage($menuSubtotal,
                                        $menuGrandTotal, $promotionModel->discount);
                            }
                        }
                    }
                } else {
                    if ($promotionModel->promotionTypeID != 9) {
                        $salesMenu['discount'] = $promotionModel->discount;
                        if ($promotionModel->flagPackageContent == 1) {
                            foreach ($salesMenu['packages'] as $package) {
                                $package['discount'] = $promotionModel->discount;
                            }
                        }
                        if ($promotionModel->flagMenuExtra == 1) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $extra['discount'] = $promotionModel->discount;
                            }
                        }
                    }
                }

                $salesMenu['promotionDetailID'] = $promotionModel->promotionID;
                if ($promotionModel->flagPackageContent == 1) {
                    if (isset($salesMenu['packages'])) {
                        foreach ($salesMenu['packages'] as $package) {
                            $package['promotionDetailID'] = $promotionModel->promotionID;
                        }
                    }
                }
                if ($promotionModel->flagMenuExtra == 1) {
                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {
                            if (!is_object($extra)) {
                                $extra['promotionDetailID'] = $promotionModel->promotionID;
                            }
                        }
                    }
                }
                if (isset($salesMenu['promotionDetailName'])) {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                } else {
                    $salesMenu['promotionDetailName'] = $promotionModel->notes;
                }
            } else {
                $salesMenu['discount'] = 0;
                $salesMenu['promotionDetailID'] = 0;
                if (isset($salesMenu['promotionDetailName'])) {
                    $salesMenu['promotionDetailName'] = '';
                } else {
                    $salesMenu['promotionDetailName'] = '';
                }
            }
        }

        if ($promotionModel->promotionTypeID != 9) {            
            $salesMenu['total'] = SalesHead::calculateOrderMenuTotal($salesMenu, 
                $visitPurposeID, $promotionModel);
        }
    }

    public static function removePromotion(&$salesMenu, $visitPurposeID) {
        $discountValue = isset($salesMenu['discountValue']) ? $salesMenu['discountValue'] : 0;
        $menuDiscountTotal = isset($salesMenu['discountTotal']) ? $salesMenu['discountTotal'] : $discountValue;
        $salesMenu['discount'] = 0;
        $salesMenu['promotionDetailID'] = 0;

        if (isset($salesMenu['promotionDetailName'])) {
            $salesMenu['promotionDetailName'] = '';
        }

        $salesMenu['total'] = SalesHead::calculateOrderMenuTotal($salesMenu,
                $visitPurposeID);

        if (SalesHead::getInclusiveFlag(Setting::getCurrentBranch(),
                $visitPurposeID)) {
            $salesMenu['total'] = $salesMenu['total'] + $menuDiscountTotal;
        }

        if (isset($salesMenu['packages'])) {
            foreach ($salesMenu['packages'] as $package) {
                $package['discount'] = 0;
                $package['promotionDetailID'] = 0;
            }
        }
    }

    private static function calculateOrderMenuTotal($salesMenu, $visitPurposeID, $promotionModel = null) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        $subtotal = (float) $salesMenu['qty'] * $salesMenu['price'];
        $discount = (float) ($salesMenu['discount'] / 100 * $subtotal);
        $otherVat = isset($salesMenu['otherVat']) ? $salesMenu['otherVat'] : 0;
        $mapBranchModel = self::getMapBranchModel(Setting::getCurrentBranch(),
            $visitPurposeID);

        if ($promotionModel) {
            if ($promotionModel->promotionTypeID == 9) {
                if ($promotionModel->discount > $salesMenu['price']) {
                    $discount = $salesMenu['price'] * $salesMenu['qty'];
                } else {
                    $discount = $promotionModel->discount * $salesMenu['qty'];
                }
            }
        }

        if (SalesHead::getInclusiveFlag($branchID, $visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {

            $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID);
            $specialMenuPrice = null;
            if (array_key_exists($salesMenu['menuID'], $specialPriceArrModel)) {
                $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
            }

            if (isset($salesMenu['total'])) {
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    return $salesMenu['total'];
                }

                return $salesMenu['total'] - $discount;
            } else {
                $menuTemplateDetailModel = MenuTemplateDetail::find()
                    ->andWhere([
                        'menuTemplateID' => $mapBranchModel->menuTemplateID,
                        'menuID' => $salesMenu['menuID']
                    ])
                    ->one();
                $applyPrice = ($salesMenu['price'] == 0 && $salesMenu['promotionDetailID'] > 0) ? 0 : $specialMenuPrice ? $specialMenuPrice : $menuTemplateDetailModel->price;

                return ($salesMenu['qty'] * $applyPrice) - $discount;
            }
        } else {

            $otherTax = (float) ($salesMenu['otherTax'] / 100 * ($otherTaxCalculationType == 2 ? $subtotal - $discount : $subtotal));
            if ($salesMenu['otherTaxOnVat'] == 0) {
                $vat = (float) ($salesMenu['vat'] / 100 * ($taxCalculationType == 2 ? $subtotal - $discount : $subtotal));
                $otherVat = (float) ($otherVat / 100 * ($subtotal - $discount));
            } else {
                $vat = (float) ($salesMenu['vat'] / 100 * (($taxCalculationType == 2 ? $subtotal - $discount : $subtotal) + $otherTax));
                $otherVat = (float) ($otherVat / 100 * (($subtotal - $discount) + $otherTax));
            }

            return $subtotal - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $discount) + $otherTax + $vat + $otherVat;
        }
    }

    public static function calculateArrayHeadTotal(
        &$salesHead,
        $promotionCategoryIDs = [],
        $promotionCategoryDetailIDs = [],
        $promotionMenuIDs = [],
        &$errorMessage = '',
        $salesUpdate = true) {
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);

        $settings = Setting::getPrintingSettings();
        $roundingMode = isset($settings['Rounding Mode']) ? $settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($settings['Rounding Nearest Value']) ? $settings['Rounding Nearest Value'] : 0;

        if (isset($salesHead->transactionModeID) && in_array($salesHead->transactionModeID, [5,7,8,9,10])) {
            //force set rounding to 0 (GoFood, Hubster, GrabFood)
            $roundingNearestValue = 0;
        }

        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $salesHead['visitPurposeID']])->one();
        $otherTaxOnVat = 0;
        $vatSubject = 0;
        if ($mapBranchModel) {
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatSubject = $mapBranchModel->vatSubject;
        }

        $deliveryCostTaxSetting = Setting::getValue1('EZO', 'Delivery Cost Tax');
        $deliveryCostTax = !is_null($deliveryCostTaxSetting) ? $deliveryCostTaxSetting : 0;
        $taxValue = 0;

        $rounding = $roundingNearestValue;

        $flagInclusive = self::getInclusiveFlag($branchID,
                $salesHead['visitPurposeID']);
        $mapBranchModel = self::getMapBranchModel($branchID,
                $salesHead['visitPurposeID']);
        $menuTemplateID = $mapBranchModel ? $mapBranchModel->menuTemplateID : 0;
        $sumMenuSubtotal = 0;
        $sumMenuDiscountTotal = 0;
        $sumInclusiveMenuDiscountTotal = 0;
        $sumMenuOtherTaxTotal = 0;
        $sumMenuVatTotal = 0;
        $sumMenuOtherVatTotal = 0;
        $sumGrandTotal = 0;
        $sumGrandTotalBeforeDiscount = 0;

        $promotionHeadTypeID = 0;
        $applyBillDiscountToPackageContent = 0;
        $applyBillDiscountToExtra = 0;
        $promotionHeadModel = PromotionHead::find()
            ->where(['promotionID' => $salesHead['promotionID']])
            ->one();
        if ($promotionHeadModel) {
            $promotionHeadTypeID = $promotionHeadModel->promotionTypeID;

            $forceProRate = in_array($promotionHeadTypeID, [3, 6, 11, 12, 14]);
            if ($forceProRate) {
                $applyBillDiscountToPackageContent = 1;
                $applyBillDiscountToExtra = 1;
            } else {
                $applyBillDiscountToPackageContent = $promotionHeadModel->flagPackageContent;
                $applyBillDiscountToExtra = $promotionHeadModel->flagMenuExtra;
            }
        }

        if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            $otherTaxCalculationType = $taxCalculationType;
        }

        if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            if ($taxCalculationType == 1 && $otherTaxCalculationType == 1) {
                $calculationMode = SalesHead::INCLUSIVE_BEFORE_DISCOUNT;
            } else {
                $calculationMode = SalesHead::INCLUSIVE_AFTER_DISCOUNT;
            }
        } else {
            if ($taxCalculationType == 1) {
                $calculationMode = SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT;
            } else {
                $calculationMode = SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT;
            }
        }

        $taxBeforeDiscount = ($calculationMode == SalesHead::INCLUSIVE_BEFORE_DISCOUNT) || ($calculationMode == SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT);
        if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            $promotionArrModel = PromotionHead::findActiveArrayValue();
            $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID);
            $newMenuArr = [];
            $tempPromoIDs = [];
            $tempMenuSubtotal = 0;
            $issetSpecialPrice = false;
            $deliveryCostTaxTotal = 0;
            $totalAfterBillDiscount = 0;
            $platformFee = 0;
            foreach ($salesHead['salesMenu'] as $salesMenu) {
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                $subsID = isset($salesMenu['subsID']) ? $salesMenu['subsID'] : 0 ;
                if ($subsID != 0) {
                    $tempMenuID = $subsID;
                }
                else{
                    $menuPromotionID = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] : 0;
                    $tempMenuID = $salesMenu['menuID'];
                    if($menuPromotionID != 0 && ($salesMenu['statusID'] != 1 || $salesMenu['statusID'] != 12)){
                        $tempMenuID = $menuPromotionID;
                    }
                }
                if ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1 || $salesMenu['statusID'] == 46) {
                    $specialMenuPrice = null;
                    $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                    if (array_key_exists($salesMenu['menuID'], $specialPriceArrModel)) {
                        $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                    }

                    $menuTemplateDetailModel = MenuTemplateDetail::find()
                        ->andWhere(['menuTemplateID' => $mapBranchModel->menuTemplateID, 'menuID' => $tempMenuID])
                        ->one();

                    $displayPriceValue = null;
                    if ($salesMenu['price'] == 0 && $salesMenu['promotionDetailID'] > 0) {
                        $applyPrice = 0;
                    } else {
                        if (isset($salesMenu['inclusivePrice']) && $salesMenu['inclusivePrice'] > 0 ) {
                            $applyPrice = $salesMenu['inclusivePrice'];
                        } else {
                            if ($specialMenuPrice && $salesMenu['price'] <> $salesMenu['originalPrice']) {
                                $applyPrice = $specialMenuPrice;
                            } else {
                                if (isset($salesMenu['displayPriceValue'])) {
                                    $displayPriceValue = $salesMenu['displayPriceValue'];
                                }

                                $applyPrice = isset($displayPriceValue)
                                    ? $displayPriceValue
                                    : ($menuTemplateDetailModel ? $menuTemplateDetailModel->price : $displayPriceValue);
                            }
                        }
                    }

                    if (isset($salesMenu['displayPriceValue']) || (isset($salesMenu['displayPriceValue']) && $salesMenu['statusID'] != 1 && $salesMenu['price'] != $salesMenu['originalPrice'])) {
                        $displayPriceValue = $salesMenu['displayPriceValue'];
                    } else {
                        $displayPriceValue = $displayPriceValue === null ? $menuTemplateDetailModel->price : $displayPriceValue;
                    }
                    $menuModel = Menu::findOne($salesMenu['menuID']);
                    $applyPrice = $menuModel->openPrice ? $displayPriceValue : $applyPrice;

                    if (isset($salesMenu['salesType']) && self::checkSalesTypeEzo($salesMenu['salesType'])) {
                      $applyPrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $applyPrice;
                    }

                    //$salesMenuDiscountVal = $salesMenu['promotionDetailID'] > 0 ? $promotionArrModel[$salesMenu['promotionDetailID']] : 0;
                    $detailPromotionTypeID = 0;
                    $detailPromotionPackage = 0;
                    $detailPromotionExtra = 0;
                    $detailPromotionDiscount = 0;
                    $menuPromotionCategoryIDs = [];
                    $menuPromotionCategoryDetailIDs = [];
                    $menuPromotionMenuIDs = [];
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] > 0) {
                        $detailPromotionModel = PromotionHead::find()
                        ->joinWith('promotionCategories')
                        ->where(['ms_promotionhead.promotionID' => $salesMenu['promotionDetailID']])
                        ->one();

                        if ($detailPromotionModel) {
                            foreach ($detailPromotionModel->promotionCategories as $promotionCategory) {
                                $menuPromotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                                $menuPromotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                                $menuPromotionMenuIDs[] = $promotionCategory->menuID;
                            }
                        }

                        if ($detailPromotionModel) {
                            if (!isset($promotionArrModel[$salesMenu['promotionDetailID']]) && !$salesUpdate) {
                                if (!in_array($detailPromotionModel->promotionID, $tempPromoIDs)) {
                                    $tempPromoIDs[] = $detailPromotionModel->promotionID;
                                    if ($errorMessage != '') {
                                        $errorMessage .= ", " . $detailPromotionModel->notes;
                                    } else {
                                        $errorMessage .= $detailPromotionModel->notes;
                                    }
                                }
                            }

                            $detailPromotionTypeID = $detailPromotionModel->promotionTypeID;
                            $detailPromotionDiscount = $detailPromotionModel->discount;
                            $detailPromotionPackage = $detailPromotionModel->flagPackageContent;
                            $detailPromotionExtra = $detailPromotionModel->flagMenuExtra;
                        }

                        if ($detailPromotionTypeID == 9) {
                            $salesMenuDiscountVal = 0;
                        } else {
                            $salesMenuDiscountVal = $detailPromotionDiscount;
                        }
                    } else {
                        $salesMenuDiscountVal = 0;
                    }

                    $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $applyPrice;
                    $menuDiscount = $menuGrandtotalBeforeDiscount * $salesMenuDiscountVal / 100;
                    if ($detailPromotionTypeID == 4 || $detailPromotionTypeID == 18 || $detailPromotionTypeID == 19) {
                        $menuDiscount = 0;
                        $applyPrice = 0;
                        $menuGrandtotalBeforeDiscount = 0;
                    }
                    if ($detailPromotionTypeID == 9) {
                        if ($detailPromotionDiscount > $applyPrice) {
                            $menuDiscount = $applyPrice * $salesMenu['qty'];
                        } else {
                            $menuDiscount = $detailPromotionDiscount * $salesMenu['qty'];
                        }
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($otherTaxOnVat) {
                                $netPrice = ($applyPrice * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']));
                            } else {
                                $netPrice = ($applyPrice * 100 / (100 + $taxValue + $salesMenu['otherTax']));
                            }
                            $inclusivePrice = $salesMenu['inclusivePrice'];
                            if ($inclusivePrice > 0) {
                                $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                            } else {
                                $tempPromotionDiscount = 0;
                            }
                            if ($tempPromotionDiscount > $netPrice) {
                                $menuDiscount = $netPrice * $salesMenu['qty'];
                            } else {
                                if ($inclusivePrice > 0) {
                                    $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                    $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                    $menuDiscount = $tempDiscountValue * $salesMenu['qty'];
                                } else {
                                    $menuDiscount = 0;
                                }                            
                            }
                        }
                    }

                    $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                    if ($otherTaxOnVat) {
                        $menuOtherTaxTotal = ($menuGrandtotalBeforeDiscount) * 100 / (100 + $taxValue) * $salesMenu['otherTax'] / (100 + $salesMenu['otherTax']);
                        $menuVatTotal = ($menuGrandtotalBeforeDiscount) * $taxValue / (100 + $taxValue);
                    } else {
                        $menuOtherTaxTotal = ($menuGrandtotalBeforeDiscount) * 100 / (100 + $salesMenu['otherTax'] + $taxValue) * $salesMenu['otherTax'] / 100;
                        $menuVatTotal = ($menuGrandtotalBeforeDiscount) * 100 / (100 + $salesMenu['otherTax'] + $taxValue) * $taxValue / 100;
                    }

                    $applyDiscountBill = false;
                    if ($promotionHeadModel) {
                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                    }

                    if ($applyDiscountBill) {
                        $tempMenuSubtotal += ($taxBeforeDiscount ? $menuGrandtotalBeforeDiscount : $menuGrandtotalAfterDiscount );
                    }

                    // Menu Package
                    foreach ($salesMenu['packages'] as $package) {
                        $isApplyPckOtherVat = ($vatSubject === 1 && (isset($package['menuFlagTax']) && $package['menuFlagTax'] === 2));
                        $pckTaxValue = $isApplyPckOtherVat ? $package['otherVat'] : $package['vat'];
                        if (isset($package['menuPromotionID']) && $package['menuPromotionID'] != 0){
                            $tempPackageMenuID = $package['menuPromotionID'];
                        }
                        else{
                            $tempPackageMenuID = $package['menuID'];
                        }
                        $menuPackageModel = MenuPackage::find()
                            ->where([
                                'menuID' => $tempPackageMenuID,
                                'menuGroupID' => $package['menuGroupID']
                            ])
                            ->one();
                        
                        $menuPackageModel = MenuPackage::find()
                            ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                                $query->andOnCondition([
                                    'map_menutemplatepackage.menuTemplateID' => $menuTemplateID
                                ]);
                            }])
                            ->where([
                                'ms_menupackage.menuID' => $tempPackageMenuID,
                                'ms_menupackage.menuGroupID' => $package['menuGroupID']
                            ])
                        ->one();

                        $displayPriceValue = null;
                        if (isset($package['displayPriceValue'])) {
                            $displayPriceValue = $package['displayPriceValue'];
                        }

                        $applyPackagePrice = isset($displayPriceValue)
                            ? $displayPriceValue
                            : ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price);

                        if (isset($package['salesType']) && self::checkSalesTypeEzo($package['salesType'])) {
                          $applyPackagePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $applyPackagePrice;
                        }

                        if ($detailPromotionPackage == 1) {
                            if (count($detailPromotionModel->promotionCategories) > 0) {
                                $menuModel = Menu::find()
                                    ->joinWith('menuCategoryDetail')
                                    ->where(['menuID' => $tempPackageMenuID])
                                    ->one();

                                if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else {
                                    $package['promotionDetailID'] = 0;
                                }
                            } else {
                                $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                            }

                            if ($package['promotionDetailID'] != 0) {
                                $applyPackagePrice = $detailPromotionTypeID == 4 ? 0 : $applyPackagePrice;
                                $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                if ($detailPromotionTypeID == 9) {
                                    if ($detailPromotionDiscount > $applyPackagePrice) {
                                        $menuDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                    } else {
                                        $menuDiscount = $salesMenu['qty'] * $package['qty'] * $detailPromotionDiscount;
                                    }
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($otherTaxOnVat) {
                                            $netPrice = ($applyPackagePrice * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']));
                                        } else {
                                            $netPrice = ($applyPackagePrice * 100 / (100 + $pckTaxValue + $package['otherTax']));
                                        }
                                        $inclusivePrice = $package['inclusivePrice'];
                                        if ($inclusivePrice > 0) {
                                            $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                                        } else {
                                            $tempPromotionDiscount = 0;
                                        }
                                        if ($tempPromotionDiscount > $netPrice) {
                                            $menuDiscount = $netPrice * $salesMenu['qty'] * $package['qty'];
                                        } else {
                                            if ($inclusivePrice > 0) {
                                                $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                                $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                                $menuDiscount = $tempDiscountValue * $salesMenu['qty'] * $package['qty'];
                                            } else {
                                                $menuDiscount = 0;
                                            }                            
                                        }
                                    }
                                } else if ($detailPromotionTypeID == 1) {
                                    $menuDiscount = $menuGrandtotalBeforeDiscount * ($detailPromotionDiscount / 100);
                                } else {
                                    $menuDiscount = $menuGrandtotalBeforeDiscount * ($package['discount'] / 100);
                                }
                            } else {
                                $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                $menuDiscount = 0;
                            }
                        } else {
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                            $menuDiscount = 0;
                        }

                        $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                        $menuOtherTaxTotal = ($menuGrandtotalBeforeDiscount) * 100 / (100 + $pckTaxValue) * $package['otherTax'] / (100 + $package['otherTax']);
                        $menuVatTotal = ($menuGrandtotalBeforeDiscount) * $pckTaxValue / (100 + $pckTaxValue);
                        $menuSubtotalAfterDiscount = $menuGrandtotalAfterDiscount + $menuDiscount - $menuVatTotal - $menuOtherTaxTotal;
                        $package['total'] = $menuGrandtotalAfterDiscount / $salesMenu['qty'];

                        if ($promotionHeadTypeID == 10) {
                            if ($applyDiscountBill) {
                                if ($applyBillDiscountToPackageContent) {
                                    $applyDiscountBillPck = false;
                                    if ($promotionHeadModel) {
                                        $applyDiscountBillPck = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                    }

                                    if ($applyDiscountBillPck) {
                                        $tempMenuSubtotal += ($taxBeforeDiscount ? $menuGrandtotalBeforeDiscount : $menuGrandtotalAfterDiscount );
                                    }
                                }
                            }
                        } else {
                            if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                    $applyDiscountBillPck = false;
                                    if ($promotionHeadModel) {
                                        $applyDiscountBillPck = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                    }

                                    if ($applyDiscountBillPck) {
                                        $tempMenuSubtotal += ($taxBeforeDiscount ? $menuGrandtotalBeforeDiscount : $menuGrandtotalAfterDiscount );
                                    }   
                                }
                        }
                    }
                    
                    // Menu Extra
                    foreach ($salesMenu['extras'] as $extra) {
                        $extTaxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $extra['vat'];
                        $menuExtraModel = MenuExtra::find()
                            ->where([
                                'menuExtraID' => $extra['menuExtraID'],
                            ])
                            ->one();

                        $displayPriceValue = null;
                        if (isset($extra['displayPriceValue'])) {
                            $displayPriceValue = $extra['displayPriceValue'];
                        }

                        $applyExtraPrice = isset($displayPriceValue)
                            ? $displayPriceValue
                            : ($menuExtraModel ? $menuExtraModel->price : 0);

                        if ($detailPromotionExtra == 1) {
                            $applyExtraPrice = $detailPromotionTypeID == 4 ? 0 : $menuExtraModel->price;
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $extra['qty'] * $applyExtraPrice;
                            if ($detailPromotionTypeID == 9) {
                                if ($detailPromotionDiscount > $menuExtraModel->price) {
                                    $menuDiscount = $salesMenu['qty'] * $extra['qty'] * $menuExtraModel->price;
                                } else {
                                    $menuDiscount = $salesMenu['qty'] * $extra['qty'] * $detailPromotionDiscount;
                                }
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    if ($otherTaxOnVat) {
                                        $netPrice = ($applyExtraPrice * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']));
                                    } else {
                                        $netPrice = ($applyExtraPrice * 100 / (100 + $extTaxValue + $extra['otherTax']));
                                    }
                                    $inclusivePrice = $extra['inclusivePrice'];
                                    if ($inclusivePrice > 0) {
                                        $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                                    } else {
                                        $tempPromotionDiscount = 0;
                                    }
                                    if ($tempPromotionDiscount > $netPrice) {
                                        $menuDiscount = $netPrice * $salesMenu['qty'] * $extra['qty'];
                                    } else {
                                        if ($inclusivePrice > 0) {
                                            $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                            $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                            $menuDiscount = $tempDiscountValue * $salesMenu['qty'] * $extra['qty'];
                                        } else {
                                            $menuDiscount = 0;
                                        }                            
                                    }
                                }
                            } else if ($detailPromotionTypeID == 1) {
                                $menuDiscount = $menuGrandtotalBeforeDiscount * ($detailPromotionDiscount / 100);
                            } else {
                                $menuDiscount = $menuGrandtotalBeforeDiscount * ($extra['discount'] / 100);
                            }
                        } else {
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $extra['qty'] * $menuExtraModel->price;
                            $menuDiscount = 0;
                        }

                        $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                        $menuOtherTaxTotal = ($menuGrandtotalBeforeDiscount) * 100 / (100 + $extTaxValue) * $extra['otherTax'] / (100 + $extra['otherTax']);
                        $menuVatTotal = ($menuGrandtotalBeforeDiscount) * $extTaxValue / (100 + $extTaxValue);
                        $menuSubtotalAfterDiscount = $menuGrandtotalAfterDiscount + $menuDiscount - $menuVatTotal - $menuOtherTaxTotal;
                        $extra['total'] = $menuGrandtotalAfterDiscount / $salesMenu['qty'];

                        if ($applyDiscountBill) {
                            if ($applyBillDiscountToExtra) {
                                $tempMenuSubtotal += ($taxBeforeDiscount ? $menuGrandtotalBeforeDiscount : $menuGrandtotalAfterDiscount );  
                            }
                        }
                    }
                    if ($detailPromotionTypeID != 4 && isset($salesMenu['originalPrice'])) {
                        if ($salesMenu['price'] <> $salesMenu['originalPrice']) {
                            $issetSpecialPrice = true;
                        }
                    }
                }
            }

            foreach ($salesHead['salesMenu'] as $salesMenu) {
                $subsID = isset($salesMenu['subsID']) ? $salesMenu['subsID'] : 0 ;
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));

                if ($subsID != 0) {
                    $tempMenuID = $subsID;
                }
                else{
                    $menuPromotionID = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] : 0;
                    $tempMenuID = $salesMenu['menuID'];
                    if($menuPromotionID != 0 && ($salesMenu['statusID'] != 1 || $salesMenu['statusID'] != 12)){
                        $tempMenuID = $menuPromotionID;
                    }
                }
                if ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1 || $salesMenu['statusID'] == 46) {
                    $specialMenuPrice = null;
                    $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                    if (isset($salesMenu['flagLuxuryItem'])) {
                        $taxValue = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenu['otherVat']) : $salesMenu['vat'];
                    }
                    
                    if (array_key_exists($salesMenu['menuID'], $specialPriceArrModel)) {
                        $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                    }

                    $menuTemplateDetailModel = MenuTemplateDetail::find()
                        ->andWhere(['menuTemplateID' => $mapBranchModel->menuTemplateID, 'menuID' => $tempMenuID])
                        ->one();

                    $displayPriceValue = null;
                    if ($salesMenu['price'] == 0 && $salesMenu['promotionDetailID'] > 0) {
                        $applyPrice = 0;
                    } else {
                        if ($specialMenuPrice) {
                            $applyPrice = $specialMenuPrice;
                            if($salesMenu['price'] != $salesMenu['originalPrice'] && isset($salesMenu['salesType']) && $salesMenu['salesType'] == 'POS'){
                                if (isset($salesMenu['inclusivePrice']) && $salesMenu['inclusivePrice'] != $specialMenuPrice) {
                                    $applyPrice = $salesMenu['inclusivePrice'];
                                }
                            }
                        } else {
                            if ($specialMenuPrice) {
                                $applyPrice = $specialMenuPrice;
                                if($salesMenu['price'] != $salesMenu['originalPrice'] && isset($salesMenu['salesType']) && $salesMenu['salesType'] == 'POS'){
                                    if (isset($salesMenu['inclusivePrice']) && $salesMenu['inclusivePrice'] != $specialMenuPrice) {
                                        $applyPrice = $salesMenu['inclusivePrice'];
                                    }
                                }
                            } else {
                                if (isset($salesMenu['displayPriceValue'])) {
                                    $displayPriceValue = $salesMenu['displayPriceValue'];
                                }

                                $applyPrice = isset($displayPriceValue)
                                    ? $displayPriceValue
                                    : ($menuTemplateDetailModel ? $menuTemplateDetailModel->price : $displayPriceValue);
                            }
                        }
                    }

                    if (isset($salesMenu['displayPriceValue']) || (isset($salesMenu['displayPriceValue']) && $salesMenu['statusID'] != 1 && $salesMenu['price'] != $salesMenu['originalPrice'])) {
                        $displayPriceValue = $salesMenu['displayPriceValue'];
                    } else {
                        $displayPriceValue = $displayPriceValue === null ? $menuTemplateDetailModel->price : $displayPriceValue;
                    }
                    
                    $menuModel = Menu::findOne($salesMenu['menuID']);
                    $applyPrice = $menuModel->openPrice ? $displayPriceValue : $applyPrice;

                    if (isset($salesMenu['salesType']) && self::checkSalesTypeEzo($salesMenu['salesType'])) {
                      $applyPrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $applyPrice;
                    }

                    //$salesMenuDiscountVal = $salesMenu['promotionDetailID'] > 0 ? $promotionArrModel[$salesMenu['promotionDetailID']] : 0;
                    $detailPromotionTypeID = 0;
                    $detailPromotionPackage = 0;
                    $detailPromotionExtra = 0;
                    $detailPromotionDiscount = 0;
                    $menuPromotionCategoryIDs = [];
                    $menuPromotionCategoryDetailIDs = [];
                    $menuPromotionMenuIDs = [];
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] > 0) {
                        $detailPromotionModel = PromotionHead::find()
                        ->joinWith('promotionCategories')
                        ->where(['ms_promotionhead.promotionID' => $salesMenu['promotionDetailID']])
                        ->one();

                        if ($detailPromotionModel) {
                            foreach ($detailPromotionModel->promotionCategories as $promotionCategory) {
                                $menuPromotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                                $menuPromotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                                $menuPromotionMenuIDs[] = $promotionCategory->menuID;
                            }

                            $detailPromotionTypeID = $detailPromotionModel->promotionTypeID;
                            $detailPromotionDiscount = $detailPromotionModel->discount;
                            $detailPromotionPackage = $detailPromotionModel->flagPackageContent;
                            $detailPromotionExtra = $detailPromotionModel->flagMenuExtra;
                        }

                        if ($detailPromotionTypeID == 9) {
                            $salesMenuDiscountVal = 0;
                        } else {
                            $salesMenuDiscountVal = $detailPromotionDiscount;
                        }
                    } else {
                        $salesMenuDiscountVal = 0;
                    }

                    $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $applyPrice;
                    $menuDiscount = $menuGrandtotalBeforeDiscount * $salesMenuDiscountVal / 100;
                    $inclusiveMenuDiscount = 0;
                    if (($detailPromotionTypeID == 4 || $detailPromotionTypeID == 18 || $detailPromotionTypeID == 19)) {
                        $menuDiscount = 0;
                        $applyPrice = 0;
                        $menuGrandtotalBeforeDiscount = 0;
                    }

                    if ($detailPromotionTypeID == 9) {
                        if ($detailPromotionDiscount > $applyPrice) {
                            $menuDiscount = $applyPrice * $salesMenu['qty'];
                        } else {
                            $menuDiscount = $detailPromotionDiscount * $salesMenu['qty'];
                        }

                        if ($calculationMode === SalesHead::INCLUSIVE_AFTER_DISCOUNT) {
                            $inclusiveMenuDiscount = $menuDiscount;
                            if ($salesMenu['otherTaxOnVat']) {
                                $netPrice = ($applyPrice * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']));
                            } else {
                                $netPrice = ($applyPrice * 100 / (100 + $taxValue + $salesMenu['otherTax']));
                            }
                            $inclusivePrice = $salesMenu['inclusivePrice'];
                            if ($inclusivePrice > 0) {
                                $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                            } else {
                                $tempPromotionDiscount = 0;
                            }
                            if ($tempPromotionDiscount > $netPrice) {
                                $menuDiscount = $netPrice * $salesMenu['qty'];
                            } else {
                                if ($inclusivePrice > 0) {
                                    $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                    $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                    $menuDiscount = $tempDiscountValue * $salesMenu['qty'];
                                } else {
                                    $menuDiscount = 0;
                                }                            
                            }
                        }
                    } else if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 10 || $detailPromotionTypeID == 11) {
                        if ($calculationMode === SalesHead::INCLUSIVE_AFTER_DISCOUNT) {
                            $inclusiveMenuDiscount = $menuDiscount;
                            $menuDiscount = ($salesMenu['price'] * $salesMenu['qty']) * $detailPromotionDiscount / 100;
                        }
                    }

                    if ($detailPromotionTypeID == 18 || $detailPromotionTypeID == 19) {
                        $salesMenu['promotionTypeID'] = $detailPromotionTypeID;
                    }

                    $salesMenu['inclusivePrice'] = (float) $applyPrice;
                    if ($displayPriceValue == 0 && (isset($salesMenu['inclusivePrice']) && $salesMenu['inclusivePrice'] > 0)) {
                        $salesMenu['displayPriceValue'] = $applyPrice;
                    }
                    $discountBill = 0;
                    $salesMenu['discountValue'] = $menuDiscount;
                    $salesMenu['inclusiveDiscountValue'] = $inclusiveMenuDiscount > 0 ? $inclusiveMenuDiscount : 0;
                    $inclusiveMenuDiscount = $salesMenu['inclusiveDiscountValue'] > 0 ? $salesMenu['inclusiveDiscountValue'] : $menuDiscount;
                    $sumInclusiveMenuDiscountTotal += $inclusiveMenuDiscount;
                    if (($salesMenu['otherTax'] >= 0 || $taxValue >= 0)) {
                        if ($issetSpecialPrice) {
                            if (in_array($promotionHeadTypeID, [3, 6, 10, 11, 12, 14, 15, 16])) {
                                $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                            }
                        } else {
                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                        }                        
                    }

                    $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                    if ($calculationMode === SalesHead::INCLUSIVE_BEFORE_DISCOUNT) {
                        $tempMenuSubtotalBeforeTax = $menuGrandtotalBeforeDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                        $tempSubtotalBeforeTax = $tempMenuSubtotal * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                        $totalAfterBillDiscount = ($discountBill > 0 || $menuDiscount > 0) ? self::getTotalAfterDisc($promotionHeadModel, $salesHead['promotionDiscount'], $menuGrandtotalBeforeDiscount, $menuDiscount, $tempMenuSubtotal, $salesHead['menuDiscountTotal'], $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandtotalBeforeDiscount;
                        $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                        if ($otherTaxOnVat) {
                            $menuSubtotalBeforeDiscount = $menuGrandtotalBeforeDiscount * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                            $menuOtherTaxTotal = $salesMenu['otherTax'] * $menuSubtotalBeforeDiscount / 100;
                            if ($isApplyOtherVat) {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                                $vatValue = ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $taxValue / 100;
                                if (isset($salesMenu['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $salesMenu['flagLuxuryItem'],
                                        $otherTaxOnVat,
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $salesMenu['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            } else {
                                $menuVatTotal = (float) (($menuSubtotalBeforeDiscount + $menuOtherTaxTotal) * $taxValue / 100);
                            }
                        } else {
                            $menuSubtotalBeforeDiscount = $menuGrandtotalBeforeDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                            $menuOtherTaxTotal = $salesMenu['otherTax'] * $menuSubtotalBeforeDiscount / 100;

                            if ($isApplyOtherVat) {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                                $vatValue = $menuSubtotalAfterDiscount * $taxValue / 100;
                                if (isset($salesMenu['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $salesMenu['flagLuxuryItem'],
                                        $otherTaxOnVat,
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $salesMenu['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            } else {
                                $menuVatTotal = (float) ($menuSubtotalBeforeDiscount * $taxValue / 100);
                            }
                        }
                        $menuOtherTaxTotal = (float) $menuOtherTaxTotal;

                    } else {

                        $totalAfterBillDiscount = $menuGrandtotalBeforeDiscount - $discountBill - $inclusiveMenuDiscount;
                        $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                        if ($otherTaxOnVat) {
                            $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                            $menuSubtotalBeforeDiscount = $applyPrice * 100 / (100 + $taxValue) * 100 / (100 + $salesMenu['otherTax']);
                        } else {
                            $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                            $menuSubtotalBeforeDiscount = $applyPrice * 100 / (100 + $taxValue + $salesMenu['otherTax']);
                        }
 
                        $menuSubtotalBeforeDiscount = $menuSubtotalBeforeDiscount * $salesMenu['qty'];
                        $otherTaxValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $salesMenu['otherTax'] / 100;
                        $menuOtherTaxTotal = (float) $otherTaxValue;
                        if ($otherTaxOnVat) {
                            $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $taxValue / 100;
                            if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $salesMenu['flagLuxuryItem'],
                                    $otherTaxOnVat,
                                    $menuSubtotalAfterDiscount,
                                    $menuOtherTaxTotal
                                );
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $salesMenu['otherVat']
                                );
                            }
                            $menuVatTotal = (float) $vatValue;
                        } else {
                            $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $taxValue / 100;
                            if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $salesMenu['flagLuxuryItem'],
                                    $otherTaxOnVat,
                                    $menuSubtotalAfterDiscount,
                                    $menuOtherTaxTotal
                                );
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $salesMenu['otherVat']
                                );
                            }
                            $menuVatTotal = (float) $vatValue;
                        }
                    }

                    if (($detailPromotionTypeID === 4 || $detailPromotionTypeID === 18 || $detailPromotionTypeID === 19)) {
                        $menuVatTotal = 0;
                        $menuOtherTaxTotal = 0;
                    };

                    $sumGrandTotalBeforeDiscount += $menuGrandtotalBeforeDiscount;
                    $sumGrandTotal += $menuGrandtotalAfterDiscount;
                    $sumMenuSubtotal += $menuSubtotalBeforeDiscount;
                    $sumMenuDiscountTotal += $menuDiscount;
                    $sumMenuOtherTaxTotal += $menuOtherTaxTotal;
                    if ($isApplyOtherVat) {
                        $sumMenuOtherVatTotal += $menuVatTotal;
                    } else {
                        $sumMenuVatTotal += $menuVatTotal;
                    }
                    $deliveryCostTaxTotal += $deliveryCostTax ? ($salesHead['deliveryCost'] * $salesMenu['vat'] / 100) : 0;  
                    // Menu Package
                    $packageNewArr = [];
                    foreach ($salesMenu['packages'] as $package) {

                        $isApplyPckOtherVat = ($vatSubject === 1 && (isset($package['menuFlagTax']) && $package['menuFlagTax'] === 2));
                        $pckTaxValue = $isApplyPckOtherVat ? $package['otherVat'] : $package['vat'];
                        if (isset($package['flagLuxuryItem'])) {
                            $pckTaxValue = $isApplyPckOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $package['otherVat']) : $package['vat'];
                        }
                        
                        if (isset($package['menuPromotionID']) && $package['menuPromotionID'] != 0){
                            $tempPackageMenuID = $package['menuPromotionID'];
                        }
                        else{
                            $tempPackageMenuID = $package['menuID'];
                        }
                        $menuPackageModel = MenuPackage::find()
                            ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                                $query->andOnCondition([
                                    'map_menutemplatepackage.menuTemplateID' => $menuTemplateID
                                ]);
                            }])
                            ->where([
                                'ms_menupackage.menuID' => $tempPackageMenuID,
                                'ms_menupackage.menuGroupID' => $package['menuGroupID']
                            ])
                        ->one();

                        
                        $displayPriceValue = null;
                        if (isset($package['displayPriceValue'])) {
                            $displayPriceValue = $package['displayPriceValue'];
                        }

                        $applyPackagePrice = isset($displayPriceValue)
                            ? $displayPriceValue
                            : ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price);

                        if (isset($package['salesType']) && self::checkSalesTypeEzo($package['salesType'])) {
                          $applyPackagePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $applyPackagePrice;
                        }
                        
                        $inclusiveMenuDiscount = 0;
                        if ($detailPromotionPackage == 1) {
                            if (count($detailPromotionModel->promotionCategories) > 0) {
                                $menuModel = Menu::find()
                                    ->joinWith('menuCategoryDetail')
                                    ->where(['menuID' => $tempPackageMenuID])
                                    ->one();

                                if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                } else {
                                    $package['promotionDetailID'] = 0;
                                }
                            } else {
                                $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                            }

                            if ($package['promotionDetailID'] != 0) {
                                $applyPackagePrice = $detailPromotionTypeID == 4 ? 0 : $applyPackagePrice;
                                $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                if ($detailPromotionTypeID == 9) {
                                    if ($detailPromotionDiscount > $applyPackagePrice) {
                                        $menuDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                    } else {
                                        $menuDiscount = $salesMenu['qty'] * $package['qty'] * $detailPromotionDiscount;
                                    }

                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $inclusiveMenuDiscount = $menuDiscount;
                                        if ($package['otherTaxOnVat']) {
                                            $netPrice = ($applyPackagePrice * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']));
                                        } else {
                                            $netPrice = ($applyPackagePrice * 100 / (100 + $pckTaxValue + $package['otherTax']));
                                        }
                                        $inclusivePrice = $package['inclusivePrice'];
                                        if ($inclusivePrice > 0) {
                                            $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                                        } else {
                                            $tempPromotionDiscount = 0;
                                        }
                                        if ($tempPromotionDiscount > $netPrice) {
                                            $menuDiscount = $netPrice * $salesMenu['qty'] * $package['qty'];
                                        } else {
                                            if ($inclusivePrice > 0) {
                                                $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                                $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                                $menuDiscount = $tempDiscountValue * $salesMenu['qty'] * $package['qty'];
                                            } else {
                                                $menuDiscount = 0;
                                            }                            
                                        }
                                    }
                                } else if ($detailPromotionTypeID == 1) {
                                    $menuDiscount = $menuGrandtotalBeforeDiscount * ($detailPromotionDiscount / 100);
                                } else {
                                    $menuDiscount = $menuGrandtotalBeforeDiscount * ($package['discount'] / 100);
                                }

                                if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 10 || $detailPromotionTypeID == 11) {
                                    if ($calculationMode === SalesHead::INCLUSIVE_AFTER_DISCOUNT) {
                                        $inclusiveMenuDiscount = $menuDiscount;
                                        $menuDiscount = ($package['price'] * $salesMenu['qty'] * $package['qty']) * $detailPromotionDiscount / 100;
                                    }
                                }
                            } else {
                                $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                                $menuDiscount = 0;
                            }
                        } else {
                            $package['promotionDetailID'] = 0;
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $package['qty'] * $applyPackagePrice;
                            $menuDiscount = 0;
                            
                        }
                        $package['inclusivePrice'] = (float) $applyPackagePrice;
                        $discountBill = 0;
                        $package['discountValue'] = $menuDiscount / $salesMenu['qty'];
                        $package['inclusiveDiscountValue'] = $inclusiveMenuDiscount > 0 ? ($inclusiveMenuDiscount / $salesMenu['qty']) : 0;
                        $inclusiveMenuDiscount = $package['inclusiveDiscountValue'] > 0 ? $package['inclusiveDiscountValue'] : ($menuDiscount / $salesMenu['qty']);
                        $sumInclusiveMenuDiscountTotal += ($inclusiveMenuDiscount * $salesMenu['qty']);
                        if ($package['otherTax'] >= 0 || $pckTaxValue >= 0) {
                            if ($issetSpecialPrice) {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $package, $inclusiveMenuDiscount, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                    }
                                } else if (in_array($promotionHeadTypeID, [3, 6, 11, 12, 14, 15, 16])) {
                                    $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                        $package, $inclusiveMenuDiscount, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                }
                            } else {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToPackageContent) {
                                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $package, $inclusiveMenuDiscount, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                        }
                                    }
                                } else {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToPackageContent) {
                                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $package, $inclusiveMenuDiscount, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                        }
                                    }
                                }
                            }                        
                        }

                        $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                        if ($calculationMode === SalesHead::INCLUSIVE_BEFORE_DISCOUNT) {
                            $tempMenuSubtotalBeforeTax = $menuGrandtotalBeforeDiscount * 100 / (100 + $pckTaxValue + $package['otherTax']);
                            $tempSubtotalBeforeTax = $tempMenuSubtotal * 100 / (100 + $pckTaxValue + $package['otherTax']);
                            $totalAfterBillDiscount = ($discountBill > 0 || $menuDiscount > 0) ? self::getTotalAfterDisc($promotionHeadModel, $salesHead['promotionDiscount'], $menuGrandtotalBeforeDiscount, $menuDiscount, $tempMenuSubtotal, $salesHead['menuDiscountTotal'], $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandtotalBeforeDiscount;
                            $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                            if ($package['otherTaxOnVat']) {
                                $menuSubtotalBeforeDiscount = $menuGrandtotalBeforeDiscount * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']);
                                $menuOtherTaxTotal = $package['otherTax'] * $menuSubtotalBeforeDiscount / 100;
                                if ($isApplyPckOtherVat) {
                                    $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']);
                                    $vatValue = ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $pckTaxValue / 100;
                                    if (isset($package['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $package['flagLuxuryItem'],
                                            $package['otherTaxOnVat'],
                                            $menuSubtotalAfterDiscount,
                                            $menuOtherTaxTotal
                                        );
                                        $vatValue = CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $package['otherVat']
                                        );
                                    }
                                    $menuVatTotal = (float) $vatValue;
                                } else {
                                    $menuVatTotal = (float) (($menuSubtotalBeforeDiscount + $menuOtherTaxTotal) * $pckTaxValue / 100);
                                }
                            } else {
                                $menuSubtotalBeforeDiscount = $menuGrandtotalBeforeDiscount * 100 / (100 + $pckTaxValue + $package['otherTax']);
                                $menuOtherTaxTotal = $package['otherTax'] * $menuSubtotalBeforeDiscount / 100;
                                if ($isApplyPckOtherVat) {
                                    $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $pckTaxValue + $package['otherTax']);
                                    $vatValue = $menuSubtotalAfterDiscount * $pckTaxValue / 100;
                                    if (isset($package['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $package['flagLuxuryItem'],
                                            $package['otherTaxOnVat'],
                                            $menuSubtotalAfterDiscount,
                                            $menuOtherTaxTotal
                                        );
                                        $vatValue = CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $package['otherVat']
                                        );
                                    }
                                    $menuVatTotal = (float) $vatValue;
                                } else {
                                    $menuVatTotal = (float) ($menuSubtotalBeforeDiscount * $pckTaxValue / 100);
                                }
                            }
                            $menuOtherTaxTotal = (float) $menuOtherTaxTotal;
    
                        } else {
                            $totalAfterBillDiscount = $menuGrandtotalBeforeDiscount - ($inclusiveMenuDiscount * $salesMenu['qty']) - ($discountBill * $salesMenu['qty']);
                            $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                            if ($package['otherTaxOnVat']) {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']);
                                $menuSubtotalBeforeDiscount = $applyPackagePrice * 100 / (100 + $pckTaxValue) * 100 / (100 + $package['otherTax']);
                            } else {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $pckTaxValue + $package['otherTax']);
                                $menuSubtotalBeforeDiscount = $applyPackagePrice * 100 / (100 + $pckTaxValue + $package['otherTax']);
                            }

                            $menuSubtotalBeforeDiscount = $menuSubtotalBeforeDiscount * $package['qty'] * $salesMenu['qty'];
                            $otherTaxValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $package['otherTax'] / 100;
                            $menuOtherTaxTotal = (float) $otherTaxValue;
                            if ($package['otherTaxOnVat']) {
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $pckTaxValue / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $package['otherTaxOnVat'],
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            } else {
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $pckTaxValue / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $package['otherTaxOnVat'],
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            }
                        }

                        $package['total'] = $menuGrandtotalAfterDiscount / $salesMenu['qty'];
                        $sumGrandTotalBeforeDiscount += $menuGrandtotalBeforeDiscount;
                        $sumGrandTotal += $menuGrandtotalAfterDiscount;
                        $sumMenuSubtotal += $menuSubtotalBeforeDiscount;
                        $sumMenuDiscountTotal += $menuDiscount;
                        $sumMenuOtherTaxTotal += $menuOtherTaxTotal;
                        if ($isApplyPckOtherVat) {
                            $sumMenuOtherVatTotal += $menuVatTotal;
                        } else {
                            $sumMenuVatTotal += $menuVatTotal;
                        }
                        $deliveryCostTaxTotal += $deliveryCostTax ? ($salesHead['deliveryCost'] * $package['vat'] / 100) : 0;  
                        $packageNewArr[] = $package;

                    }

                    $salesMenu['packages'] = $packageNewArr;

                    // Menu Extra
                    $extraNewArr = [];
                    foreach ($salesMenu['extras'] as $extra) {
                        $extTaxValue = $isApplyOtherVat ? $extra['otherVat'] : $extra['vat'];
                        if (isset($extra['flagLuxuryItem'])) {
                            $extTaxValue = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($extra['flagLuxuryItem'], $extra['otherVat']) : $extra['vat'];
                        }
                        
                        $menuExtraModel = MenuExtra::find()
                            ->where([
                                'menuExtraID' => $extra['menuExtraID'],
                            ])
                            ->one();
                          
                        
                        $displayPriceValue = null;
                        if (isset($extra['displayPriceValue'])) {
                            $displayPriceValue = $extra['displayPriceValue'];
                        }

                        $applyExtraPrice = isset($displayPriceValue)
                            ? $displayPriceValue
                            : ($menuExtraModel ? $menuExtraModel->price : 0);

                        if (isset($salesMenu['salesType']) && self::checkSalesTypeEzo($salesMenu['salesType'])) {
                            $applyExtraPrice = isset($extra['inclusivePrice']) ? $extra['inclusivePrice'] : $applyExtraPrice;
                        }
                        if ($detailPromotionExtra == 1) {
                            $applyExtraPrice = $detailPromotionTypeID == 4 ? 0 : $applyExtraPrice;
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $extra['qty'] * $applyExtraPrice;
                            if ($detailPromotionTypeID == 9) {
                                if ($detailPromotionDiscount > $applyExtraPrice) {
                                    $menuDiscount = $salesMenu['qty'] * $extra['qty'] * $applyExtraPrice;
                                } else {
                                    $menuDiscount = $salesMenu['qty'] * $extra['qty'] * $detailPromotionDiscount;
                                }
                                
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $inclusiveMenuDiscount = $menuDiscount;
                                    if ($extra['otherTaxOnVat']) {
                                        $netPrice = ($applyExtraPrice * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']));
                                    } else {
                                        $netPrice = ($applyExtraPrice * 100 / (100 + $extTaxValue + $extra['otherTax']));
                                    }
                                    $inclusivePrice = $extra['inclusivePrice'];
                                    if ($inclusivePrice > 0) {
                                        $tempPromotionDiscount = $netPrice / $inclusivePrice * $detailPromotionDiscount;
                                    } else {
                                        $tempPromotionDiscount = 0;
                                    }
                                    if ($tempPromotionDiscount > $netPrice) {
                                        $menuDiscount = $netPrice * $salesMenu['qty'] * $extra['qty'];
                                    } else {
                                        if ($inclusivePrice > 0) {
                                            $percentageDiscountValue = $detailPromotionDiscount / $inclusivePrice * 100;
                                            $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                            $menuDiscount = $tempDiscountValue * $salesMenu['qty'] * $extra['qty'];
                                        } else {
                                            $menuDiscount = 0;
                                        }                            
                                    }
                                }
                            } else if ($detailPromotionTypeID == 1) {
                                $menuDiscount = $menuGrandtotalBeforeDiscount * ($detailPromotionDiscount / 100);
                            } else {
                                $menuDiscount = $menuGrandtotalBeforeDiscount * ($extra['discount'] / 100);
                            }

                            if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 10 || $detailPromotionTypeID == 11) {
                                if ($calculationMode === SalesHead::INCLUSIVE_AFTER_DISCOUNT) {
                                    $inclusiveMenuDiscount = $menuDiscount;
                                    $menuDiscount = ($extra['price'] * $salesMenu['qty'] * $extra['qty']) * $detailPromotionDiscount / 100;
                                }
                            }
                        } else {
                            $menuGrandtotalBeforeDiscount = $salesMenu['qty'] * $extra['qty'] * $applyExtraPrice;
                            $menuDiscount = 0;
                        }

                        $extra['inclusivePrice'] = (float) $applyExtraPrice;
                        $discountBill = 0;
                        $extra['discountValue'] = $menuDiscount / $salesMenu['qty'];
                        $extra['inclusiveDiscountValue'] = $inclusiveMenuDiscount > 0 ? ($inclusiveMenuDiscount / $salesMenu['qty']) : 0;
                        $inclusiveMenuDiscount = $extra['inclusiveDiscountValue'] > 0 ? $extra['inclusiveDiscountValue'] : ($menuDiscount / $salesMenu['qty']);
                        $sumInclusiveMenuDiscountTotal += ($inclusiveMenuDiscount * $salesMenu['qty']);

                        if ($extra['otherTax'] >= 0 || $extTaxValue >= 0) {
                            if ($issetSpecialPrice) {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyBillDiscountToExtra) {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $extra, $inclusiveMenuDiscount, true, $salesMenu['promotionDetailID'], $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                    }
                                } else if (in_array($promotionHeadTypeID, [3, 6, 11, 12, 14, 15, 16])) {
                                    $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                        $extra, $inclusiveMenuDiscount, true, $salesMenu['promotionDetailID'], $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                }
                            } else {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToExtra) {
                                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $extra, $inclusiveMenuDiscount, true, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                        }
                                    }
                                } else {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToExtra) {
                                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $extra, $inclusiveMenuDiscount, true, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                        }
                                    }
                                }
                            }
                        }

                        $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
                        if ($calculationMode === SalesHead::INCLUSIVE_BEFORE_DISCOUNT) {
                            $tempMenuSubtotalBeforeTax = $menuGrandtotalBeforeDiscount * 100 / (100 + $extTaxValue + $extra['otherTax']);
                            $tempSubtotalBeforeTax = $tempMenuSubtotal * 100 / (100 + $extTaxValue + $extra['otherTax']);
                            $totalAfterBillDiscount = ($discountBill > 0 || $menuDiscount > 0) ? self::getTotalAfterDisc($promotionHeadModel, $salesHead['promotionDiscount'], $menuGrandtotalBeforeDiscount, $menuDiscount, $tempMenuSubtotal, $salesHead['menuDiscountTotal'], $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandtotalBeforeDiscount;
                            $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;

                            if ($extra['otherTaxOnVat']) {
                                $menuSubtotalBeforeDiscount = ($menuGrandtotalBeforeDiscount * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']));
                                $menuOtherTaxTotal = $extra['otherTax'] * $menuSubtotalBeforeDiscount / 100;
                                if ($isApplyOtherVat) {
                                    $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']);
                                    $extTaxValue = ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $extTaxValue / 100;
                                    if (isset($extra['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $extra['flagLuxuryItem'],
                                            $extra['otherTaxOnVat'],
                                            $menuSubtotalAfterDiscount,
                                            $menuOtherTaxTotal
                                        );
                                        $extTaxValue = CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $extra['otherVat']
                                        );
                                    }
                                    $menuVatTotal = (float) $extTaxValue;
                                } else {
                                    $menuVatTotal = (float) (($menuSubtotalBeforeDiscount + $menuOtherTaxTotal) * $extTaxValue / 100);
                                }
                            } else {
                                $menuSubtotalBeforeDiscount = ($menuGrandtotalBeforeDiscount * 100 / (100 + $extTaxValue + $extra['otherTax']));
                                $menuOtherTaxTotal = $extra['otherTax'] * $menuSubtotalBeforeDiscount / 100;
                                if ($isApplyOtherVat) {
                                    $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $extTaxValue + $extra['otherTax']);
                                    $extTaxValue = $menuSubtotalAfterDiscount * $extTaxValue / 100;
                                    if (isset($extra['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $extra['flagLuxuryItem'],
                                            $extra['otherTaxOnVat'],
                                            $menuSubtotalAfterDiscount,
                                            $menuOtherTaxTotal
                                        );
                                        $extTaxValue = CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $extra['otherVat']
                                        );
                                    }
                                    $menuVatTotal = (float) $extTaxValue;
                                } else {
                                    $menuVatTotal = (float) ($menuSubtotalBeforeDiscount * $extTaxValue / 100);
                                }
                            }
                            $menuOtherTaxTotal = (float) $menuOtherTaxTotal;
                        } else {
                            $totalAfterBillDiscount = $menuGrandtotalBeforeDiscount - ($inclusiveMenuDiscount * $salesMenu['qty']) - ($discountBill * $salesMenu['qty']);
                            $totalAfterBillDiscount = 0 > $totalAfterBillDiscount ? 0 : $totalAfterBillDiscount;
                            if ($extra['otherTaxOnVat']) {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']);
                                $menuSubtotalBeforeDiscount = $applyExtraPrice * 100 / (100 + $extTaxValue) * 100 / (100 + $extra['otherTax']);
                            } else {
                                $menuSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $extTaxValue + $extra['otherTax']);
                                $menuSubtotalBeforeDiscount = $applyExtraPrice * 100 / (100 + $extTaxValue + $extra['otherTax']);
                            }

                            $menuSubtotalBeforeDiscount = $menuSubtotalBeforeDiscount * $extra['qty'] * $salesMenu['qty'];
                            $otherTaxValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $extra['otherTax'] / 100;
                            $menuOtherTaxTotal = (float) $otherTaxValue;
                            if ($extra['otherTaxOnVat']) {
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : ($menuSubtotalAfterDiscount + $menuOtherTaxTotal) * $extTaxValue / 100;
                                if (isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $extra['otherTaxOnVat'],
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            } else {
                                $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : $menuSubtotalAfterDiscount * $extTaxValue / 100;
                                if (isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $extra['otherTaxOnVat'],
                                        $menuSubtotalAfterDiscount,
                                        $menuOtherTaxTotal
                                    );
                                    $vatValue = $menuSubtotalAfterDiscount == 0 ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $menuVatTotal = (float) $vatValue;
                            }
                        }

                        $extra['total'] = $menuGrandtotalAfterDiscount / $salesMenu['qty'];
                        $sumGrandTotalBeforeDiscount += $menuGrandtotalBeforeDiscount;
                        $sumGrandTotal += $menuGrandtotalAfterDiscount;
                        $sumMenuSubtotal += $menuSubtotalBeforeDiscount;
                        $sumMenuDiscountTotal += $menuDiscount;
                        $sumMenuOtherTaxTotal += $menuOtherTaxTotal;
                        if ($isApplyOtherVat) {
                            $sumMenuOtherVatTotal += $menuVatTotal;
                        } else {
                            $sumMenuVatTotal += $menuVatTotal;
                        }
                        $deliveryCostTaxTotal += $deliveryCostTax ? ($salesHead['deliveryCost'] * $extra['vat'] / 100) : 0;
                        $extraNewArr[] = $extra;
                    }

                    $salesMenu['extras'] = $extraNewArr;
                }
                $newMenuArr[] = $salesMenu;
            }

            $salesHead['salesMenu'] = $newMenuArr;          
            $salesHead['menuDiscountTotal'] = $sumMenuDiscountTotal;
            $salesHead['inclusiveMenuDiscountTotal'] = $sumInclusiveMenuDiscountTotal;
            $salesHead['grandTotal'] = $sumGrandTotalBeforeDiscount - $salesHead['menuDiscountTotal'];
            $salesHead['otherTaxTotal'] = $sumMenuOtherTaxTotal;
            $salesHead['vatTotal'] = $sumMenuVatTotal + $deliveryCostTaxTotal;
            $salesHead['otherVatTotal'] = $sumMenuOtherVatTotal;
            $salesHead['subtotal'] = $sumMenuSubtotal;            

            // @notes: Kalkulasi platform fee
            if (isset($salesHead['platformFee'])) {
                $platformFee = SalesPlatformFee::getPlatformFeeForCalculateTotal($platformFee, $salesHead);
            }

            SalesHead::calculateDiscountTotal($salesHead, false, $tempMenuSubtotal);
            $orderFee = isset($salesHead['orderFee']) ?  $salesHead['orderFee'] : 0;
            $salesHead['grandTotal'] = $salesHead['subtotal'] + $salesHead['deliveryCost'] + $orderFee - $salesHead['discountTotal'] - $salesHead['menuDiscountTotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'] + $salesHead['otherVatTotal'] + $salesHead['voucherTotal'];
            $salesHead['grandTotal'] = ROUND($salesHead['grandTotal'], 3);

            if (0 > $salesHead['grandTotal']) {
                $salesHead['grandTotal']  = 0;
            }

            $finalGrandTotal = $salesHead['grandTotal'];
            if ($rounding != 0) {
                if ($roundingMode == 'DOWN') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'UP') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'AUTO') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding;
                }
            }

            $salesHead['grandTotal'] = $salesHead['grandTotal'] + $platformFee;
        } else {
            $promotionArrModel = PromotionHead::findActiveArrayValue();
            $tempMenuSubtotal = 0;
            $allMenuSubtotal = 0;
            $platformFee = 0;
            $platformFeeIncludeOtherTax = 0;
            $totalPlatformFee = 0;
            $sumSubtotalPlatformFee = 0;
            $tempPromoIDs = [];
            $issetSpecialPrice = false;
            $allMenuDiscountTotal = 0;

            if (isset($salesHead['platformFee'])) {
                foreach ($salesHead['platformFee'] as $row) {
                    if (isset($row['platformFeeTypeID'])) {
                        if ($row['platformFeeTypeID'] == 2 && $row['percentage'] == 0) {
                            $platformFeeIncludeOtherTax += $row['amount'];
                        }
                    }
                }
            }

            foreach ($salesHead['salesMenu'] as $salesMenu) {
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                $discountBill = 0;
                $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                if ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1) {
                    // Normal Menu
                    $billDiscount = 0;
                    $promotionModel = NULL;
                    $promotionTypeID = isset($salesMenu['promotionTypeID']) ? $salesMenu['promotionTypeID'] : 0;
                    $menuSubtotal = $salesMenu['qty'] * $salesMenu['price'];
                    if ($salesHead['promotionID'] != 0) {
                        $promotionModel = PromotionHead::find()->andWhere(['promotionID' => $salesHead['promotionID']])->one();
                    }
                    if ($promotionModel) {
                        if ($promotionModel->promotionTypeID == 1) {
                            //$billDiscount = $menuSubtotal * $salesHead['promotionDiscount'] / 100;
                        }
                    }

                    //$menuDiscount = $promotionTypeID == 3 ? $salesMenu['discount'] * $salesMenu['qty'] : $menuSubtotal * ($salesMenu['discount'] / 100);

                    $menuDiscount = $menuSubtotal * ($salesMenu['discount'] / 100);

                    $detailPromotionTypeID = 0;
                    $detailPromotionModel = null;
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID']) {
                        $detailPromotionModel = PromotionHead::find()
                            ->joinWith('promotionCategories')
                            ->where(['ms_promotionhead.promotionID' => $salesMenu['promotionDetailID']])
                            ->one();
                    }

                    $menuPromotionCategoryIDs = [];
                    $menuPromotionCategoryDetailIDs = [];
                    $menuPromotionMenuIDs = [];
                    if ($detailPromotionModel) {
                        foreach ($detailPromotionModel->promotionCategories as $promotionCategory) {
                            $menuPromotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                            $menuPromotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                            $menuPromotionMenuIDs[] = $promotionCategory->menuID;
                        }
                    }

                    if ($detailPromotionModel) {
                        if (!isset($promotionArrModel[$salesMenu['promotionDetailID']]) && !$salesUpdate) {
                            if (!in_array($detailPromotionModel->promotionID, $tempPromoIDs)) {
                                $tempPromoIDs[] = $detailPromotionModel->promotionID;
                                if ($errorMessage != '') {
                                    $errorMessage .= ", " . $detailPromotionModel->notes;
                                } else {
                                    $errorMessage .= $detailPromotionModel->notes;
                                }
                            }
                        }

                        $detailPromotionTypeID = $detailPromotionModel->promotionTypeID;
                        if ($detailPromotionTypeID == 9) {
                            if ($detailPromotionModel->discount > $salesMenu['price']) {
                                $menuDiscount = $salesMenu['price'] * $salesMenu['qty'];
                            } else {
                                $menuDiscount = $detailPromotionModel->discount * $salesMenu['qty'];
                            }
                        }
                    }

                    $applyDiscountBill = false;
                    if ($promotionHeadModel) {
                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                    }

                    if ($applyDiscountBill) {
                        if ($taxBeforeDiscount) {
                            $tempMenuSubtotal += $salesMenu['qty'] * $salesMenu['price'];
                        } else {
                            $tempMenuSubtotal += $salesMenu['qty'] * $salesMenu['price'] - $menuDiscount;
                        }
                    }

                    $allMenuSubtotal += $salesMenu['qty'] * $salesMenu['price'];
                    $allMenuDiscountTotal += $menuDiscount;

                    // Menu Package
                    foreach ($salesMenu['packages'] as $package) {
                        if (isset($package['menuPromotionID']) && $package['menuPromotionID'] != 0){
                            $tempPackageMenuID = $package['menuPromotionID'];
                        }
                        else{
                            $tempPackageMenuID = $package['menuID'];
                        }
                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagPackageContent == 1) {
                                if (count($detailPromotionModel->promotionCategories) > 0) {
                                    $menuModel = Menu::find()
                                        ->joinWith('menuCategoryDetail')
                                        ->where(['menuID' => $tempPackageMenuID])
                                        ->one();

                                    if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    } else {
                                        $package['promotionDetailID'] = 0;
                                    }
                                } else {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                }
                            }
                        }


                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagPackageContent == 1) {
                                if ($package['promotionDetailID'] != 0) {
                                    if ($detailPromotionModel->promotionTypeID == 4) {
                                        $package['price'] = 0;
                                    }
                                }
                            }
                        }

                        $menuPackageSubtotal = $salesMenu['qty'] * $package['qty'] * $package['price'];
                        $menuPackageDiscountTotal = $menuPackageSubtotal * ($package['discount'] / 100);
                        if ($detailPromotionModel) {                           
                            if ($detailPromotionModel->flagPackageContent == 1) {  
                                if ($package['promotionDetailID'] != 0) {
                                    if ($detailPromotionTypeID == 9) {
                                        if ($detailPromotionModel->discount > $package['price']) {
                                            $menuPackageDiscountTotal = $menuPackageSubtotal;
                                        } else {
                                            $menuPackageDiscountTotal = $salesMenu['qty'] * $package['qty'] * $detailPromotionModel->discount;
                                        }
                                    } else if ($detailPromotionTypeID == 1) {
                                        $menuPackageDiscountTotal = $menuPackageSubtotal * $detailPromotionModel->discount / 100;
                                    } else {
                                        $menuPackageDiscountTotal = 0;
                                    }                                                              
                                } else {
                                    $menuPackageDiscountTotal = 0;
                                }                                
                            } else {
                                $package['promotionDetailID'] = 0;
                                $menuPackageDiscountTotal = 0;
                            }
                        } else {
                            $package['promotionDetailID'] = 0;
                            $menuPackageDiscountTotal = 0;
                        }

                        if ($applyDiscountBill) {
                            if ($promotionHeadTypeID == 10) {
                                if ($applyBillDiscountToPackageContent) {
                                    $applyBillDiscountPck = false;
                                    if ($promotionHeadModel) {
                                        $applyBillDiscountPck = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                    }

                                    if ($applyBillDiscountPck) {
                                        if ($taxBeforeDiscount) {
                                            $tempMenuSubtotal += $menuPackageSubtotal;
                                        } else {
                                            $tempMenuSubtotal += $menuPackageSubtotal - $menuPackageDiscountTotal;
                                        }
                                    }
                                }
                            } else {
                                if ($applyBillDiscountToPackageContent) {
                                    if ($taxBeforeDiscount) {
                                        $tempMenuSubtotal += $menuPackageSubtotal;
                                    } else {
                                        $tempMenuSubtotal += $menuPackageSubtotal - $menuPackageDiscountTotal;
                                    }
                                }
                            }
                        }

                        $allMenuSubtotal += $menuPackageSubtotal;
                        $allMenuDiscountTotal += $menuPackageDiscountTotal;
                    }

                    // Menu Extra
                    foreach ($salesMenu['extras'] as $extra) {
                        $discountBillExtra = 0;
                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagMenuExtra == 1 && $detailPromotionModel->promotionTypeID == 4) {
                                $extra['price'] = 0;
                            }
                        }

                        $menuExtraSubtotal = $salesMenu['qty'] * $extra['qty'] * $extra['price'];
                        $menuExtraDiscountTotal = $menuExtraSubtotal * ($extra['discount'] / 100);

                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagMenuExtra == 1) {
                                $extra['promotionDetailID'] = $detailPromotionModel->promotionID;
                                if ($detailPromotionTypeID == 9) {
                                    if ($detailPromotionModel->discount > $extra['price']) {
                                        $menuExtraDiscountTotal = $menuExtraSubtotal;
                                    } else {
                                        $menuExtraDiscountTotal = $salesMenu['qty'] * $extra['qty'] * $detailPromotionModel->discount;
                                    }
                                } else if ($detailPromotionTypeID == 1) {
                                    $menuExtraDiscountTotal = $menuExtraSubtotal * $detailPromotionModel->discount / 100;
                                } else {
                                    $menuExtraDiscountTotal = 0;
                                }
                            } else {
                                $extra['promotionDetailID'] = 0;
                                $menuExtraDiscountTotal = 0;
                            }
                        } else {
                            $extra['promotionDetailID'] = 0;
                            $menuExtraDiscountTotal = 0;
                        }

                        if ($applyDiscountBill) {
                            if ($applyBillDiscountToExtra) {
                                if ($taxBeforeDiscount) {
                                    $tempMenuSubtotal += $menuExtraSubtotal;
                                } else {
                                    $tempMenuSubtotal += $menuExtraSubtotal - $menuExtraDiscountTotal;
                                }
                            }
                        }

                        $allMenuSubtotal += $menuExtraSubtotal;
                        $allMenuDiscountTotal += $menuExtraDiscountTotal;
                    }

                    if ($detailPromotionTypeID != 4 && isset($salesMenu['originalPrice'])) {
                        if ($salesMenu['price'] <> $salesMenu['originalPrice']) {
                            $issetSpecialPrice = true;
                        }
                    }
                }
            }

            foreach ($salesHead['salesMenu'] as $salesMenu) {
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                $discountBill = 0;
                $otherTaxDiscountBill = 0;
                $taxValue = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                if ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1 || $salesMenu['statusID'] == 46) {
                    // Normal Menu
                    $billDiscount = 0;
                    $promotionModel = NULL;
                    $promotionTypeID = isset($salesMenu['promotionTypeID']) ? $salesMenu['promotionTypeID'] : 0;
                    $menuSubtotal = $salesMenu['qty'] * $salesMenu['price'];
                    if ($salesHead['promotionID'] != 0) {
                        $promotionModel = PromotionHead::find()->andWhere(['promotionID' => $salesHead['promotionID']])->one();
                    }
                    if ($promotionModel) {
                        if ($promotionModel->promotionTypeID == 1) {
                            //$billDiscount = $menuSubtotal * $salesHead['promotionDiscount'] / 100;
                        }
                    }

                    $menuDiscount = $promotionTypeID == 3 ? $salesMenu['discount'] * $salesMenu['qty'] : $menuSubtotal * ($salesMenu['discount'] / 100);

                    $detailPromotionTypeID = 0;
                    $detailPromotionModel = null;
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID']) {
                        $detailPromotionModel = PromotionHead::find()
                            ->joinWith('promotionCategories')
                            ->where(['ms_promotionhead.promotionID' => $salesMenu['promotionDetailID']])
                            ->one();
                    }

                    $menuPromotionCategoryIDs = [];
                    $menuPromotionCategoryDetailIDs = [];
                    $menuPromotionMenuIDs = [];
                    if ($detailPromotionModel) {
                        foreach ($detailPromotionModel->promotionCategories as $promotionCategory) {
                            $menuPromotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                            $menuPromotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                            $menuPromotionMenuIDs[] = $promotionCategory->menuID;
                        }

                        $detailPromotionTypeID = $detailPromotionModel->promotionTypeID;
                        if ($detailPromotionTypeID == 9) {
                            if ($detailPromotionModel->discount > $salesMenu['price']) {
                                $menuDiscount = $salesMenu['price'] * $salesMenu['qty'];
                            } else {
                                $menuDiscount = $detailPromotionModel->discount * $salesMenu['qty'];
                            }
                        }
                    }

                    $applyDiscountBill = false;
                    if ($promotionHeadModel) {
                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesHead['promotionID'], $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                    }

                    if ($salesMenu['otherTax'] >= 0 || $taxValue >= 0) {
                        if ($issetSpecialPrice) {
                            if (in_array($promotionHeadTypeID, [3, 6, 10, 11, 12, 14, 15, 16])) {
                                $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);

                                if ($otherTaxCalculationType == 2) {
                                    $otherTaxDiscountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                        $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, 
                                        [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                }
                            }
                        } else {
                            $discountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, 
                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT, $allMenuDiscountTotal);
                                
                            if ($otherTaxCalculationType == 2) {
                                $otherTaxDiscountBill = SalesHead::calculateDiscountArrayHead($salesHead,
                                    $salesMenu, $menuDiscount, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, 
                                    [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                            }
                        }                        
                    }
                    
                    if ($taxCalculationType == 2 && $otherTaxCalculationType == 2) {
                        $otherTaxDiscountBill = $discountBill;
                    }

                    $menuPlatformFee = 0;
                    if ($platformFeeIncludeOtherTax > 0 && $menuSubtotal > 0 && $allMenuSubtotal > 0) {
                        $menuPlatformFee = round($menuSubtotal / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                        $totalPlatformFee += $menuPlatformFee;
                        $sumSubtotalPlatformFee += $menuSubtotal;

                        if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                            $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                            $menuPlatformFee = $menuPlatformFee + $diffPlatformFee;
                        }
                    }

                    $menuOtherTaxTotal = (($menuSubtotal) -
                        ($otherTaxCalculationType == 2 ? $menuDiscount : 0) -
                        ($otherTaxCalculationType == 2 ? $billDiscount : 0) - ($otherTaxCalculationType == 2 ? $otherTaxDiscountBill : 0)) * ($salesMenu['otherTax'] / 100);

                    if ($menuOtherTaxTotal < 0) {
                        $menuOtherTaxTotal = 0;
                    }

                    if ($menuPlatformFee > 0) {
                        $menuOtherTaxTotal = $menuOtherTaxTotal + $menuPlatformFee;
                    }

                    $sumMenuSubtotal += $menuSubtotal;
                    $sumMenuDiscountTotal += $detailPromotionTypeID == 9 ? $menuDiscount : $menuSubtotal * ($salesMenu['discount'] / 100);
                    $sumMenuOtherTaxTotal += $menuOtherTaxTotal;

                    if ($taxCalculationType == 2) {
                        $newMenuSubtotal = $menuSubtotal - $menuDiscount - $billDiscount - $discountBill;
                    } else {
                        $newMenuSubtotal = $menuSubtotal;
                    }

                    $newMenuOtherTaxSubtotal = 0;
                    if ($isApplyOtherVat) {
                        if ($otherTaxCalculationType == 1) {
                            $otherTaxDiscountBill = $discountBill;
                        }
                        $newMenuOtherTaxSubtotal = $menuSubtotal - $menuDiscount - $billDiscount - $otherTaxDiscountBill;
                    }

                    if ($newMenuOtherTaxSubtotal < 0) {
                        $newMenuOtherTaxSubtotal = 0;
                    }
                    
                    if ($isApplyOtherVat) {
                        if (isset($salesMenu['flagLuxuryItem'])) {
                            $dppValue = CalculateTotal::getDppValue(
                                $salesMenu['flagLuxuryItem'],
                                $salesMenu['otherTaxOnVat'],
                                $newMenuOtherTaxSubtotal,
                                $menuOtherTaxTotal
                            );
                            $sumMenuOtherVatTotal += CalculateTotal::getOtherVatValue(
                                $dppValue,
                                $taxValue
                            );
                        } else {
                            $sumMenuOtherVatTotal += (
                                $newMenuOtherTaxSubtotal +
                                ($salesMenu['otherTaxOnVat'] == 1 ? $menuOtherTaxTotal : 0)
                            ) * ($taxValue / 100);
                        }
                    } else {
                        $sumMenuVatTotal += (
                            $newMenuSubtotal +
                            ($salesMenu['otherTaxOnVat'] == 1 ? $menuOtherTaxTotal : 0)
                        ) * ($taxValue / 100);   
                    }

                    // Menu Package
                    foreach ($salesMenu['packages'] as $package) {
                        $isApplyPckOtherVat = ($vatSubject === 1 && (isset($package['menuFlagTax']) && $package['menuFlagTax'] === 2));
                        $pckTaxValue = $isApplyPckOtherVat ? $package['otherVat'] : $package['vat'];
                        if (isset($package['menuPromotionID']) && $package['menuPromotionID'] != 0){
                            $tempPackageMenuID = $package['menuPromotionID'];
                        }
                        else{
                            $tempPackageMenuID = $package['menuID'];
                        }
                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagPackageContent == 1) {
                                if (count($detailPromotionModel->promotionCategories) > 0) {
                                    $menuModel = Menu::find()
                                        ->joinWith('menuCategoryDetail')
                                        ->where(['menuID' => $tempPackageMenuID])
                                        ->one();

                                    if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                        $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                    }else {
                                        $package['promotionDetailID'] = 0;
                                    }
                                } else {
                                    $package['promotionDetailID'] = $salesMenu['promotionDetailID'];
                                }
                            }
                        }

                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagPackageContent == 1) {
                                if ($package['promotionDetailID'] != 0) {
                                    if ($detailPromotionModel->promotionTypeID == 4) {
                                        $package['price'] = 0;
                                    }
                                }
                            }
                        }

                        $menuPackageSubtotal = $salesMenu['qty'] * $package['qty'] * $package['price'];
                        $menuPackageDiscountTotal = $menuPackageSubtotal * ($package['discount'] / 100);
                        if ($detailPromotionModel) {                           
                            if ($detailPromotionModel->flagPackageContent == 1) {
                                if ($package['promotionDetailID'] != 0) {
                                    if ($detailPromotionTypeID == 9) {
                                        if ($detailPromotionModel->discount > $package['price']) {
                                            $menuPackageDiscountTotal = $salesMenu['qty'] * $package['qty'] * $package['price'];
                                        } else {
                                            $menuPackageDiscountTotal = $salesMenu['qty'] * $package['qty'] * $detailPromotionModel->discount;
                                        }
                                    } else if ($detailPromotionTypeID == 1) {
                                        $menuPackageDiscountTotal = $menuPackageSubtotal * $detailPromotionModel->discount / 100;
                                    } else {
                                        $menuPackageDiscountTotal = 0;
                                    }
                                } else {
                                    $menuPackageDiscountTotal = 0;
                                }
                            } else {
                                $package['promotionDetailID'] = 0;
                                $menuPackageDiscountTotal = 0;
                            }
                        } else {
                            $package['promotionDetailID'] = 0;
                            $menuPackageDiscountTotal = 0;
                        }

                        $discountBillPackage = 0;
                        $otherTaxDiscountBillPackage = 0;
                        if ($package['otherTax'] >= 0 || $pckTaxValue >= 0) {
                            if ($issetSpecialPrice) {
                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11, 12, 14, 15, 16])) {
                                    if ($promotionHeadTypeID == 10) {
                                        if ($applyDiscountBill) {
                                            if ($applyBillDiscountToPackageContent) {
                                                $discountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                                    $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode) * $salesMenu['qty'] ;
                                            }
                                        }
                                    } else {
                                        $discountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode) * $salesMenu['qty'];

                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal) * $salesMenu['qty'] ;
                                        }
                                    }
                                }
                            } else {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyDiscountBill) {
                                        if ($applyBillDiscountToPackageContent) {
                                            $discountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal) * $salesMenu['qty'] ;
                                        }
                                    }
                                } else {
                                    $discountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                        $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode) * $salesMenu['qty'] ;
                                    
                                    if ($otherTaxCalculationType == 2) {
                                        $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $package, $menuPackageDiscountTotal > 0 ? $menuPackageDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                            [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal) * $salesMenu['qty'] ;
                                    }
                                }
                            }
                        }

                        if ($promotionHeadTypeID == 10) {
                            $otherTaxDiscountBillPackage = $discountBillPackage >= 0 ? $discountBillPackage : 0;
                        }

                        $menuPackagePlatformFee = 0;
                        if ($platformFeeIncludeOtherTax > 0 && $package['price'] > 0 && $allMenuSubtotal > 0) {
                            $menuPackageSubtotal = $salesMenu['qty'] * $package['qty'] * $package['price'];
                            $menuPackagePlatformFee = round($menuPackageSubtotal / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                            $totalPlatformFee += $menuPackagePlatformFee;
                            $sumSubtotalPlatformFee += $menuPackageSubtotal;
    
                            if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                $menuPackagePlatformFee = $menuPackagePlatformFee + $diffPlatformFee;
                            }
                        }

                        $menuPackageOtherTaxTotal = (($salesMenu['qty'] * $package['qty'] * $package['price']) - ($otherTaxCalculationType == 2 ? $menuPackageDiscountTotal + $otherTaxDiscountBillPackage : 0)) * ($package['otherTax'] / 100);
                        if ($menuPackageOtherTaxTotal < 0) {
                            $menuPackageOtherTaxTotal = 0;
                        }
                        if ($menuPackagePlatformFee > 0) {
                            $menuPackageOtherTaxTotal = $menuPackageOtherTaxTotal + $menuPackagePlatformFee;
                        }
                        $sumMenuSubtotal += $menuPackageSubtotal;
                        $sumMenuDiscountTotal += $menuPackageDiscountTotal;
                        $sumMenuOtherTaxTotal += $menuPackageOtherTaxTotal;

                        if ($discountBillPackage < 0) {
                            $discountBillPackage = 0;
                        }

                        if ($taxCalculationType == 2 || $isApplyPckOtherVat) {
                            $newPckMenuSubtotal = $menuPackageSubtotal - ($menuPackageDiscountTotal + $discountBillPackage);
                        } else {
                            $newPckMenuSubtotal = $menuPackageSubtotal;
                        }

                        $newPckMenuOtherTaxSubtotal = 0;
                        if ($otherTaxCalculationType == 2 || $isApplyPckOtherVat) {
                            $newPckMenuOtherTaxSubtotal = $menuPackageSubtotal - ($menuPackageDiscountTotal + $otherTaxDiscountBillPackage);
                        }

                        if ($newPckMenuOtherTaxSubtotal < 0) {
                            $newPckMenuOtherTaxSubtotal = 0;
                        }

                        if ($isApplyPckOtherVat) {
                            if (isset($package['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $package['flagLuxuryItem'],
                                    $package['otherTaxOnVat'],
                                    $newPckMenuOtherTaxSubtotal,
                                    $menuPackageOtherTaxTotal
                                );
                                $sumMenuOtherVatTotal += CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $pckTaxValue
                                );
                            } else {
                                $sumMenuOtherVatTotal += (
                                    $newPckMenuOtherTaxSubtotal + 
                                    ($package['otherTaxOnVat'] == 1 ? $menuPackageOtherTaxTotal : 0)
                                ) * ($pckTaxValue / 100);    
                            }
                        } else {
                            $sumMenuVatTotal += (
                                $newPckMenuSubtotal + 
                                ($package['otherTaxOnVat'] == 1 ? $menuPackageOtherTaxTotal : 0)
                            ) * ($pckTaxValue / 100); 
                        }

                    }

                    // Menu Extra
                    foreach ($salesMenu['extras'] as $extra) {
                        $extTaxValue = $isApplyOtherVat ? $extra['otherVat'] : $extra['vat'];
                        $discountBillExtra = 0;
                        $otherTaxDiscountBillExtra = 0;
                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagMenuExtra == 1 && $detailPromotionModel->promotionTypeID == 4) {
                                $extra['price'] = 0;
                            }
                        }

                        $menuExtraSubtotal = $salesMenu['qty'] * $extra['qty'] * $extra['price'];
                        $menuExtraDiscountTotal = $menuExtraSubtotal * ($extra['discount'] / 100);

                        if ($detailPromotionModel) {
                            if ($detailPromotionModel->flagMenuExtra == 1) {
                                $extra['promotionDetailID'] = $detailPromotionModel->promotionID;
                                if ($detailPromotionTypeID == 9) {
                                    if ($detailPromotionModel->discount > $extra['price']) {
                                        $menuExtraDiscountTotal = $salesMenu['qty'] * $extra['qty'] * $extra['price'];
                                    } else {
                                        $menuExtraDiscountTotal = $salesMenu['qty'] * $extra['qty'] * $detailPromotionModel->discount;
                                    }
                                } else if ($detailPromotionTypeID == 1) {
                                    $menuExtraDiscountTotal = $menuExtraSubtotal * $detailPromotionModel->discount / 100;
                                } else {
                                    $menuExtraDiscountTotal = 0;
                                }
                            } else {
                                $extra['promotionDetailID'] = 0;
                                $menuExtraDiscountTotal = 0;
                            }
                        } else {
                            $extra['promotionDetailID'] = 0;
                            $menuExtraDiscountTotal = 0;
                        }

                        if ($extra['otherTax'] >= 0 || $extTaxValue >= 0) {
                            if ($issetSpecialPrice) {
                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11, 12, 14, 15, 16])) {
                                    if ($promotionHeadTypeID == 10) {
                                        if ($applyDiscountBill) {
                                            if ($applyBillDiscountToExtra) {
                                                $discountBillExtra = SalesHead::calculateDiscountArrayHead($salesHead,
                                                    $extra, $menuExtraDiscountTotal > 0 ? $menuExtraDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode) * $salesMenu['qty'];
                                            }
                                        }
                                    } else {
                                        $discountBillExtra = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $extra, $menuExtraDiscountTotal > 0 ? $menuExtraDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode) * $salesMenu['qty'];

                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillExtra = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $extra, $menuExtraDiscountTotal > 0 ? $menuExtraDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal) * $salesMenu['qty'];
                                        }
                                    }
                                }
                            } else {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {
                                        $discountBillExtra = SalesHead::calculateDiscountArrayHead($salesHead,
                                            $extra, $menuExtraDiscountTotal > 0 ? $menuExtraDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode) * $salesMenu['qty'];
                                           
                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillExtra = SalesHead::calculateDiscountArrayHead($salesHead,
                                                $extra, $menuExtraDiscountTotal > 0 ? $menuExtraDiscountTotal / $salesMenu['qty'] : 0, 0, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal) * $salesMenu['qty'];
                                        }
                                    }
                                }
                            }
                        }

                        if ($promotionHeadTypeID == 10) {
                            $otherTaxDiscountBillExtra = $discountBillExtra >= 0 ? $discountBillExtra : 0;
                        }

                        $applyExtraPrice = ($salesMenu['qty'] * $extra['qty'] * $extra['price']);
                        $menuExtraPlatformFee = 0;
                        if ($platformFeeIncludeOtherTax > 0 && $applyExtraPrice > 0 && $allMenuSubtotal > 0) {
                            $menuExtraPlatformFee = round($applyExtraPrice / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                            $totalPlatformFee += $menuExtraPlatformFee;
                            $sumSubtotalPlatformFee += $applyExtraPrice;
    
                            if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                $menuExtraPlatformFee = $menuExtraPlatformFee + $diffPlatformFee;
                            }
                        }

                        $menuExtraOtherTaxTotal = (
                            $applyExtraPrice - 
                            ($otherTaxCalculationType == 2 ? $menuExtraDiscountTotal + $otherTaxDiscountBillExtra : 0)
                        ) * ($extra['otherTax'] / 100);

                        if ($menuExtraOtherTaxTotal < 0) {
                            $menuExtraOtherTaxTotal = 0;
                        }

                        if ($menuExtraPlatformFee > 0) {
                            $menuExtraOtherTaxTotal = $menuExtraOtherTaxTotal + $menuExtraPlatformFee;
                        }
                        
                        $sumMenuSubtotal += $menuExtraSubtotal;
                        $sumMenuDiscountTotal +=$menuExtraDiscountTotal;
                        $sumMenuOtherTaxTotal += $menuExtraOtherTaxTotal;

                        if ($discountBillExtra < 0) {
                            $discountBillExtra = 0;
                        }

                        if ($taxCalculationType == 2 || $isApplyOtherVat) {
                            $newExtMenuSubtotal = $menuExtraSubtotal - ($menuExtraDiscountTotal + $discountBillExtra);
                        } else {
                            $newExtMenuSubtotal = $menuExtraSubtotal;
                        }

                        $newExtMenuOtherTaxSubtotal = 0;
                        if ($otherTaxCalculationType == 2 || $isApplyOtherVat) {
                            $newExtMenuOtherTaxSubtotal = $menuExtraSubtotal - ($menuExtraDiscountTotal + $otherTaxDiscountBillExtra);
                        }

                        if ($newExtMenuOtherTaxSubtotal < 0) {
                            $newExtMenuOtherTaxSubtotal = 0;
                        }

                        if ($newExtMenuSubtotal < 0) {
                            $newExtMenuSubtotal = 0;
                        }

                        if ($isApplyOtherVat) {
                            if (isset($extra['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $extra['flagLuxuryItem'],
                                    $extra['otherTaxOnVat'],
                                    $newExtMenuOtherTaxSubtotal,
                                    $menuExtraOtherTaxTotal
                                );
                                $sumMenuOtherVatTotal += CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $extTaxValue
                                );
                            } else {
                                $sumMenuOtherVatTotal += (
                                    $newExtMenuOtherTaxSubtotal + 
                                    ($extra['otherTaxOnVat'] == 1 ? $menuExtraOtherTaxTotal : 0)
                                ) * ($extTaxValue / 100);    
                            }
                        } else {
                            $sumMenuVatTotal += (
                                $newExtMenuSubtotal + 
                                ($extra['otherTaxOnVat'] == 1 ? $menuExtraOtherTaxTotal : 0)
                            ) * ($extTaxValue / 100);
                        }
                    }
                }
            }

            $deliveryCost = isset($salesHead['deliveryCost']) ? $salesHead['deliveryCost'] : 0;
            $orderFee = isset($salesHead['orderFee']) ? $salesHead['orderFee'] : 0;
            $deliveryCostTaxValue = $deliveryCostTax && $deliveryCost > 0 ? ($deliveryCost * $taxValue / 100) : 0;

            $salesHead['subtotal'] = $sumMenuSubtotal;
            $salesHead['menuDiscountTotal'] = $sumMenuDiscountTotal;
            $salesHead['inclusiveMenuDiscountTotal'] = $sumMenuDiscountTotal;
            $salesHead['otherTaxTotal'] = $sumMenuOtherTaxTotal;
            $salesHead['vatTotal'] = $sumMenuVatTotal + $deliveryCostTaxValue;
            $salesHead['otherVatTotal'] = $sumMenuOtherVatTotal;

            SalesHead::calculateDiscountTotal($salesHead, true, $tempMenuSubtotal);

            // @notes: Kalkulasi platform fee
            if (isset($salesHead['platformFee'])) {
                $platformFee = SalesPlatformFee::getPlatformFeeForCalculateTotal($platformFee, $salesHead);
            }

            $salesHead['grandTotal'] = $salesHead['subtotal'] + $deliveryCost + $orderFee - $salesHead['discountTotal'] - $salesHead['menuDiscountTotal'] + $salesHead['otherTaxTotal'] + $salesHead['vatTotal'] + $salesHead['otherVatTotal'] + $salesHead['voucherTotal'];

            $finalGrandTotal = $salesHead['grandTotal'];

            if ($rounding != 0) {
                if ($roundingMode == 'DOWN') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - (floor($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'UP') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - (ceil($finalGrandTotal / $rounding) * $rounding);
                } elseif ($roundingMode == 'AUTO') {
                    $salesHead['roundingTotal'] = $finalGrandTotal - ROUND($finalGrandTotal / $rounding) * $rounding;
                }
            }

            $salesHead['grandTotal'] = $salesHead['grandTotal'] + $platformFee;
        }
    }

    public static function getQueueNumber($salesNum, $salesDate, $branchID, $isTakeAwayAfterPayment = false) {
        $queueNumberStartFrom = Setting::getValue1('POS', 'Queue Number Start From');
        $queueNumberResetAfterReach = Setting::getValue1('POS', 'Queue Number Reset After Reach');
        $shiftTimeIsOpen = ShiftLog::find()
            ->select('shiftInTime')
            ->andWhere(['IS', 'shiftOutTime', null])
            ->andWhere(['branchID' => $branchID])
            ->scalar();

        $queueNumQuery = (new Query())
                ->select(['queueNum' => new Expression('COUNT(salesNum)')])
                ->from(SalesHead::tableName())
                ->where(['=', 'salesDate', $salesDate])
                ->andWhere("queueNum IS NOT NULL")
                ->andWhere(['>', 'salesDateIn', $shiftTimeIsOpen]);

        if (!$isTakeAwayAfterPayment) {
            $queueNumQuery->andFilterwhere(['<', 'salesNum', $salesNum]);
        }

        $queueNum = $queueNumQuery->scalar();

        if ($queueNumberStartFrom == 0) {
            $queueNumberStartFrom = 1;
        }
        $queueNumMod = $queueNum % ($queueNumberResetAfterReach - ($queueNumberStartFrom - 1));
        if ($queueNumMod == 0) {
            $queueNumMod = 0;
        }
        return $queueNumberStartFrom + $queueNumMod;
    }

    public static function getInclusiveFlag($branchID, $visitPurposeID) {
        $result = NULL;
        $menuTemplateDetailModel = MapBranchVisitPurpose::find()
            ->andWhere(['branchID' => $branchID, 'visitPurposeID' => $visitPurposeID])
            ->one();
        $menuTemplateHeadModel = MenuTemplateHead::findOne($menuTemplateDetailModel->menuTemplateID);
        if ($menuTemplateHeadModel) {
            $result = $menuTemplateHeadModel->flagInclusive;
        }
        return $result;
    }

    private static function getMapBranchModel($branchID, $visitPurposeID) {
        $mapBranchModel = MapBranchVisitPurpose::find()
            ->andWhere(['branchID' => $branchID, 'visitPurposeID' => $visitPurposeID])
            ->one();

        if ($mapBranchModel) {
            return $mapBranchModel;
        }
        return NULL;
    }

    public static function groupingCategoryOrderForBilling($salesMenusModel, &$menuDiscountTotal, &$grandTotal = 0) {
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);

        $groupMenuCategory = [];
        foreach ($salesMenusModel as $salesMenu) {
            if ($salesMenu->price > 0 || $salesMenu->menu->flagCustomerPrint) {
                $inclusive = false;
                $categoryID = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
                if ($salesMenu->salesHead->flagInclusive) {
                    $inclusive = true;
                    $total = (float) $salesMenu->qty * $salesMenu->inclusivePrice;
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $total = (float) $salesMenu->qty * $salesMenu->inclusivePrice;
                        $menuDiscountTotal += (float) $salesMenu->inclusiveDiscountValue;
                    }
                } else {
                    $total = (float) $salesMenu->price * $salesMenu->qty;
                }

                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $perPackage) {
                        if ($inclusive) {
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                // $percentageDiscount = $perPackage->discountValue / ($perPackage->price * $perPackage->qty) * 100;
                                // $tempTotal = (100 / (100 - $percentageDiscount)) * $perPackage->total;
                                // $tempTotal = ceil($tempTotal);
                                // $tempDiscount = $tempTotal * $percentageDiscount / 100;
                                $total += (float) $salesMenu->qty * ($perPackage->qty * $perPackage->inclusivePrice);
                                $menuDiscountTotal += (float) $salesMenu->qty * $perPackage->inclusiveDiscountValue;
                            } else {
                                $total += (float) $salesMenu->qty * ($perPackage->total + $perPackage->discountValue);
                            }
                        } else {
                            $total += (float) $salesMenu->qty * $perPackage->qty * $perPackage->price;
                        }
                    }
                }

                if ($salesMenu->salesExtras) {
                    foreach ($salesMenu->salesExtras as $perExtra) {
                        if ($inclusive) {
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                $total += (float) $salesMenu->qty * ($perExtra->qty * $perExtra->inclusivePrice);
                                $menuDiscountTotal += (float) $salesMenu->qty * $perExtra->inclusiveDiscountValue;
                            } else {
                                $total += (float) $salesMenu->qty * ($perExtra->qty * $perExtra->inclusivePrice);
                            }
                        } else {
                            $total += (float) $salesMenu->qty * $perExtra->qty * $perExtra->price;
                        }                        
                    }
                }

                if (!array_key_exists($categoryID, $groupMenuCategory)) {
                    $groupMenuCategory[$categoryID]['menuCategoryID'] = $categoryID;
                    $groupMenuCategory[$categoryID]['menuCategoryDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategory->menuCategoryDesc;
                    $groupMenuCategory[$categoryID]['total'] = $total;
                } else {
                    $groupMenuCategory[$categoryID]['total'] += $total;
                }
            }
        }
        
        $groupMenuCategoryTotal = [];
        $i = 0;
        foreach ($groupMenuCategory as $key => $menuCategory) {
            $groupMenuCategoryTotal[$i]['menuCategoryID'] = $key;
            $groupMenuCategoryTotal[$i]['menuCategoryDesc'] = $menuCategory['menuCategoryDesc'];
            $groupMenuCategoryTotal[$i]['total'] = $menuCategory['total'];

            $grandTotal += $menuCategory['total'];
            $i++;
        }

        return $groupMenuCategoryTotal;
    }

    public static function groupingCategoryOrderForBillingArray($salesHead, $salesMenusModel, &$menuDiscountTotal, &$grandTotal = 0) {
      $groupMenuCategory = [];
      foreach ($salesMenusModel as $salesMenu) {
          if ($salesMenu['price'] > 0 || $salesMenu['flagCustomerPrint']) {
              $inclusive = false;
              $categoryID = $salesMenu['menuCategoryID'];
              if ($salesHead['flagInclusive']) {
                  $inclusive = true;
                  $total = (float) $salesMenu['qty'] * $salesMenu['inclusivePrice'];
                  if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                      $total = (float) $salesMenu['qty'] * $salesMenu['inclusivePrice'];
                      $menuDiscountTotal += (float) $salesMenu['inclusiveDiscountValue'];
                  }
              } else {
                  $total = (float) $salesMenu['price'] * $salesMenu['qty'];
              }

              if ($salesMenu['packages']) {
                  foreach ($salesMenu['packages'] as $perPackage) {
                      if ($inclusive) {
                          if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                              $total += (float) $salesMenu['qty'] * ($perPackage['qty'] * $perPackage['inclusivePrice']);
                              $menuDiscountTotal += (float) $salesMenu['qty'] * $perPackage['inclusiveDiscountValue'];
                          } else {
                              $total += (float) $salesMenu['qty'] * ($perPackage['total'] + $perPackage['discountValue']);
                          }
                      } else {
                          $total += (float) $salesMenu['qty'] * $perPackage['qty'] * $perPackage['price'];
                      }
                  }
              }

              if ($salesMenu['extras']) {
                  foreach ($salesMenu['extras'] as $perExtra) {
                      if ($inclusive) {
                          if ($salesHead['posOtherTaxCalculationID'] == 2 && $salesHead['posTaxCalculationID'] == 2) {
                              $total += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice']);
                              $menuDiscountTotal += (float) $salesMenu['qty'] * $perExtra['inclusiveDiscountValue'];
                          } else {
                              $total += (float) $salesMenu['qty'] * ($perExtra['qty'] * $perExtra['inclusivePrice']);
                          }
                      } else {
                          $total += (float) $salesMenu['qty'] * $perExtra['qty'] * $perExtra['price'];
                      }                        
                  }
              }

              if (!array_key_exists($categoryID, $groupMenuCategory)) {
                  $groupMenuCategory[$categoryID]['menuCategoryID'] = $categoryID;
                  $groupMenuCategory[$categoryID]['menuCategoryDesc'] = $salesMenu['menuCategoryDesc'];
                  $groupMenuCategory[$categoryID]['total'] = $total;
              } else {
                  $groupMenuCategory[$categoryID]['total'] += $total;
              }
          }
      }
      
      $groupMenuCategoryTotal = [];
      $i = 0;
      foreach ($groupMenuCategory as $key => $menuCategory) {
          $groupMenuCategoryTotal[$i]['menuCategoryID'] = $key;
          $groupMenuCategoryTotal[$i]['menuCategoryDesc'] = $menuCategory['menuCategoryDesc'];
          $groupMenuCategoryTotal[$i]['total'] = $menuCategory['total'];

          $grandTotal += $menuCategory['total'];
          $i++;
      }

      return $groupMenuCategoryTotal;
  }

    public static function calculateInclusiveDiscountPercentage($subtotal, $grandTotal, $discount) {
        $discountVal = 0;
        if ($discount > 0 && $subtotal > 0) {
            $discountVal = FLOOR(($grandTotal * $discount / 100) / $subtotal * 100);
        }
        return $discountVal;
    }

    public static function calculateDiscountHead($salesMenu, $promotionID = 0) {
        $discountBill = 0;
        if ($promotionID == 0) {
            return $discountBill;
        }

        $dataSalesHead = SalesHead::findOne(['salesNum' => $salesMenu['salesNum']]);

        if ($dataSalesHead->promotionID != $promotionID) {
            return $discountBill;
        }

        $subtotalData = (new Query())
            ->select([
                'sumTotal' => new Expression('SUM(a.qty * a.price)')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->where(['IN', 'a.salesNum', $salesMenu['salesNum']])
            ->andWhere(['OR',
                ['>', 'a.otherTax', 0],
                ['>', 'a.vat', 0]
            ])
            ->one();
        $promotionModel = PromotionHead::find()
            ->andWhere(['promotionID' => $dataSalesHead->promotionID])
            ->andWhere(['promotionTypeID' => 3])
            ->one();
        if ($promotionModel) {
            if ($promotionModel->promotionTypeID == 3) {               
                $subTotal = $subtotalData['sumTotal'] && $subtotalData['sumTotal'] != 0 ? $subtotalData['sumTotal'] : 1;              
                $discountBill = ($salesMenu['qty'] * $salesMenu['price'] / $subTotal) * $dataSalesHead->discountTotal;
            }
        }

        return $discountBill;
    }

    public static function calculateDiscountArrayHead(
        $salesData, 
        $salesMenuData, 
        $menuDiscount = 0, 
        $menuExtra = false, 
        $promotionDetailID = 0, 
        $promotionCategoryIDs = [], 
        $promotionCategoryDetailIDs = [], 
        $promotionMenuIDs = [], 
        $sumMenuSubtotal = 0, 
        $menuType = 'Main', 
        $calculationMode, 
        $taxCalculation = [], 
        $sumMenuGrandTotal = 0, 
        $otherTaxTotal = 0, 
        $vatTotal = 0,
        $otherTaxCalculationMode = SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT,
        $allMenuDiscountTotal = 0) {

        if ($calculationMode == SalesHead::INCLUSIVE_AFTER_DISCOUNT) {
            $taxInclusiveAfterDiscount = true;
        } else {
            $taxInclusiveAfterDiscount = false;
        }

        if ($calculationMode == SalesHead::INCLUSIVE_BEFORE_DISCOUNT) {
            $taxInclusiveBeforeDiscount = true;
        } else {
            $taxInclusiveBeforeDiscount = false;
        }

        if ($calculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT) {
            $taxNonInclusiveAfterDiscount = true;
        } else {
            $taxNonInclusiveAfterDiscount = false;
        }

        if ($taxInclusiveAfterDiscount) {
            $menuDiscount = $salesMenuData['inclusiveDiscountValue'];
        }

        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $salesData['visitPurposeID']])->one();
        $vatSubject = 0;
        if ($mapBranchModel) {
            $vatSubject = $mapBranchModel->vatSubject;
        }

        $freeMenuExist = false;
        $discountBill = 0;
        $sumtotal = 0;
        $salesMenuParent = null;
        foreach ($salesData['salesMenu'] as $salesMenu) {
            if ($salesMenu['vat'] >= 0 || $salesMenu['otherTax'] >= 0 || $salesMenu['otherVat'] >= 0) {
                $sumtotal += $salesMenu['qty'] * $salesMenu['price'];
                if (isset($salesMenu['packages'])) {
                    foreach ($salesMenu['packages'] as $package) {
                        if ($package['vat'] >= 0 || $package['otherTax'] >= 0 || $package['otherVat'] >= 0) {
                            $sumtotal += $salesMenu['qty'] * $package['qty'] * $package['price'];
                        }

                        if ($menuType == 'Package') {
                            if ($package['menuID'] == $salesMenuData['menuID'] &&
                                $package['menuGroupID'] == $salesMenuData['menuGroupID']) {
                                $salesMenuParent = $salesMenu; 
                            }
                        }
                    }
                }

                if (isset($salesMenu['extras'])) {
                    foreach ($salesMenu['extras'] as $extra) {
                        if ($extra['vat'] >= 0 || $extra['otherTax'] >= 0 || $extra['otherVat'] >= 0) {
                            $sumtotal += $salesMenu['qty'] * $extra['qty'] * $extra['price'];
                        }

                        if ($menuType == 'Extra') {
                            if ($extra['menuExtraID'] == $salesMenuData['menuExtraID']) {
                                $salesMenuParent = $salesMenu; 
                            }
                        }
                    }
                }
            }
            if (!in_array($salesMenu['statusID'], [12,19])) {
                if (isset($salesMenu['originalPrice'])) {
                    if ($salesMenu['originalPrice'] != $salesMenu['price']) $freeMenuExist = true;
                }
            }
        }

        $issetSpecialPrice = false;
        if ($menuType != 'Extra') {
            if ((isset($salesMenuData['promotionDetailID']) && $salesMenuData['promotionDetailID'] == 0) &&
                isset($salesMenuData['originalPrice']) && $salesMenuData['originalPrice'] <> $salesMenuData['price']) {
                $issetSpecialPrice = true;
            }
        }

        if ($salesMenuParent) {
            if ((isset($salesMenuParent['promotionDetailID']) && $salesMenuParent['promotionDetailID'] == 0) &&
                isset($salesMenuParent['originalPrice']) && $salesMenuParent['originalPrice'] <> $salesMenuParent['price']) {
                $issetSpecialPrice = true;
            }
        }

        $promotionModel = NULL;
        $tempPromotionModel = PromotionHead::find()
            ->where(['promotionID' => $salesData['promotionID']])
            ->one();
        if ($tempPromotionModel) {
            if ($tempPromotionModel->promotionTypeID == 10) {
                $promotionModel = $tempPromotionModel;
            } else {
                $promotionModel = PromotionHead::find()
                ->joinWith('promotionCategories')
                ->andWhere(['ms_promotionhead.promotionID' => $salesData['promotionID']])
                ->andWhere(['IN', 'ms_promotionhead.promotionTypeID', [1, 3, 5, 6, 11, 12, 14, 15, 16]])
                ->andWhere(['IS', 'ms_promotioncategory.ID', NULL])
                ->one();
            }
        }

        if ($promotionModel) {
            if ($promotionModel->promotionTypeID == 3 || $promotionModel->promotionTypeID == 6) {
                $discountTotal = isset($salesData['discountTotal']) ? $salesData['discountTotal'] : $promotionModel['discount'];
                if ($calculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT) {
                    if ($discountTotal > $sumMenuSubtotal) {
                        $discountTotal = $sumMenuSubtotal;
                    }
                }

                $grandtotalBeforeDiscount = $sumMenuSubtotal;
                if ($taxInclusiveAfterDiscount || $taxInclusiveBeforeDiscount) {
                    // $menuGrandtotalBeforeDiscount = 0;
                    // $subtotal = 0;
                    $inclusiveDiscountTotal = $promotionModel['discount'];
                    $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesData, $vatSubject, $promotionModel, false, $salesMenuData, $menuType, $menuDiscount);
                    $qtyHead = 1;
                    foreach ($salesData['salesMenu'] as $key => $sales) {
                        $salesNum = isset($sales['salesNum']) ? $sales['salesNum'] : $salesData['salesNum'];
                        if ($salesNum == $salesMenuData['salesNum']) {
                            $qtyHead = $sales['qty'];
                        }
                    }
                    return $menuType !== 'Main' ?  $result['discountBill'] / $qtyHead : $result['discountBill'];

                    // $discountTotal = $finalBillDisc;
                }

                $applyPrice = (($taxInclusiveAfterDiscount || $taxInclusiveBeforeDiscount) ? $salesMenuData['inclusivePrice'] : $salesMenuData['price']) * $salesMenuData['qty'];

                if ($salesMenuData['discount'] > 0) {
                    if ($taxInclusiveAfterDiscount) {
                        $applyPrice = ($salesMenuData['inclusivePrice'] * $salesMenuData['qty']) - (($salesMenuData['inclusivePrice'] * $salesMenuData['qty']) * $salesMenuData['discount'] / 100);
                    } else {
                        $applyPrice = ($salesMenuData['price'] * $salesMenuData['qty']);
                    }
                }
                                
                if ($sumMenuSubtotal > 0) {
                    $tempMenuDisc = $taxNonInclusiveAfterDiscount ? $menuDiscount : 0;
                    $finalBillDisc = ($discountTotal * ($applyPrice - $tempMenuDisc)) / $sumMenuSubtotal;
                    $discountTotal = $finalBillDisc > $sumMenuSubtotal ? $sumMenuSubtotal : $finalBillDisc;
                } else {
                    $discountTotal = 0;
                }
                return $discountTotal;
            } else if ($promotionModel->promotionTypeID == 1 || $promotionModel->promotionTypeID == 5) {
                $applyDiscount = false;
                if (count($promotionCategoryIDs) > 0) {
                    if (in_array($salesMenu['menuCategoryID'], $promotionCategoryIDs)) {
                        $applyDiscount = true;
                    }
                }                
                if (count($promotionCategoryDetailIDs) > 0) {
                    if (in_array($salesMenu['menuCategoryDetailID'], $promotionCategoryDetailIDs)) {
                        $applyDiscount = true;
                    }
                }
                if (count($promotionCategoryIDs) > 0) {
                    if (in_array($salesMenu['menuID'], $promotionMenuIDs)) {
                        $applyDiscount = true;
                    }
                }

                if (count($promotionCategoryIDs) == 0 && count($promotionCategoryDetailIDs) == 0 && count($promotionMenuIDs) == 0) {
                    $applyDiscount = true;
                }

                if ($menuType != 'Main') {
                    $applyDiscount = true;
                }

                if ($applyDiscount) {
                    if ($menuExtra) {
                        if ($promotionModel->promotionID != $promotionDetailID && $promotionModel->flagMenuExtra == 1) {
                            $discountTotal = $sumMenuSubtotal * $promotionModel['discount'] / 100;
                            $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;

                            if ($taxInclusiveAfterDiscount) {
                                if ($taxCalculation) {
                                    $grandtotalBeforeDiscount = $sumMenuGrandTotal;
                                    $otherTaxOnVat = $taxCalculation['otherTaxOnVat'];
                                    $vatValue = $taxCalculation['vat'];
                                    $otherTaxValue = $taxCalculation['otherTax'];
                                    $salesDecimalSetting = $taxCalculation['salesDecimalSetting'];
                                    $settingDecimalMode = $taxCalculation['settingDecimalMode'];

                                    $menuGrandtotalBeforeDiscount = 0;
                                    $subtotal = 0;
                                    foreach ($salesData['salesMenu'] as $salesMenu) {
                                        $applyPrice = $salesMenu['inclusivePrice'];
                                        $menuGrandtotalBeforeDiscount = ($applyPrice * $salesMenu['qty']);
                                        $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                                        $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $vatValue;
                                        if ($otherTaxOnVat) {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100 / (100 + $appliedVat) * 100 / (100 + $otherTaxValue));
                                        } else {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100) / (100 + $appliedVat + $otherTaxValue);
                                        }
                                    }

                                    $discountTotal = $subtotal * $promotionModel['discount'] / 100;
    
                                    if ($discountTotal > $promotionModel->maxSalesPrice) {
                                        $tempDiscount = $promotionModel->maxSalesPrice > $grandtotalBeforeDiscount ? $grandtotalBeforeDiscount : $promotionModel->maxSalesPrice;
                                        $percentageDiscount = $tempDiscount / $grandtotalBeforeDiscount * 100;
                                        $discountTotal = ceil($subtotal * $percentageDiscount / 100);
                                    }
                                }
                            }
                        } else {
                            $discountTotal = 0;
                        }
                    } else {
                        if (!isset($salesMenuData['promotionDetailID'])) {
                            $salesMenuData['promotionDetailID'] = 0;
                        }
                        if ($promotionModel->promotionID != $salesMenuData['promotionDetailID']) {
                            $discountTotal = $sumMenuSubtotal * $promotionModel['discount'] / 100;
                            $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;
                            if ($taxInclusiveAfterDiscount) {
                                if ($sumMenuGrandTotal > 0) {
                                    $discountBill = (($salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount) / $sumMenuSubtotal) * $discountTotal;
                                    $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenuData['menuFlagTax']) && $salesMenuData['menuFlagTax'] === 2));
                                    $appliedVat = $isApplyOtherVat ? $salesMenuData['otherVat'] : $salesMenuData['vat'];
                                    $inclusiveTotalAfterDiscount = $salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount - $discountBill;
                                    $subtotalAfterDiscount = $inclusiveTotalAfterDiscount * 100 / (100 + $appliedVat + $salesMenuData['otherTax']);
                                    $inclusiveDiscountBill = $salesMenuData['qty'] * $salesMenuData['price'] - $subtotalAfterDiscount;
                                    return $inclusiveDiscountBill;
                                }
                            }
                        } else {
                            $discountTotal = 0;
                        }
                        if ($menuType == 'Package' && $promotionModel->flagPackageContent == 0) {
                            return 0;
                        }
                    }
                    if ($freeMenuExist) $discountTotal = 0;
                } else {
                    $discountTotal = 0;
                }
            } else if ($promotionModel->promotionTypeID == 10) {
                $applyDiscount = false;

                if ($menuType != 'Extra') {
                    $menuModel = Menu::find()
                    ->joinWith('menuCategoryDetail')
                    ->where(['menuID' => $salesMenuData['menuID']])
                    ->one();

                    if (count($promotionCategoryIDs) > 0) {
                        if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $promotionCategoryIDs)) {
                            $applyDiscount = true;
                        }
                    }                
                    if (count($promotionCategoryDetailIDs) > 0) {
                        if (in_array($menuModel->menuCategoryDetailID, $promotionCategoryDetailIDs)) {
                            $applyDiscount = true;
                        }
                    }
                    if (count($promotionMenuIDs) > 0) {
                        if (in_array($menuModel->menuID, $promotionMenuIDs)) {
                            $applyDiscount = true;
                        }
                    }
                }

                if ($menuType == 'Extra') {
                    $applyDiscount = true;
                }

                if ($issetSpecialPrice) $applyDiscount = false;

                if ($applyDiscount) {
                    if ($menuExtra) {
                        if ($promotionModel->promotionID != $promotionDetailID) {
                            //$discountTotal = ($sumMenuSubtotal - $allMenuDiscountTotal) * $promotionModel['discount'] / 100;
                            $discountTotal = $sumMenuSubtotal * $promotionModel['discount'] / 100;
                            $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;

                            if ($taxInclusiveAfterDiscount) {
                                if ($taxCalculation) {
                                    $grandtotalBeforeDiscount = $sumMenuGrandTotal;
                                    $otherTaxOnVat = $taxCalculation['otherTaxOnVat'];
                                    $vatValue = $taxCalculation['vat'];
                                    $otherTaxValue = $taxCalculation['otherTax'];
                                    $salesDecimalSetting = $taxCalculation['salesDecimalSetting'];
                                    $settingDecimalMode = $taxCalculation['settingDecimalMode'];

                                    $menuGrandtotalBeforeDiscount = 0;
                                    $subtotal = 0;
                                    foreach ($salesData['salesMenu'] as $salesMenu) {
                                        $applyPrice = $salesMenu['inclusivePrice'];
                                        $menuGrandtotalBeforeDiscount = ($applyPrice * $salesMenu['qty']);
                                        $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                                        $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $vatValue;
                                        if ($otherTaxOnVat) {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100 / (100 + $appliedVat) * 100 / (100 + $otherTaxValue));
                                        } else {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100) / (100 + $appliedVat + $otherTaxValue);
                                        }
                                    }

                                    $discountTotal = $subtotal * $promotionModel['discount'] / 100;
    
                                    if ($discountTotal > $promotionModel->maxSalesPrice) {
                                        $tempDiscount = $promotionModel->maxSalesPrice > $grandtotalBeforeDiscount ? $grandtotalBeforeDiscount : $promotionModel->maxSalesPrice;
                                        $percentageDiscount = $tempDiscount / $grandtotalBeforeDiscount * 100;
                                        $discountTotal = ceil($subtotal * $percentageDiscount / 100);
                                    }
                                }
                            }
                        } else {
                            $discountTotal = 0;
                        }
                    } else {
                        if ($promotionModel->promotionID != $salesMenuData['promotionDetailID']) {
                            //$discountTotal = ($sumMenuSubtotal - $allMenuDiscountTotal) * $promotionModel['discount'] / 100;
                            $discountTotal = $sumMenuSubtotal * $promotionModel['discount'] / 100;
                            $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;

                            if ($taxInclusiveAfterDiscount) {
                                if ($sumMenuGrandTotal > 0) {
                                    $discountBill = (($salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount) / $sumMenuSubtotal) * $discountTotal;
                                    $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenuData['menuFlagTax']) && $salesMenuData['menuFlagTax'] === 2));
                                    $appliedVat = $isApplyOtherVat ? $salesMenuData['otherVat'] : $salesMenuData['vat'];

                                    $inclusiveTotalAfterDiscount = $salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount - $discountBill;
                                    $subtotalAfterDiscount = $inclusiveTotalAfterDiscount * 100 / (100 + $appliedVat + $salesMenuData['otherTax']);
                                    $inclusiveDiscountBill = $salesMenuData['qty'] * $salesMenuData['price'] - $subtotalAfterDiscount;
                                    return $inclusiveDiscountBill;
                                }   
                            }
                        } else {
                            $discountTotal = 0;
                        }
                    }

                    $discountBill = (($salesMenuData['qty'] * $salesMenuData['price'] - $menuDiscount) / ($sumMenuSubtotal != 0 ? $sumMenuSubtotal : 1)) * $discountTotal;
                    if ($taxInclusiveAfterDiscount) {
                        $discountBill = (($salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount) / ($sumMenuSubtotal != 0 ? $sumMenuSubtotal : 1)) * $discountTotal;
                    }

                    if ($taxInclusiveBeforeDiscount) {
                        $discountBill = (($salesMenuData['qty'] * $salesMenuData['inclusivePrice']) / ($sumMenuSubtotal != 0 ? $sumMenuSubtotal : 1)) * $discountTotal;
                    }

                    if ($otherTaxCalculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT) {
                        $discountTotal = ($sumMenuSubtotal - $allMenuDiscountTotal) * $promotionModel['discount'] / 100;
                        $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;
                        $discountBill = $discountTotal * ($salesMenuData['qty'] * $salesMenuData['price']) / $sumMenuSubtotal;
                    }

                    return $discountBill;
                } else {
                    $discountTotal = 0;
                }
            } else if ($promotionModel->promotionTypeID == 11) {
                $applyDiscount = false;
                if (count($promotionCategoryIDs) > 0) {
                    if (in_array($salesMenu['menuCategoryID'], $promotionCategoryIDs)) {
                        $applyDiscount = true;
                    }
                }                
                if (count($promotionCategoryDetailIDs) > 0) {
                    if (in_array($salesMenu['menuCategoryDetailID'], $promotionCategoryDetailIDs)) {
                        $applyDiscount = true;
                    }
                }
                if (count($promotionCategoryIDs) > 0) {
                    if (in_array($salesMenu['menuID'], $promotionMenuIDs)) {
                        $applyDiscount = true;
                    }
                }

                if (count($promotionCategoryIDs) == 0 && count($promotionCategoryDetailIDs) == 0 && count($promotionMenuIDs) == 0) {
                    $applyDiscount = true;
                }

                if ($menuType != 'Main') {
                    $applyDiscount = true;
                }

                if ($issetSpecialPrice) $applyDiscount = false;

                if ($applyDiscount) {
                    if ($menuExtra) {
                        if ($promotionModel->promotionID != $promotionDetailID) {
                            $promotionDiscount = $salesData['promotionDiscount'] > 100 ? 100 : $salesData['promotionDiscount'];
                            $discountTotal = $sumMenuSubtotal * $promotionDiscount / 100;
                            $discountTotal = $discountTotal > $sumMenuSubtotal ? $sumMenuSubtotal : $discountTotal;

                            if ($taxInclusiveAfterDiscount) {
                                if ($taxCalculation) {
                                    $grandtotalBeforeDiscount = $sumMenuGrandTotal;
                                    $otherTaxOnVat = $taxCalculation['otherTaxOnVat'];
                                    $vatValue = $taxCalculation['vat'];
                                    $otherTaxValue = $taxCalculation['otherTax'];
                                    $salesDecimalSetting = $taxCalculation['salesDecimalSetting'];
                                    $settingDecimalMode = $taxCalculation['settingDecimalMode'];

                                    $menuGrandtotalBeforeDiscount = 0;
                                    $subtotal = 0;
                                    foreach ($salesData['salesMenu'] as $salesMenu) {
                                        $applyPrice = $salesMenu['inclusivePrice'];
                                        $menuGrandtotalBeforeDiscount = ($applyPrice * $salesMenu['qty']);
                                        $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
                                        $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $vatValue;
                                        if ($otherTaxOnVat) {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100 / (100 + $appliedVat) * 100 / (100 + $otherTaxValue));
                                        } else {
                                            $subtotal += ($menuGrandtotalBeforeDiscount * 100) / (100 + $appliedVat + $otherTaxValue);
                                        }
                                    }

                                    $discountTotal = $subtotal * $promotionDiscount / 100;
                                }
                            }
                        } else {
                            $discountTotal = 0;
                        }
                    } else {
                        if ($promotionModel->promotionID != $salesMenuData['promotionDetailID']) {
                            $promotionDiscount = $salesData['promotionDiscount'] > 100 ? 100 : $salesData['promotionDiscount'];
                            $discountTotal = $sumMenuSubtotal * $promotionDiscount / 100;

                            if ($taxInclusiveAfterDiscount) {
                                if ($sumMenuGrandTotal > 0) {
                                    $discountBill = (($salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount) / $sumMenuSubtotal) * $discountTotal;
                                    $inclusiveTotalAfterDiscount = $salesMenuData['qty'] * $salesMenuData['inclusivePrice'] - $menuDiscount - $discountBill;
                                    
                                    $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenuData['menuFlagTax']) && $salesMenuData['menuFlagTax'] === 2));
                                    $appliedVat = $isApplyOtherVat ? $salesMenuData['otherVat'] : $salesMenuData['vat'];
                                    
                                    $subtotalAfterDiscount = $inclusiveTotalAfterDiscount * 100 / (100 + $appliedVat + $salesMenuData['otherTax']);
                                    $inclusiveDiscountBill = $salesMenuData['qty'] * $salesMenuData['price'] - $subtotalAfterDiscount;
                                    return $inclusiveDiscountBill;
                                }  
                            }
                        } else {
                            $discountTotal = 0;
                        }
                    }
                } else {
                    $discountTotal = 0;
                }
            } else if ($promotionModel->promotionTypeID == 12 || $promotionModel->promotionTypeID == 14 || $promotionModel->promotionTypeID == 15 || $promotionModel->promotionTypeID == 16) {
                $promotionDiscount = $salesData['promotionDiscount'] > $sumMenuSubtotal ? $sumMenuSubtotal : $salesData['promotionDiscount'] ;
                $discountTotal = isset($salesData['discountTotal']) ? $salesData['discountTotal'] : $promotionDiscount;
                if ($calculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT) {
                    if ($discountTotal > $sumMenuSubtotal) {
                        $discountTotal = $sumMenuSubtotal;
                    }
                }

                if ($taxInclusiveAfterDiscount || $taxInclusiveBeforeDiscount) {
                    $grandtotalBeforeDiscount = $sumMenuSubtotal;
                    $inclusiveDiscountTotal = $promotionDiscount;
                    $result = SalesHead::calculateDiscountSalesMenu($inclusiveDiscountTotal, $grandtotalBeforeDiscount, $salesData, $vatSubject, $promotionModel, false, $salesMenuData, 'Main', $menuDiscount);
                    return $result['discountBill'];
                }
            }

            if ($discountTotal == 0) {
                return $discountTotal;
            }

            $applyPrice = ($taxInclusiveAfterDiscount || $taxInclusiveBeforeDiscount) ? $salesMenuData['inclusivePrice'] : $salesMenuData['price'];

            if ($taxInclusiveAfterDiscount || $taxNonInclusiveAfterDiscount) {
                if ($sumMenuSubtotal > 0) {
                    $discountBill = ((($salesMenuData['qty'] * $applyPrice) - $menuDiscount) / $sumMenuSubtotal) * $discountTotal;
                } else {
                    $discountBill = (($salesMenuData['qty'] * $applyPrice) - $menuDiscount) * $discountTotal;
                }
            } else {
                if ($sumMenuSubtotal > 0) {
                    $discountBill = (($salesMenuData['qty'] * $applyPrice) / $sumMenuSubtotal) * $discountTotal;
                    if (!$promotionModel && ((isset($salesMenuData['menuFlagTax']) && $salesMenuData['menuFlagTax'] == 2) || (isset($salesMenuData['otherVat']) && $salesMenuData['otherVat'] == '11'))) {
                        $subtotalAfterDisc = $sumMenuSubtotal - $allMenuDiscountTotal;
                        $discountTotal = $subtotalAfterDisc * $promotionModel['discount'] / 100;
                        $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;
                        
                        $discountBill = (($salesMenuData['qty'] * $applyPrice) / $sumMenuSubtotal) * $discountTotal;
                    }
                    if ($otherTaxCalculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT && $promotionModel->promotionTypeID == 12) {
                        return $discountBill;
                    } else if ($otherTaxCalculationMode == SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT) {
                        if ($promotionModel->promotionTypeID == 11) {
                            $discountTotal = ($sumMenuSubtotal - $allMenuDiscountTotal) * $salesData['promotionDiscount'] / 100;
                            $discountBill = $discountTotal * ($salesMenuData['qty'] * $applyPrice) / $sumMenuSubtotal;
                        } else {
                            $discountTotal = ($sumMenuSubtotal - $allMenuDiscountTotal) * $promotionModel['discount'] / 100;
                            $discountTotal = $discountTotal > $promotionModel['maxSalesPrice'] ? $promotionModel['maxSalesPrice'] : $discountTotal;
                            $discountBill = $discountTotal * ($salesMenuData['qty'] * $applyPrice) / $sumMenuSubtotal;
                        }
                    }
                } else {
                    $discountBill = ($salesMenuData['qty'] * $applyPrice) * $discountTotal;
                }
            }

        }
        return $discountBill;
    }

    public static function totalBill($salesNum) {
        $salesMenuData = SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['menuGroupID' => 0])
            ->all();

        $sumTotal = 0;
        if ($salesMenuData) {
            foreach ($salesMenuData as $salesMenu) {
                if ($salesMenu->vat > 0 || $salesMenu->otherTax > 0 || $salesMenu->otherVat > 0) {
                    $sumTotal += $salesMenu->qty * $salesMenu->price;
                }
                
                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $package) {
                        if ($package->vat > 0 || $package->otherTax > 0 || $package->otherVat > 0) {
                            $sumTotal += $salesMenu->qty * $package->qty * $package->price;
                        }
                    }
                }

                if ($salesMenu->salesExtras) {
                    foreach ($salesMenu->salesExtras as $extra) {
                        if ($extra->vat > 0 || $extra->otherTax > 0 || $extra->otherVat > 0) {
                            $sumTotal += $salesMenu->qty * $extra->qty * $extra->price;
                        }
                    }
                }
            }
        }


        // $subtotalData = (new Query())
        //     ->select([
        //         'sumTotal' => new Expression('COALESCE(SUM(a.qty * a.price),0)')
        //     ])
        //     ->from(SalesMenu::tableName() . ' a')
        //     ->where(['IN', 'a.salesNum', $salesNum])
        //     ->andWhere(['OR',
        //         ['>', 'a.otherTax', 0],
        //         ['>', 'a.vat', 0]
        //     ])
        //     ->one();

        // return $subtotalData['sumTotal'];
        return $sumTotal;
    }

    public static function getPromotionPaymentMethodID($salesNum) {
        $promotionPaymentMethodID = 0;
        $salesModel = SalesHead::find()
            ->with('promotion')
            ->with('activeMainSalesMenus.promotion')
            ->andWhere([SalesHead::tableName() . '.salesNum' => $salesNum])
            ->one();

        if ($salesModel->promotion) {
            if ($salesModel->promotion->paymentMethodID) {
                $promotionPaymentMethodID = $salesModel->promotion->paymentMethodID;
            }
        }

        foreach ($salesModel->activeMainSalesMenus as $salesMenu) {
            if (!$promotionPaymentMethodID) {
                if ($salesMenu->promotion) {
                    if ($salesMenu->promotion->paymentMethodID) {
                        $promotionPaymentMethodID = $salesMenu->promotion->paymentMethodID;
                    }
                }
            }
        }

        return $promotionPaymentMethodID;
    }

    public static function getOrderTimeOut($startDate, $endDate) {
        // @Notes: Return in seconds
        $dateDiff = strtotime($endDate->format('Y-m-d H:i:s')) - strtotime($startDate->format('Y-m-d H:i:s'));

        $minutes = floor($dateDiff / 60);
        return str_pad($minutes, 2, '0', STR_PAD_LEFT);
    }

    public static function getCreatorEditor($by, $subject) {
        if (in_array($by, [null, '', 'BASIC'])) {
            return 'SELF ORDER';
        } else if ($subject) {
            return isset($subject->fullName) ? $subject->fullName : $by;
        } else {
            return $by;
        }
    }

    private static function afterFindCustomerData($model, $fieldName) {
        $result = '';
        if ($model->customer) {
            $now = new DateTime('now');
            $salesDateOut = new DateTime($model->salesDateOut);
            $difference = $salesDateOut->diff($now);
            $yesterdayTransaction =  $difference->days >= 1 ? true : false;
            $yesterdayTransaction = $fieldName == 'fullName' ? false : $yesterdayTransaction;
            $result = $yesterdayTransaction ? '-' : $model->customer->$fieldName;
        }
        return $result;
    }

    private static function defineSalesMenu($salesMenuModel, $isMainSales = true, $parentFlagTax = null, $flagSeparateTaxCalculation = null) {
        $newSalesMenuModel = $salesMenuModel;
        for ($i=0; $i < count($newSalesMenuModel); $i++) { 
            // $menuFlagTax = isset($menu->flagSeparateTaxCalculation) && $menu->flagSeparateTaxCalculation === 0 ? $menu->flagTax : $item->menu->flagTax;
            $flagSeparateTaxCalculation = $isMainSales ? $newSalesMenuModel[$i]['menu']['flagSeparateTaxCalculation'] : $flagSeparateTaxCalculation;
            $parentFlagTax = $isMainSales ? $newSalesMenuModel[$i]['menu']['flagTax'] : $parentFlagTax;

            $menuFlagTax = $newSalesMenuModel[$i]['menu']['flagTax'];
            $menuFlagTax = $isMainSales ? $menuFlagTax : ($flagSeparateTaxCalculation !== null && $flagSeparateTaxCalculation == 0) ? $parentFlagTax : $menuFlagTax;
            $flagLuxuryItem = $newSalesMenuModel[$i]['menu']['flagLuxuryItem'];
            
            $newSalesMenuModel[$i]['menuFlagTax'] = $menuFlagTax;
            $newSalesMenuModel[$i]['flagLuxuryItem'] = $flagLuxuryItem;
            $newSalesMenuModel[$i]['packages'] = $isMainSales ? Self::defineSalesMenu($newSalesMenuModel[$i]['childSalesMenus'], false, $parentFlagTax, $flagSeparateTaxCalculation) : [];

            $newSalesExtras = $isMainSales ? $newSalesMenuModel[$i]['salesExtras'] : [];
            for ($j=0; $j < count($newSalesExtras); $j++) {
                $flagLuxuryItemExtra = $newSalesExtras[$j]['menuExtra']['menu']['flagLuxuryItem'];
                $newSalesExtras[$j]['flagLuxuryItem'] = $flagLuxuryItemExtra;
                unset($newSalesExtras[$j]['menuExtra']);
            }

            $newSalesMenuModel[$i]['extras'] = $isMainSales ? $newSalesExtras : [];
            unset($newSalesMenuModel[$i]['menu']);
            if (isset($newSalesMenuModel[$i]['childSalesMenus'])) {
                unset($newSalesMenuModel[$i]['childSalesMenus']);
            }
            if (isset($newSalesMenuModel[$i]['salesExtras'])) {
                unset($newSalesMenuModel[$i]['salesExtras']);
            }
        }
        return $newSalesMenuModel;
    }

    public static function getTotalAfterDisc($promotionHeadModel, $discount, $menuGrandtotalBeforeDiscount, $menuDiscount, $grandtotalBeforeDiscount, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax=0, $tempSubtotalBeforeTax=0) {
        $menuGrandtotalAfterDiscount = $menuGrandtotalBeforeDiscount - $menuDiscount;
        if ($promotionHeadModel && $discountBill > 0) {
            if ($promotionHeadModel->promotionTypeID > 0) {
                $hitMaxDisc = false;
                if (0 >= $menuGrandtotalAfterDiscount || $grandtotalBeforeDiscount == 0) { return 0; }
                $promoPercentage = [1, 5, 10, 11, 15];
                $appliedDiscount = $promotionHeadModel->discount;
                if (in_array($promotionHeadModel->promotionTypeID, [11, 12])) {
                    $appliedDiscount = $discount;
                }
                $promoMaxSalesPrice = [1, 5, 10, 15];
                if (in_array($promotionHeadModel->promotionTypeID, $promoMaxSalesPrice)) {
                    $maxDiscount = $grandtotalBeforeDiscount * $appliedDiscount / 100;
                    if ($promotionHeadModel->maxSalesPrice > 0 && ($maxDiscount > $promotionHeadModel->maxSalesPrice)) {
                        $appliedDiscount = $promotionHeadModel->maxSalesPrice;
                        $hitMaxDisc = true;
                    }
                }

                if (in_array($promotionHeadModel->promotionTypeID, $promoPercentage) && ($hitMaxDisc == false)) {
                    $billDiscPerMenu =  $menuGrandtotalAfterDiscount / 100 * $appliedDiscount;
                } else {
                    $discountAllMenu = $grandtotalBeforeDiscount - $menuDiscountTotal == 0 ? true : false;
                    $billDiscPerMenu = ($appliedDiscount * $menuGrandtotalAfterDiscount) / ($discountAllMenu ? $grandtotalBeforeDiscount : $grandtotalBeforeDiscount - $menuDiscountTotal);

                }

                $billDiscRpType = [3];
                if (in_array($promotionHeadModel->promotionTypeID, $billDiscRpType)) {
                    if ($billDiscPerMenu > $tempMenuSubtotalBeforeTax) {
                        $billDiscPerMenu = $tempMenuSubtotalBeforeTax;
                    }

                }
                return $menuGrandtotalBeforeDiscount - $billDiscPerMenu - $menuDiscount;
            }
        }
        return $menuGrandtotalBeforeDiscount - $menuDiscount;
    }

    public static function groupingOrderMenuByCategory($salesMenusModel) {
        $promotionDetailIDs = [];
        foreach($salesMenusModel as $salesMenu){
            if($salesMenu->promotionDetailID != 0 && !in_array($salesMenu->promotionDetailID, $promotionDetailIDs)){
                array_push($promotionDetailIDs, $salesMenu->promotionDetailID);
            }
            if ($salesMenu->childSalesMenus) {
                foreach ($salesMenu->childSalesMenus as $package) {
                    if($package->promotionDetailID != 0 && !in_array($package->promotionDetailID, $promotionDetailIDs)){
                        array_push($promotionDetailIDs, $package->promotionDetailID);
                    }
                }
            }
        }

        $promotionModel = PromotionHead::find()->where(['IN', 'promotionID', $promotionDetailIDs])->all();
        $groupMenuCategories = [];
        foreach ($salesMenusModel as $salesMenu) {
            $groupMenuCategories = self::fillGroupMenuCategories(
                $groupMenuCategories,
                $promotionModel,
                "main",
                $salesMenu
            );

            if ($salesMenu->childSalesMenus) {
                foreach ($salesMenu->childSalesMenus as $salesMenuPackage) {
                    $groupMenuCategories = self::fillGroupMenuCategories(
                        $groupMenuCategories,
                        $promotionModel,
                        "package",
                        $salesMenuPackage,
                        $salesMenu
                    );
                }
            }

            if($salesMenu->salesExtras){
                foreach ($salesMenu->salesExtras as $salesMenuExtra) {
                    $groupMenuCategories = self::fillGroupMenuCategories(
                        $groupMenuCategories,
                        $promotionModel,
                        "extra",
                        $salesMenuExtra,
                        $salesMenu
                    );
                }
            }
        }
        return $groupMenuCategories;
    }

    private static function fillGroupMenuCategories($groupMenuCategories, $promotionModel, $salesMenuType, $salesMenu, $parentSalesMenu = null) {
        $parentSalesMenuID = null;
        $parentSalesMenuQty = 1;

        if ($parentSalesMenu != null) {
            $parentSalesMenuID = $parentSalesMenu->ID;
            $parentSalesMenuQty = $parentSalesMenu->qty;
        }
        
        if ($salesMenuType == "extra") {
            $tempMenuExtraModel = $salesMenu->menuExtra->menu ? $salesMenu->menuExtra->menu : $parentSalesMenu->menu;
            $categoryID = $tempMenuExtraModel->menuCategoryDetail->menuCategoryID;
            $menuCategoryDesc = $tempMenuExtraModel->menuCategoryDetail->menuCategory->menuCategoryDesc;
            $menuName =  $salesMenu->menuExtra->menuExtraShortName;
            $promotionDetailID = $parentSalesMenu->promotionDetailID;
        } else {
            $categoryID = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
            $menuCategoryDesc = $salesMenu->menu->menuCategoryDetail->menuCategory->menuCategoryDesc;
            $menuName =  $salesMenu->menu->menuName;
            $promotionDetailID = $salesMenu->promotionDetailID;
        }
        
        $total = (float)($salesMenu->price * $salesMenu->qty);
        $subTotal = (float)($salesMenu->price * $salesMenu->qty * $parentSalesMenuQty);
        $menuDiscount = (float)($salesMenu->discountValue * $parentSalesMenuQty);
        $otherTax = (float)($salesMenu->otherTaxValue * $parentSalesMenuQty);
        $vat = (float)($salesMenu->vatValue  * $parentSalesMenuQty);
        $otherVat = (float)($salesMenu->otherVatValue * $parentSalesMenuQty);
        $grandTotal = (float)($salesMenu->total * $parentSalesMenuQty);

        if (!array_key_exists($categoryID, $groupMenuCategories)) {
            $groupMenuCategories[$categoryID]['menuCategoryID'] = $categoryID;
            $groupMenuCategories[$categoryID]['menuCategoryDesc'] = $menuCategoryDesc;
            $groupMenuCategories[$categoryID]['totalPerCategory'] = ($total - $menuDiscount);
            $groupMenuCategories[$categoryID]['total'] = $total;
            $groupMenuCategories[$categoryID]['subTotal'] = $subTotal;
            $groupMenuCategories[$categoryID]['menuDiscount'] = $menuDiscount;
            $groupMenuCategories[$categoryID]['otherTax'] = $otherTax;
            $groupMenuCategories[$categoryID]['vat'] = $vat;
            $groupMenuCategories[$categoryID]['otherVat'] = $otherVat;
            $groupMenuCategories[$categoryID]['grandTotal'] = $grandTotal;
        } else {
            $groupMenuCategories[$categoryID]['totalPerCategory'] += ($total - $menuDiscount);
            $groupMenuCategories[$categoryID]['total'] += $total;
            $groupMenuCategories[$categoryID]['subTotal'] += $subTotal;
            $groupMenuCategories[$categoryID]['menuDiscount'] += $menuDiscount;
            $groupMenuCategories[$categoryID]['otherTax'] += $otherTax;
            $groupMenuCategories[$categoryID]['vat'] += $vat;
            $groupMenuCategories[$categoryID]['otherVat'] += $otherVat;
            $groupMenuCategories[$categoryID]['grandTotal'] += $grandTotal;
        }

        $newSalesMenu = [
            "ID" => $salesMenu->ID,
            "salesMenuType" => $salesMenuType,
            "menuName" => $menuName,
            "qty" => (float) $salesMenu->qty,
            "parentSalesMenuID" => $parentSalesMenuID,
            "parentQty" => (float) $parentSalesMenuQty,
            "menuDiscount" => (float) $menuDiscount,
            "promotionDetailID" => 0,
            "promotionTypeID" => 0,
            "promotionDetailName" => null
        ];

        $currentPromotion = null;
        if ($promotionDetailID != 0) {
            foreach ($promotionModel as $promotion) {
                if ($promotion->promotionID == $promotionDetailID) {
                    if ($salesMenuType == 'main') {
                        $currentPromotion = $promotion;
                        break;
                    } else if ($salesMenuType == 'package' && $promotion->flagPackageContent == 1) {
                        $currentPromotion = $promotion;
                        break;
                    } else if ($salesMenuType == 'extra' && $promotion->flagMenuExtra == 1) {
                        $currentPromotion = $promotion;
                        break;
                    }
                }
            }
        }

        if($currentPromotion != null){
            $newSalesMenu['promotionDetailID'] = $currentPromotion->promotionID;
            $newSalesMenu['promotionTypeID'] = $currentPromotion->promotionTypeID;
            $newSalesMenu['promotionDetailName'] = $currentPromotion->notes;
        }

        if ($salesMenuType === "extra") {
            $groupMenuCategories[$categoryID]['salesMenus'][$salesMenu->menuDetailID ."|". $salesMenu->ID] = $newSalesMenu;
        } else {
            $groupMenuCategories[$categoryID]['salesMenus'][$salesMenu->ID] = $newSalesMenu;
        }
        return $groupMenuCategories;
    }

    public static function groupingOrderMenuByCategoryArray($salesMenusModel) {
      $groupMenuCategories = [];
      foreach ($salesMenusModel as $salesMenu) {
          $groupMenuCategories = self::fillGroupMenuCategoriesArray(
              $groupMenuCategories,
              "main",
              $salesMenu
          );

          if ($salesMenu['packages']) {
              foreach ($salesMenu['packages'] as $salesMenuPackage) {
                  $groupMenuCategories = self::fillGroupMenuCategoriesArray(
                      $groupMenuCategories,
                      "package",
                      $salesMenuPackage,
                      $salesMenu
                  );
              }
          }

          if($salesMenu['extras']){
              foreach ($salesMenu['extras'] as $salesMenuExtra) {
                  $groupMenuCategories = self::fillGroupMenuCategoriesArray(
                      $groupMenuCategories,
                      "extra",
                      $salesMenuExtra,
                      $salesMenu
                  );
              }
          }
      }
      return $groupMenuCategories;
    }

    private static function fillGroupMenuCategoriesArray($groupMenuCategories, $salesMenuType, $salesMenu, $parentSalesMenu = null) {
        $parentSalesMenuID = null;
        $parentSalesMenuQty = 1;

        if ($parentSalesMenu != null) {
            $parentSalesMenuID = $parentSalesMenu['ID'];
            $parentSalesMenuQty = $parentSalesMenu['qty'];
        }
        
        if ($salesMenuType == "extra") {
            $categoryID = $salesMenu['menuCategoryID'];
            $menuCategoryDesc = $salesMenu['menuCategoryDesc'];
            $menuName =  $salesMenu['menuExtraShortName'];
            $promotionDetailID = $parentSalesMenu['promotionDetailID'];
        } else {
            $categoryID = $salesMenu['menuCategoryID'];
            $menuCategoryDesc = $salesMenu['menuCategoryDesc'];
            $menuName =  $salesMenu['menuName'];
            $promotionDetailID = $salesMenu['promotionDetailID'];
        }
        
        $total = (float) ($salesMenu['price'] * $salesMenu['qty']);
        $subTotal = (float) ($salesMenu['price'] * $salesMenu['qty'] * $parentSalesMenuQty);
        $menuDiscount = (float) ($salesMenu['discountValue'] * $parentSalesMenuQty);
        $otherTax = (float) ($salesMenu['otherTaxValue'] * $parentSalesMenuQty);
        $vat = (float) ($salesMenu['vatValue']  * $parentSalesMenuQty);
        $otherVat = (float) ($salesMenu['otherVatValue'] * $parentSalesMenuQty);
        $grandTotal = (float) ($salesMenu['total'] * $parentSalesMenuQty);

        if (!array_key_exists($categoryID, $groupMenuCategories)) {
            $groupMenuCategories[$categoryID]['menuCategoryID'] = $categoryID;
            $groupMenuCategories[$categoryID]['menuCategoryDesc'] = $menuCategoryDesc;
            $groupMenuCategories[$categoryID]['totalPerCategory'] = ($total - $menuDiscount);
            $groupMenuCategories[$categoryID]['total'] = $total;
            $groupMenuCategories[$categoryID]['subTotal'] = $subTotal;
            $groupMenuCategories[$categoryID]['menuDiscount'] = $menuDiscount;
            $groupMenuCategories[$categoryID]['otherTax'] = $otherTax;
            $groupMenuCategories[$categoryID]['vat'] = $vat;
            $groupMenuCategories[$categoryID]['otherVat'] = $otherVat;
            $groupMenuCategories[$categoryID]['grandTotal'] = $grandTotal;
        } else {
            $groupMenuCategories[$categoryID]['totalPerCategory'] += ($total - $menuDiscount);
            $groupMenuCategories[$categoryID]['total'] += $total;
            $groupMenuCategories[$categoryID]['subTotal'] += $subTotal;
            $groupMenuCategories[$categoryID]['menuDiscount'] += $menuDiscount;
            $groupMenuCategories[$categoryID]['otherTax'] += $otherTax;
            $groupMenuCategories[$categoryID]['vat'] += $vat;
            $groupMenuCategories[$categoryID]['otherVat'] += $otherVat;
            $groupMenuCategories[$categoryID]['grandTotal'] += $grandTotal;
        }

        $newSalesMenu = [
            "ID" => $salesMenu['ID'],
            "salesMenuType" => $salesMenuType,
            "menuName" => $menuName,
            "qty" => (float) $salesMenu['qty'],
            "parentSalesMenuID" => $parentSalesMenuID,
            "parentQty" => (float) $parentSalesMenuQty,
            "menuDiscount" => (float) $menuDiscount,
            "promotionDetailID" => 0,
            "promotionTypeID" => 0,
            "promotionDetailName" => null
        ];

        $currentPromotion = null;
        if ($promotionDetailID != 0) {
          if ($salesMenuType == 'main') {
              $currentPromotion = [
                'promotionID' => $salesMenu['masterPromoID'],
                'promotionTypeID' => $salesMenu['promotionTypeID'],
                'notes' => $salesMenu['promotionDetailName']
              ];
          } else if ($salesMenuType == 'package' && $salesMenu['flagPackageContent'] == 1) {
            $currentPromotion = [
              'promotionID' => $salesMenu['masterPromoID'],
              'promotionTypeID' => $salesMenu['promotionTypeID'],
              'notes' => $salesMenu['promotionDetailName'],
            ];
          } else if ($salesMenuType == 'extra' && $parentSalesMenu['flagMenuExtra'] == 1) {
            $currentPromotion = [
              'promotionID' => $parentSalesMenu['masterPromoID'],
              'promotionTypeID' => $parentSalesMenu['promotionTypeID'],
              'notes' => $parentSalesMenu['promotionDetailName'],
            ];
          }
        }

        if($currentPromotion != null){
            $newSalesMenu['promotionDetailID'] = $currentPromotion['promotionID'];
            $newSalesMenu['promotionTypeID'] = $currentPromotion['promotionTypeID'];
            $newSalesMenu['promotionDetailName'] = $currentPromotion['notes'];
        }

        if ($salesMenuType === "extra") {
            $groupMenuCategories[$categoryID]['salesMenus'][$salesMenu['menuDetailID'] ."|". $salesMenu['ID']] = $newSalesMenu;
        } else {
            $groupMenuCategories[$categoryID]['salesMenus'][$salesMenu['ID']] = $newSalesMenu;
        }

        return $groupMenuCategories;
    }

    public static function validatePromoBillAndMenu($salesModel) {
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $externalMember = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $isMemberID = ($externalMember == 1 && $membershipType == 'memberid' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isLoyalty = ($externalMember == 1 && $membershipType == 'esbloyalty' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isMemberTada = ($externalMember == 1 && $membershipType == 'tada' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isLoopLite = ($externalMember == 1 && $membershipType == 'looplite' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isCapillary = ($externalMember == 1 && $membershipType == 'capillary' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isCapillaryV2 = ($externalMember == 1 && $membershipType == 'capillaryV2' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $isStamps = ($externalMember == 1 && $membershipType == 'stamps' && isset($salesModel['flagExternalMemberID']) && $salesModel['flagExternalMemberID']) ? TRUE : FALSE;
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($salesModel['visitPurposeID']);
        $mapBranchModel = MapBranchVisitPurpose::find()
            ->where(['visitPurposeID' => $salesModel['visitPurposeID']])
            ->one();
        $vatSubject = 0;
        $otherTaxOnVat = 0;
        if ($mapBranchModel) {
            $vatSubject = $mapBranchModel->vatSubject;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
        }
        $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID);
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();


        $newSalesMenu = [];
        $promotionDetailModel = null;
        foreach ($salesModel['salesMenu'] as $salesMenu) {
            if (in_array($salesMenu['statusID'], [12, 19])) continue;
            $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
            if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] > 0) {
                $promotionDetailModel = PromotionHead::findOne(['promotionID' => $salesMenu['promotionDetailID']]);
            } else {
                $promotionDetailModel = null;
            }
            if ($promotionDetailModel) {
                if ($salesModel['memberID'] == null && $salesModel['employeeCode'] == null && (!$isMemberID && !$isMemberTada && !$isLoyalty && !$isLoopLite && !$isCapillary && !$isCapillaryV2 && !$isStamps)) {
                    if (in_array($promotionDetailModel->promotionMemberTypeID, [1, 2, 3])) {
                        $salesMenu['promotionDetailID'] = 0;
                        $salesMenu['promotionDetailName'] = '';
                        $salesMenu['promotionVoucherCode'] = '';
                        $salesMenu['discount'] = 0;
                        $promotionDetailModel = null;
                    }
                } else if ($salesModel['memberID'] == null && $salesModel['employeeCode'] != null) {
                    if (in_array($promotionDetailModel->promotionMemberTypeID, [3])) {
                        $salesMenu['promotionDetailID'] = 0;
                        $salesMenu['promotionDetailName'] = '';
                        $salesMenu['promotionVoucherCode'] = '';
                        $salesMenu['discount'] = 0;
                        $promotionDetailModel = null;
                    }
                } else if ($salesModel['memberID'] != null && $salesModel['employeeCode'] == null) {
                    if (in_array($promotionDetailModel->promotionMemberTypeID, [2])) {
                        $salesMenu['promotionDetailID'] = 0;
                        $salesMenu['promotionDetailName'] = '';
                        $salesMenu['discount'] = 0;
                        $promotionDetailModel = null;
                    }
                } else if ($salesModel['memberID'] == null && $salesModel['employeeCode'] == null && ( $isMemberID || $isMemberTada || $isLoyalty || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps)) {
                    if (in_array($promotionDetailModel->promotionMemberTypeID, [2])) {
                        $salesMenu['promotionDetailID'] = 0;
                        $salesMenu['promotionDetailName'] = '';
                        $salesMenu['discount'] = 0;
                        $promotionDetailModel = null;
                    }
                }
            }

            if ($promotionDetailModel == null) {
                $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];

                $specialMenuPrice = null;
                if (array_key_exists($salesMenu['menuID'], $specialPriceArrModel)) {
                    $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                }

                if ($salesMenu['price'] == 0) {
                    if ($specialMenuPrice) {
                        if ($inclusiveMenuTemplateID) {
                            $salesMenu['inclusivePrice'] = $specialMenuPrice;
                            $salesMenu['price'] = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat, $specialMenuPrice);
                        } else {
                            $salesMenu['price'] = $specialMenuPrice;
                        }
                    } else {
                        if ($inclusiveMenuTemplateID) {
                            $salesMenu['inclusivePrice'] = $menuTemplateDetailModel[$salesMenu['menuID']]->price;
                        }
                        $salesMenu['price'] = $salesMenu['originalPrice'];
                    }
                } else {
                    if (isset($salesMenu['afterApplyFreeItem']) && $salesMenu['afterApplyFreeItem'] == true) {
                        if ($specialMenuPrice) {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $specialMenuPrice;
                                $salesMenu['price'] = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat, $specialMenuPrice);
                            } else {
                                $salesMenu['price'] = $specialMenuPrice;
                            }
                        } else {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $menuTemplateDetailModel[$salesMenu['menuID']]->price;
                            }
                            $salesMenu['price'] = $salesMenu['originalPrice'];
                        }
                    } else {
                        if ($inclusiveMenuTemplateID) {
                            $specialMenuPrice = null;

                            $applyPrice = isset($menuTemplateDetailModel[$salesMenu['menuID']]) 
                                ? $menuTemplateDetailModel[$salesMenu['menuID']]->price
                                : $salesMenu['inclusivePrice'];

                            if (in_array($salesMenu['statusID'], [13, 34, 46]) && $salesMenu['price'] != $salesMenu['originalPrice']) {
                                $specialPriceWithFilterArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID, $salesMenu['createdDate']);

                                if (array_key_exists($salesMenu['menuID'], $specialPriceWithFilterArrModel)) {
                                    $specialMenuPrice = $specialPriceWithFilterArrModel[$salesMenu['menuID']];
                                }

                                if ($specialMenuPrice) {
                                    $applyPrice = $specialMenuPrice;
                                } else {
                                    $applyPrice = $salesMenu['inclusivePrice'];
                                    $salesMenu['price'] = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat, $applyPrice);
                                }
                            }

                            $salesMenu['inclusivePrice'] = $applyPrice;

                            if(isset($salesMenu['salesType']) && $salesMenu['salesType'] == 'POS'){
                                $salesMenu['displayPriceValue'] = $salesMenu['inclusivePrice'];
                            }
                        } else {
                            $salesMenu['displayPriceValue'] = $salesMenu['price'];
                        }
                    }
                }
            }
            $newSalesMenu[] = $salesMenu;
        }
        return $newSalesMenu;
    }

    public static function getNetPrice($otherTaxValue, $otherTaxOnVat, $vatValue, $price = 0) {
        $result = 0;
        $applyPrice = $price;
        if ($otherTaxOnVat) {
            $result = ($applyPrice * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
        } else {
            $result = ($applyPrice * 100 / (100 + $vatValue + $otherTaxValue));
        }

        return $result;
    }

    public static function findPromotionSalesHead($salesNum)
    {
        return SalesHead::find()
            ->where(['salesNum' => $salesNum])
            ->asArray()
            ->one();
    }

    public static function getSalesPlatformFee($salesNum) {
        $result = [];
        $currentSalesPlatformFee = SalesPlatformFee::find()
            ->where(['=', 'salesNum', $salesNum])
            ->all();

        if (count($currentSalesPlatformFee) > 0) {
            foreach ($currentSalesPlatformFee as $row) {
                $result[] = [
                    "orderID" => "",
                    "salesNum" => $row->salesNum,
                    "platformFeeTypeID" => $row->platformFeeTypeID,
                    "feeNameEN" => $row->feeNameEN,
                    "feeNameID" => $row->feeNameID,
                    "percentage" => (float) $row->percentage,
                    "amount" => (float) $row->amount,
                    "minAmount" => (float) $row->minAmount,
                    "maxAmount" => (float) $row->maxAmount
                ];
            }
        }

        return $result;
    }

    public static function updateSalesPlatformFee($salesNum, $percentage, $amount) {
        $singlePlatformFee = SalesPlatformFee::find()
            ->andWhere(['=', 'salesNum', $salesNum])
            ->andWhere(['=', 'percentage', $percentage])
            ->one();

        if ($singlePlatformFee) {
            $singlePlatformFee->amount = $amount;
            $singlePlatformFee->save();
        }

        return true;
    }

    public static function getOtherAttributeSalesHead($salesHead, $mainSalesModel, $settings, $page = 'order') {
      $salesHead['modePromotion'] = 0;
      $salesHead['subtotal'] = $salesHead['subtotal'];
      $salesHead['grandTotal'] = $salesHead['grandTotal'];
      if ($page == 'order') {
        $interval = $salesHead['orderTimeOut'] ? SalesHead::getOrderTimeOut(
            date_create($salesHead['salesDateIn']),
            date_create($salesHead['orderTimeOut'])
        ) : null;
        $salesHead['orderTimeOut'] = $interval;
      }
      $salesHead['customerName'] = self::getCustomerData($salesHead, 'fullName');
      $salesHead['customerPhone'] = self::getCustomerData($salesHead, 'phoneNumber');
      $salesHead['customerEmail'] = self::getCustomerData($salesHead, 'email');
      $salesHead['promotionDiscountText'] = self::getPromotionDiscountText($salesHead, $settings);
      $salesHead['promotionPaymentMethodID'] = self::getPromotionPaymentMethod($salesHead, $mainSalesModel);
      $salesHead['newPromotionPaymentMethodID'] = self::getPromotionPaymentMethod($salesHead, $mainSalesModel);

      return $salesHead;
    }

    private static function getCustomerData($salesHead, $fieldName) {
      $result = null;
      if ($salesHead[$fieldName]) {
          $now = new DateTime('now');
          $salesDateOut = new DateTime($salesHead['salesDateOut']);
          $difference = $salesDateOut->diff($now);
          $yesterdayTransaction =  $difference->days >= 1 ? true : false;
          $yesterdayTransaction = $fieldName == 'fullName' ? false : $yesterdayTransaction;
          $result = $yesterdayTransaction ? '-' : $salesHead[$fieldName];
      }
      return $result;
    }

    private static function getPromotionDiscountText($salesHead, $settings) {
      $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
      $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
      $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
      $promotionDiscountText = '';
  
      if($salesHead['masterPromoID']) {
        $promotionDiscountText = in_array($salesHead['promotionTypeID'], [11, 12, 14, 15, 16]) ? $salesHead['promotionDiscount'] : $salesHead['masterPromoDiscount'];
        if (in_array($salesHead['promotionTypeID'], [12, 14, 15, 16])) {
          $promotionDiscountText = $salesHead['discountTotal'];
        }
      }
      
      return $salesHead['promotionID'] != 0 && $salesHead['masterPromoID'] ? (number_format($promotionDiscountText,
            $salesDecimalSetting, "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator") . (in_array($salesHead['promotionTypeID'], [1, 10, 11]) ? '%' : '')) : '';
    }

    private static function getPromotionPaymentMethod($salesHead, $mainSalesModel) {
      $tempPromotionPaymentMethodID = 0;
      if ($salesHead['masterPromoID']) {
        if ($salesHead['paymentMethodID']) {
          $tempPromotionPaymentMethodID = $salesHead['paymentMethodID'];
        }
      }
  
      if (isset($mainSalesModel[$salesHead['salesNum']])) {
        foreach ($mainSalesModel[$salesHead['salesNum']] as $salesMenu) {
          if (!$tempPromotionPaymentMethodID) {
            if ($salesMenu['masterPromoID']) {
              if ($salesMenu['paymentMethodID']) {
                $tempPromotionPaymentMethodID = $salesMenu['paymentMethodID'];
              }
            }
          }
        }
      }
      return $tempPromotionPaymentMethodID;
    }

    public static function getFindOutstandingOrderRawQuery($branchID, $parent) {
      return "SELECT " .
          $parent . ".*,
          tr_salesmergetable.salesNum AS mergeTableSalesNum,
          tr_saleslink.salesNum AS linkSalesNum,
          childSalesLinks.salesNum AS childLinkSalesNum,
          headLinkSales.salesNum AS headLinkSalesNum,
          ms_member.memberCode,
          COALESCE(ms_member.memberName, 'No Member') AS memberName,
          COALESCE(ms_member.memberAddress, 'No Address') AS memberAddress,
          COALESCE(ms_visitpurpose.visitPurposeName, '') AS visitPurposeName,
          ms_visitpurpose.visitPurposeID AS masterVisitPurposeID,
          ms_visitpurpose.flagActive AS flagVispurActive,
          ms_promotionhead.paymentMethodID,
          ms_promotionhead.flagPackageContent,
          ms_promotionhead.flagMenuExtra,
          ms_promotionhead.maxSalesPrice,
          ms_promotionhead.discount AS masterPromoDiscount,
          ms_promotionhead.promotionID AS masterPromoID,
          ms_promotionhead.flagActive AS flagPromoActive,
          COALESCE(ms_promotionhead.notes, '') AS promotionName,
          COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
          ms_table.tableID AS masterTableID,
          ms_table.flagActive AS flagTableActive,
          ms_table.stationID AS tableStationID,
          (CASE WHEN tr_saleshead.tableID > 0 THEN ms_table.tableName ELSE COALESCE(tr_salesinfo.value, 'Quick Service') END) AS tableName,
          ms_menutemplatehead.menuTemplateID AS menuTemplateHeadID,
          map_branchvisitpurpose.visitPurposeID AS mapBranchVispurID,
          map_branchvisitpurpose.menuTemplateID AS mapBranchVispurTemplateID,
          ms_menutemplatehead.flagActive AS flagMenuTemplateActive,
          COALESCE(ms_menutemplatehead.menuTemplateName, '') AS menuTemplateName,
          (CASE WHEN ms_menutemplatehead.flagInclusive = 1 THEN ms_menutemplatehead.menuTemplateID ELSE 0 END) AS inclusiveMenuTemplateID,
          lk_status.statusName,
          COALESCE(creator.fullName, 'SELF ORDER') AS creator,
          COALESCE(editor.fullName, 'SELF ORDER') AS editor,
          tr_salescontactinfo.salesContactInfoID,
          tr_salescontactinfo.customerPhoneNum,
          tr_salesrewardhead.rewardType,
          tr_salesconditionalpromo.conditionalPromoID,
          tr_salespaymentgateway.selfOrderIdKiosk,
          ms_branch.posTaxCalculationID,
          ms_branch.posOtherTaxCalculationID,
          customer.fullName,
          customer.phoneNumber,
          customer.email
        FROM
          tr_saleshead
        LEFT JOIN
          tr_salesmergetable ON tr_saleshead.salesNum = tr_salesmergetable.salesNum
        LEFT JOIN
          tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.salesNum
        LEFT JOIN
          tr_saleslink childSalesLinks ON tr_saleshead.salesNum = tr_saleslink.linkSalesNum
        LEFT JOIN
          tr_saleshead headLinkSales ON tr_saleslink.linkSalesNum = headLinkSales.salesNum
        LEFT JOIN
          tr_customertransaction customer ON $parent.salesNum = customer.salesNum
        LEFT JOIN
          ms_branch ON $parent.branchID = ms_branch.branchID
        LEFT JOIN
          ms_member ON $parent.memberID = ms_member.memberID
        LEFT JOIN
          ms_promotionhead ON $parent.promotionID = ms_promotionhead.promotionID
        LEFT JOIN
          ms_visitpurpose ON $parent.visitPurposeID = ms_visitpurpose.visitPurposeID
        LEFT JOIN
          map_branchvisitpurpose ON ms_visitpurpose.visitPurposeID = map_branchvisitpurpose.visitPurposeID
        LEFT JOIN
          ms_menutemplatehead ON map_branchvisitpurpose.menuTemplateID = ms_menutemplatehead.menuTemplateID
        LEFT JOIN
          ms_table ON $parent.tableID = ms_table.tableID
        LEFT JOIN
          lk_status ON $parent.statusID = lk_status.statusID
        LEFT JOIN
          ms_posuser creator ON $parent.createdBy = creator.username
        LEFT JOIN
          ms_posuser editor ON $parent.editedBy = editor.username
        LEFT JOIN
          tr_salescontactinfo ON $parent.salesNum = tr_salescontactinfo.salesNum
        LEFT JOIN
          tr_salesrewardhead ON $parent.salesNum = tr_salesrewardhead.salesNum
        LEFT JOIN
          tr_salesconditionalpromo ON $parent.salesNum = tr_salesconditionalpromo.salesNum
        LEFT JOIN
          tr_salespaymentgateway ON $parent.salesNum = tr_salespaymentgateway.salesNum
        LEFT JOIN
          tr_salesinfo ON $parent.salesNum = tr_salesinfo.salesNum AND tr_salesinfo.key = 'Table Name'
        WHERE
          $parent.branchID = $branchID";
    }

    public static function getMainSalesRawQuery($branchID, $tableID, $salesNum = null) {
      $connection = Yii::$app->getDb();

      $salesModelQuery = "SELECT 
          head.*
        FROM
          tr_saleshead
              LEFT JOIN
          tr_salesmergetable ON tr_saleshead.salesNum = tr_salesmergetable.salesNum
              LEFT JOIN
          tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.linkSalesNum
              LEFT JOIN
          tr_saleshead head ON COALESCE(tr_saleslink.salesNum, tr_saleshead.salesNum) = head.salesNum";

      if (isset($salesNum)) {
        $salesModelQuery .= "
          WHERE
            (tr_saleshead.branchID = $branchID)
            AND (tr_saleshead.salesNum = '$salesNum')";
      } else {
        $salesModelQuery .= "
          WHERE
            (tr_saleshead.branchID = $branchID)
              AND (tr_saleshead.salesDateOut IS NULL)
              AND ((tr_saleshead.tableID = $tableID) OR (tr_salesmergetable.tableID = $tableID))
          ORDER BY salesDate, salesNum";
      }

      return $connection->createCommand($salesModelQuery)->queryOne();
    }

    public static function getOrderRawQuery($salesNum, $parent) {
        return "SELECT " .
            $parent . ".*,
            tr_saleslink.salesNum AS linkSalesNum,
            ms_member.memberCode,
            COALESCE(ms_member.memberName, 'No Member') AS memberName,
            COALESCE(ms_member.memberAddress, 'No Address') AS memberAddress,
            COALESCE(ms_promotionhead.notes, '') AS promotionName,
            COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
            ms_promotionhead.promotionID AS masterPromoID,
            COALESCE(ms_visitpurpose.visitPurposeName, '') AS visitPurposeName,
            (CASE WHEN tr_saleshead.tableID > 0 THEN ms_table.tableName ELSE COALESCE(tr_salesinfo.value, 'Quick Service') END) AS tableName,
            (CASE WHEN ms_menutemplatehead.flagInclusive = 1 THEN ms_menutemplatehead.menuTemplateID ELSE 0 END) AS inclusiveMenuTemplateID,
            lk_status.statusName,
            COALESCE(creator.fullName, 'SELF ORDER') AS creator,
            COALESCE(editor.fullName, 'SELF ORDER') AS editor,
            tr_salescontactinfo.customerPhoneNum,
            tr_salesrewardhead.rewardType,
            COALESCE(tr_salesconditionalpromo.conditionalPromoID, 0) AS conditionalPromoID,
            tr_salespaymentgateway.selfOrderIdKiosk,
            ms_branch.posTaxCalculationID,
            ms_branch.posOtherTaxCalculationID,
            customer.fullName,
            customer.phoneNumber,
            customer.email,
            customer.fullName AS customerName,
            customer.phoneNumber AS customerPhone,
            customer.email AS customerEmail
        FROM
            tr_saleshead
        LEFT JOIN
          tr_saleslink ON tr_saleshead.salesNum = tr_saleslink.salesNum
        LEFT JOIN
          tr_saleslink childSalesLinks ON tr_saleshead.salesNum = tr_saleslink.linkSalesNum
        LEFT JOIN
          tr_saleshead headLinkSales ON tr_saleslink.linkSalesNum = headLinkSales.salesNum
        LEFT JOIN
            ms_member ON tr_saleshead.memberID = ms_member.memberID
        LEFT JOIN
            tr_customertransaction customer ON tr_saleshead.salesNum = customer.salesNum
        LEFT JOIN
            ms_promotionhead ON tr_saleshead.promotionID = ms_promotionhead.promotionID
        LEFT JOIN
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
        LEFT JOIN
            map_branchvisitpurpose ON ms_visitpurpose.visitPurposeID = map_branchvisitpurpose.visitPurposeID
        LEFT JOIN
            ms_menutemplatehead ON map_branchvisitpurpose.menuTemplateID = ms_menutemplatehead.menuTemplateID
        LEFT JOIN
            ms_table ON tr_saleshead.tableID = ms_table.tableID
        LEFT JOIN
            ms_branch ON tr_saleshead.branchID = ms_branch.branchID
        LEFT JOIN
            lk_status ON tr_saleshead.statusID = lk_status.statusID
        LEFT JOIN
            ms_posuser creator ON tr_saleshead.createdBy = creator.username
        LEFT JOIN
            ms_posuser editor ON tr_saleshead.editedBy = editor.username
        LEFT JOIN
            tr_salescontactinfo ON tr_saleshead.salesNum = tr_salescontactinfo.salesNum
        LEFT JOIN
            tr_salesrewardhead ON tr_saleshead.salesNum = tr_salesrewardhead.salesNum
        LEFT JOIN
            tr_salesconditionalpromo ON tr_saleshead.salesNum = tr_salesconditionalpromo.salesNum
        LEFT JOIN
            tr_salespaymentgateway ON tr_saleshead.salesNum = tr_salespaymentgateway.salesNum
        LEFT JOIN
            tr_salesinfo ON tr_saleshead.salesNum = tr_salesinfo.salesNum AND tr_salesinfo.key = 'Table Name'
        WHERE
            tr_saleshead.salesNum = '$salesNum'";
    }

    public static function checkSalesTypeEzo($salesType) {
      return strpos($salesType, 'EZO') !== false;
    }
}
