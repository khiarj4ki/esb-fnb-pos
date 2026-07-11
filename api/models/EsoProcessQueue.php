<?php

namespace app\models;

use app\services\QueueService;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_esoprocessqueue".
 *
 * @property string $orderID
 * @property string $status
 */
class EsoProcessQueue extends ActiveRecord {

    public const PENDING = "PENDING";
    public const SUCCESS = "SUCCESS";
    public const TYPE_NEW = "NEW";
    public const TYPE_VOID = "VOID";
    public const ESO_QUEUE_PROCESS_CMD = "eso-process-queue/run";

    public static function tableName() {
        return 'tr_esoprocessqueue';
    }

    public function rules() {
        return [
            [['orderID'], 'required'],
            [['orderID', 'salesNum'], 'string', 'max' => 20],
            [['status', 'eventType'], 'string', 'max' => 10],
            [['voidNotes'], 'string', 'max' => 200],
            [['status'], 'default', 'value' => EsoProcessQueue::PENDING],
            [['eventType'], 'default', 'value' => EsoProcessQueue::TYPE_NEW],
        ];
    }

    public static function getCount()
    {
        return EsoProcessQueue::find()
            ->where(['status' => EsoProcessQueue::PENDING])
            ->count();
    }

    public static function getSuccessOrderId()
    {
        return EsoProcessQueue::find()
            ->select("orderID")
            ->where(["status" => EsoProcessQueue::SUCCESS])
            ->column();
    }

    public static function findPendingData()
    {
        return EsoProcessQueue::find()
            ->where(['status' => EsoProcessQueue::PENDING])
            ->all();
    }

    public static function findPendingDataByOrderId($orderID, $eventType)
    {
        return EsoProcessQueue::find()
            ->where(['status' => EsoProcessQueue::PENDING])
            ->andWhere(['orderID' => $orderID])
            ->andWhere(['eventType' => $eventType])
            ->one();
    }

    public static function checkExistingOrderId($orderID)
    {
        $checkSalesIsCreated = SalesPayment::find()
            ->where(['selfOrderID' => $orderID])
            ->one();

        return $checkSalesIsCreated;
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
                        'salesNum' => $salesHead['salesNum'],
                        'billNum' => $salesHead['billNum'],
                        'queueNum' => intval($salesHead['queueNum']),
                        'statusID' => $salesHead['statusID'],
                        'additionalInfo' => $salesHead['additionalInfo'],
                        'orderID' => $salesHead['orderID'],
                        'orderInProgress' => []
                    ];
                }

                EsoProcessQueue::deleteAll(['AND',
                    ['IN', 'orderID', $orderIds],
                    ['status' => EsoProcessQueue::SUCCESS],
                ]);
            }
        }
        return $response;
    }
}
