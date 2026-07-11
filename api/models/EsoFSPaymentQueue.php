<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tr_esofspaymentqueue".
 *
 * @property string $orderID
 * @property string $status
 * @property string $salesNum
 * @property string $voidNotes
 */
class EsoFSPaymentQueue extends \yii\db\ActiveRecord
{

    const PENDING = "PENDING";
    const SUCCESS = "SUCCESS";
    const ESO_QUEUE_PROCESS_CMD = "eso-fs-payment-queue/run";

    public static function tableName()
    {
        return 'tr_esofspaymentqueue';
    }

    public function rules()
    {
        return [
            [['orderID'], 'required'],
            [['orderID', 'salesNum'], 'string', 'max' => 20],
            [['paymentTotal', 'paymentMethod'], 'safe'],
            [['status'], 'string', 'max' => 10],
            [['orderID'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'orderID' => 'Order ID',
            'status' => 'Status',
            'salesNum' => 'Sales Number',
            'paymentMethod' => 'Payment Method',
            'paymentTotal' => 'Payment Total'
        ];
    }

    public static function getCount()
    {
        return EsoFSPaymentQueue::find()
            ->where(['status' => EsoFSPaymentQueue::PENDING])
            ->count();
    }

    public static function getSuccessOrderId()
    {
        return EsoFSPaymentQueue::find()
            ->select("orderID")
            ->where(["status" => EsoFSPaymentQueue::SUCCESS])
            ->column();
    }

    public static function findPendingData()
    {
        return EsoFSPaymentQueue::find()
            ->where(['status' => EsoFSPaymentQueue::PENDING])
            ->all();
    }

    public static function findPendingDataByOrderId($orderID)
    {
        return EsoFSPaymentQueue::find()
            ->where(['status' => EsoFSPaymentQueue::PENDING])
            ->andWhere(['orderID' => $orderID])
            ->one();
    }

    public static function checkExistingOrderId($orderID)
    {
        $checkSalesIsCreated = SalesPayment::find()
            ->where(['selfOrderID' => $orderID])
            ->one();

        return $checkSalesIsCreated;
    }

    
    public static function saveEsoFsProcessQueue($orderID, $salesNum, $paymentMethod, $paymentTotal){
        $checkSalesPayment = EsoFSPaymentQueue::checkExistingOrderId($orderID);
    
        if ($checkSalesPayment) {
            return false;
        }


        $pendingDataByOrderId = EsoFSPaymentQueue::findPendingDataByOrderId($orderID);
        if ($pendingDataByOrderId && !$checkSalesPayment) return true;
        
        $model = new EsoFSPaymentQueue();
        $model->orderID = $orderID;
        $model->status = EsoFSPaymentQueue::PENDING;
        $model->salesNum = $salesNum;
        $model->paymentMethod = $paymentMethod;
        $model->paymentTotal = $paymentTotal;
        if (!$model->save()) {
            Yii::error($model->getErrors());
            return false;
        }
        return true;
    }

    public static function checkStatusSalesNum($salesNum)
    {
        $checkSales = SalesHead::find()
            ->where(['salesNum' => $salesNum])
            ->one();

        return $checkSales;
    }

    public static function getSuccessEsoSales($orderIds)
    {
        $response = [];
        if (!empty($orderIds))
        {
            $data = SalesPayment::getSalesNumBySelfOrderIds($orderIds);
            if (!empty($data))
            {
                foreach ($data as $salesHead) {
                    $response[] = [
                        'billNum' => $salesHead['billNum'],
                        'tableID' => $salesHead['tableID'],
                        'salesNum' => $salesHead['salesNum']
                    ];
                }

                EsoFSPaymentQueue::deleteAll(['AND',
                    ['IN', 'orderID', $orderIds],
                    ['status' => EsoFSPaymentQueue::SUCCESS],
                ]);
            }
        }
        return $response;
    }


}
