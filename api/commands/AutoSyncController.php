<?php

namespace app\commands;

use app\models\forms\SyncFetch;
use app\models\forms\SyncPush;
use app\models\Setting;
use Yii;
use yii\console\Controller;

class AutoSyncController extends Controller
{
    public function actionPushSales()
    {
        return true;
    }

    public function actionRun($endRunDate)
    {
        $run = true;
        while ($run) {
            if (date('Ymd') >= $endRunDate) {
                $run = false;
                break;
            }

            $settingAutoSyncPOS = Setting::getSetting('Local Setting', 'POS Auto Sync');
            if ($settingAutoSyncPOS && $settingAutoSyncPOS->value1 == 1) {
                file_put_contents(Yii::$app->basePath . '/' . Yii::$app->params['autoSyncLogFile'], microtime(true));
                $pushModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_SALES
                    ]
                ]);
                $pushBranchMenuModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_BRANCH_MENU
                    ]
                ]);
                $pushShiftModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_SHIFT
                    ]
                ]);
                $pushBranchEventModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_BRANCH_EVENT
                    ]
                ]);
                $pushBranchMenuTransactionModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_BRANCH_MENU_TRANSACTION
                    ]
                ]);
                $fetchBranchMenuModel = new SyncFetch([
                    'attributes' => [
                        'syncType' => SyncFetch::FETCH_BRANCH_MENU
                    ]
                ]);
                $fetchPosNotificationModel = new SyncFetch([
                    'attributes' => [
                        'syncType' => SyncFetch::FETCH_POS_NOTIFICATION
                    ]
                ]);
                $pushPaymentTrackingLogModel = new SyncPush([
                    'attributes' => [
                        'syncType' => SyncPush::PUSH_PAYMENT_ONLINE_TRACKING_LOG
                    ]
                ]);

                $pushModel->doSync();
                $pushBranchMenuModel->doSync();
                $pushShiftModel->doSync();
                $pushBranchEventModel->dosync();
                $pushBranchMenuTransactionModel->doSync();
                $fetchBranchMenuModel->doSync();
                $fetchPosNotificationModel->doSync();
                $pushPaymentTrackingLogModel->doSync();
                sleep(300); //5 minutes
            } else {
                $run = false;
            }
        }
    }
}
