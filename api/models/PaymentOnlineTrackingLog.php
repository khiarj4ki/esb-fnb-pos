<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "tr_paymentonlinetrackinglog".
 *
 * @property string $salesNum
 * @property string $billNum
 * @property string $branchID
 * @property string $productType
 * @property string $externalPaymentCode
 * @property string $paymentAmount
 * 
 * @property PaymentMethod[] $paymentMethods
 */
class PaymentOnlineTrackingLog extends ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_paymentonlinetrackinglog';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['ID', 'salesNum', 'billNum', 'branchID', 'productType', 'externalPaymentCode', 'paymentAmount'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Number',
            'billNum' => 'Bill Number',
            'productType' => 'Product Type',
            'externalPaymentCode' => 'External Payment Code',
            'paymentAmount' => 'Payment Amount',
        ];
    }

    public function checkOnlinePaymentTrackingLog( $data ) {

        $query = (new Query())
            ->select([
                'a.salesNum',
                'a.billNum',
                'a.branchID',
                'productType' => new Expression("'POS HYBRID'"),
                'c.posExternalPaymentID',
                'b.paymentAmount'
            ])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(SalesPayment::tableName() . ' b',
                'a.salesNum = b.salesNum')
            ->innerJoin(PaymentMethod::tableName() . ' c',
                'b.paymentMethodID = c.paymentMethodID')
            ->where(['a.salesNum' => $data->salesNum])
            ->andWhere(['IS NOT', 'c.posExternalPaymentID', null])
            ->one();

        if($query) {
            $modelPaymentOnlineLog = new PaymentOnlineTrackingLog();
            $modelPaymentOnlineLog->salesNum =  $query['salesNum'];
            $modelPaymentOnlineLog->billNum = $query['billNum'];
            $modelPaymentOnlineLog->branchID = $query['branchID'];
            $modelPaymentOnlineLog->productType = $query['productType'];
            $modelPaymentOnlineLog->externalPaymentCode = $query['posExternalPaymentID'];
            $modelPaymentOnlineLog->paymentAmount = $query['paymentAmount'];

            if(!$modelPaymentOnlineLog->save()) {
                Yii::error('Failed to Save Payment Online Tracking Log!');
            }
        }
    }

    public function checkOnlinePaymentTrackingLogKiosk( $data ) {

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $query = (new Query())
                ->select([
                    'a.salesNum',
                    'a.billNum',
                    'a.branchID',
                    'productType' => new Expression("'KIOSK'"),
                    'c.posExternalPaymentID',
                    'b.paymentAmount'
                ])
                ->from(SalesHead::tableName() . ' a')
                ->innerJoin(SalesPayment::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tableName() . ' c',
                    'b.paymentMethodID = c.paymentMethodID')
                ->where(['b.selfOrderID' => $data->orderID])
                ->andWhere(['IS NOT', 'c.posExternalPaymentID', null])
                ->one();

            if($query) {
                $modelPaymentOnlineLog = new PaymentOnlineTrackingLog();
                $modelPaymentOnlineLog->salesNum =  $query['salesNum'];
                $modelPaymentOnlineLog->billNum = $query['billNum'];
                $modelPaymentOnlineLog->branchID = $query['branchID'];
                $modelPaymentOnlineLog->productType = $query['productType'];
                $modelPaymentOnlineLog->externalPaymentCode = $query['posExternalPaymentID'];
                $modelPaymentOnlineLog->paymentAmount = $query['paymentAmount'];

                if(!$modelPaymentOnlineLog->save()) {
                    Yii::error('Failed to Save Payment Online Tracking Log!');
                }

                $transaction->commit();
            } else {
                Yii::error('Payment Online Tracking Not Found!');
            }
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    public function checkOnlinePaymentVoucherTrackingLogKiosk( $data ) {

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $query = (new Query())
                ->select([
                    'a.salesNum',
                    'a.billNum',
                    'a.branchID',
                    'productType' => new Expression("'KIOSK'"),
                    'c.paymentMethodName',
                    'b.paymentAmount'
                ])
                ->from(SalesHead::tableName() . ' a')
                ->innerJoin(SalesPayment::tableName() . ' b',
                    'a.salesNum = b.salesNum')
                ->innerJoin(PaymentMethod::tableName() . ' c',
                    'b.paymentMethodID = c.paymentMethodID')
                ->where(['b.selfOrderID' => $data->orderID])
                ->one();

            if($query) {
                $modelPaymentOnlineLog = new PaymentOnlineTrackingLog();
                $modelPaymentOnlineLog->salesNum =  $query['salesNum'];
                $modelPaymentOnlineLog->billNum = $query['billNum'];
                $modelPaymentOnlineLog->branchID = $query['branchID'];
                $modelPaymentOnlineLog->productType = $query['productType'];
                $modelPaymentOnlineLog->externalPaymentCode = $query['paymentMethodName'];
                $modelPaymentOnlineLog->paymentAmount = $query['paymentAmount'];

                if(!$modelPaymentOnlineLog->save()) {
                    Yii::error('Failed to Save Payment Online Tracking Log!');
                }

                $transaction->commit();
            } else {
                Yii::error('Payment Online Tracking Not Found!');
            }
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    public static function syncUpdate($stationID, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            PaymentOnlineTrackingLog::updateAll(
                ['syncDate' => $syncDate],
                ['AND', ['branchID' => $branchID], ['salesNum' => $stationID]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }
}
