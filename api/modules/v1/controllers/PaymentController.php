<?php

namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\components\AppHelper;
use app\models\forms\ExternalMember;
use app\models\forms\ExternalPaymentMethod;
use app\models\forms\PrintPayment;
use app\models\forms\PrintPaymentLhdnInvoice;
use app\models\forms\SavePayment;
use app\models\forms\SelfOrderTakeAway;
use app\models\PaymentOnlineTrackingLog;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesPayment;
use app\models\Setting;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class PaymentController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
                [
                    'print-void'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        return SalesHead::findOutstanding()
                ->with('table')
                ->with('member')
                ->with('promotion')
                ->with('status')
                ->with('creator')
                ->with('editor')
                ->joinWith('childSalesLinks')
                ->andWhere(['IS', SalesLink::tableName() . '.salesNum', null])
                ->andWhere(['>', 'billingPrintCount', '0'])
                ->all();
    }

    public function actionCreate() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $paymentModel = new SavePayment([
            'attributes' => $this->request->post()
        ]);
        try {
            //list current Mac Address
            AppHelper::checkMacAddress();
            $paymentModel->flagSavePaymentFs = isset($this->request->post()['flagSavePaymentFs']) ? $this->request->post()['flagSavePaymentFs'] : false;
            if (!$paymentModel->save()) {
                throw new Exception(json_encode($paymentModel->errors));
            } else {

                // @notes logging external online payment
                $modelExternalpaymentLog = new PaymentOnlineTrackingLog();
                $modelExternalpaymentLog->checkOnlinePaymentTrackingLog($paymentModel);

            }
            return $paymentModel->externalTransaction;
        } catch (Exception $ex) {
            Yii::error($ex);
            // @notes : code 2 = external Voucher (custom error message)
            if($ex->getCode() === 2) {
                throw new HttpException(400, $ex->getMessage());
            } else {
                throw new HttpException(500, $ex->getMessage());
            }
            
        }
    }

    public function actionView() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        if ($this->request->post('tableID') != 0) {
            $orderPayment = SalesHead::findOrderPaymentAsArray($this->request->post('tableID'),
                    $this->request->post('salesNum'), false, true);
        } else {
            $orderPayment = SalesHead::findOrderPaymentAsArray(null,
                    $this->request->post('salesNum'), false, true);
        }
        if (!$orderPayment) {
            throw new HttpException(404, 'Order not found');
        }
        if ($orderPayment['order']['statusID'] != 1) {
            throw new HttpException(404, 'Invalid sales number');
        }

        return $orderPayment;
    }

    public function actionViewForEdit() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }

        $orderPayment = SalesHead::findOrderPaymentAsArray(null,
                $this->request->post('salesNum'), false, true);
        if (!$orderPayment) {
            throw new HttpException(404, 'Order not found');
        }

        if ($orderPayment['order']['transactionModeID'] > 4) {
            throw new HttpException(404, 'Transaction mode cannot edit payment');
        }

        if ($orderPayment['order']['statusID'] != 8) {
            throw new HttpException(404, 'Invalid sales number');
        }

        return $orderPayment;
    }

    public function actionPrint() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPayment([
            'attributes' => $this->request->post()
        ]);
        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0, $printingModel->salesNum, true)) {
            $printingModel->doPrint();

            if ($printingModel->printResult) {
                return [
                    "printDataError" => $printingModel->printResult,
                    "printData" => AndroidPrintConnector::getData()
                ];
            }
        }
    }

    public function actionReprint() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPayment([
            'attributes' => $this->request->post()
        ]);
        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0,
                $printingModel->salesNum)) {
            $printingModel->scenario = PrintPayment::SCENARIO_REPRINT;
            $printingModel->doPrint();

            if ($printingModel->printResult) {
                return [
                    "printDataError" => $printingModel->printResult,
                    "printData" => AndroidPrintConnector::getData()
                ];
            }
        }
    }

    public function actionPrintLhdnInvoice() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPaymentLhdnInvoice([
            'attributes' => $this->request->post()
        ]);
        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0, $printingModel->salesNum, true)) {
            $printingModel->doPrint();

            if ($printingModel->printResult) {
                return [
                    "printDataError" => $printingModel->printResult,
                    "printData" => AndroidPrintConnector::getData()
                ];
            }
        }
    }

    public function actionReprintLhdnInvoice() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPaymentLhdnInvoice([
            'attributes' => $this->request->post()
        ]);
        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0,
                $printingModel->salesNum)) {
            $printingModel->scenario = PrintPayment::SCENARIO_REPRINT;
            $printingModel->doPrint();

            if ($printingModel->printResult) {
                return [
                    "printDataError" => $printingModel->printResult,
                    "printData" => AndroidPrintConnector::getData()
                ];
            }
        }
    }

    public function actionPrintEdited() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPayment([
            'attributes' => $this->request->post()
        ]);
        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0,
                $printingModel->salesNum)) {
            $printingModel->scenario = PrintPayment::SCENARIO_EDITED;
            $printingModel->doPrint();

            return AndroidPrintConnector::getData();
        }
    }

    public function actionPrintVoid() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPayment([
            'attributes' => $this->request->post()
        ]);
        $printingModel->scenario = PrintPayment::SCENARIO_VOID;
        $printingModel->doPrint();

        if ($printingModel->printResult) {
            return [
                "printDataError" => $printingModel->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionSendEmail() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException(400);
        }

        try {
            AppHelper::sendEmail($this->request->post('salesNum'));
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to send email'));
        }
    }

    public function actionValidateSelfOrderId() {
        $model = new SelfOrderTakeAway();
        $ezoSettings = Setting::getEZOSetting();
        $activateEzoTA = isset($ezoSettings['Activate EZO TA']) ? $ezoSettings['Activate EZO TA'] : 0;
        $activateQoQi = isset($ezoSettings['Activate QoQi']) ? $ezoSettings['Activate QoQi'] : 0;
        if ($activateEzoTA || $activateQoQi) {
            $result = $model->loadOrder($this->request->post('orderID'), $this->request->post('ezoServerID'));

            if (!$result) {
                throw new HttpException(404, 'Self Order ID not found');
            }
        } else {
            $model->orderID = $this->request->post('orderID');
            $model->validateOrderPayment('orderID');
            if ($model->errors) {
                throw new HttpException(404, 'Self Order ID has been registered');
            }
        }
    }

    public function actionCheckSalesPayment() {
        $salesNum = $this->request->post('salesNum');
        $forceCheckPaymentStatus = $this->request->post('forceCheckPaymentStatus');
        $model = new ExternalPaymentMethod();
        return $model->getCheckSalesNum($salesNum, $forceCheckPaymentStatus);
    }

    public function actionCheckSalesPaymentQr() {
        $salesNum = $this->request->post('salesNum');
        $terminalID = $this->request->post('terminalID', null);
        $forceCheckPaymentStatus = $this->request->post('forceCheckPaymentStatus');
        $model = new ExternalPaymentMethod();
        return $model->getCheckSalesNumHandlingQr($salesNum, $terminalID, $forceCheckPaymentStatus);
    }

    public function actionCheckSalesPaymentYukk() {
        $salesNum = $this->request->post('salesNum');
        $model = new ExternalPaymentMethod();
        return $model->getCheckSalesNumYukk($salesNum);
    }

    public function actionCreatePaymentGateway() {
        $model = new ExternalPaymentMethod([
            'attributes' => $this->request->post()
        ]);
        return $model->createPaymentExternalData();
    }

    public function actionPrintQrCode() {
        $model = new ExternalPaymentMethod([
            'attributes' => $this->request->post()
        ]);
        return $model->printQrCode();
    }

    public function actionSendReceiptEmail() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $printingModel = new PrintPayment([
            'attributes' => $this->request->post()
        ]);

        if (SalesHead::updatePrintCount(SalesHead::PRINT_PAYMENT, 0,
            $printingModel->salesNum)) {
                try {
                    $sendEmail = $printingModel->doSendEmail();
                    return $sendEmail;
                } catch (Exception $ex) {
                    Yii::error($ex->getMessage());
                    return [
                        'status' => "01",
                        'message' => Yii::t('app', 'Failed to send email')
                    ];
                }
        }
    }

    public function actionCapillaryOtp()
    {
        if (!$this->request->post('email') || !$this->request->post('point')) {
            throw new HttpException(400);
        }
        return ExternalMember::capillaryCallOtp($this->request->post('email'), $this->request->post('point'));
    }

    public function actionCapillaryOtpV2()
    {
        if (!$this->request->post('email') || !$this->request->post('point')) {
            throw new HttpException(400);
        }
        return ExternalMember::capillaryCallOtpV2($this->request->post('email'), $this->request->post('point'));
    }

    public function actionCapillaryRedeemPoint()
    {
        if (
            !$this->request->post('salesNum') ||
            !$this->request->post('email') ||
            !$this->request->post('point') ||
            !$this->request->post('otpCode')
        ) {
            throw new HttpException(400);
        }
        return ExternalMember::capillaryRedeemPoint(
            $this->request->post('salesNum'),
            $this->request->post('email'),
            $this->request->post('point'),
            $this->request->post('otpCode')
        );
    }

    public function actionMemberidRedeemPoint() {
        if (
            !$this->request->post('salesNum') ||
            !$this->request->post('memberCode') ||
            !$this->request->post('amountCoin')
        ) {
            throw new HttpException(400);
        }
        
        return ExternalMember::memberidRedeemPoint(
            $this->request->post('salesNum'),
            $this->request->post('memberCode'),
            $this->request->post('amountCoin')
        );
    }

    public function actionCapillaryRedeemPointV2()
    {
        if (
            !$this->request->post('salesNum') ||
            !$this->request->post('email') ||
            !$this->request->post('point') ||
            !$this->request->post('otpCode')
        ) {
            throw new HttpException(400);
        }
        return ExternalMember::capillaryRedeemPointV2(
            $this->request->post('salesNum'),
            $this->request->post('email'),
            $this->request->post('point'),
            $this->request->post('otpCode')
        );
    }

    public function actionCheckSalesEsbVoucher() {
        $paymentModel = new SavePayment([
            'attributes' => $this->request->post()
        ]);

        $paymentModel->checkSalesEsbVoucher();

        return $paymentModel->responseErrorMessage;
    }

    public function actionGenerateQrText() {
        if (!$this->request->post('salesNum')) {
            throw new HttpException("salesNum is required", 400);
        }
        return AppHelper::generateQrText($this->request->post('salesNum'));
    }

    public function actionCreateUltraVoucherPayment() {
        if (!$this->request->post('salesPayment')) {
            throw new HttpException(400);
        }

        $paymentModel = new SavePayment([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$paymentModel->saveUltraVoucherPayment($this->request->post('salesPayment'))) {
                throw new Exception(json_encode($paymentModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionRemoveUltraVoucherPayment() {
        if (!$this->request->post('paymentMethodID') || !$this->request->post('salesNum') || !$this->request->post('voucherCode')) {
            throw new HttpException(400);
        }

        $paymentModel = new SavePayment([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$paymentModel->removeUltraVoucherPayment(
                $this->request->post('salesNum'),
                $this->request->post('paymentMethodID'),
                $this->request->post('voucherCode')
            )) {
                throw new Exception(json_encode($paymentModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionCheckOnlinePaymentExists()
    {
        if (!$this->request->post('selfOrderID')) {
            throw new HttpException(400);
        }
        $selfOrderID = $this->request->post('selfOrderID');
        return SalesPayment::getSalesNumBySelfOrderIds($selfOrderID);
    }
}
