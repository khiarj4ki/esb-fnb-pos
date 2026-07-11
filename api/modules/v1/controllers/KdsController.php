<?php

namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\BrandSetting;
use app\models\Setting;
use app\models\forms\AllOrderCompletion;
use app\models\forms\GoFoodNotification;
use app\models\forms\OrderCompletion;
use app\models\forms\PrintOdsOrder;
use app\models\forms\SmsGateway;
use app\models\forms\SyncSalesMenu;
use Yii;
use yii\web\HttpException;

class KdsController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
                'get-order', 'complete-order', 'get-checker-order'
        ]);
        return $behaviors;
    }

    public function actionGetOrder() {
        $this->validatePost();
        $viewMode = $this->request->post("viewMode");
        $stationID = $this->request->post("stationID");
        $visitPurposeID = $this->request->post("visitPurposeID");
        return OrderCompletion::getOutstandingOrder($viewMode, $stationID, $visitPurposeID);
    }

    public function actionGetCheckerOrder() {
        $this->validatePost();
        return OrderCompletion::getOutstandingCheckerOrder();
    }

    public function actionCompleteOrder() {
        $this->validatePost();

        $orderCompletionModel = new OrderCompletion([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$orderCompletionModel->save()) {
                throw new HttpException(500,
                    json_encode($orderCompletionModel->errors));
            }

            $ezoSettings = Setting::getEZOSetting();
            if ($ezoSettings['Activate EZO TA'] == 1) {
                $syncSalesMenuModel = new SyncSalesMenu([
                    'attributes' => $this->request->post()
                ]);
                $syncSalesMenuModel->type = SyncSalesMenu::TYPE_SINGLE_MENU;
                $syncSalesMenuModel->addQueue();

                $smsGatewayProvider = BrandSetting::getBrandSetting('EXTERNAL', 'SMS Gateway Provider');
                $smsSendApiUrl = BrandSetting::getBrandSetting('EXTERNAL', 'SMS Send API URL');

                if ($smsGatewayProvider && $smsSendApiUrl) {
                    $smsModel = new SmsGateway([
                        'attributes' => $this->request->post()
                    ]);
                    $smsModel->sendSms();
                }
            }

            //GOFOOD
            $goFoodClientID = BrandSetting::getBrandSetting('POS', 'GoFood Client ID');
            if($goFoodClientID != null || $goFoodClientID != ''){
                $GoFoodNotificationModel = new GoFoodNotification([
                    'attributes' => $this->request->post()
                ]);
                $GoFoodNotificationModel->markFoodReady();
            }

        } catch (\Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionCompleteAllOrder() {
        $this->validatePost();

        $orderCompletionModel = new AllOrderCompletion([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$orderCompletionModel->save()) {
                throw new HttpException(500,
                    json_encode($orderCompletionModel->errors));
            }

            $ezoSettings = Setting::getEZOSetting();
            if ($ezoSettings['Activate EZO TA'] == 1) {
                $syncSalesMenuModel = new SyncSalesMenu([
                    'attributes' => $this->request->post()
                ]);
                $syncSalesMenuModel->type = SyncSalesMenu::TYPE_ALL_MENU;
                $syncSalesMenuModel->addQueue();

                $smsGatewayProvider = BrandSetting::getBrandSetting('EXTERNAL', 'SMS Gateway Provider');
                $smsSendApiUrl = BrandSetting::getBrandSetting('EXTERNAL', 'SMS Send API URL');

                if ($smsGatewayProvider && $smsSendApiUrl) {
                    $smsModel = new SmsGateway([
                        'attributes' => $this->request->post()
                    ]);
                    $smsModel->sendSms();
                }
            }

            //GOFOOD
            $goFoodClientID = BrandSetting::getBrandSetting('POS', 'GoFood Client ID');
            if($goFoodClientID != null || $goFoodClientID != ''){
                $GoFoodNotificationModel = new GoFoodNotification([
                    'attributes' => $this->request->post()
                ]);
                $GoFoodNotificationModel->markFoodReady();
            }

        } catch (\Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionPrintOdsOrder() {
        $this->validatePost();

        $printingModel = new PrintOdsOrder([
            'attributes' => $this->request->post()
        ]);
        $printingModel->doPrint();

        return AndroidPrintConnector::getData();
    }

    public function actionGetHistoryOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $viewMode = $this->request->post("viewMode");
        return OrderCompletion::getHistoryOrder(null, $viewMode, $stationID);
    }

    private function validatePost() {
        if (!$this->request->isPost) {
            throw new HttpException(400);
        }
    }

    private function returnSaveError($ex) {
        Yii::error($ex->getMessage());
        throw new HttpException(500, Yii::t('app', 'Failed to save data'));
    }

}
