<?php

namespace app\models\forms;

use app\models\MemberDeposit;
use app\models\SalesDepositWithdrawal;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesPayment;
use app\models\SalesVoucher;
use app\models\Voucher;
use app\models\Setting;
use app\models\QueueSelfOrder;
use app\models\forms\EsbOrder;
use app\models\Queue;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;

/**
 * @property string $salesNum
 * @property string $voidNotes
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 */
class VoidSales extends Model {
    public $salesNum;
    public $voidNotes;
    public $salesModel;
    public $orderID;
    public $errorMsg;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'voidNotes'], 'required'],
            [['salesNum'], 'string', 'max' => 20],
            [['voidNotes'], 'string', 'max' => 200],
            [['orderID'], 'string', 'max' => 20],
            [['salesNum'], 'validateSalesNum']
        ];
    }

    public function validateSalesNum($attribute) {
        $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        $error = false;
        $errorMessage = '';
        if (!$this->salesModel) {
            $errorMessage = 'Invalid sales number';
            $error = true;
        } else {
            // @Notes: 12 = Cancelled
            if ($this->salesModel->statusID == 12) {
                $errorMessage = 'Sales has been cancelled';
                $error = true;
            }

            // @Notes: 24 = void
            if ($this->salesModel->statusID == 24) {
                $errorMessage = 'Sales has been void';
                $error = true;
            }
        }

        if ($error) {
            $this->addError($attribute, $errorMessage);
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $linkSalesNums = SalesLink::find()
                ->select('linkSalesNum')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->column();
            $salesNums = array_merge([$this->salesModel->salesNum],
                $linkSalesNums);

            $externalCancelTransID = null;
            if ($this->salesModel->flagExternalAPI === 1) {
                if ($this->salesModel->flagExternalMemberID) {
                    $errMsg = '';
                    if ($this->salesModel->externalMembershipTypeID === 'tada') {
                        ExternalMember::voidTransactionTada($this->salesModel, $this->voidNotes, $errMsg);
                    }
                    if ($errMsg != '') {
                        throw new Exception($errMsg, 400);
                    }
                } else {
                    if ($externalVoidTransaction = ExternalMember::voidTransaction($this->salesModel->externalTransID)) {
                        $externalCancelTransID = $externalVoidTransaction['transactionId'];
                    }
                }
            }

            
            // @Notes: 24 = Void
            SalesHead::updateAll([
                'additionalInfo' => $this->voidNotes,
                'statusID' => 24,
                'externalCancelTransID' => $externalCancelTransID,
                'editedDate' => date('Y-m-d H:i:s'),
                'syncDate' => null
                ], ['IN', 'salesNum', $salesNums]);

            // Reset Voucher that has been purchased
            $voucherPurchaseIDs = SalesVoucher::find()
                ->select('voucherID')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->column();

            Voucher::updateAll([
                'voucherStartDate' => null,
                'voucherEndDate' => null,
                'usedBranchID' => null,
                'usedDate' => null,
                'salesNum' => null,
                'syncDate' => null
                ], ['IN', 'voucherID', $voucherPurchaseIDs]);

            // Reset Voucher that has been used for payment
            $voucherPaymentIDs = SalesPayment::find()
                ->select('voucherCode')
                ->innerJoinWith('paymentMethod')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->andWhere(['paymentMethodTypeID' => 4])
                ->column();

            Voucher::updateAll([
                'usedBranchID' => null,
                'usedDate' => null,
                'salesNum' => null,
                'syncDate' => null
                ], ['IN', 'voucherID', $voucherPaymentIDs]);

            $paymentMethodTypeIDs = SalesPayment::find()
                ->select('paymentMethodTypeID')
                ->innerJoinWith('paymentMethod')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->column();

            $paymentMethodMemberDeposit = 6;

            if (in_array($paymentMethodMemberDeposit, $paymentMethodTypeIDs)) {
                $salesWithdrawalModel = SalesDepositWithdrawal::find()
                    ->andWhere(['salesNum' => $this->salesModel->salesNum])
                    ->all();
    
                foreach ($salesWithdrawalModel as $detail) {
                    MemberDeposit::updateAll([
                        'usedDepositTotal' => new Expression('usedDepositTotal - ' . $detail->paymentTotal),
                        'syncDate' => null
                        ], ['=', 'memberDepositNum', $detail->memberDepositNum]);
                }
            }
//            SalesMenu::updateAll(['statusID' => 24],
//                ['IN', 'salesNum', $salesNums]);
//
//            SalesMenuExtra::updateAll(['statusID' => 24],
//                ['IN', 'salesNum', $salesNums]);

            $transaction->commit();

            Logging::save($this->salesModel->salesNum, Logging::VOID_SALES,
            $this->getAttributes());
            
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('voidNotes', $ex->getMessage());
            return false;
        }
    }

    public function voidSalesEso() {
        if (!$this->validate()) {
            $errors = $this->getErrors();

            $model = new EsbOrder();
            $model->orderID = $this->orderID;
            $model->notifSelfOrderError($errors);

            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $externalCancelTransID = null;
            if ($this->salesModel->flagExternalAPI === 1) {
                if ($this->salesModel->flagExternalMemberID) {
                    $errMsg = '';
                    if ($this->salesModel->externalMembershipTypeID === 'tada') {
                        ExternalMember::voidTransactionTada($this->salesModel, $this->voidNotes, $errMsg);
                    }
                    if ($errMsg != '') {
                        throw new Exception($errMsg, 400);
                    }
                } else {
                    if ($externalVoidTransaction = ExternalMember::voidTransaction($this->salesModel->externalTransID)) {
                        $externalCancelTransID = $externalVoidTransaction['transactionId'];
                    }
                }
            }

            // @Notes: 24 = Void
            SalesHead::updateAll([
                'additionalInfo' => $this->voidNotes,
                'statusID' => 24,
                'externalCancelTransID' => $externalCancelTransID,
                'syncDate' => null
                ], ['IN', 'salesNum', $this->salesModel->salesNum]);

            // Reset Voucher that has been purchased
            $voucherPurchaseIDs = SalesVoucher::find()
                ->select('voucherID')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->column();

            if ($voucherPurchaseIDs && count($voucherPurchaseIDs) > 0) {
                Voucher::updateAll([
                    'voucherStartDate' => null,
                    'voucherEndDate' => null,
                    'usedBranchID' => null,
                    'usedDate' => null,
                    'salesNum' => null,
                    'syncDate' => null
                    ], ['IN', 'voucherID', $voucherPurchaseIDs]);
            }

            // Reset Voucher that has been used for payment
            $voucherPaymentIDs = SalesPayment::find()
                ->select('voucherCode')
                ->innerJoinWith('paymentMethod')
                ->andWhere(['salesNum' => $this->salesModel->salesNum])
                ->andWhere(['paymentMethodTypeID' => 4])
                ->column();

            if ($voucherPaymentIDs && count($voucherPaymentIDs) > 0) {
                Voucher::updateAll([
                    'usedBranchID' => null,
                    'usedDate' => null,
                    'salesNum' => null,
                    'syncDate' => null
                    ], ['IN', 'voucherID', $voucherPaymentIDs]);
            }
            
            $transaction->commit();
    
            $apiUrl = Setting::getEsoQsApiUrl();
            if ($apiUrl) {
                $model = new EsbOrder();
                $model->orderID = $this->orderID;
                $model->addQueue($this->salesModel->salesNum, QueueSelfOrder::TYPE_SALES_VOID);
            }

            Logging::save($this->salesModel->salesNum, Logging::VOID_SALES_ESO,
                $this->getAttributes());

            return [
                'salesNum' => $this->salesModel->salesNum,
                'billNum' => $this->salesModel->billNum,
            ];
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('voidNotes', $ex->getMessage());
            return false;
        }
    }
}
