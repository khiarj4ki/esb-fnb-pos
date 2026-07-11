<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\MapSelfOrderPaymentMethod;
use app\models\PaymentMethod;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use yii\base\Model;
use yii\httpclient\Client;
use yii\web\NotFoundHttpException;

/**
 * @property string $salesNum
 * @property string $result
 */
class CheckEzoPayment extends Model {
    public $salesNum;
    public $result;
    
    public function rules() {
        return [
            [['salesNum'], 'required']
        ];
    }
    
    public function init() {
        $selfOrderApi = Setting::getEsoFsApiUrl();
        $transId = AppHelper::encryptSalesNum($this->salesNum);
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $selfOrderApi . 'check-payment';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
            'data-branch' => $branch->branchCode,
            'data-company' => $companyCode,
            'data-transId' => $transId
        ];
        $datas = [
            'salesNumber' => $this->salesNum
        ];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $datas, $options);

        if ($result->getIsOk()) {
            $paymentMethodID = MapSelfOrderPaymentMethod::find()
                ->select("paymentMethodID")
                ->where([
                    "selfOrderPaymentMethodID" => $result->getData()['paymentMethod'],
                    "branchID" => Setting::getCurrentBranch()
                ])
                ->scalar();
            $paymentMethod = PaymentMethod::findOne($paymentMethodID);
            
            $orderPayments = [];
            $orderVoucherUsages = [];
            if (!empty($result->getData()['orderPayment'])) {
                foreach ($result->getData()['orderPayment'] as $orderPayment) {
                    $paymentMethodVoucher = PaymentMethod::findOne($orderPayment['paymentMethodID']);
                    $orderPayment['paymentMethodName'] = $paymentMethodVoucher->paymentMethodName;
                    $orderPayment['paymentMethodTypeID'] = $paymentMethodVoucher->paymentMethodTypeID;
                    $orderPayment['selfOrderID'] = $orderPayment['orderID'];
                    $orderPayments[] = $orderPayment;
                }
            }
            if (!empty($result->getData()['orderVoucherUsage'])) {
                foreach ($result->getData()['orderVoucherUsage'] as $orderVoucherUsage) {
                    $paymentMethodVoucher = PaymentMethod::findOne($orderVoucherUsage['paymentMethodID']);
                    $orderVoucherUsage['paymentMethodName'] = $paymentMethodVoucher->paymentMethodName;
                    $orderVoucherUsage['paymentMethodTypeID'] = $paymentMethodVoucher->paymentMethodTypeID;
                    $orderVoucherUsage['selfOrderID'] = $orderVoucherUsage['orderID'];
                    $orderVoucherUsages[] = $orderVoucherUsage;
                }
            }

            $this->result = [
                'ezoPaymentStatus' => $result->getData()['status'],
                'coaNo' => $paymentMethod->coaNo,
                'fullPaymentAmount' => $result->getData()['paymentTotal'],
                'paymentAmount' => $result->getData()['paymentTotal'],
                'selfOrderID' => $result->getData()['orderID'],
                'paymentMethodID' => $paymentMethodID,
                'paymentMethodName' => $paymentMethod->paymentMethodName,
                'paymentMethodTypeID' => $paymentMethod->paymentMethodTypeID,
                'orderPayment' => $orderPayments,
                'orderVoucherUsage' => $orderVoucherUsages,
            ];
            
        } else {
            throw new NotFoundHttpException();
        }
    }
}
