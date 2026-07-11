<?php
namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\models\Notification;
use app\models\SalesHead;
use app\models\SalesPayment;
use app\models\ShiftLog;
use app\models\Table;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

class NotificationController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
                'index', 'create-waiter', 'create-bill', 'delete-waiter'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $waiter = [];
        $bill = [];
        $campaign = [];
        $paymentEzo = [];

        $shiftInDate = ShiftLog::getShiftInDate();
        $shiftInDate = ($shiftInDate ? $shiftInDate : date('Y-m-d H:i:s'));

        $notifModel = Notification::find()
            ->with('table')
            ->andWhere(['action' => Notification::ACTION_WAITER])
            ->andWhere(['>=', 'tr_notification.createdDate', $shiftInDate])
            ->limit(5)
            ->orderBy('createdDate DESC')
            ->all();
        foreach ($notifModel as $notif) {
            $waiter[] = [
                'tableID' => $notif->tableID,
                'action' => $notif->action,
                'tableName' => $notif->table->tableName,
                'createdDate' => str_replace("-","/", $notif->createdDate)
            ];
        }

        $billModel = Notification::find()
            ->with('table')
            ->andWhere(['action' => Notification::ACTION_BILL])
            ->andWhere(['>=', 'tr_notification.createdDate', $shiftInDate])
            ->orderBy('createdDate DESC')
            ->all();
        foreach ($billModel as $notif) {
            $bill[] = [
                'tableID' => $notif->tableID,
                'action' => $notif->action,
                'tableName' => $notif->table->tableName,
                'createdDate' => str_replace("-","/", $notif->createdDate)
            ];
        }
        
        $campaignModel = Notification::find()
            ->with('table')
            ->andWhere(['action' => Notification::ACTION_CAMPAIGN])
            ->andWhere(['>=', 'tr_notification.createdDate', $shiftInDate])
            ->orderBy('createdDate DESC')
            ->all();
        foreach ($campaignModel as $notif) {
            $campaign[] = [
                'tableID' => $notif->tableID,
                'action' => $notif->action,
                'tableName' => $notif->table ? $notif->table->tableName : null,
                'createdDate' => str_replace("-","/", $notif->createdDate)
            ];
        }
        
        $paymentEzoModel = SalesPayment::find()
            ->select([
                'tableID' => new Expression(SalesHead::tableName() . '.tableID'),
                'tableName' => new Expression(Table::tableName() . '.tableName'),
                'createdDate' => new Expression(SalesHead::tableName() . '.salesDateOut'),
                'tr_salespayment.selfOrderID'
                ])
            ->joinWith('salesHead.table')
            ->andWhere(['IS NOT', 'selfOrderID', null])
            ->andWhere(['>=', 'tr_saleshead.salesDate', date('Y-m-d', strtotime($shiftInDate))])
            ->orderBy('tr_saleshead.salesDateOut DESC')
            ->limit(5)
            ->all();
        foreach ($paymentEzoModel as $notif) {
            $paymentEzo[] = [
                'tableID' => $notif->tableID,
                'action' => 'Payment Success',
                'tableName' => $notif->tableName,
                'createdDate' => str_replace("-","/", $notif->createdDate)
            ];
        }

        return [
            'waiter' => $waiter,
            'bill' => $bill,
            'campaign' => $campaign,
            'paymentEzo' => $paymentEzo
        ];
    }

    public function actionCreateWaiter() {
        if (!$transId = $this->request->post('transId')) {
            throw new BadRequestHttpException();
        }

        if (($tableID = $this->getTableFromTransId($transId))) {
            if (!Notification::saveNotif($tableID, Notification::ACTION_WAITER)) {
                throw new ServerErrorHttpException(Yii::t('app',
                        'Failed to save data'));
            }
        }
    }

    public function actionCreateBill() {
        if (!$transId = $this->request->post('transId')) {
            throw new BadRequestHttpException();
        }

        if (($tableID = $this->getTableFromTransId($transId))) {
            if (!Notification::saveNotif($tableID, Notification::ACTION_BILL)) {
                throw new ServerErrorHttpException(Yii::t('app',
                        'Failed to save data'));
            }
        }
    }
    
    public function actionCreateTableWaiter() {
        if (!$tableID = $this->request->post('transId')) {
            throw new BadRequestHttpException();
        }

        if (!Notification::saveNotif($tableID, Notification::ACTION_WAITER)) {
            throw new ServerErrorHttpException(Yii::t('app',
                    'Failed to save data'));
        }
    }

    public function actionCreateTableBill() {
        if (!$tableID = $this->request->post('transId')) {
            throw new BadRequestHttpException();
        }
        
        if (!Notification::saveNotif($tableID, Notification::ACTION_BILL)) {
            throw new ServerErrorHttpException(Yii::t('app',
                    'Failed to save data'));
        } else {
            return true;
        }
    }

    public function actionDeleteWaiter() {
        if (!$tableID = $this->request->post('tableID')) {
            throw new BadRequestHttpException();
        }

        Notification::deleteAll([
            'tableID' => $tableID,
            'action' => Notification::ACTION_WAITER
        ]);
    }

    private function getTableFromTransId($transId) {
        $salesNum = AppHelper::decryptTransId($transId);
        $salesModel = SalesHead::findOne(['salesNum' => $salesNum]);
        if ($salesModel) {
            return $salesModel->tableID;
        }

        return 0;
    }

}
