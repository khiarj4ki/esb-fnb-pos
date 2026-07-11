<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "tr_salespayment".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $paymentMethodID
 * @property string $voucherCode
 * @property string $voucherCategoryID
 * @property string $notes
 * @property string $cardNumber
 * @property string $bankName
 * @property string $accountName
 * @property string $verificationCode
 * @property string $traceNumber
 * @property string $canceledVerificationCode
 * @property int $flagExternalVoucherAPI
 * @property string $externalVoucherCode
 * @property string $externalTransactionId
 * @property string $externalBatchNumber
 * @property string $externalCanceledTransactionId
 * @property string $externalCanceledBatchNumber
 * @property string $coaNo
 * @property string $paymentAmount
 * @property string $fullPaymentAmount
 * @property string $syncDate
 * 
 * @property PaymentMethod $paymentMethod
 */
class SalesPayment extends ActiveRecord {
    public $tableID;
    public $tableName;
    public $createdDate;
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salespayment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['voucherCode', 'notes', 'cardNumber', 'bankName', 'accountName', 'verificationCode'], 'default', 'value' => ''],
            [['localID', 'paymentMethodID', 'flagExternalVoucherAPI'], 'integer'],
            [['salesNum', 'paymentMethodID', 'coaNo', 'paymentAmount'], 'required'],
            [['paymentAmount', 'fullPaymentAmount'], 'number'],
            [['syncDate', 'selfOrderID', 'canceledVerificationCode', 'externalVoucherCode', 'externalTransactionId', 'externalBatchNumber', 'externalCanceledTransactionId', 'externalCanceledBatchNumber', 'voucherCategoryID', 'edcTerminalID'], 'safe'],
            [['salesNum', 'accountName', 'selfOrderID', 'voucherCode', 'traceNumber'], 'string', 'max' => 50],
            [['cardNumber', 'coaNo'], 'string', 'max' => 20],
            [['bankName', 'verificationCode'], 'string', 'max' => 100],
            [['notes'], 'string', 'max' => 500]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'paymentMethodID' => 'Payment Method ID',
            'voucherCode' => 'Voucher Code',
            'notes' => 'Notes',
            'cardNumber' => 'Card Number',
            'bankName' => 'Bank Name',
            'accountName' => 'Account Name',
            'verificationCode' => 'Verification Code',
            'edcTerminalID' => 'Edc Terminal ID',
            'coaNo' => 'Coa No',
            'paymentAmount' => 'Payment Amount',
            'fullPaymentAmount' => 'Full Payment Amount',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['paymentAmount'] = function ($model) {
            return (float) $model->paymentAmount;
        };
        $fields['fullPaymentAmount'] = function ($model) {
            return (float) $model->fullPaymentAmount;
        };
        $fields['paymentMethodTypeID'] = function ($model) {
            return $model->paymentMethod->paymentMethodTypeID;
        };
        $fields['paymentMethodTypeName'] = function ($model) {
            return $model->paymentMethod->paymentMethodType->paymentMethodTypeName;
        };
        $fields['paymentMethodName'] = function ($model) {
            return $model->paymentMethod->paymentMethodName;
        };
        $fields['flagUseEmployeeLimit'] = function ($model) {
            return $model->paymentMethod->flagUseEmployeeLimit;
        };
        $fields['posExternalPaymentID'] = function ($model) {
            return $model->paymentMethod->posExternalPaymentID;
        };
        $fields['depositSourceID'] = function ($model) {
            return $model->paymentMethod->depositSourceID;
        };
        $fields['voucherSourceID'] = function ($model) {
            return $model->paymentMethod->voucherSourceID;
        };

        return $fields;
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function getPaymentMethod() {
        return $this->hasOne(PaymentMethod::class,
                ['paymentMethodID' => 'paymentMethodID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public static function getNonSalesTransaction($salesNum) {
        // @Notes: paymentMethodTypeID 7 = NON SALES
        return (new Query())
                ->select('a.salesNum')
                ->from(SalesHead::tableName() . ' a')
                ->innerJoin(SalesPayment::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tableName() . ' c',
                    'b.paymentMethodID = c.paymentMethodID')
                ->andWhere(['a.salesNum' => $salesNum])
                ->andWhere(['statusID' => 8])
                ->andWhere(['paymentMethodTypeID' => 7])
                ->one();
    }

    public static function getNonSalesQuery($branchID, $startDate, $endDate) {
        // @Notes: paymentMethodTypeID 7 = NON SALES
        $linkSales = (new Query())
            ->select('c.linkSalesNum')
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(SalesPayment::tableName() . ' b',
                'a.salesNum = b.salesNum')
            ->innerJoin(SalesLink::tableName() . ' c', 'a.salesNum = c.salesNum')
            ->innerJoin(PaymentMethod::tableName() . ' d',
                'b.paymentMethodID = d.paymentMethodID')
            ->andWhere(['a.branchID' => $branchID])
            ->andWhere(['>', 'salesDateOut', $startDate])
            ->andFilterWhere(['<', 'salesDateOut', $endDate])
            ->andWhere(['paymentMethodTypeID' => 7]);

        $dataNonSales = (new Query())
            ->select('a.salesNum')
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(SalesPayment::tableName() . ' b',
                'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tableName() . ' c',
                'b.paymentMethodID = c.paymentMethodID')
            ->andWhere(['a.branchID' => $branchID])
            ->andWhere(['>', 'salesDateOut', $startDate])
            ->andFilterWhere(['<', 'salesDateOut', $endDate])
            ->andWhere(['statusID' => 8])
            ->andWhere(['paymentMethodTypeID' => 7]);

        $unionQuery = (new Query())
            ->select('unionNonSales.salesNum')
            ->from([
            'unionNonSales' => $dataNonSales->union($linkSales, true)
        ]);
        return $unionQuery;
    }

    public static function getCashSalesQuery($branchID, $startDate, $endDate) {
        // @Notes: paymentMethodTypeID 1 = CASH
        return (new Query())
                ->select('a.salesNum')
                ->from(SalesHead::tableName() . ' a')
                ->innerJoin(SalesPayment::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tableName() . ' c',
                    'b.paymentMethodID = c.paymentMethodID')
                ->andWhere(['a.branchID' => $branchID])
                ->andWhere(['>', 'salesDateOut', $startDate])
                ->andFilterWhere(['<', 'salesDateOut', $endDate])
                ->andWhere(['statusID' => 8])
                ->andWhere(['paymentMethodTypeID' => 1]);
    }

    public static function getTotalGrandTotal($shiftID, $shiftLogModel = null) {
        if (!$shiftLogModel) {
            $shiftLogModel = ShiftLog::find()
                ->andWhere(['shiftID' => $shiftID])
                ->one();
        }
        if (!$shiftLogModel) {
            Yii::error('Cannot find shift model');
            return 0;
        }

        $nonSalesNumQuery = SalesPayment::getNonSalesQuery($shiftLogModel->branchID,
                $shiftLogModel->shiftInTime, $shiftLogModel->shiftOutTime);
        return (new Query())
                ->select('SUM(grandTotal - roundingTotal)')
                ->from(SalesHead::tableName())
                ->andWhere(['branchID' => $shiftLogModel->branchID])
                ->andWhere(['>', 'salesDateIn', $shiftLogModel->shiftInTime])
                ->andFilterWhere(['<', 'salesDateOut', $shiftLogModel->shiftOutTime])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'salesNum', $nonSalesNumQuery])
                ->scalar();
    }

    public static function getTotalNonCash($shiftID, $shiftLogModel = null) {
        if (!$shiftLogModel) {
            $shiftLogModel = ShiftLog::find()
                ->andWhere(['shiftID' => $shiftID])
                ->one();
        }
        if (!$shiftLogModel) {
            Yii::error('Cannot find shift model');
            return 0;
        }

        $nonSalesNumQuery = SalesPayment::getNonSalesQuery($shiftLogModel->branchID,
                $shiftLogModel->shiftInTime, $shiftLogModel->shiftOutTime);
        //@Notes: paymentMethodTypeID 1 = CASH
        return (new Query())
                ->select('SUM(paymentAmount)')
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->andWhere(['b.branchID' => $shiftLogModel->branchID])
                ->andWhere(['>', 'salesDateIn', $shiftLogModel->shiftInTime])
                ->andFilterWhere(['<', 'salesDateOut', $shiftLogModel->shiftOutTime])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'a.salesNum', $nonSalesNumQuery])
                ->andWhere(['<>', 'paymentMethodTypeID', 1])
                ->scalar();
    }

    public static function getTotalGrandTotalShift($shiftLogCashID, $shiftLogModel = null) {
        $shiftLogCashModel = ShiftLogCash::find()
            ->andWhere(['ID' => $shiftLogCashID])
            ->one();
        if (!$shiftLogCashModel) {
            Yii::error('Cannot find shift log cash model');
            return 0;
        }

        $shiftInTime = $shiftLogCashModel->shiftInTime;

        if ($shiftLogCashModel->shiftNumber > 1) {
            $shiftNumberPrev = $shiftLogCashModel->shiftNumber - 1;
            $shiftLogCashPrevModel = ShiftLogCash::find()
                    ->joinWith('shiftLog')
                    ->where(['tr_shiftlogcash.shiftID' => $shiftLogCashModel->shiftID])
                    ->andWhere(['shiftNumber' => $shiftNumberPrev])
                    ->one();

            if ($shiftLogCashPrevModel) {
                $shiftInTime = $shiftLogCashPrevModel->shiftOutTime;
            }
        }

        $nonSalesNumQuery = SalesPayment::getNonSalesQuery($shiftLogModel->branchID,
                $shiftInTime, $shiftLogCashModel->shiftOutTime);

        return (new Query())
                ->select('SUM(grandTotal - roundingTotal)')
                ->from(SalesHead::tableName())
                ->andWhere(['branchID' => $shiftLogModel->branchID])
                ->andWhere(['>=', 'salesDateIn', $shiftInTime])
                ->andFilterWhere(['<=', 'salesDateOut', $shiftLogCashModel->shiftOutTime])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'salesNum', $nonSalesNumQuery])
                ->scalar();
    }

    public static function getTotalNonCashShift($shiftLogCashID, $shiftLogModel = null) {
        $shiftLogCashModel = ShiftLogCash::find()
            ->andWhere(['ID' => $shiftLogCashID])
            ->one();
        if (!$shiftLogCashModel) {
            Yii::error('Cannot find shift log cash model');
            return 0;
        }

        $shiftInTime = $shiftLogCashModel->shiftInTime;

        if ($shiftLogCashModel->shiftNumber > 1) {
            $shiftNumberPrev = $shiftLogCashModel->shiftNumber - 1;
            $shiftLogCashPrevModel = ShiftLogCash::find()
                    ->joinWith('shiftLog')
                    ->where(['tr_shiftlogcash.shiftID' => $shiftLogCashModel->shiftID])
                    ->andWhere(['shiftNumber' => $shiftNumberPrev])
                    ->one();

            if ($shiftLogCashPrevModel) {
                $shiftInTime = $shiftLogCashPrevModel->shiftOutTime;
            }
        }

        $nonSalesNumQuery = SalesPayment::getNonSalesQuery($shiftLogModel->branchID,
                $shiftInTime, $shiftLogCashModel->shiftOutTime);
        //@Notes: paymentMethodTypeID 1 = CASH
        return (new Query())
                ->select('SUM(paymentAmount)')
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(SalesHead::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tablename() . ' c',
                    'a.paymentMethodID = c.paymentMethodID')
                ->andWhere(['b.branchID' => $shiftLogModel->branchID])
                ->andWhere(['>', 'salesDateIn', $shiftInTime])
                ->andFilterWhere(['<', 'salesDateOut', $shiftLogCashModel->shiftOutTime])
                ->andWhere(['statusID' => 8])
                ->andWhere(['NOT IN', 'a.salesNum', $nonSalesNumQuery])
                ->andWhere(['<>', 'paymentMethodTypeID', 1])
                ->scalar();
    }

    public static function getSalesNumBySelfOrderIds($selfOrderIds)
    {
        return (new Query())
            ->distinct()
            ->select([
                'b.salesNum',
                'orderID' => new Expression("a.selfOrderID"),
                'b.billNum', 
                'b.queueNum', 
                'b.statusID',
                'b.additionalInfo',
                'b.tableID'
                ])
            ->from(SalesPayment::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', "a.salesNum = b.salesNum")
            ->where(['IN', 'a.selfOrderID', $selfOrderIds])
            ->all();
    }

}
