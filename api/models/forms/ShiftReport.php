<?php

namespace app\models\forms;

use app\models\BranchMenu;
use app\models\DepositWithdrawalHead;
use app\models\MemberDeposit;
use app\models\Menu;
use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\MenuExtra;
use app\models\MenuPromotion;
use app\models\MenuPromotionHead;
use app\models\MenuTemplateDetail;
use app\models\PaymentMethod;
use app\models\PaymentMethodType;
use app\models\PosUser;
use app\models\ProductDetailMenu;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesPayment;
use app\models\SalesPlatformFee;
use app\models\SalesVoucherUsage;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\Table;
use app\models\TableSection;
use app\models\VisitPurpose;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;

/**
 * @property int $shiftID
 * @property string $startDate
 * @property string $endDate
 * 
 * PRIVATE
 * @property int $branchID
 * @property ShiftLog $shiftLogModel
 * @property string $totalGrandTotal
 * @property string $totalNonCash
 * @property boolean $validated
 */
class ShiftReport extends Model {
    const SCENARIO_BY_ID = 'by shift id';
    const SCENARIO_BY_TIME = 'by start date and end date';

    public $shiftID;
    public $startDate;
    public $endDate;
    public $branchID;
    public $shiftLogModel;
    public $totalGrandTotal;
    public $totalNonCash;
    public $validated;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['shiftID'], 'integer'],
            [['shiftID'], 'validateShift', 'skipOnEmpty' => false, 'on' => self::SCENARIO_BY_ID],
            [['startDate', 'endDate'], 'required', 'on' => self::SCENARIO_BY_TIME],
            [['startDate'], 'setBranch', 'on' => self::SCENARIO_BY_TIME],
        ];
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_BY_ID] = ['shiftID'];
        $scenarios[self::SCENARIO_BY_TIME] = ['startDate', 'endDate'];

        return $scenarios;
    }

    public function validateShift($attribute) {
        if (!$this->validated) {
            if (!$this->shiftID) {
                $this->shiftLogModel = ShiftLog::findActive();
            } else {
                $branchID = Setting::getCurrentBranch();
                $this->shiftLogModel = ShiftLog::find()
                    ->andWhere(['branchID' => $branchID])
                    ->andWhere(['shiftID' => $this->shiftID])
                    ->one();
                if (!$this->shiftLogModel) {
                    $this->addError($attribute, 'Invalid shift ID');
                }
            }

            if ($this->shiftLogModel) {
                $this->validated = true;
                $this->branchID = $this->shiftLogModel->branchID;
                $this->startDate = $this->shiftLogModel->shiftInTime;
                $this->endDate = $this->shiftLogModel->shiftOutTime;
            }
        }
    }

    public function setBranch() {
        if (!$this->validated) {
            $this->validated = true;
            $this->branchID = Setting::getCurrentBranch();
        }
    }

    public function getShiftHead() {
        if (!$this->validate()) {
            return false;
        }

        if ($this->scenario == self::SCENARIO_BY_TIME) {
            return [];
        }

        if ($this->shiftLogModel) {
            $this->shiftLogModel->shiftInTime = str_replace("-", "/", $this->shiftLogModel->shiftInTime);
        }
        
        $shift = $this->shiftLogModel->toArray();
        if (!$this->shiftLogModel->shiftOutTime) {
            // @Notes: if getSalesPaymentRecap called first, then no need to calculate totalGrandTotal and totalNonCash
            if (!$this->totalGrandTotal) {
                $this->totalGrandTotal = SalesPayment::getTotalGrandTotal($this->shiftLogModel->shiftID,
                        $this->shiftLogModel);
            }
            if (!$this->totalNonCash) {
                $this->totalNonCash = SalesPayment::getTotalNonCash($this->shiftLogModel->shiftID,
                        $this->shiftLogModel);
            }
            $shift['systemCashReceivedTotal'] = ($this->totalGrandTotal - $this->totalNonCash);
        }

        return array_merge($shift,
            ['cashPayment' => $this->totalGrandTotal - $this->totalNonCash]
        );
    }

    public function getShiftDetail() {
        if (!$this->validate()) {
            return false;
        }

        if ($this->scenario == self::SCENARIO_BY_TIME) {
            return [];
        }

        return $this->shiftLogModel->shiftLogDetails;
    }

    public function getShiftCash() {
        if (!$this->validate()) {
            return false;
        }

        if ($this->scenario == self::SCENARIO_BY_TIME) {
            return [];
        }

        return $this->shiftLogModel->shiftLogCash;
    }

    public function getSalesRecap() {
        if (!$this->validate()) {
            return false;
        }

        // @Notes: statusID 8 = Finished
        $closedSales = (new Query())
            ->select(['salesTotal' => 'COALESCE(SUM(subtotal), 0)',
                'discountTotal' => 'COALESCE(SUM(discountTotal) + SUM(menuDiscountTotal), 0)',
                'billDiscount' => 'COALESCE(SUM(discountTotal), 0)',
                'voucherDiscountTotal' => 'COALESCE(SUM(voucherDiscountTotal), 0)',
                'menuDiscountTotal' => 'COALESCE(SUM(menuDiscountTotal), 0)',
                'netSales' => 'COALESCE(SUM(subtotal - discountTotal - menuDiscountTotal - voucherDiscountTotal), 0)',
                'deliveryCostTotal' => 'COALESCE(SUM(deliveryCost), 0)',
                'orderFee' => 'COALESCE(SUM(orderFee), 0)',
                'otherTaxTotal' => 'COALESCE(SUM(otherTaxTotal), 0)',
                'vatTotal' => 'COALESCE(SUM(vatTotal), 0)',
                'otherVatTotal' => 'COALESCE(SUM(otherVatTotal), 0)',
                'platformFee' => 'COALESCE(SUM(b.amount), 0)',
                'voucherSalesTotal' => 'COALESCE(SUM(voucherTotal), 0)',
                'roundingTotal' => 'COALESCE(SUM(roundingTotal), 0)',
                'grossSales' => 'COALESCE(SUM(grandTotal - roundingTotal), 0)',
                'paxTotal' => 'COALESCE(SUM(paxTotal), 0)',
                'avgNetPax' => 'COALESCE(SUM(subtotal - discountTotal - menuDiscountTotal) / SUM(paxTotal), 0)',
                'avgGrossPax' => 'COALESCE(SUM(grandTotal - roundingTotal) / SUM(paxTotal), 0)',
                'numOfBills' => 'COALESCE(COUNT(a.salesNum), 0)',
                'avgNetBill' => 'COALESCE(SUM(subtotal - discountTotal - menuDiscountTotal) / COUNT(a.salesNum), 0)',
                'avgGrossBill' => 'COALESCE(SUM(grandTotal - roundingTotal) / COUNT(a.salesNum), 0)'])
            ->from(SalesHead::tableName() . ' a')
            ->leftJoin(['b' => SalesPlatformFee::tableName()],
                        "a.salesNum = b.salesNum" . " AND b.platformFeeTypeID = 1")
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->one();

        // @Notes: statusID 1 = New
        $pendingSales = (new Query())
            ->select(['grossSales' => 'COALESCE(SUM(subtotal), 0)'])
            ->from(SalesHead::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andFilterWhere(['<=', 'salesDateIn', $this->endDate])
            ->andWhere(['IS', 'salesDateOut', null])
            ->andWhere(['statusID' => 1])
            ->one();

        $cancelMenuPackage = (new Query())
            ->select([
                'subtotal' => new Expression('a.price * a.qty * COALESCE(packageHead.qty,1)')  
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->leftJoin(["packageHead" => SalesMenu::tableName()],
            'a.menuRefID = packageHead.localID'. ' AND a.salesNum = packageHead.salesNum' . ' AND a.menuGroupID > 0')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'b.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'b.salesDateOut', $this->endDate])
            ->andFilterWhere(['in', 'b.statusID', [8, 12]])
            ->andFilterWhere(['in', 'a.statusID', [12, 19]]);
        
        $cancelMenuExtra = (new Query())
            ->select([
                'subtotal' => new Expression('a.price * a.qty * COALESCE(headSalesMenu.qty, 1)')
            ])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->leftJoin(['headSalesMenu' => SalesMenu::tableName()],
                        "a.menuDetailID = headSalesMenu.ID "
                        . "AND a.salesNum = headSalesMenu.salesNum")
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'b.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'b.salesDateOut', $this->endDate])
            ->andFilterWhere(['in', 'b.statusID', [8, 12]])
            ->andFilterWhere(['in', 'a.statusID', [12, 19]]);
        
        $cancelSales = (new Query())
            ->select(['cancelSales' => 'COALESCE(SUM(unionCancelSales.subtotal), 0)'])
            ->from(['unionCancelSales' => $cancelMenuPackage->union($cancelMenuExtra, true)])
            ->one();

        $voidSales = (new Query())
            ->select(['voidSales' => 'COALESCE(SUM(grandTotal), 0)'])
            ->from(SalesHead::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 24])
            ->one();

        foreach ($closedSales as $field => $value) {
            $closedSales[$field] = (float) $value;
        }

        return array_merge([
            'pendingSales' => (float) $pendingSales['grossSales']
            ], $closedSales, $cancelSales, $voidSales);
    }

    public function getSalesPaymentRecap() {
        if (!$this->validate()) {
            return false;
        }

        // @Notes: statusID 8 = Finished, paymentMethodTypeID 1 = CASH
        $salesPayments = (new Query())
            ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                'paymentMethodTypeName', 'paymentMethodName',
                'paymentAmount' => 'SUM(CASE WHEN c.paymentMethodTypeID = 1 '
                . 'THEN a.paymentAmount - b.paymentTotal + b.grandTotal - b.roundingTotal + IFNULL(e.total, 0) '
                . 'ELSE a.paymentAmount END)'])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tablename() . ' c',
                'a.paymentMethodID = c.paymentMethodID')
            ->innerJoin(PaymentMethodType::tablename() . ' d',
                'c.paymentMethodTypeID = d.paymentMethodTypeID')
            ->leftJoin(['e' => SalesLink::getGroupLinkedTotal($this->branchID,
                    $this->startDate, $this->endDate)],
                'a.salesNum = e.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        foreach ($salesPayments as $sales) {
            $salesPayments[$index]['paymentMethodTypeID'] = (int) $sales['paymentMethodTypeID'];
            $salesPayments[$index]['paymentAmount'] = (float) $sales['paymentAmount'];

            $index++;
        }

        return $salesPayments;
    }

    public function getOverVoucherValue() {
        if (!$this->validate()) {
            return false;
        }

        $startDate = "";
        $endDate = "";

        if ($this->shiftID != null) {
            $shift = (new Query())
                ->from(ShiftLog::tableName())
                ->where(['=', 'shiftID', $this->shiftID])
                ->one();
            
            $startDate = $shift['shiftInTime'];
            $endDate = $shift['shiftOutTime'];

        }else{
            $startDate = $this->startDate;
            $endDate = $this->endDate;
        }

        // @Notes: statusID 8 = Finished
        $overVoucherValue = (new Query())
            ->select([
                'amount' => 'COALESCE(ROUND(SUM(a.fullpaymentAmount - a.paymentAmount)),0)'
                ])
            ->from(SalesPayment::tableName() . " a")
            ->leftJoin(SalesHead::tableName() . " b", 'a.salesNum = b.salesNum')
            ->leftJoin(PaymentMethod::tableName(). ' c', 'c.paymentMethodID = a.paymentMethodID')
            ->where(['IN', 'c.paymentMethodTypeID', [4, 5]])
            ->andWhere(['=', 'b.statusID', '8'])
            ->andWhere(['>=', 'b.salesDateOut', $startDate])
            ->andFilterWhere(['<=', 'b.salesDateOut', $endDate])
            ->one();

        return $overVoucherValue;
    }

    public function getNonSalesPaymentRecap() {
        if (!$this->validate()) {
            return false;
        }

        // @Notes: if getShiftHead called first, then no need to calculate totalGrandTotal
        if (!$this->totalGrandTotal) {
            $this->totalGrandTotal = (new Query())
                ->select('SUM(grandTotal - roundingTotal)')
                ->from(SalesHead::tableName())
                ->andWhere(['branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['statusID' => 8])
                ->andWhere(['IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->scalar();
        }

        // @Notes: statusID 8 = Finished
        $salesPayments = (new Query())
            ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                'paymentMethodTypeName', 'paymentMethodName',
                'paymentAmount' => 'SUM(paymentAmount)'])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tablename() . ' c',
                'a.paymentMethodID = c.paymentMethodID')
            ->innerJoin(PaymentMethodType::tablename() . ' d',
                'c.paymentMethodTypeID = d.paymentMethodTypeID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        foreach ($salesPayments as $sales) {
            $salesPayments[$index]['paymentMethodTypeID'] = (int) $sales['paymentMethodTypeID'];
            $salesPayments[$index]['paymentAmount'] = (float) $sales['paymentAmount'];

            $index++;
        }

        return $salesPayments;
    }

    public function getSalesPaymentPerCashier() {
        if (!$this->validate()) {
            return false;
        }

        $salesPayments = [];
        // @Notes: statusID 8 = Finished
        $subQuery = (new Query())
            ->select([
                'username' => new Expression("CASE WHEN a.editedBy IS NULL OR a.editedBy = 'BASIC' THEN 'SELF ORDER' ELSE a.editedBy END"),
                'fullName' => new Expression("CASE WHEN a.editedBy IS NULL OR a.editedBy = 'BASIC' THEN 'SELF ORDER' ELSE CASE WHEN b.fullName IS NULL THEN a.editedBy ELSE b.fullName END END"),
                'grandTotal' => 'SUM(grandTotal - roundingTotal)'])
            ->from(SalesHead::tableName() . ' a')
            ->leftJoin(PosUser::tableName() . ' b', 'a.editedBy = b.username')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                new Expression("CASE WHEN a.editedBy IS NULL OR a.editedBy = 'BASIC' THEN 'SELF ORDER' ELSE a.editedBy END"),
                new Expression("CASE WHEN a.editedBy IS NULL OR a.editedBy = 'BASIC' THEN 'SELF ORDER' ELSE CASE WHEN b.fullName IS NULL THEN a.editedBy ELSE b.fullName END END")
            ]);
        
        $cashierSalesRecap = (new Query())
            ->select([
                'subQuery.username',
                'subQuery.fullName',
                'grandTotal' => 'SUM(subQuery.grandTotal)'])
            ->from(['subQuery' => $subQuery])
            ->groupBy([
                'subQuery.username',
                'subQuery.fullName'
            ])
            ->orderBy('subQuery.username ASC', 'subQuery.fullName ASC')
            ->all();
        
        foreach ($cashierSalesRecap as $salesRecap) {
            $grandTotal = (float) $salesRecap['grandTotal'];
            // @Notes: statusID 8 = Finished, paymentMethodTypeID 1 = CASH
            $username = $salesRecap['username'] != 'SELF ORDER' ? $salesRecap['username'] : NULL;
            
            if($salesRecap['username'] == 'SELF ORDER'){
                $query1 = (new Query())
                ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                    'paymentMethodTypeName', 'paymentMethodName',
                    'paymentAmount' => 'SUM(CASE WHEN c.paymentMethodTypeID = 1 '
                    . 'THEN a.paymentAmount - b.paymentTotal + b.grandTotal - b.roundingTotal + IFNULL(e.total, 0) '
                    . 'ELSE a.paymentAmount END)'])
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->innerJoin(PaymentMethodType::tablename() . ' d',
                    'c.paymentMethodTypeID = d.paymentMethodTypeID')
                ->leftJoin(['e' => SalesLink::getGroupLinkedTotal($this->branchID,
                        $this->startDate, $this->endDate)],
                    'a.salesNum = e.salesNum')
                ->andWhere(['b.branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['b.editedBy' => NULL])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
                ->orderBy('paymentMethodName');
                
                $query2 = (new Query())
                ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                    'paymentMethodTypeName', 'paymentMethodName',
                    'paymentAmount' => 'SUM(CASE WHEN c.paymentMethodTypeID = 1 '
                    . 'THEN a.paymentAmount - b.paymentTotal + b.grandTotal - b.roundingTotal + IFNULL(e.total, 0) '
                    . 'ELSE a.paymentAmount END)'])
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->innerJoin(PaymentMethodType::tablename() . ' d',
                    'c.paymentMethodTypeID = d.paymentMethodTypeID')
                ->leftJoin(['e' => SalesLink::getGroupLinkedTotal($this->branchID,
                        $this->startDate, $this->endDate)],
                    'a.salesNum = e.salesNum')
                ->andWhere(['b.branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['b.editedBy' => 'BASIC'])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
                ->orderBy('paymentMethodName');
                
                $cashierSalesPayment = $query1->union($query2,true)->all();
            } else {
                $cashierSalesPayment = (new Query())
                ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                    'paymentMethodTypeName', 'paymentMethodName',
                    'paymentAmount' => 'SUM(CASE WHEN c.paymentMethodTypeID = 1 '
                    . 'THEN a.paymentAmount - b.paymentTotal + b.grandTotal - b.roundingTotal + IFNULL(e.total, 0) '
                    . 'ELSE a.paymentAmount END)'])
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->innerJoin(PaymentMethodType::tablename() . ' d',
                    'c.paymentMethodTypeID = d.paymentMethodTypeID')
                ->leftJoin(['e' => SalesLink::getGroupLinkedTotal($this->branchID,
                        $this->startDate, $this->endDate)],
                    'a.salesNum = e.salesNum')
                ->andWhere(['b.branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['b.editedBy' => $username])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
                ->orderBy('paymentMethodName')
                ->all();
            }

            $index = 0;
            foreach ($cashierSalesPayment as $payment) {
                $cashierSalesPayment[$index]['paymentMethodTypeID'] = (int) $payment['paymentMethodTypeID'];
                $cashierSalesPayment[$index]['paymentAmount'] = (float) $payment['paymentAmount'];

                $index++;
            }

            $salesPayments[] = [
                'cashierName' => $salesRecap['fullName'],
                'total' => $grandTotal,
                'salesPayment' => $cashierSalesPayment
            ];
        }

        return $salesPayments;
    }

    public function getNonSalesPaymentPerCashier() {
        if (!$this->validate()) {
            return false;
        }

        $salesPayments = [];
        // @Notes: statusID 8 = Finished
        $cashierSalesRecap = (new Query())
            ->select(['username' => 'a.editedBy',
                'fullName' => 'b.fullName',
                'grandTotal' => 'SUM(grandTotal - roundingTotal)'])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(PosUser::tableName() . ' b', 'a.editedBy = b.username')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('a.editedBy, b.fullName')
            ->orderBy('a.editedBy, b.fullName')
            ->all();
        foreach ($cashierSalesRecap as $salesRecap) {
            $grandTotal = (float) $salesRecap['grandTotal'];
            // @Notes: statusID 8 = Finished
            $cashierSalesPayment = (new Query())
                ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                    'paymentMethodTypeName', 'paymentMethodName',
                    'paymentAmount' => 'SUM(paymentAmount)'])
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->innerJoin(PaymentMethodType::tablename() . ' d',
                    'c.paymentMethodTypeID = d.paymentMethodTypeID')
                ->andWhere(['b.branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['b.editedBy' => $salesRecap['username']])
                ->andWhere(['statusID' => 8])
                ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->groupBy('c.paymentMethodTypeID, paymentMethodTypeName, paymentMethodName')
                ->orderBy('paymentMethodName')
                ->all();

            $index = 0;
            foreach ($cashierSalesPayment as $payment) {
                $cashierSalesPayment[$index]['paymentMethodTypeID'] = (int) $payment['paymentMethodTypeID'];
                $cashierSalesPayment[$index]['paymentAmount'] = (float) $payment['paymentAmount'];
                $index++;
            }

            $salesPayments[] = [
                'cashierName' => $salesRecap['fullName'],
                'total' => $grandTotal,
                'nonSalesPayment' => $cashierSalesPayment
            ];
        }

        return $salesPayments;
    }

    public function getPendingSales() {
        if (!$this->validate()) {
            return false;
        }

        return SalesHead::findOutstanding()
                ->with('table')
                ->with('member')
                ->with('promotion')
                ->with('status')
                ->with('creator')
                ->with('editor')
                ->andFilterWhere(['<=', 'salesDateIn', $this->endDate])
                ->andWhere(['statusID' => 1])
                ->all();
    }

    public function getSalesMenuPromotion() {
        if (!$this->validate()) {
            return false;
        }

        $promotionSummaries = [];
        $salesHeadQuery = (new Query())
            ->select(['a.promotionID', 'b.notes',
                'qty' => 'COUNT(*)', 'discountTotal' => 'SUM(discountTotal)'])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(PromotionHead::tableName() . ' b',
                'a.promotionID = b.promotionID')
            ->andWhere(['>', 'discountTotal', 0])
            ->groupBy('a.promotionID, b.notes');

        $salesMenuQuery = (new Query())
            ->select(['promotionID', 'b.notes',
                'qty' => 'SUM(a.qty)', 'discountTotal' => 'SUM(a.discountValue)'])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(PromotionHead::tableName() . ' b',
                'a.promotionDetailID = b.promotionID')
            ->andWhere(['>', 'a.discount', 0])
            ->groupBy('promotionID, b.notes');

        $promotions = (new Query())
            ->select(['promotionID', 'notes',
                'qty' => 'SUM(qty)', 'discountTotal' => 'SUM(discountTotal)'])
            ->from($salesHeadQuery->union($salesMenuQuery, true))
            ->groupBy('promotionID, notes')
            ->orderBy('notes')
            ->all();
        foreach ($promotions as $promotion) {
            $promotion['promotionID'] = (int) $promotion['promotionID'];
            $promotion['qty'] = (int) $promotion['qty'];
            $promotion['discountTotal'] = (float) $promotion['discountTotal'];
            $promotionSummaries[] = $promotion;
        }

        return $promotionSummaries;
    }

    public function getSalesMenuPerCategory() {
        if (!$this->validate()) {
            return false;
        }

        $salesMenuCategories = [];
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'qty' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END))',
                'subtotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)',
                //'menuDiscountTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.discount / 100))',
                'menuDiscountTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.discountValue * z.qty ELSE a.discountValue END)',
                'otherTaxTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherTaxValue * z.qty ELSE a.otherTaxValue END)',
                'vatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.vatValue * z.qty ELSE a.vatValue END)',
                'otherVatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherVatValue * z.qty ELSE a.otherVatValue END)',
                'total' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.total ELSE a.total * z.qty END)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'c.menuCategoryDetailID = d.ID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'd.menuCategoryID = e.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' package',
                'a.menuRefID = package.ID AND 
                a.ID <> package.menuRefID AND 
                package.menuRefID <> 0 AND 
                a.salesNum = package.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('e.menuCategoryID, e.menuCategoryDesc')
            ->orderBy('e.menuCategoryDesc')
            ->all();
        foreach ($salesMenu as $sales) {
            $salesMenuCategories[] = [
                'description' => $sales['menuCategoryDesc'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) $sales['total']
            ];
        }

        $salesMenuExtra = $this->getSalesMenuExtra();
        if ($salesMenuExtra['qty'] > 0) {
            $salesMenuCategories[] = [
                'description' => 'MENU EXTRA',
                'qty' => (float) $salesMenuExtra['qty'],
                'subtotal' => (float) $salesMenuExtra['subtotal'],
                'menuDiscountTotal' => (float) $salesMenuExtra['discountValue'],
                'otherTaxTotal' => (float) $salesMenuExtra['otherTaxTotal'],
                'vatTotal' => (float) $salesMenuExtra['vatTotal'],
                'otherVatTotal' => (float) $salesMenuExtra['otherVatTotal'],
                'grandTotal' => (float) $salesMenuExtra['total']
            ];
        }

        return $salesMenuCategories;
    }

    public function getSalesMenuPerCategoryDetail() {
        if (!$this->validate()) {
            return false;
        }

        $salesMenuCategoryDetails = [];
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'menuCategoryDetailID' => 'd.ID',
                'menuCategoryDetailDesc' => 'CONCAT(e.menuCategoryDesc, \' - \', d.menuCategoryDetailDesc)',
                'qty' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)',
                'subtotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)',
                //'menuDiscountTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.discount / 100))',
                'menuDiscountTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.discountValue * z.qty ELSE a.discountValue END)',
                'otherTaxTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherTaxValue * z.qty ELSE a.otherTaxValue END)',
                'vatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.vatValue * z.qty ELSE a.vatValue END)',
                'otherVatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherVatValue * z.qty ELSE a.otherVatValue END)',
                'total' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.total ELSE a.total * z.qty END)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'c.menuCategoryDetailID = d.ID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'd.menuCategoryID = e.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' package', 
                'a.menuRefID = package.ID AND 
                a.ID <> package.menuRefID AND 
                package.menuRefID <> 0 AND 
                a.salesNum = package.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.ID, e.menuCategoryDesc, d.menuCategoryDetailDesc')
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc')
            ->all();
        foreach ($salesMenu as $sales) {
            $salesMenuCategoryDetails[] = [
                'description' => $sales['menuCategoryDetailDesc'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) $sales['total']
            ];
        }

        $salesMenuExtra = $this->getSalesMenuExtra();
        if ($salesMenuExtra['qty'] > 0) {
            $salesMenuCategoryDetails[] = [
                'description' => 'MENU EXTRA',
                'qty' => (float) $salesMenuExtra['qty'],
                'subtotal' => (float) $salesMenuExtra['subtotal'],
                'menuDiscountTotal' => (float) $salesMenuExtra['discountValue'],
                'otherTaxTotal' => (float) $salesMenuExtra['otherTaxTotal'],
                'vatTotal' => (float) $salesMenuExtra['vatTotal'],
                'otherVatTotal' => (float) $salesMenuExtra['otherVatTotal'],
                'grandTotal' => (float) $salesMenuExtra['total']
            ];
        }

        return $salesMenuCategoryDetails;
    }

    private function getSalesMenuExtra() {
        return (new Query())
                ->select([
                    'qty' => 'SUM(c.qty * a.qty)',
                    'subtotal' => 'SUM(c.qty * a.qty * a.price)', 'menuDiscountTotal' => 'SUM(a.qty * a.price * (a.discount / 100))',
                    'discountValue' => 'SUM(a.discountValue * c.qty)',
                    'otherTaxTotal' => 'SUM(a.otherTaxValue * c.qty)',
                    'vatTotal' => 'SUM(a.vatValue * c.qty)',
                    'otherVatTotal' => 'SUM(a.otherVatValue * c.qty)',
                    'total' => 'SUM(c.qty * a.total)'
                ])
                ->from(SalesMenuExtra::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(SalesMenu::tableName() . ' c',
                    'a.menuDetailID = c.ID')
                ->andWhere(['b.branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['b.statusID' => 8])
                ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->one();
    }

    public function getSalesMenu() {
        if (!$this->validate()) {
            return false;
        }
        
        $salesMenus = [];
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'c.menuName',
                'qty' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)',
                'subtotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)',
                //'menuDiscountTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.discount / 100))',
                'menuDiscountTotal' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.discountValue ELSE a.discountValue * z.qty END)',
                'otherTaxTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherTaxValue * z.qty ELSE a.otherTaxValue END)',
                'vatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.vatValue * z.qty ELSE a.vatValue END)',
                'otherVatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherVatValue * z.qty ELSE a.otherVatValue END)',
                'total' => 'SUM(CASE WHEN a.menuGroupID = 0 THEN a.total ELSE a.total * z.qty END)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.ID and a.salesNum = z.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' package', 
                'a.menuRefID = package.ID AND 
                a.ID <> package.menuRefID AND 
                package.menuRefID <> 0 AND 
                a.salesNum = package.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.menuName')
            ->orderBy('c.menuName')
            ->all();

        foreach ($salesMenu as $sales) {
            $salesMenus[] = [
                'description' => $sales['menuName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) $sales['total']
            ];
        }

        $salesMenuExtra = (new Query())
            ->select(['d.menuExtraName', 
                'qty' => 'SUM(c.qty * a.qty)',
                'subtotal' => 'SUM(c.qty * a.qty * a.price)', 
                'menuDiscountTotal' => 'SUM(c.qty * a.qty * a.price * (a.discount / 100))',
                'otherTaxTotal' => 'SUM(a.otherTaxValue * c.qty)',
                'vatTotal' => 'SUM(a.vatValue * c.qty)',
                'otherVatTotal' => 'SUM(a.otherVatValue * c.qty)',
                'grandTotal' => 'SUM(b.grandTotal)' 
            ]) 
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.menuExtraName')
            ->orderBy('d.menuExtraName')
            ->all();
        foreach ($salesMenuExtra as $sales) {
            $salesMenus[] = [
                'description' => $sales['menuExtraName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) ($sales['subtotal'] - $sales['menuDiscountTotal'] + $sales['otherTaxTotal'] + $sales['vatTotal'] + $sales['otherVatTotal'])
            ];
        }

        //@notes: sort menuName and menuExtraName in ascending
        if(isset($salesMenus)){
            usort($salesMenus, function($a, $b) {
                return strcmp($a['description'], $b['description']);
            });   
        }     
        
        return $salesMenus;
    }

    public function getCancelledMenu() {
        if (!$this->validate()) {
            return false;
        }

        $cancelledMenus = [];
        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenu = (new Query())
            ->select([
                'c.menuName',
                'qty' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END))',
                'subtotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)',
                //'menuDiscountTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.discount / 100))',
                'menuDiscountTotal' => 'SUM(a.discountValue)',
                'otherTaxTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherTaxValue * z.qty ELSE a.otherTaxValue END)',
                'vatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.vatValue * z.qty ELSE a.vatValue END)',
                'otherVatTotal' => 'SUM(CASE WHEN (a.menuRefID = package.menuRefID) THEN a.otherVatValue * z.qty ELSE a.otherVatValue END)',
                'grandTotal' => 'SUM(b.grandTotal)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' package', 
                'a.menuRefID = package.ID AND 
                a.ID <> package.menuRefID AND 
                package.menuRefID <> 0 AND 
                a.salesNum = package.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['OR',
                ['IN', 'b.statusID', [12, 24]],
                ['NOT IN', 'a.statusID', [13, 14, 34]]
            ])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.menuName')
            ->orderBy('c.menuName')
            ->all();
        foreach ($salesMenu as $sales) {
            $cancelledMenus[] = [
                'description' => $sales['menuName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) ($sales['subtotal'] - $sales['menuDiscountTotal'] + $sales['otherTaxTotal'] + $sales['vatTotal'] + $sales['otherVatTotal'])
            ];
        }

        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenuExtra = (new Query())
            ->select([
                'd.menuExtraName', 'qty' => 'SUM(c.qty * a.qty)',
                'subtotal' => 'SUM(c.qty * a.qty * a.price)', 'menuDiscountTotal' => 'SUM(c.qty * a.qty * a.price * (a.discount / 100))',
                'otherTaxTotal' => 'SUM(a.otherTaxValue * c.qty)',
                'vatTotal' => 'SUM(a.vatValue * c.qty)',
                'otherVatTotal' => 'SUM(a.otherVatValue * c.qty)',
                'grandTotal' => 'SUM(b.grandTotal)'
            ])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['OR',
                ['IN', 'b.statusID', [12, 24]],
                ['NOT IN', 'c.statusID', [13, 14, 34]]
            ])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.menuExtraName')
            ->orderBy('d.menuExtraName')
            ->all();
        foreach ($salesMenuExtra as $sales) {
            $cancelledMenus[] = [
                'description' => $sales['menuExtraName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'otherVatTotal' => (float) $sales['otherVatTotal'],
                'grandTotal' => (float) ($sales['subtotal'] - $sales['menuDiscountTotal'] + $sales['otherTaxTotal'] + $sales['vatTotal'] + $sales['otherVatTotal'])
            ];
        }

        if(isset($cancelledMenus)){
            usort($cancelledMenus, function($a, $b) {
                return strcmp($a['description'], $b['description']);
            });   
        }   

        return $cancelledMenus;
    }

    public function getCancelledMenuPerSales() {
        if (!$this->validate()) {
            return false;
        }

        $cancelledMenus = [];
        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenu = (new Query())
            ->select([
                'b.salesNum',
                'c.menuName',
                'qty' => '(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)',
                'cancelNotes' => '(CASE WHEN a.cancelNotes <> \'\' THEN a.cancelNotes ELSE b.additionalInfo END)',
                'd.fullName', 'a.editedDate'])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(PosUser::tableName() . ' d', 'a.editedBy = d.username')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['OR',
                ['IN', 'b.statusID', [12, 24]],
                ['NOT IN', 'a.statusID', [13, 14, 34]]
            ])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->orderBy('c.menuName, b.salesNum')
            ->all();
        foreach ($salesMenu as $sales) {
            $sales['qty'] = (float) $sales['qty'];
            $cancelledMenus[] = $sales;
        }

        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenuExtra = (new Query())
            ->select(['b.salesNum', 'menuName' => 'd.menuExtraName', 'qty' => '(c.qty * a.qty)',
                'cancelNotes' => '(CASE WHEN c.cancelNotes <> \'\' THEN c.cancelNotes ELSE b.additionalInfo END)',
                'e.fullName', 'c.editedDate'])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->innerJoin(PosUser::tableName() . ' e', 'c.editedBy = e.username')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['OR',
                ['IN', 'b.statusID', [12, 24]],
                ['NOT IN', 'a.statusID', [13, 14, 34]]
            ])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->orderBy('d.menuExtraName, b.salesNum')
            ->all();
        foreach ($salesMenuExtra as $sales) {
            $sales['qty'] = (int) $sales['qty'];
            $cancelledMenus[] = $sales;
        }

        return $cancelledMenus;
    }

    // @TODO: to be further developed
    public function getNonSalesMenu() {
        if (!$this->validate()) {
            return false;
        }
    }

    public function getSalesType() {
        if (!$this->validate()) {
            return false;
        }

        $salesTypes = [];
        // @Notes: statusID 8 = Finished
        $salesType = (new Query())
            ->select(['type' => '(CASE WHEN tableID = 0 THEN \'Quick Service\' ELSE \'Dine In\' END)',
                'netSales' => 'COALESCE(SUM(subtotal - discountTotal - menuDiscountTotal), 0)'])
            ->from(SalesHead::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('type')
            ->orderBy('type')
            ->all();

        foreach ($salesType as $sales) {
            $sales['netSales'] = (float) $sales['netSales'];
            $salesTypes[] = $sales;
        }

        return $salesTypes;
    }

    public function getSalesByTableSection() {
        if (!$this->validate()) {
            return false;
        }

        $salesByTableSections = (new Query())
            ->select([
                'type' => new Expression("(CASE WHEN a.tableID = 0 THEN 'Quick Service' ELSE c.tableSectionName END)"),
                'netSales' => new Expression('COALESCE(SUM(a.subtotal - a.discountTotal - a.menuDiscountTotal - a.voucherDiscountTotal), 0)'),
                'billTotal' => new Expression('COUNT(a.salesNum)')
            ])
            ->from(['a' => SalesHead::tableName()])
            ->leftJoin(['b' => Table::tableName()], 'a.tableID = b.tableID')
            ->leftJoin(['c' => TableSection::tableName()], 'b.tableSectionID = c.tableSectionID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>=', 'a.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'a.salesDateOut', $this->endDate])
            ->andWhere(['a.statusID' => 8])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID, $this->startDate, $this->endDate)])
            ->groupBy([
                'type',
                'tableSectionName'
            ])
            ->orderBy('type')
            ->all();
        
        $result = [];
        foreach ($salesByTableSections as $salesByTableSection) {
            $salesByTableSection['netSales'] = (float) $salesByTableSection['netSales'];
            $salesByTableSection['billTotal'] = (float) $salesByTableSection['billTotal'];
            $result[] = $salesByTableSection;
        }

        return $result;
    }

    public function getDepositPaymentPerPaymentMethod() {
        if (!$this->validate()) {
            return false;
        }

        $memberDeposits = [];
        $depositPaymentRecap = (new Query())
            ->select(['paymentMethodTypeID', 'a.paymentMethodID', 'paymentMethodName',
                'paymentAmount' => 'COALESCE(SUM(depositTotal), 0)'])
            ->from(MemberDeposit::tableName() . ' a')
            ->innerJoin(PaymentMethod::tableName() . ' b',
                'a.paymentMethodID = b.paymentMethodID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>', 'a.createdDate', $this->startDate])
            ->andFilterWhere(['<', 'a.createdDate', $this->endDate])
            ->groupBy('paymentMethodTypeID, a.paymentMethodID, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        foreach ($depositPaymentRecap as $depositRecap) {
            $depositRecap['paymentMethodTypeID'] = (int) $depositRecap['paymentMethodTypeID'];
            $depositRecap['paymentAmount'] = (float) $depositRecap['paymentAmount'];
            $memberDeposit = (new Query())
                ->select(['memberDepositNum', 'depositTotal'])
                ->from(MemberDeposit::tableName())
                ->andWhere(['branchID' => $this->branchID])
                ->andWhere(['>', 'createdDate', $this->startDate])
                ->andFilterWhere(['<', 'createdDate', $this->endDate])
                ->andWhere(['paymentMethodID' => (int) $depositRecap['paymentMethodID']])
                ->orderBy('memberDepositNum')
                ->all();

            $memberDeposits[$index] = $depositRecap;
            foreach ($memberDeposit as $deposit) {
                $deposit['depositTotal'] = (float) $deposit['depositTotal'];
                $memberDeposits[$index]['deposit'][] = $deposit;
            }
            $index++;
        }

        return $memberDeposits;
    }
    
    public function getSalesPaymentPerPaymentMethod() {
        if (!$this->validate()) {
            return false;
        }

        $salesPayments = [];
        // @Notes: statusID 8 = Finished
        
        $salesPaymentMethods = (new Query())
            ->select([
                'a.salesNum',
                'b.billNum',
                'a.paymentMethodID', 
                'c.paymentMethodName',
                'paymentAmount' => 'SUM(CASE WHEN c.paymentMethodTypeID = 1 '
                . 'THEN a.paymentAmount - b.paymentTotal + b.grandTotal - b.roundingTotal + IFNULL(d.total, 0) '
                . 'ELSE a.paymentAmount END)'])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b',
                'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tableName() . ' c',
                'a.paymentMethodID = c.paymentMethodID')
            ->leftJoin(['d' => SalesLink::getGroupLinkedTotal($this->branchID,
                    $this->startDate, $this->endDate)],
                'a.salesNum = d.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'b.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'b.salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'a.salesNum',
                'b.billNum',
                'a.paymentMethodID', 
                'c.paymentMethodName',
            ])
            ->orderBy('c.paymentMethodName ASC, b.billNum ASC')
            ->all();
        
        $data = [];
        foreach ($salesPaymentMethods as $sales) {
            $key = $sales['paymentMethodID'];
            $paymentAmount = $sales['paymentAmount'];
            
            $group = [
                'paymentMethodID' => $key,
                'paymentMethodName' => $sales['paymentMethodName'],
                'paymentAmount' => (array_key_exists($key, $data) ? $data[$key]['paymentAmount'] : 0) + $paymentAmount,
                'salesPayment' => array_key_exists($key, $data) ? $data[$key]['salesPayment'] : []
            ];

            $data[$key] = $group;

            $i = 0;
            if (in_array($key, $data)) {
                $data[$key]['salesPayment'][$i]['paymentAmount'] = $data[$key]['salesPayment'][$i]['paymentAmount'] + $paymentAmount;
            } else {
                $data[$key]['salesPayment'][] = [
                    'salesNum' => $sales['salesNum'],
                    'billNum' => $sales['billNum'],
                    'paymentAmount' => $paymentAmount,
                ];
            }
        }
        
        $salesPayments = array_values($data);
        return $salesPayments;
    }

    public function getNonSalesPaymentPerPaymentMethod() {
        if (!$this->validate()) {
            return false;
        }

        $salesPayments = [];
        // @Notes: statusID 8 = Finished
        $paymentMethodRecap = (new Query())
            ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                'a.paymentMethodID', 'paymentMethodName',
                'paymentAmount' => 'SUM(paymentAmount)'])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tablename() . ' c',
                'a.paymentMethodID = c.paymentMethodID')
            ->innerJoin(PaymentMethodType::tablename() . ' d',
                'c.paymentMethodTypeID = d.paymentMethodTypeID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.paymentMethodTypeID, a.paymentMethodID, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        forEach ($paymentMethodRecap as $paymentRecap) {
            $totalPayment = 0;
            $paymentRecap['paymentMethodTypeID'] = (int) $paymentRecap['paymentMethodTypeID'];
            $paymentRecap['paymentMethodID'] = (int) $paymentRecap['paymentMethodID'];
            // @Notes: paymentMethodTypeID 1 = CASH
            if ($paymentRecap['paymentMethodTypeID'] != 1) {
                $salesPaymentMethod = (new Query())
                    ->select(['a.salesNum', 'b.salesNum', 'paymentAmount'])
                    ->from(SalesPayment::tableName() . ' a')
                    ->innerJoin(SalesHead::tableName() . ' b',
                        'a.salesNum = b.salesNum')
                    ->andWhere(['b.branchID' => $this->branchID])
                    ->andWhere(['>=', 'salesDateOut', $this->startDate])
                    ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                    ->andWhere(['paymentMethodID' => $paymentRecap['paymentMethodID']])
                    ->andWhere(['statusID' => 8])
                    ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                            $this->startDate, $this->endDate)])
                    ->orderBy('a.salesNum')
                    ->all();

                $salesPaymentMethods = [];
                foreach ($salesPaymentMethod as $salesPayment) {
                    $salesPayment['paymentAmount'] = (float) $salesPayment['paymentAmount'];
                    $salesPaymentMethods[] = $salesPayment;
                }
                $totalPayment = (float) $paymentRecap['paymentAmount'];
            }

            $salesPayments[$index] = $paymentRecap;
            $salesPayments[$index]['paymentAmount'] = (float) $totalPayment;
            $salesPayments[$index]['salesPayment'] = $salesPaymentMethods;

            $index++;
        }

        return $salesPayments;
    }

    public function getSalesPerDate() {
        if (!$this->validate()) {
            return false;
        }

        $salesDetails = [];
        $dailySalesRecap = (new Query())
            ->select(['salesDate' => 'DATE(salesDateOut)',
                'numOfBills' => 'COUNT(*)', 'grandTotal' => 'SUM(grandTotal - roundingTotal)'])
            ->from(SalesHead::tableName())
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('DATE(salesDateOut)')
            ->orderBy('DATE(salesDateOut)')
            ->all();

        $index = 0;
        foreach ($dailySalesRecap as $dailyRecap) {
            $dailyRecap['numOfBills'] = (int) $dailyRecap['numOfBills'];
            $dailyRecap['grandTotal'] = (float) $dailyRecap['grandTotal'];
            $salesHeads = (new Query())
                ->select(['salesNum', 'billNum', 'grandTotal' => '(grandTotal - roundingTotal)'])
                ->from(SalesHead::tableName())
                ->andWhere(['branchID' => $this->branchID])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->andWhere(['DATE(salesDateOut)' => $dailyRecap['salesDate']])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                        $this->startDate, $this->endDate)])
                ->all();

            $sales = [];
            foreach ($salesHeads as $salesHead) {
                $sales[] = [
                    'salesNum' => $salesHead['salesNum'],
                    'billNum' => $salesHead['billNum'],
                    'grandTotal' => (float) $salesHead['grandTotal']
                ];
            }
            $salesDetails[$index] = $dailyRecap;
            $salesDetails[$index]['salesDetails'] = $sales;

            $index++;
        }

        return $salesDetails;
    }

    public function getVoidPaymentPerPaymentMethod() {
        if (!$this->validate()) {
            return false;
        }

        $voidPayments = [];
        // @Notes: statusID 24 = void
        $paymentMethodRecap = (new Query())
            ->select(['paymentMethodTypeID' => 'c.paymentMethodTypeID',
                'a.paymentMethodID', 'paymentMethodName',
                'paymentAmount' => 'SUM(paymentAmount)'])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tablename() . ' c',
                'a.paymentMethodID = c.paymentMethodID')
            ->innerJoin(PaymentMethodType::tablename() . ' d',
                'c.paymentMethodTypeID = d.paymentMethodTypeID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 24])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.paymentMethodTypeID, a.paymentMethodID, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        forEach ($paymentMethodRecap as $paymentRecap) {
            $totalPayment = 0;
            $paymentRecap['paymentMethodTypeID'] = (int) $paymentRecap['paymentMethodTypeID'];
            $paymentRecap['paymentMethodID'] = (int) $paymentRecap['paymentMethodID'];
            // @Notes: paymentMethodTypeID 1 = CASH
            if ($paymentRecap['paymentMethodTypeID'] != 1) {
                $salesPaymentMethod = (new Query())
                    ->select(['a.salesNum', 'b.billNum', 'paymentAmount'])
                    ->from(SalesPayment::tableName() . ' a')
                    ->innerJoin(SalesHead::tableName() . ' b',
                        'a.salesNum = b.salesNum')
                    ->andWhere(['b.branchID' => $this->branchID])
                    ->andWhere(['>=', 'salesDateOut', $this->startDate])
                    ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                    ->andWhere(['paymentMethodID' => $paymentRecap['paymentMethodID']])
                    ->andWhere(['statusID' => 24])
                    ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                            $this->startDate, $this->endDate)])
                    ->orderBy('b.billNum', 'a.salesNum')
                    ->all();

                $salesPaymentMethods = [];
                foreach ($salesPaymentMethod as $salesPayment) {
                    $salesPayment['paymentAmount'] = (float) $salesPayment['paymentAmount'];
                    $salesPaymentMethods[] = $salesPayment;
                }
                $totalPayment = (float) $paymentRecap['paymentAmount'];
            } else {
                $salesPaymentMethod = (new Query())
                    ->select(['a.salesNum', 'a.billNum', 'grandTotal' => '(grandTotal - roundingTotal)',
                        'totalNonCash' => 'SUM(CASE WHEN paymentMethodTypeID <> 1 THEN paymentAmount ELSE 0 END)'])
                    ->from(SalesHead::tableName() . ' a')
                    ->leftJoin(SalesPayment::tableName() . ' b',
                        'a.salesNum = b.salesNum')
                    ->innerJoin(PaymentMethod::tableName() . ' c',
                        'b.paymentMethodID = c.paymentMethodID')
                    ->andWhere(['a.branchID' => $this->branchID])
                    ->andWhere(['>=', 'salesDateOut', $this->startDate])
                    ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                    ->andWhere(['b.paymentMethodID' => $paymentRecap['paymentMethodID']])
                    ->andWhere(['statusID' => 24])
                    ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                            $this->startDate, $this->endDate)])
                    ->groupBy('a.salesNum, a.billNum, grandTotal, roundingTotal')
                    ->orderBy('a.billNum, a.salesNum')
                    ->all();

                $salesPaymentMethods = [];
                foreach ($salesPaymentMethod as $salesPayment) {
                    $totalPayment += (float) ($salesPayment['grandTotal'] - $salesPayment['totalNonCash']);
                    $salesPaymentMethods[] = [
                        'salesNum' => $salesPayment['salesNum'],
                        'billNum' => $salesPayment['billNum'],
                        'paymentAmount' => (float) ($salesPayment['grandTotal'] - $salesPayment['totalNonCash'])
                    ];
                }
            }
            $voidPayments[$index] = $paymentRecap;
            $voidPayments[$index]['paymentAmount'] = (float) $totalPayment;
            $voidPayments[$index]['salesPayment'] = $salesPaymentMethods;

            $index++;
        }

        return $voidPayments;
    }

    public function getPromotionSummary() {
        if (!$this->validate()) {
            return false;
        }

        $promotionSummaries = [];
        // @Notes Sales Head: statusID 8 = Finished
        // @Notes Sales Menu: statusID 13 = Preparing
        $promotionSalesHead = (new Query())
            ->select([
                'a.promotionID',
                'promotionName' => 'CONCAT(b.notes, " (BILL DISCOUNT)")',
                'qty' => 'COUNT(a.salesNum)',
                'discountTotal' => 'SUM(a.discountTotal)'
            ])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(PromotionHead::tableName() . ' b',
                'a.promotionID = b.promotionID')
            ->where(['a.statusID' => 8])
            ->andWhere(['=', 'a.branchID', $this->branchID])
            ->andWhere(['>=', 'a.salesDateOut', $this->startDate])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->andFilterWhere(['<=', 'a.salesDateOut', $this->endDate])
            ->groupBy([
            'a.promotionID', 'b.notes'
        ]);

        $promotionMenuExtra = (new Query())
            ->select([
                'promotionName' => 'b.notes',
                'qty' => 'SUM(
                    CASE
                        WHEN a.id = extra.menuDetailID
                        THEN (extra.qty * a.qty)
                        ELSE 0
                    END
                )',
                'discountTotal' => 'SUM(
                    CASE
                        WHEN (a.id = extra.menuDetailID)
                        THEN (extra.discountValue * a.qty)
                        ELSE 0
                    END
                )'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(PromotionHead::tableName() . ' b',
                'a.promotionDetailID = b.promotionID')
            ->innerJoin(SalesHead::tableName() . ' c', 'a.salesNum = c.salesNum')
            ->leftJoin(SalesMenuExtra::tableName() . ' extra', 'a.ID = extra.menuDetailID')
            ->where([
                'c.statusID' => 8
            ])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['=', 'c.branchID', $this->branchID])
            ->andWhere(['>=', 'c.salesDateOut', $this->startDate])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->andFilterWhere(['<=', 'c.salesDateOut', $this->endDate])
            ->groupBy([
            'a.promotionDetailID', 'b.notes'
        ])
        ->all();

        $promotionSalesMenu = (new Query())
            ->select([
                'promotionID' => 'a.promotionDetailID',
                'promotionName' => 'b.notes',
                'qty' => 'SUM(a.qty)',
                // 'discountTotal' => 'SUM(a.qty * a.originalPrice * IF(a.discount > 0, a.discount / 100, 1))'
                'discountTotal' => 'COALESCE(
                    SUM(CASE WHEN (a.menuGroupID = 0) THEN a.discountValue ELSE 0 END) +
                    SUM((CASE WHEN (a.menuRefID = head.ID) THEN a.discountValue ELSE 0 END) * COALESCE(head.qty, 1))
                )'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(PromotionHead::tableName() . ' b',
                'a.promotionDetailID = b.promotionID')
            ->innerJoin(SalesHead::tableName() . ' c', 'a.salesNum = c.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' head', 'a.salesNum = head.salesNum
                AND a.menuRefID = head.localID
                AND a.menuGroupID > 0'
            )
            ->where([
                'c.statusID' => 8
            ])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['=', 'c.branchID', $this->branchID])
            ->andWhere(['>=', 'c.salesDateOut', $this->startDate])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->andFilterWhere(['<=', 'c.salesDateOut', $this->endDate])
            ->groupBy([
            'a.promotionDetailID', 'b.notes'
        ]);

        $promotionMenuDetail = (new Query())
            ->select([
                'promotionID' => 'a.menuPromotionID',
                'promotionName' => 'c.notes',
                'qty' => 'SUM(a.qty)',
                // 'discountTotal' => 'SUM(a.qty * a.price * IF(a.discount > 0, a.discount / 100, 1))'
                'discountTotal' => 'COALESCE(
                    SUM(CASE WHEN (a.menuGroupID = 0) THEN a.discountValue ELSE 0 END) + 
                    SUM((CASE WHEN (a.menuRefID = head.ID) THEN a.discountValue ELSE 0 END) * COALESCE(head.qty, 1)) 
                )'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(MenuPromotion::tableName() . ' b',
                'a.menuPromotionID = b.menuPromotionID')
            ->innerJoin(MenuPromotionHead::tableName() . ' c', 'b.headID = c.ID')
            ->innerJoin(SalesHead::tableName() . ' d', 'a.salesNum = d.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' head', 'a.salesNum = head.salesNum
                AND a.menuRefID = head.localID
                AND a.menuGroupID > 0'
            )
            ->where([
                'd.statusID' => 8
            ])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['=', 'd.branchID', $this->branchID])
            ->andWhere(['>=', 'd.salesDateOut', $this->startDate])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->andFilterWhere(['<=', 'd.salesDateOut', $this->endDate])
            ->groupBy([
            'a.menuPromotionID', 'c.notes'
        ]);

        $promotionQuery = (new Query())
            ->select([
                'unionPromotion.promotionName',
                'unionPromotion.qty',
                'unionPromotion.discountTotal'
            ])
            ->from([
                'unionPromotion' => $promotionSalesHead->union($promotionSalesMenu,
                    true)->union($promotionMenuDetail, true)
            ])
            ->orderBy('unionPromotion.promotionName ASC')
            ->all();

        for ($i=0; $i<count($promotionQuery); $i++) {
            if ($promotionMenuExtra) {
                foreach ($promotionMenuExtra as $menuExtra) {
                    if ($promotionQuery[$i]['promotionName'] == $menuExtra['promotionName']) {
                        $promotionQuery[$i]['qty'] += $menuExtra['qty'];
                        $promotionQuery[$i]['discountTotal'] += $menuExtra['discountTotal'];
                    }
                }
            }

            $promotionQuery[$i]['qty'] = (int) $promotionQuery[$i]['qty'];
            $promotionQuery[$i]['discountTotal'] = (float) $promotionQuery[$i]['discountTotal'];
            $promotionSummaries[] = $promotionQuery[$i];
        }

        return $promotionSummaries;
    }

    public function getCancelledMenuSummary() {
        if (!$this->validate()) {
            return false;
        }

        $cancelledMenuSummary = [];
        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenuQuery = (new Query())
            ->select([
                'salesNum' => 'COALESCE(b.billNum, b.salesNum)',
                'cancelTotal' => 'SUM(a.qty * a.price)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['NOT IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy(new Expression('COALESCE(b.billNum, b.salesNum)'))
            ->orderBy(new Expression('COALESCE(b.billNum, b.salesNum)'))
            ->all();
        foreach ($salesMenuQuery as $sales) {
            $sales['cancelTotal'] = (int) $sales['cancelTotal'];
            $cancelledMenuSummary[] = $sales;
        }

        return $cancelledMenuSummary;
    }

    public function getSalesMenuPackage() {

        if (!$this->validate()) {
            return false;
        }

        $datas = [];
        $queryPackage = (new Query())
            ->select([
                'packageID' => 'b.menuID',
                'packageName' => 'd.menuName',
                'packageQtyTotal' => 'SUM(b.qty)'
            ])
            ->from(SalesMenu::tableName() . ' a')//menu
            ->innerJoin(SalesMenu::tableName() . ' b',
                'a.salesNum = b.salesNum and a.menuRefID = b.localID')//paket
            ->innerJoin(SalesHead::tablename() . ' c', 'a.salesNum = c.salesNum')
            ->innerJoin(Menu::tablename() . ' d', 'b.menuID = d.menuID')//paket
            ->innerJoin(Menu::tablename() . ' e', 'a.menuID = e.menuID')//menu
            ->andWhere('a.menuGroupID = 0')
            ->andWhere(['c.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->groupBy('b.menuID, d.menuName')
            ->all();
        foreach ($queryPackage as $package) {

            $queryMenu = (new Query())
                ->select([
                    'menuID' => 'a.menuID',
                    'menuName' => 'e.menuName',
                    'menuQtyTotal' => '(a.qty * b.qty)',
                ])
                ->from(SalesMenu::tableName() . ' a')//menu
                ->innerJoin(SalesMenu::tableName() . ' b',
                    'a.salesNum = b.salesNum and a.menuRefID = b.localID and b.statusID IN (13 , 14, 34)')//paket
                ->innerJoin(SalesHead::tablename() . ' c',
                    'a.salesNum = c.salesNum')
                ->innerJoin(Menu::tablename() . ' d', 'b.menuID = d.menuID')//paket
                ->innerJoin(Menu::tablename() . ' e', 'a.menuID = e.menuID')//menu
                ->andWhere('a.menuGroupID > 0')
                ->andWhere(['c.statusID' => 8])
                ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
                ->andWhere(['=', 'b.menuid', $package['packageID']])
                ->andWhere(['>=', 'salesDateOut', $this->startDate])
                ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
                ->all();

            $keyPackageID = $package['packageID'];

            $group = [
                'packageID' => $package['packageID'],
                'packageName' => $package['packageName'],
                'packageQtyTotal' => (float)$package['packageQtyTotal'],
                //'menus' => $queryMenu
                'menus' => array_key_exists($keyPackageID, $datas) ? $datas[$keyPackageID]['menus'] : []
            ];

            $datas[$keyPackageID] = $group;

            foreach ($queryMenu as $menu) {
                $menuKey = $menu['menuID'];

                if (!array_key_exists($menuKey, $datas[$keyPackageID]['menus'])) {
                    $datas[$keyPackageID]['menus'][$menuKey] = [
                        'menuID' => $menu['menuID'],
                        'menuName' => $menu['menuName'],
                        'menuQtyTotal' => 0,
                    ];
                }
                $datas[$keyPackageID]['menus'][$menuKey]['menuQtyTotal'] += (float)$menu['menuQtyTotal'];
            }
        }
        return array_values($datas);
    }

    public function getSalesMenuByCategory() {
        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryDesc',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'qty' => new Expression('SUM(a.qty)')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['a.statusID' => 13])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'e.menuCategoryDesc',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc')
            ->all();

        $data = [];
        foreach ($salesMenu as $sales) {
            $key = $sales['menuCategoryDetailDesc'];
            $qty = $sales['qty'];
            $group = [
                'menuCategoryDetailDesc' => $key,
                'menuCategoryDesc' => $sales['menuCategoryDesc'],
                'subTotalQty' => (array_key_exists($key, $data) ? $data[$key]['subTotalQty'] : 0) + $qty,
                'menus' => array_key_exists($key, $data) ? $data[$key]['menus'] : []
            ];

            $data[$key] = $group;

            $i = 0;
            if (in_array($key, $data)) {
                $data[$key]['menus'][$i]['subTotalQty'] = $data[$key]['menus'][$i]['subTotalQty'] + $qty;
            } else {
                $data[$key]['menus'][] = [
                    'menuName' => $sales['menuName'],
                    'qty' => $qty,
                ];
            }
        }

        $realData = [];
        foreach ($data as $obj) {
            //$realData[$obj['menuCategoryDetailDesc']]['menuCategoryDetailDesc'] = $obj['menuCategoryDetailDesc'];
            $realData[$obj['menuCategoryDesc']]['categorys'][] = $obj;
        }

        return $realData;
    }

    public function getSalesByMenuQtyValue() {
        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'menuCategoryDetailID' => new Expression('d.ID'),
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'price' => new Expression('SUM(a.qty * COALESCE(z.qty,1) * a.price)'),
                'qty' => new Expression('SUM(a.qty * COALESCE(z.qty,1))')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum AND a.menuGroupID > 0')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ]);

        $salesMenuExtra = (new Query())
            ->select([
                'menuCategoryID' => new Expression('0'),
                'menuCategoryDesc' => new Expression('"Menu Extra"'),
                'menuCategoryDetailID' => new Expression('0'),
                'menuCategoryDetailDesc' => new Expression('"Menu Extra"'),
                'menuID' => new Expression('d.menuExtraID'),
                'menuName' => new Expression('d.menuExtraName'),
                'price' => 'SUM(c.qty * a.qty * a.price)', 
                'qty' => 'SUM(c.qty * a.qty)',
            ])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.menuExtraID, d.menuExtraName')
            ->orderBy('d.menuExtraName');

        $salesReport = (new Query())
            ->select('*')
            ->from(['sales' => $salesMenu->union($salesMenuExtra, true)])
            ->orderBy([
                'menuCategoryDesc' => SORT_ASC, 
                'menuCategoryDetailDesc' => SORT_ASC, 
                'menuName' => SORT_ASC
            ])
            ->all();

        $menuCategoryArr = [];
        $menuCategoryIDs = [];
        $menuCategoryDetailIDs = [];
        foreach($salesReport as $sales){
            $menuCategoryID = $sales['menuCategoryID'];
            $menuCategoryDesc = $sales['menuCategoryDesc'];
            $menuCategoryDetailID = $sales['menuCategoryDetailID'];
            $menuCategoryDetailDesc = $sales['menuCategoryDetailDesc'];
            $menuID = $sales['menuID'];
            $menuName = $sales['menuName'];
            $qty = (float)$sales['qty'];
            $price = (float)$sales['price'];

            $isNewMenuCategory = false;
            if(!in_array($menuCategoryID, $menuCategoryIDs)){
                array_push($menuCategoryIDs, $menuCategoryID);
                $menuCategoryArr[] = [
                    "menuCategoryID" => $menuCategoryID,
                    "menuCategoryDesc" => $menuCategoryDesc,
                    "summaryCategoryQty" => (float)$qty,
                    "summaryCategoryPrice" => (float)$price,
                    "categoryDetails" => []
                ];
                $isNewMenuCategory = true;
            }
            
            $menuCategoryKey = self::searchArrayKey('menuCategoryID', $menuCategoryID, $menuCategoryArr);
            if(!$isNewMenuCategory){
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryPrice'] += (float)$price;
            }

            $isNewMenuCategoryDetail = false;
            if(!in_array($menuCategoryDetailID, $menuCategoryDetailIDs)){
                array_push($menuCategoryDetailIDs, $menuCategoryDetailID);
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][] = [
                    "menuCategoryDetailID" => $menuCategoryDetailID,
                    "menuCategoryDetailDesc" => $menuCategoryDetailDesc,
                    "subTotalQty" => (float)$qty,
                    "subTotalPrice" => (float)$price,
                    "menus" => []
                ];
                $isNewMenuCategoryDetail = true;
            }

            $menuCategoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $menuCategoryDetailID, $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails']);
            if(!$isNewMenuCategoryDetail){
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalPrice'] += (float)$price;
            }

            $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['menus'][] = [
                "menuID" => $menuID,
                "menuName" => $menuName,
                "qty" => (float)$qty,
                "price" => (float)$price
            ];
        }

        return $menuCategoryArr;
    }
    
    public function getSalesByMenuQty() {
        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'menuCategoryDetailID' => new Expression('d.ID'),
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'price' => new Expression('SUM(a.qty * COALESCE(z.qty,1) * a.price)'),
                'qty' => new Expression('SUM(a.qty * COALESCE(z.qty,1))')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum AND a.menuGroupID > 0')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc, c.menuName')
            ->all();

        $menuCategoryArr = [];
        $menuCategoryIDs = [];
        $menuCategoryDetailIDs = [];
        foreach($salesMenu as $sales){
            $menuCategoryID = $sales['menuCategoryID'];
            $menuCategoryDesc = $sales['menuCategoryDesc'];
            $menuCategoryDetailID = $sales['menuCategoryDetailID'];
            $menuCategoryDetailDesc = $sales['menuCategoryDetailDesc'];
            $menuID = $sales['menuID'];
            $menuName = $sales['menuName'];
            $qty = (float) $sales['qty'];
            $price = (float) $sales['price'];

            $isNewMenuCategory = false;
            if(!in_array($menuCategoryID, $menuCategoryIDs)){
                array_push($menuCategoryIDs, $menuCategoryID);
                $menuCategoryArr[] = [
                    "menuCategoryID" => $menuCategoryID,
                    "menuCategoryDesc" => $menuCategoryDesc,
                    "summaryCategoryQty" => (float) $qty,
                    "summaryCategoryPrice" => (float) $price,
                    "categoryDetails" => []
                ];
                $isNewMenuCategory = true;
            }
            
            $menuCategoryKey = self::searchArrayKey('menuCategoryID', $menuCategoryID, $menuCategoryArr);
            if(!$isNewMenuCategory){
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryQty'] += (float) $qty;
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryPrice'] += (float) $price;
            }

            $isNewMenuCategoryDetail = false;
            if(!in_array($menuCategoryDetailID, $menuCategoryDetailIDs)){
                array_push($menuCategoryDetailIDs, $menuCategoryDetailID);
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][] = [
                    "menuCategoryDetailID" => $menuCategoryDetailID,
                    "menuCategoryDetailDesc" => $menuCategoryDetailDesc,
                    "subTotalQty" => (float) $qty,
                    "subTotalPrice" => (float) $price,
                    "menus" => []
                ];
                $isNewMenuCategoryDetail = true;
            }

            $menuCategoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $menuCategoryDetailID, $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails']);
            if(!$isNewMenuCategoryDetail){
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalQty'] += (float) $qty;
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalPrice'] += (float) $price;
            }

            $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['menus'][] = [
                "menuID" => $menuID,
                "menuName" => $menuName,
                "qty" => (float) $qty,
                "price" => (float) $price
            ];
        }

        return $menuCategoryArr;
    }

    public function getNonSalesByMenu() {
        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'menuCategoryDetailID' => new Expression('d.ID'),
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'price' => new Expression('SUM(a.qty * COALESCE(z.qty,1) * a.price)'),
                'qty' => new Expression('SUM(a.qty * COALESCE(z.qty,1))')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum and a.menuGroupID > 0')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc');

        $salesMenuExtra = (new Query())
            ->select([
                'menuCategoryID' => new Expression('0'),
                'menuCategoryDesc' => new Expression('"Menu Extra"'),
                'menuCategoryDetailID' => new Expression('0'),
                'menuCategoryDetailDesc' => new Expression('"Menu Extra"'),
                'menuID' => new Expression('d.menuExtraID'),
                'menuName' => new Expression('d.menuExtraName'),
                'price' => 'SUM(c.qty * a.qty * a.price)', 
                'qty' => 'SUM(c.qty * a.qty)',
            ])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.menuExtraID, d.menuExtraName')
            ->orderBy('d.menuExtraName');

        $salesReport = $salesMenu->union($salesMenuExtra,true)->all();

        $menuCategoryArr = [];
        $menuCategoryIDs = [];
        $menuCategoryDetailIDs = [];
        foreach($salesReport as $sales){
            $menuCategoryID = $sales['menuCategoryID'];
            $menuCategoryDesc = $sales['menuCategoryDesc'];
            $menuCategoryDetailID = $sales['menuCategoryDetailID'];
            $menuCategoryDetailDesc = $sales['menuCategoryDetailDesc'];
            $menuID = $sales['menuID'];
            $menuName = $sales['menuName'];
            $qty = (float)$sales['qty'];
            $price = (float)$sales['price'];

            $isNewMenuCategory = false;
            if(!in_array($menuCategoryID, $menuCategoryIDs)){
                array_push($menuCategoryIDs, $menuCategoryID);
                $menuCategoryArr[] = [
                    "menuCategoryID" => $menuCategoryID,
                    "menuCategoryDesc" => $menuCategoryDesc,
                    "summaryCategoryQty" => (float)$qty,
                    "summaryCategoryPrice" => (float)$price,
                    "categoryDetails" => []
                ];
                $isNewMenuCategory = true;
            }
            
            $menuCategoryKey = self::searchArrayKey('menuCategoryID', $menuCategoryID, $menuCategoryArr);
            if(!$isNewMenuCategory){
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryPrice'] += (float)$price;
            }

            $isNewMenuCategoryDetail = false;
            if(!in_array($menuCategoryDetailID, $menuCategoryDetailIDs)){
                array_push($menuCategoryDetailIDs, $menuCategoryDetailID);
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][] = [
                    "menuCategoryDetailID" => $menuCategoryDetailID,
                    "menuCategoryDetailDesc" => $menuCategoryDetailDesc,
                    "subTotalQty" => (float)$qty,
                    "subTotalPrice" => (float)$price,
                    "menus" => []
                ];
                $isNewMenuCategoryDetail = true;
            }

            $menuCategoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $menuCategoryDetailID, $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails']);
            if(!$isNewMenuCategoryDetail){
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalPrice'] += (float)$price;
            }

            $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['menus'][] = [
                "menuID" => $menuID,
                "menuName" => $menuName,
                "qty" => (float)$qty,
                "price" => (float)$price
            ];
        }
        return $menuCategoryArr;
    }

    public function getNonSalesBillSummary() {
        if (!$this->validate()) {
            return false;
        }

        $nonSalesBillSummary = [];
        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenuQuery = (new Query())
            ->select([
                'salesNum' => 'COALESCE(a.billNum, a.salesNum)',
                'grandTotal' => 'SUM(a.grandTotal)'
            ])
            ->from(SalesHead::tableName() . ' a')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>=', 'a.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'a.salesDateOut', $this->endDate])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy(new Expression('COALESCE(a.billNum, a.salesNum)'))
            ->orderBy(new Expression('COALESCE(a.billNum, a.salesNum)'))
            ->all();
        foreach ($salesMenuQuery as $sales) {
            $sales['grandTotal'] = (int) $sales['grandTotal'];
            $nonSalesBillSummary[] = $sales;
        }

        return $nonSalesBillSummary;
    }

    public function getNonSalesMenuSummary() {
        if (!$this->validate()) {
            return false;
        }

        $nonSalesMenus = [];
        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenu = (new Query())
            ->select([
                'c.menuName',
                'qty' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END))',
                'subtotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)',
                //'menuDiscountTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.discount / 100))',
                'menuDiscountTotal' => 'SUM(a.discountValue)',
                'otherTaxTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.otherTax / 100))',
                'vatTotal' => 'SUM(CASE WHEN a.otherTaxOnVat = 1 THEN (((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)* a.price) '
                . '+ ((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.otherTax / 100))) * (a.vat / 100) '
                . 'ELSE (CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price * (a.vat / 100) END)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('c.menuName')
            ->orderBy('c.menuName')
            ->all();
        foreach ($salesMenu as $sales) {
            $nonSalesMenus[] = [
                'description' => $sales['menuName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'grandTotal' => (float) ($sales['subtotal'] - $sales['menuDiscountTotal'] + $sales['otherTaxTotal'] + $sales['vatTotal'])
            ];
        }

        // @Notes: statusID 12 = Cancelled, 13 = Preparing, 24 = Void
        $salesMenuExtra = (new Query())
            ->select(['d.menuExtraName', 'qty' => 'SUM(c.qty * a.qty)',
                'subtotal' => 'SUM(c.qty * a.qty * a.price)', 'menuDiscountTotal' => 'SUM(c.qty * a.qty * a.price * (a.discount / 100))',
                'otherTaxTotal' => 'SUM(a.qty * a.price * (a.otherTax / 100))',
                'vatTotal' => 'SUM(CASE WHEN a.otherTaxOnVat = 1 THEN ((c.qty * a.qty * a.price) + (c.qty * a.qty * a.price * (a.otherTax / 100))) * (a.vat / 100) '
                . 'ELSE c.qty * a.qty * a.price * (a.vat / 100) END)'])
            ->from(SalesMenuExtra::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(SalesMenu::tableName() . ' c', 'a.menuDetailID = c.ID')
            ->innerJoin(MenuExtra::tableName() . ' d',
                'a.menuExtraID = d.menuExtraID')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy('d.menuExtraName')
            ->orderBy('d.menuExtraName')
            ->all();
        foreach ($salesMenuExtra as $sales) {
            $nonSalesMenus[] = [
                'description' => $sales['menuExtraName'],
                'qty' => (float) $sales['qty'],
                'subtotal' => (float) $sales['subtotal'],
                'menuDiscountTotal' => (float) $sales['menuDiscountTotal'],
                'otherTaxTotal' => (float) $sales['otherTaxTotal'],
                'vatTotal' => (float) $sales['vatTotal'],
                'grandTotal' => (float) ($sales['subtotal'] - $sales['menuDiscountTotal'] + $sales['otherTaxTotal'] + $sales['vatTotal'])
            ];
        }

        return $nonSalesMenus;
    }

    public function getSalesByMode() {

        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $sales = (new Query())
            ->select([
                'b.visitPurposeName',
                'totalBill' => new Expression('COUNT(a.billNum)'),
                'subTotal' => new Expression('SUM(a.subtotal)')
            ])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(VisitPurpose::tableName() . ' b',
                'b.visitPurposeID = a.visitPurposeID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['a.statusID' => 8])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'b.visitPurposeName'
            ])
            ->orderBy('b.visitPurposeName')
            ->all();

        return $sales;
    }

    public function getSalesMenuByMode() {

        if (!$this->validate()) {
            return false;
        }
        $salesMenuPerVisitPurpose = [];

        $salesMenu = (new Query())
            ->select([
                'd.visitPurposeName',
                'c.menuName',
                'qty' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END))',
                'subTotal' => 'SUM((CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price)'
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(VisitPurpose::tableName() . ' d',
                'b.visitPurposeID = d.visitPurposeID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'b.visitPurposeID',
                'a.menuID'
            ])
            ->orderBy('d.visitPurposeName, c.menuName')
            ->all();

        $data = [];
        foreach ($salesMenu as $sales) {
            $key = $sales['visitPurposeName'];
            $group = [
                'menuName' => $sales['menuName'],
                'qty' => (float) $sales['qty'],
                'subTotal' => (float) $sales['subTotal'],
            ];

            if (isset($data[$key])) {
                array_push($data[$key], $group);
            } else {
                $data[$key] = array($group);
            }
        }

        return $data;
    }

    public function getSalesVoucherUsage() {

        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $sales = (new Query())
            ->select([
                'a.salesNum',
                'b.paymentMethodName',
                'a.voucherAmount'
            ])
            ->from(SalesVoucherUsage::tableName() . ' a')
            ->innerJoin(PaymentMethod::tableName() . ' b',
                'a.paymentMethodID = b.paymentMethodID')
            ->innerJoin(SalesHead::tableName() . ' c', 'a.salesNum = c.salesNum')
            ->andWhere(['c.branchID' => $this->branchID])
            ->andWhere(['>=', 'c.salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'c.salesDateOut', $this->endDate])
            ->andWhere(['c.statusID' => 8])
            ->all();

        return $sales;
    }

    public function getSalesMenuGroup() {
        if (!$this->validate()) {
            return false;
        }
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'menuCategoryDetailID' => new Expression('d.ID'),
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'qty' => new Expression('SUM(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)'),
                'value' => new Expression('SUM(CASE WHEN a.menuGroupID = 0 THEN a.price * a.qty ELSE a.price * a.qty * z.qty END)')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d',
                'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e',
                'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc, c.menuName')
            ->all();

        $menuCategoryArr = [];
        $menuCategoryIDs = [];
        $menuCategoryDetailIDs = [];
        foreach($salesMenu as $sales){
            $menuCategoryID = $sales['menuCategoryID'];
            $menuCategoryDesc = $sales['menuCategoryDesc'];
            $menuCategoryDetailID = $sales['menuCategoryDetailID'];
            $menuCategoryDetailDesc = $sales['menuCategoryDetailDesc'];
            $menuID = $sales['menuID'];
            $menuName = $sales['menuName'];
            $qty = (float)$sales['qty'];
            $value = (float)$sales['value'];

            $isNewMenuCategory = false;
            if(!in_array($menuCategoryID, $menuCategoryIDs)){
                array_push($menuCategoryIDs, $menuCategoryID);
                $menuCategoryArr[] = [
                    "menuCategoryID" => $menuCategoryID,
                    "menuCategoryDesc" => $menuCategoryDesc,
                    "summaryCategoryQty" => (float)$qty,
                    "summaryCategoryValue" => (float)$value,
                    "categoryDetails" => []
                ];
                $isNewMenuCategory = true;
            }
            
            $menuCategoryKey = self::searchArrayKey('menuCategoryID', $menuCategoryID, $menuCategoryArr);
            if(!$isNewMenuCategory){
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['summaryCategoryValue'] += (float)$value;
            }

            $isNewMenuCategoryDetail = false;
            if(!in_array($menuCategoryDetailID, $menuCategoryDetailIDs)){
                array_push($menuCategoryDetailIDs, $menuCategoryDetailID);
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][] = [
                    "menuCategoryDetailID" => $menuCategoryDetailID,
                    "menuCategoryDetailDesc" => $menuCategoryDetailDesc,
                    "subTotalQty" => (float)$qty,
                    "subTotalValue" => (float)$value,
                    "menus" => []
                ];
                $isNewMenuCategoryDetail = true;
            }

            $menuCategoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $menuCategoryDetailID, $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails']);
            if(!$isNewMenuCategoryDetail){
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalQty'] += (float)$qty;
                $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['subTotalValue'] += (float)$value;
            }

            $menuCategoryArr[$menuCategoryKey]['menuCategoryDetails'][$menuCategoryDetailKey]['menus'][] = [
                "menuID" => $menuID,
                "menuName" => $menuName,
                "qty" => (float)$qty,
                "value" => (float)$value
            ];
        }
        return $menuCategoryArr;
    }

    public function getSalesByVisitPurpose() {
        if (!$this->validate()) {
            return false;
        }

        $salesByVisitPurposeModel = [];
        $salesByVisitPurposeQuery = SalesHead::find()
            ->select([
                'tr_saleshead.branchID',
                'tr_saleshead.visitPurposeID',
                'subtotal' => new Expression('sum(subtotal - discountTotal - menuDiscountTotal)'),
                'visitBillTotal' => new Expression('count(salesNum)')
            ])
            ->joinWith('visitPurpose')
            ->andWhere(['branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['NOT IN', 'salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'tr_saleshead.branchID',
                'tr_saleshead.visitPurposeID'
            ])
            ->orderBy('ms_visitpurpose.visitPurposeName ASC')
            ->all();

        foreach ($salesByVisitPurposeQuery as $detailVisit) {
            $salesByVisitPurposeModel[] = [
                'branchID' => $detailVisit->branchID,
                'visitPurposeID' => $detailVisit->visitPurposeID,
                'visitPurposeName' => $detailVisit->visitPurpose->visitPurposeName,
                'visitBillTotal' => (int) $detailVisit->visitBillTotal,
                'subtotal' => (float) $detailVisit->subtotal
            ];
        }

        return $salesByVisitPurposeModel;
    } 

    public function getSpecialPriceSummary() {
        if (!$this->validate()) {
            return false;
        }

        // @Notes: statusID 8 = Finished, 13 = Preparing
        $subQuery = (new Query())
            ->select([
                'a.menuID',
                'c.menuName',
                'originalPrice' => new Expression('CASE WHEN a.menuGroupID = 0 THEN a.originalPrice ELSE a.originalPrice * z.qty END'),
                'specialPrice' => new Expression('CASE WHEN a.menuGroupID = 0 THEN a.price ELSE a.price * z.qty END'),
                'qty' => new Expression('SUM(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->where('a.originalPrice <> a.price')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['<>', 'a.originalPrice', 0])
            ->andWhere(['>', 'a.price', 0])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->groupBy([
                'a.menuID', 
                'a.menuGroupID', 
                'z.qty', 
                'a.originalPrice', 
                'a.price'
            ])
            ->orderBy('c.menuName');

        $specialPriceQuery = (new Query)
            ->select([
                'subQuery.menuID',
                'subQuery.menuName',
                'originalPrice' => new Expression('SUM(subQuery.originalPrice)'),
                'specialPrice' => new Expression('SUM(subQuery.specialPrice)'),
                'qty' => new Expression('SUM(subQuery.qty)'),
                'specialPriceTotal' => new Expression('(SUM(subQuery.originalPrice) - SUM(subQuery.specialPrice)) * SUM(subQuery.qty)')
            ])
            ->from(["subQuery" => $subQuery])
            ->groupBy([
                'subQuery.menuID',
                'subQuery.menuName',
                'subQuery.originalPrice',
                'subQuery.specialPrice'
            ])
            ->orderBy('subQuery.menuName')
            ->all();

        $salesMenus = [];
        foreach ($specialPriceQuery as $sales) {
            $salesMenus[] = [
                'menuName' => $sales['menuName'],
                'qty' => (float) $sales['qty'],
                'value' => (float) $sales['specialPriceTotal']
            ];
        }

        return $salesMenus;
    }
    
    public function getCustomMenuSales() {
        if (!$this->validate()) {
            return false;
        }

        $customMenus = [];
        $salesMenu = (new Query())
            ->select([
                'b.salesNum',
                'a.customMenuName',
                'qty' => '(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END)',
                'value' => '(CASE WHEN a.menuGroupID = 0 THEN a.qty ELSE a.qty * z.qty END) * a.price',
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(PosUser::tableName() . ' d', 'a.editedBy = d.username')
            ->leftJoin(SalesMenu::tableName() . ' z',
                'a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['>=', 'salesDateOut', $this->startDate])
            ->andWhere(['<>', 'a.customMenuName', ""])
            ->andFilterWhere(['<=', 'salesDateOut', $this->endDate])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['IN', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,
                    $this->startDate, $this->endDate)])
            ->orderBy('a.customMenuName, b.salesNum')
            ->all();
        foreach ($salesMenu as $sales) {
            $sales['qty'] = (float) $sales['qty'];
            $customMenus[] = $sales;
        }
        return $customMenus;
    }

    public function getDepositWithdrawalPerPaymentMethod() {
        if (!$this->validate()) {
            return false;
        }

        $memberDepositWithdrawals = [];
        $depositWithdrawalPaymentRecap = (new Query())
            ->select(['paymentMethodTypeID', 'a.paymentMethodID', 'paymentMethodName',
                'paymentAmount' => 'COALESCE(SUM(withdrawalTotal), 0)'])
            ->from(DepositWithdrawalHead::tableName() . ' a')
            ->innerJoin(PaymentMethod::tableName() . ' b',
                'a.paymentMethodID = b.paymentMethodID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['>', 'a.createdDate', $this->startDate])
            ->andFilterWhere(['<', 'a.createdDate', $this->endDate])
            ->groupBy('paymentMethodTypeID, a.paymentMethodID, paymentMethodName')
            ->orderBy('paymentMethodName')
            ->all();

        $index = 0;
        foreach ($depositWithdrawalPaymentRecap as $depositWithdrawalRecap) {
            $depositWithdrawalRecap['paymentMethodTypeID'] = (int) $depositWithdrawalRecap['paymentMethodTypeID'];
            $depositWithdrawalRecap['paymentAmount'] = (float) $depositWithdrawalRecap['paymentAmount'];
            $memberDepositWithDrawal = (new Query())
                ->select(['depositWithdrawalNum', 'withdrawalTotal'])
                ->from(DepositWithdrawalHead::tableName())
                ->andWhere(['branchID' => $this->branchID])
                ->andWhere(['>', 'createdDate', $this->startDate])
                ->andFilterWhere(['<', 'createdDate', $this->endDate])
                ->andWhere(['paymentMethodID' => (int) $depositWithdrawalRecap['paymentMethodID']])
                ->orderBy('depositWithdrawalNum')
                ->all();

            $memberDepositWithdrawals[$index] = $depositWithdrawalRecap;
            foreach ($memberDepositWithDrawal as $withdrawal) {
                $withdrawal['withdrawalTotal'] = (float) $withdrawal['withdrawalTotal'];
                $memberDepositWithdrawals[$index]['depositWithdrawal'][] = $withdrawal;
            }
            $index++;
        }

        return $memberDepositWithdrawals;
    }

    public function getDailyMemberSummary(){
        $MemberDepositWithdrawalOnline = new MemberDepositWithdrawalOnline();
        return $MemberDepositWithdrawalOnline->getAllOutstandingDeposit();
    }

    public function getBranchMenu() {
        if (!$this->validate()) {
            return false;
        }

        $branchMenuData = (new Query())
            ->select([
                'menuCategoryID' => 'e.menuCategoryID',
                'menuCategoryDesc' => 'e.menuCategoryDesc',
                'menuCategoryDetailID' => 'd.ID',
                'menuCategoryDetailDesc' => 'd.menuCategoryDetailDesc',
                'menuName' => 'b.menuName',
                'qty' => 'a.qty'
            ])
            ->from(BranchMenu::tableName() . ' a')
            ->innerJoin(Menu::tableName() . ' b', 'a.menuID = b.menuID AND b.flagActive = 1')
            ->innerJoin(MenuTemplateDetail::tableName() . ' c', 'c.menuID = b.menuID AND c.flagActive = 1')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d', 'd.ID = b.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e', 'e.menuCategoryID = d.menuCategoryID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['b.flagActive' => 1])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'b.menuName',
                'a.qty'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc')
            ->all();
            
        $branchMenuArr = [];
        $categoryDetailIDs = [];
        foreach($branchMenuData as $data) {
            $categoryID = $data['menuCategoryID'];
            $categoryDesc = $data['menuCategoryDesc'];
            $categoryDetailID = $data['menuCategoryDetailID'];
            $categoryDetailDesc = $data['menuCategoryDetailDesc'];
            
            if(!isset($branchMenuArr[$categoryID])) {
                $branchMenuArr[$categoryID] = [
                    'menuCategoryID' => $categoryID,
                    'menuCategoryDesc' => $categoryDesc,
                    'menuCategoryDetails' => []
                ];
            }
            if(!in_array($categoryDetailID, $categoryDetailIDs)) {
                array_push($categoryDetailIDs, $categoryDetailID);
                $branchMenuArr[$categoryID]['menuCategoryDetails'][] = [
                    'menuCategoryDetailID' => $categoryDetailID,
                    'menuCategoryDetailDesc' => $categoryDetailDesc,
                    'menus' => []
                ];
            }
            $categoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $categoryDetailID, $branchMenuArr[$categoryID]['menuCategoryDetails']);
            $branchMenuArr[$categoryID]['menuCategoryDetails'][$categoryDetailKey]['menus'][] = [
                'menuName' => $data['menuName'],
                'qty' => $data['qty']
            ];
        }
        $branchMenuArr = array_values($branchMenuArr);
        
        return $branchMenuArr;
    }

    public function getBranchMenuReadyToSale() {
        if (!$this->validate()) {
            return false;
        }

        $branchMenuData = (new Query())
            ->select([
                'menuCategoryID' => 'e.menuCategoryID',
                'menuCategoryDesc' => 'e.menuCategoryDesc',
                'menuCategoryDetailID' => 'd.ID',
                'menuCategoryDetailDesc' => 'd.menuCategoryDetailDesc',
                'menuName' => 'b.menuName',
                'qty' => 'FLOOR(a.qty/f.convertionQty)'
            ])
            ->from(BranchMenu::tableName() . ' a')
            ->innerJoin(Menu::tableName() . ' b', 'a.menuID = b.menuID AND b.flagActive = 1')
            ->innerJoin(MenuTemplateDetail::tableName() . ' c', 'c.menuID = b.menuID AND c.flagActive = 1')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d', 'd.ID = b.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e', 'e.menuCategoryID = d.menuCategoryID')
            ->innerJoin(ProductDetailMenu::tableName() . ' f', 'f.menuID = b.menuID')
            ->andWhere(['a.branchID' => $this->branchID])
            ->andWhere(['b.flagActive' => 1])
            ->groupBy([
                'e.menuCategoryID',
                'e.menuCategoryDesc',
                'd.ID',
                'd.menuCategoryDetailDesc',
                'b.menuName',
                'a.qty',
                'f.convertionQty'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc, b.menuName')
            ->all();
            Yii::warning('masuk');
        $branchMenuArr = [];
        $categoryDetailIDs = [];
        foreach($branchMenuData as $data) {
            $categoryID = $data['menuCategoryID'];
            $categoryDesc = $data['menuCategoryDesc'];
            $categoryDetailID = $data['menuCategoryDetailID'];
            $categoryDetailDesc = $data['menuCategoryDetailDesc'];
            
            if(!isset($branchMenuArr[$categoryID])) {
                $branchMenuArr[$categoryID] = [
                    'menuCategoryID' => $categoryID,
                    'menuCategoryDesc' => $categoryDesc,
                    'menuCategoryDetails' => []
                ];
            }
            if(!in_array($categoryDetailID, $categoryDetailIDs)) {
                array_push($categoryDetailIDs, $categoryDetailID);
                $branchMenuArr[$categoryID]['menuCategoryDetails'][] = [
                    'menuCategoryDetailID' => $categoryDetailID,
                    'menuCategoryDetailDesc' => $categoryDetailDesc,
                    'menus' => []
                ];
            }
            $categoryDetailKey = self::searchArrayKey('menuCategoryDetailID', $categoryDetailID, $branchMenuArr[$categoryID]['menuCategoryDetails']);
            $branchMenuArr[$categoryID]['menuCategoryDetails'][$categoryDetailKey]['menus'][] = [
                'menuName' => $data['menuName'],
                'qty' => $data['qty']
            ];
        }
        $branchMenuArr = array_values($branchMenuArr);
        
        return $branchMenuArr;
    }

    private static function searchArrayKey($column, $value, $array) {
        foreach ($array as $key => $val) {
            if ($val[$column] === $value) {
                return $key;
            }
        }
        return null;
    }
}
