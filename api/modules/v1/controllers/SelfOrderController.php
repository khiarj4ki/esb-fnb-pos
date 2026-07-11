<?php

namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\Branch;
use app\models\Brand;
use app\models\forms\ApplyOrderPromo;
use app\models\BrandSetting;
use app\models\EsoProcessQueue;
use app\models\forms\CheckEzoPayment;
use app\models\forms\CheckItems;
use app\models\forms\DecryptQrCodeData;
use app\models\forms\EsbOrder;
use app\models\MsPosCustomerDisplayDetail;
use app\models\forms\OutstandingOrder;
use app\models\forms\PrintKioskQrCode;
use app\models\forms\PrintOrder;
use app\models\forms\RemoveMemberFs;
use app\models\forms\SaveMemberFs;
use app\models\forms\SelfOrderTakeAway;
use app\models\LkColor;
use app\models\forms\VoidSales;
use app\models\forms\SyncSelfOrder;
use app\models\forms\UpdateOrder;
use app\models\MapBranchVisitPurpose;
use app\models\SalesHead;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\EsoFSPaymentQueue;
use app\models\EsoLogEvent;
use app\services\EsoFsQueueService;
use app\services\QueueService;
use Exception;
use Yii;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

class SelfOrderController extends BaseController {

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
                [ 
                    'get-pos-settings','print-void-to-station', 'get-eso-fs-payment-queue', 'get-eso-process-queue'
        ]);
        return $behaviors;
    }

    public function actionGetPosSettings() {
        $settingModel = Setting::find()
            ->andWhere(['key1' => ['Local Setting', 'POS']])
            ->andWhere(['key2' => [
                'Basic Rest Password',
                'Basic Rest Username',
                'Sales Decimal Setting',
                'Sales Decimal Separator Setting',
                'Voucher Usage on Kiosk V2',
                'Voucher Management'
            ]])
            ->all();

        $result = [];
        foreach ($settingModel as $setting) {
            $key = lcfirst(str_replace(' ', '', $setting->key2));
            if ($setting->value2 == 'Enc') {
                $result[$key] = Yii::$app->security->decryptByKey(base64_decode($setting->value1),
                    Yii::$app->params['key']);
            } else {
                $result[$key] = $setting->value1;
            }
        }
        
        $flagNotificationClosing = Setting::getSetting('POS', 'Shift Notification Closing');
        if ($flagNotificationClosing) {
            $shift = ShiftLog::find()
                ->where('shiftOutTime is null')
                ->one();

            if ($shift) {
                $checkShiftDate = date('Y-m-d H:i:s') > date('Y-m-d ',
                        strtotime($shift->shiftInTime . ' +1 day')) . $flagNotificationClosing->value2;

                if ($flagNotificationClosing->value1 == 1 && $checkShiftDate == true) {
                    $result['flagNotificationClosing'] = 1;
                } else {
                    $result['flagNotificationClosing'] = 0;
                }
            } else {
                $result['flagNotificationClosing'] = 0;
            }
        }

        $branchID = Setting::getCurrentBranch();

        $branchModel = Branch::findOne($branchID);
        $brandModel = Brand::find()
            ->where(['brandID' => $branchModel->brandID])
            ->one(); 

        $mapBranchVisitPurpose = MapBranchVisitPurpose::findOne([
            'branchID' => $branchID,
            'flagKiosk' => 1
        ]);

        $kioskLogo = BrandSetting::getBrandSetting('KIOSK', 'Kiosk Logo');
        $kioskImage = MsPosCustomerDisplayDetail::getCustomerDisplayImage('kiosk');

        $result['companyCode'] = $branchModel->companyCode;
        $result['branchCode'] = $branchModel->branchCode;
        $result['branchName'] = $branchModel->branchName;

        $visitPurposeID = 0;
        $flagOtherTaxVat = 0;
        $additionalTaxValue = 0;
        $taxValue = 0;
        $vatSubject = 0;
        $flagErrorVisitPurpose = false;

        if (isset($mapBranchVisitPurpose)) {
            $visitPurposeID = $mapBranchVisitPurpose->visitPurposeID;
            $flagOtherTaxVat = $mapBranchVisitPurpose->flagOtherTaxVat;
            $additionalTaxValue = $mapBranchVisitPurpose->additionalTaxValue;
            $taxValue = $mapBranchVisitPurpose->taxValue;
            $vatSubject = $mapBranchVisitPurpose->vatSubject;
        } else {
            $flagErrorVisitPurpose = true;
        }

        $result['selfOrderVisitPurposeID'] = $visitPurposeID;
        $result['flagOtherTaxVat'] = (float) ($flagOtherTaxVat ? $flagOtherTaxVat : $branchModel->flagOtherTaxVat);
        $result['additionalTaxValue'] = (float) ($additionalTaxValue ? $additionalTaxValue : $branchModel->additionalTaxValue);
        $result['additionalTaxName'] = $branchModel->additionalTaxName;
        $result['taxValue'] = (float) ($taxValue ? $taxValue : 0);
        $result['taxName'] = Setting::getValue1('POS', 'Tax Text');
        $result['vat'] = $vatSubject ? Setting::getValue1('VAT', 'Value') : 0;
        $result['vatName'] = isset($branchModel->vatName) ? $branchModel->vatName : '';
        $result['loginType'] = Setting::getValue1('POS', 'Login Type');
        $result['address'] = $branchModel->address;
        $result['menuSetting'] = Setting::getValue1('POS', 'Menu Setting');
        $result['brandName'] = $brandModel->brandName;
        $result['logoUrl'] = $kioskLogo ? $kioskLogo : null;
        $result['colors'] = LkColor::findColors();
        $result['isClosing'] = !$shift ? true : false;
        $result['countImageKiosk'] = count($kioskImage);
        $result['kioskImageUrls'] = $kioskImage;
        $result['flagErrorVisitPurpose'] = $flagErrorVisitPurpose;

        return $result;

    }

    public function actionPrintKioskQrCode() {
        $model = new PrintKioskQrCode([
            'attributes' => $this->request->post()
        ]);
        
        if (!$model->printQrCode()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }

        $printData = AndroidPrintConnector::getData();

        return $printData;
    }

    public function actionPrintKioskQrCodeQrisTimeout() {
        $model = new PrintKioskQrCode([
            'attributes' => $this->request->post()
        ]);
        
        if (!$model->printQrCodeQrisTimeout()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }

        $printData = AndroidPrintConnector::getData();

        return $printData;
    }

    public function actionPrintKioskQrCodeEdcTimeout() {
        $model = new PrintKioskQrCode([
            'attributes' => $this->request->post()
        ]);
        
        if (!$model->printQrCodeQrisTimeout()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }

        $printData = AndroidPrintConnector::getData();

        return $printData;
    }

    public function actionCreateTakeAway() {
        $model = new SelfOrderTakeAway();
        $model->ezoServerID = $this->request->post('ezoServerID');
        $model->loadOrderId($this->request->post('orderID'));

        if (!$result = $model->save()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }

    public function actionCreateEsbOrder() {
        $model = new EsbOrder();
        $saveEsoProcessQueue = $model->saveEsoProcessQueue($this->request->post('orderID'));
        if (!$saveEsoProcessQueue) {
            return false;
        }

        $queueService = new QueueService();
        $queueService->runQueue(EsoProcessQueue::ESO_QUEUE_PROCESS_CMD);

        return true;
    }

    public function actionCreateTakeAwayOffline() {
        ini_set('memory_limit', '-1');
        $model = new SelfOrderTakeAway([
            'attributes' => $this->request->post()
        ]);
        $model->scanQrTakeAwayOff = isset($this->request->post()['scanQrTakeAwayOff']) 
            ? $this->request->post()['scanQrTakeAwayOff'] : false;

        if (!$result = $model->save()) {
            throw new BadRequestHttpException(json_encode($model->errors));
        }
        return $result;
    }

    public function actionCheckItems() {
        $model = new CheckItems([
            'attributes' => $this->request->post()
        ]);

        if (!$model->checkItems()) {
            throw new BadRequestHttpException(json_encode($model->soldOutItems));
        } else {
            return true;
        }
    }

    public function actionDecryptOrder() {
        $model = new DecryptQrCodeData([
            'attributes' => $this->request->post()
        ]);
        
        $checkOrder = $model->checkOrder();
        if($checkOrder != null){
            throw new BadRequestHttpException(Yii::t('app', $checkOrder));
        }

        if (!$result = $model->decrypt()) {
            throw new BadRequestHttpException(Yii::t('app', 'Invalid QR Code'));
        } else {
            return $result;
        }
    }

    public function actionSavePayment() {
        if (!$orderID = $this->request->post('orderID')) {
            throw new BadRequestHttpException();
        }
        
        if (!$salesNum = $this->request->post('salesNum')) {
            throw new BadRequestHttpException();
        }
        
        if (!$paymentMethod = $this->request->post('paymentMethod')) {
            throw new BadRequestHttpException();
        }
        
        if (!$paymentTotal = $this->request->post('paymentTotal')) {
            throw new BadRequestHttpException();
        }

        $orderPayment = $this->request->post('orderPayment');
       
        
        $outstandingSales = SalesHead::findOutstandingOrder()
                    ->with('table.tableSection')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $salesNum])
                    ->one();
        
        if (!$outstandingSales) {
            throw new BadRequestHttpException();
        }
        $model = new SelfOrderTakeAway();
        $model->orderID = $orderID;
        $model->flagSavePaymentFs = true;
        if ($orderPayment) {
            return true;
        }
        EsoFSPaymentQueue::saveEsoFsProcessQueue($orderID, $salesNum, $paymentMethod, $paymentTotal);
        $queueService = new EsoFsQueueService();
        $queueService->runQueue(EsoFSPaymentQueue::ESO_QUEUE_PROCESS_CMD);

        return true;
    }

    public function actionGetEsoFsPaymentQueue()
    {
        ini_set('memory_limit', '-1');

        try {
            $orderIds = EsoFSPaymentQueue::getSuccessOrderId();
            
            return EsoFSPaymentQueue::getSuccessEsoSales($orderIds);
        
        } catch (\Exception $ex) {
            \Yii::error($ex->getMessage());
            return [];
        }
    }

    
    public function actionSaveMember() {
        if (!$this->request->post('flagExternalAPI')) {
            throw new BadRequestHttpException();
        }
        
        if (!$this->request->post('flagExternalMemberID')) {
            throw new BadRequestHttpException();
        }
        
        if (!$this->request->post('flagExternalMemberPhone')) {
            throw new BadRequestHttpException();
        }
        
        if (!$this->request->post('flagExternalCardID')) {
            throw new BadRequestHttpException();
        }
        
        $model = new SaveMemberFs([
            'attributes' => $this->request->post()
        ]);
        
        if (!$result = $model->saveMember()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }
    
    public function actionRemoveMember() {
        if (!$this->request->post('salesNum')) {
            throw new BadRequestHttpException();
        }
        $model = new RemoveMemberFs([
            'attributes' => $this->request->post()
        ]);
        
        if (!$result = $model->removeMember()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }
    
    public function actionCheckPayment() {
        if (!Yii::$app->request->post('salesNum')) {
            throw new BadRequestHttpException();
        }
        
        $model = new CheckEzoPayment([
            'attributes' => $this->request->post()
        ]);
        
        try {
            return $model->result;
        } catch (Exception $ex) {
            return new ServerErrorHttpException($ex->getMessage());
        }
    }

    public function actionVoidSalesEso() {
        
        if (!Yii::$app->request->post('salesNum')) {
            Yii::error("Sales number required when run void eso sales");
            return false;
        }
        if (!Yii::$app->request->post('orderID')) {
            Yii::error("Order ID required when run void eso sales");
            return false;
        }

        
        $orderID = Yii::$app->request->post('orderID');
        $salesNum = Yii::$app->request->post('salesNum');
        $voidNotes = Yii::$app->request->post('voidNotes');

        $model = new EsbOrder();
        $saveEsoProcessQueue = $model->saveVoidEsoProcessQueue($orderID, $salesNum, $voidNotes);
        if (!$saveEsoProcessQueue) {
            return false;
        }

        $queueService = new QueueService();
        $queueService->runQueue(EsoProcessQueue::ESO_QUEUE_PROCESS_CMD);

        return true;
    }

    public function actionPrintVoidToStation()
    {
        if (!Yii::$app->request->post('salesNum')) {
            throw new BadRequestHttpException();
        }

        $printingModel = new PrintOrder([
            'attributes' => $this->request->post()
        ]);
        $printingModel->tableID = 0;
        $printingModel->batchID = 1;
        $printingModel->scenario = PrintOrder::SCENARIO_SELF_ORDER_VOID;
        if (!$printingModel->doPrint()) {
            Yii::warning(json_encode($printingModel->errors));
        }

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }
    
    public function actionSavePromotion() {
        if (!Yii::$app->request->post('salesNum')) {
            throw new BadRequestHttpException();
        }
        $startTime = time();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $model = new OutstandingOrder();
            $model->salesNum = $this->request->post('salesNum');
            $order = $model->get();

            $applyPromoModel = new ApplyOrderPromo();
            $applyPromoModel->promotionID = $this->request->post('promotionID');
            $applyPromoModel->tableID = $order['tableID'];
            $applyPromoModel->order = Json::decode(Json::encode($order));
            $applyPromoModel->order['promotionVoucherCode'] = $this->request->post('promotionVoucherCode');
            $applyPromoModel->order['rewardType'] = (!$this->request->post('rewardType')) ? null : $this->request->post('rewardType');
            $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_HEAD;
            
            if (!$applyPromoModel->save()) {
                Yii::error($applyPromoModel->getErrors());
                throw new Exception("Failed to apply order promo", 500);
            }

            $updateModel = new UpdateOrder([
                'attributes' => $applyPromoModel['order']
            ]);

            if (!$updateModel->save()) {
                Yii::error($updateModel->getErrors());
                throw new Exception("Failed to update order", 500);
            }

            $timeoutTime = 20;
            if (time() - $startTime > $timeoutTime) {
                throw new Exception("Request Time out > $timeoutTime 's", 500);
            }

            $transaction->commit();

            $ezoSettings = Setting::getEZOSetting();
            if ($ezoSettings['Activate EZO'] == 1) {
                $apiUrl = Setting::getEsoFsApiUrl();
                if ($apiUrl) {
                    $syncSelfOrderModel = new SyncSelfOrder();
                    $syncSelfOrderModel->refNum = $this->request->post('salesNum');
                    $syncSelfOrderModel->type = 'salesNum';
                    $syncSelfOrderModel->addQueue();
                }
            }
            
            return ['statusCode' => 200 , 'message' => null];
        } catch (Exception $ex) {
            $transaction->rollBack();
            return ['statusCode' => $ex->getCode(), 'message' => $ex->getMessage()];
        }
    }

    public function actionUpdateOrderStatus() {
        if (!Yii::$app->request->post('orderID')) {
            throw new BadRequestHttpException();
        }
        
        $model = new EsbOrder([
            'attributes' => $this->request->post()
        ]);
        $username = Yii::$app->request->post('username');
        
        if (!$result = $model->updateOrderStatus($username)) {
            throw new ServerErrorHttpException(json_encode($model->errors));
        }
        return $result;
    }

    public function actionGetEsoProcessQueue()
    {
        ini_set('memory_limit', '-1');

        try {
            $orderIds = EsoProcessQueue::getSuccessOrderId(EsoProcessQueue::TYPE_NEW);
            
            return EsoProcessQueue::getSuccessEsoSales($orderIds);
        
        } catch (\Exception $ex) {
            \Yii::error($ex->getMessage());
            return [];
        }
    }

    public function actionErrorEsoList() {
        $model = new EsoLogEvent([
            'attributes' => $this->request->post()
        ]);
        try {

            return $model->getListErrorEso();
        } catch (\Exception $ex) {
            \Yii::error($ex->getMessage());
            return [];
        }
    }
}
