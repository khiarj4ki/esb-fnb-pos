<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\PaymentMethod;
use app\models\Setting;
use app\models\Station;
use app\models\TableUsage;
use app\models\TempOrder;
use app\services\http_helper\HttpHelperService;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use yii\httpclient\Exception;
use QRcode;
use yii\helpers\Url;
use yii\web\HttpException;

/**
 * @property string $referenceID
 * @property string $username
 * @property TableUsage $tableUsageModel
 */
class ExternalPaymentMethod extends Model
{
    public $salesNum;
    public $branchID;
    public $paymentMethod;
    public $paymentAmount;
    public $phoneNumber;
    public $paymentMethodID;
    public $stationID;
    public $stationModel;
    public $printer;
    public $paymentMethodCode;
    public $visitPurposeID;
    public $salesMenu;
    public $customerName;
    public $payload;
    public $terminalID;
    const API_VERSION = 'esb_api';

    /**
     * {@inheritdoc}
     */
    public $apiKey;
    public $apiUrl;

    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->apiKey = Setting::getApiKey();
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    public function rules()
    {
        return [
            [['salesNum', 'branchID', 'paymentAmount', 'paymentMethod', 'branchID'], 'required'],
            [['stationID', 'payload', 'terminalID'], 'safe'],
            [['customerName', 'salesMenu', 'visitPurposeID', 'paymentMethodID', 'paymentMethodCode'], 'default'],
            [['branchID'], 'validateBranch'],
            [
                ['phoneNumber'], 'required', 'when' => function ($model) {
                    return $model->paymentMethod == 'ovo';
                }
            ],
        ];
    }

    public function validateBranch()
    {
        $branch = Branch::findOne(['branchID' => $this->branchID]);
        if (!$branch) {
            $this->addError('branchID', 'Branch doesnot exist');
            return false;
        } else {
            $this->branch = $branch;
        }
        if ($branch->brand) {
            $this->brand = $branch->brand;
            if ($this->paymentMethod == 'ovo' || $this->paymentMethod == 'dana') {
                if (empty($this->brand->posXenditApiKey) || empty($this->brand->posXenditVerificationToken)) {
                    $this->addError('branchID', 'Invalid xendit parameter');
                }
            }
            if ($this->paymentMethod == 'gopay') {
                if (empty($this->brand->posMidtransServerKey)) {
                    $this->addError('branchID', 'Invalid midtrans parameter');
                }
            }
        } else {
            $this->addError('branchID', 'Branch currently doesnot have brand');
            return false;
        }
    }

    public function getCheckSalesNum($salesNum, $forceCheckPaymentStatus)
    {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/payment/payment/validate';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   [
            'salesNum' => $salesNum,
            'forceCheckPaymentStatus' => $forceCheckPaymentStatus
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        if ($response->getIsOk()) {
            if (isset($response->getData()['paymentTransactionStatus'])) {
                if ($response->getData()['paymentTransactionStatus'] == 'settlement') {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getCheckSalesNumHandlingQr($salesNum, $terminalID, $forceCheckPaymentStatus)
    {
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/payment/payment/validate';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   [
            'salesNum' => $salesNum,
            'forceCheckPaymentStatus' => $forceCheckPaymentStatus,
            'terminalID' => $terminalID
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        $responseData = $response->getData();
        if (isset($responseData['paymentTransactionStatus'])) {
            if (
                isset($responseData['paymentMethod'])
                && in_array($responseData['paymentMethod'], ['qrisotopay', 'qrisgpay'])
                && $responseData['paymentTransactionStatus'] == 'settlement'
            ) {
                $logData = ["posExternalPaymentID" => $responseData['paymentMethod'], "request" => $datas, "response" => $responseData];
                Logging::save($salesNum, Logging::SETTLEMENT_PAYMENT_QRIS, $logData);
            }else if(
                isset($responseData['paymentMethod'])
                && $responseData['paymentMethod'] == 'uvlpoint'
                && $responseData['paymentTransactionStatus'] == 'settlement'
            ){
                $logData = ["posExternalPaymentID" => $responseData['paymentMethod'], "request" => $datas, "response" => $responseData];
                Logging::save($salesNum, Logging::VALIDATE_PAYMENT_UVL , $logData);
            }
            return $response->getData();
        } else {
            return false;
        }
    }

    public function getCheckSalesNumYukk($salesNum)
    {
   
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/payment/payment/validate';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   [
        'salesNum' => [$salesNum]
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        if (isset($response->getData()['paymentTransactionStatus'])) {
            return $response->getData();
        } else {
            return false;
        }
    }

    public function generateQrisQrCode()
    {

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/payment/payment/create';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   [
            'salesNum' => $this->salesNum,
            'branchID' => $this->branchID,
            'paymentAmount' => $this->paymentAmount,
            'paymentMethod' => $this->paymentMethod,
            'phoneNumber' => null
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        return array_merge($response->getData(), array('salesNum' => $this->salesNum, 'paymentMethod' => $this->paymentMethod));
    }

    private function generateQrCodeData()
    {
        $branchCode = Branch::findOne([
            'branchID' => Setting::getCurrentBranch()
        ])->branchCode;

        $result = [];
        $result[0] = [
            'n',
            $this->customerName
        ];
        $result[1] = [
            'b',
            $branchCode
        ];
        $result[2] = [
            'v',
            $this->visitPurposeID
        ];
        try {
            $i = 3;
            foreach ($this->salesMenu as $sales) {
                $result[$i] = [
                    'o',
                    $sales['menuID'],
                    $sales['qty'],
                    $sales['notes'],
                    $sales['price']
                ];
                if (!empty($sales['packages'])) {
                    foreach ($sales['packages'] as $packages) {
                        $i += 1;
                        $result[$i] = [
                            'p',
                            $packages['menuID'],
                            $packages['menuGroupID'],
                            $packages['qty'],
                            $packages['price'],
                        ];
                    }
                }
                if (!empty($sales["extras"])) {
                    foreach ($sales["extras"] as $extras) {
                        $i += 1;
                        $result[$i] = [
                            'x',
                            $extras['menuExtraID'],
                            $extras['qty'],
                            $extras['price'],
                        ];
                    }
                }
                $i += 1;
            }

            Yii::error(self::array2csv($result));

            $encryptedData = Yii::$app->security->encryptByKey(
                self::array2csv($result),
                Setting::getApiKey()
            );
            return base64_encode($encryptedData);
        } catch (Exception $ex) {
            Yii::error($ex);
            $this->addError('salesMenu', $ex->getMessage());
            return false;
        }
    }

    private static function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\")
    {
        $f = fopen('php://memory', 'r+');
        foreach ($data as $item) {
            fputcsv($f, $item, $delimiter, $enclosure, $escape_char);
        }
        rewind($f);
        return stream_get_contents($f);
    }

    public function createPaymentExternalData()
    {
        $paymentMethodModel = PaymentMethod::findOne(['paymentMethodID' => $this->paymentMethodID]);
        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $this->apiUrl . '/payment/payment/create';
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        $datas =   [
            'salesNum' => $this->salesNum,
            'branchID' => $this->branchID,
            'paymentAmount' => $this->paymentAmount,
            'paymentMethod' => $paymentMethodModel->posExternalPaymentID,
            'phoneNumber' => $this->phoneNumber,
            'terminalID' => $this->terminalID ? $this->terminalID : null
        ];
        $options = ['timeOut' => 300];
        $response = $httpService->post($url, $headers, $datas, $options);

        $data = $response->getData();
        $result['success'] = false;
        $result['payload'] = null;
        $result['paymentType'] = $paymentMethodModel->posExternalPaymentID;
        $result['isPaymentSuccess'] = false;
        if ($data) {
            if(isset($data['status'])) {
                $result['success'] = false;
                $result['status'] = $data['status'];
                if($data['status'] == 503){
                    $result['payload'] = $data['message'];
                }else{        
                    $msgError = '';
                    foreach (json_decode($data['message'])->paymentMethod as $value) {
                        $msgError .= $value;
                    }
                    $result['payload'] = $msgError;
                }
            }else {
                if ($paymentMethodModel->posExternalPaymentID == 'gopay') {
                    $result['success'] = true;
                    $result['payload'] = $response->getData()['paymentUrl'];
                } else if (in_array($paymentMethodModel->posExternalPaymentID, ['dana', 'danaesb'])) {
                    $result['success'] = true;
                    $result['payload'] = $response->getData()['paymentUrl'];
                } else if ($paymentMethodModel->posExternalPaymentID == 'qris'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisyukk'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisshopee'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisbpdbli'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisnobu'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisesb'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisotopay'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisgpay'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisbri'
                    || $paymentMethodModel->posExternalPaymentID == 'qrisdki') {
                    $qrisData = $response->getData()['qrisData'];
                    $result['success'] = true;
                    $result['payload'] = isset($qrisData['qrValue']) ? $qrisData['qrValue'] : $qrisData;
                    if (isset($qrisData['nmid'])) {
                        $result['nmid'] = $qrisData['nmid'];
                    }
                    if (isset($qrisData['merchantName'])) {
                        $result['merchantName'] = $qrisData['merchantName'];
                    }
                    if (isset($data['Id'])) {
                        $result['id'] = $data['Id'];
                    }
                    if (isset($data['En'])) {
                        $result['en'] = $data['En'];
                    }
                } else if (in_array($paymentMethodModel->posExternalPaymentID, ['ovo', 'ovoesb'])) {
                    $result['success'] = true;
                    $result['payload'] = null;
                } else if ($paymentMethodModel->posExternalPaymentID == 'uvlpoint') {
                    $qrisData = $response->getData()['qrisData'];
                    $result['success'] = true;
                    $result['payload'] = isset($qrisData['result']) ? $qrisData['result']['providerRefNum'] : $qrisData;
                }
            }
        }

        if (in_array($paymentMethodModel->posExternalPaymentID, ['qrisotopay', 'qrisgpay'])) {
            $logData = ["posExternalPaymentID" => $paymentMethodModel->posExternalPaymentID, "request" => $datas, "response" => $data];
            Logging::save($this->salesNum, Logging::CREATE_PAYMENT_QRIS, $logData);
        }else if($paymentMethodModel->posExternalPaymentID == 'uvlpoint'){
            $logData = ["posExternalPaymentID" => $paymentMethodModel->posExternalPaymentID, "request" => $datas, "response" => $data];
            Logging::save($this->salesNum, Logging::CREATE_PAYMENT_UVL, $logData);
        }
        return $result;
    }

    public function printQrCode()
    {
        $paymentMethodModel = PaymentMethod::findOne(['paymentMethodID' => $this->paymentMethodID]);
        if ($paymentMethodModel) {
            return $this->doPrint($paymentMethodModel->posExternalPaymentID, $this->payload);
        } else {
            throw new Exception("Error Processing Print");
        }
    }

    public function doPrint($method, $externalCode)
    {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();
        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();

        try {
            $connector = Station::getConnectorByModel(
                $this->stationModel,
                $this->salesNum
            );

            if($connector == null){
                throw new Exception("Failed to print. Connector not found", 400);
            }

            $this->printer = new Printer($connector);
            $printer = $this->printer;

            $this->printHeader($branchModel);

            $printer->text(str_pad(Yii::t('app', 'Pax'), 7, ' '));
            $printer->text(' : ');
            $printer->text('4');
            $printer->feed(2);
            
            if($method != 'gopay' && $method != 'dana') {
                $fileName = EscposImage::load(Yii::$app->basePath . '/web/assets_b/images/bill_qris_logo.png');
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->bitImage($fileName);
            }
            
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->generateQR($method, $printer, $externalCode);

            $printer->feed(1);
            $printer->text('SCAN ME TO PAY');
            $printer->feed(2);
            $printer->setJustification();
            if ($this->stationModel->flagAutocut == '1') {
                $printer->cut(Printer::CUT_PARTIAL);
            }

            $printer->close();
        } catch (Exception $ex) {
            throw new HttpException(400, $ex->getMessage(), $ex->getCode());
        }
    }

    private function printHeader($branchModel)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        foreach (explode('>><<', $branchModel->printingHeader) as $lineHeader) {
            $printer->text($lineHeader);
            $printer->feed(1);
        }
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);
    }

    private function generateQR($method, $printer, $externalCode)
    {
        if ($method) {
            $filename = Yii::$app->basePath . '/web/assets_b/images/' . md5(uniqid(rand(), true)) . '.png';
            require_once(Yii::$app->basePath . '/web/phpqrcode/qrlib.php');
            \QRcode::png($externalCode, $filename, 'L', 6, 0);
            $img = EscposImage::load($filename);
            $printer->bitImage($img);
            unlink($filename);
        }
    }
}
