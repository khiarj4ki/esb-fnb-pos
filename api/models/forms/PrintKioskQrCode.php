<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\PaymentMethod;
use app\models\Setting;
use app\models\Station;
use app\models\TempOrder;
use app\models\VisitPurpose;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use QRcode;
use yii\base\Model;
use Yii;

class PrintKioskQrCode extends Model {

    public $stationID;
    public $customerName;
    public $salesMenu;
    public $visitPurposeID;
    public $tenantName;
    public $posExternalPaymentID;
    public $salesNum;
    public $salesPaymentGatewayNum;
    public $data;

    private $stationModel;
    private $printer;
    private $orderID;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['stationID', 'customerName', 'salesMenu', 'visitPurposeID'], 'required'],
            [['stationID'], 'validateStation'],
            [['tenantName', 'posExternalPaymentID', 'salesNum', 'salesPaymentGatewayNum', 'data'], 'safe']
        ];
    }

    public function validateStation($attribute) {
        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();

        if(!$this->stationModel){
            $this->addError($attribute,'Invalid station ID');
        }
    }

    public function printQrCode() {
        if(!$this->validate()){
            return false;
        }
        
        $orderData = $this->generateQrCodeData();

        $orderID = round(microtime(true) * 1000);
        $tempOrder = new TempOrder();
        $tempOrder->orderID = $orderID;
        $tempOrder->orderData = $orderData;
        if(!$tempOrder->save()){
            Yii::error($tempOrder->errors);
            return false;
        }
        
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();

        $qrData = '<<TEMP_ORDER>>' . $orderID;

        $filename = Yii::$app->basePath . '/web/assets_b/images/' . $orderID . '.png';
        try {
            require_once(Yii::$app->basePath . '/web/phpqrcode/qrlib.php');

            QRcode::png($qrData, $filename, 'L', 10, 0);
            $connector = Station::getConnectorByModel($this->stationModel);
            if ($connector === null) {
                throw new Exception("Connector not found", 400);
            }
            $this->printer = new Printer($connector);
            $printer = $this->printer;

            $this->printHeader($branchModel);

            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $img = EscposImage::load($filename);
            $printer->bitImage($img);
            $printer->feed(1);
            $printer->text(Yii::t('app', 'Please give the receipt to our staff.'));
            if ($this->stationModel->printerConnectionID == 11) {
                $printer->feed(3);
            } else {
                $printer->feed(2);
            }
            $printer->setJustification();
            if ($this->stationModel->printerTypeID == 16) {
                $printer->cut(Printer::CUT_PARTIAL);
            } else {
                $printer->cut();
            }

            $printer->close();
            unlink($filename);
            return true;
        } catch (Exception $ex) {
            unlink($filename);
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public function printQrCodeQrisTimeout() {
        if(!$this->validate()){
            return false;
        }
        
        $orderData = $this->generateQrCodeData();

        $orderID = round(microtime(true) * 1000);
        $this->orderID = $orderID;
        $tempOrder = new TempOrder();
        $tempOrder->orderID = $orderID;
        $tempOrder->orderData = $orderData;
        if(!$tempOrder->save()){
            Yii::error($tempOrder->errors);
            return false;
        }

        $qrData = '<<TEMP_ORDER>>' . $orderID;

        $filename = Yii::$app->basePath . '/web/assets_b/images/' . $orderID . '.png';
        try {
            require_once(Yii::$app->basePath . '/web/phpqrcode/qrlib.php');

            QRcode::png($qrData, $filename, 'L', 10, 0);
            $connector = Station::getConnectorByModel($this->stationModel);
            if ($connector === null) {
                throw new Exception("Connector not found", 400);
            }
            $this->printer = new Printer($connector);
            $printer = $this->printer;

            $this->printHeaderTimeout();
            $this->printOrderInfo();
            $this->printOrderDetail();

            $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(Yii::t('app', 'Please bring this Order Form and proof of payment to cashier'));
            $printer->feed(1);
            $printer->feed(1);
            $img = EscposImage::load($filename);
            $printer->bitImage($img);
            $printer->feed(2);
            $printer->setJustification();
            if ($this->stationModel->printerTypeID == 16) {
                $printer->cut(Printer::CUT_PARTIAL);
            } else {
                $printer->cut();
            }

            $printer->close();
            unlink($filename);
            return true;
        } catch (Exception $ex) {
            unlink($filename);
            Yii::error($ex->getMessage());
            return false;
        }
    }

    private function generateQrCodeData() {
        $branchCode = Branch::findOne([
            'branchID' => Setting::getCurrentBranch()
        ])->branchCode;

        $edcPayment = isset($this->data) && isset($this->data['edcPayment']) ? $this->data['edcPayment'] : null;
        $paymentMethodId = null;
        $paymentMethodModel = PaymentMethod::findOne([
            'posExternalPaymentID' => $this->posExternalPaymentID
        ]);
        if ($edcPayment) {
            $paymentMethodModel = PaymentMethod::find()
                ->where([
                    'paymentMethodID' => $edcPayment['paymentMethodID']
                ])
                ->one();
        }

        if ($paymentMethodModel)
        {
            $paymentMethodId = $paymentMethodModel->paymentMethodID;
        }

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

        $result[4] = [
            's',
            $this->salesNum
        ];

        $result[5] = [
            'spg',
            $this->salesPaymentGatewayNum
        ];

        $result[6] = [
            'm',
            $paymentMethodId
        ];

        $i = 7;
        if ($edcPayment) {
            $result[7] = [
                'edc',
                $this->orderID,
                $paymentMethodId,
                $edcPayment['posExternalPaymentID'],
                $edcPayment['cardNumber'],
                $edcPayment['bankName'],
                $edcPayment['accountName'],
                $edcPayment['traceNumber'],
                $edcPayment['verificationCode'],
                $edcPayment['edcTerminalID'],
                $edcPayment['cardNumberValidationTypeID'],
                $edcPayment['edcPort'],
                $edcPayment['edcWssUrl'],
                $edcPayment['fixedAmount'],
                $edcPayment['flagEdcActive'],
                $edcPayment['flagMandatoryCardNumber'],
                $edcPayment['flagMandatoryVerificationCode'],
                $edcPayment['paymentMethodCode'],
                $this->data['paymentTotal'],
                $edcPayment['posExternalPaymentID'],
                $this->stationID,
                $edcPayment['coaNo']
            ];

            $i = 8;
        }

        try {
            foreach ($this->salesMenu as $sales) {
                $result[$i] = [
                    'o',
                    $sales['menuID'],
                    $sales['qty'],
                    $sales['notes'],
                    $sales['price'],
                    'KIOSK'
                ];
                if (!empty($sales['packages'])) {
                    foreach ($sales['packages'] as $packages) {
                        $i += 1;
                        $result[$i] = [
                            'p',
                            $packages['menuID'],
                            $packages['menuGroupID'],
                            $packages['qty'],
                            $packages['notes'],
                            $packages['price'],
                            'KIOSK'
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

            $encryptedData = Yii::$app->security->encryptByKey(self::array2csv($result),
                Setting::getApiKey());
            return base64_encode($encryptedData);
        } catch (Exception $ex) {
            Yii::error($ex);
            $this->addError('salesMenu', $ex->getMessage());
            return false;
        }
    }

    private static function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\") {
        $f = fopen('php://memory', 'r+');
        foreach ($data as $item) {
            fputcsv($f, $item, $delimiter, $enclosure, $escape_char);
        }
        rewind($f);
        return stream_get_contents($f);
    }

    private function printHeader($branchModel) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
        $printer->text($branchModel->branchName);
        $printer->feed(1);
        
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
        if ($this->tenantName) {
            $printer->text($this->tenantName);
            $printer->feed(2);
        }
    }

    private function printHeaderTimeout() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
        $printer->text('ORDER FORM');
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);
    }

    private function printOrderInfo() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $visitPurposeModel = VisitPurpose::findOne($this->visitPurposeID);
        $edcPayment = isset($this->data) && isset($this->data['edcPayment']) ? $this->data['edcPayment'] : null;
        $paymentMethod = PaymentMethod::find()->where(['posExternalPaymentID' => $this->posExternalPaymentID])->one();
        if ($edcPayment) {
            $paymentMethod = PaymentMethod::find()
                ->where([
                    'paymentMethodID' => $edcPayment['paymentMethodID']
                ])
                ->one();
        }

        $printer->initialize();

        $printer->text(str_pad('Date', 14, ' '));
        $printer->text(' : ');
        $printer->text(date_format(date_create(date('d-m-Y H:i')), 'd-m-Y'));
        $printer->feed(1);

        $printer->text(str_pad('Time', 14, ' '));
        $printer->text(' : ');
        $printer->text(date_format(date_create(date('d-m-Y H:i')), 'H:i'));
        $printer->feed(1);

        if ($visitPurposeModel) {
            $printer->text(str_pad('Purpose', 14, ' '));
            $printer->text(' : ');
            $printer->text($visitPurposeModel->visitPurposeName);
            $printer->feed(1);
        }

        $printer->text(str_pad('No', 14, ' '));
        $printer->text(' : ');
        $printer->text($this->orderID);
        $printer->feed(1);

        if ($paymentMethod) {
            $printer->text(str_pad('Payment Method', 2, ' '));
            $printer->text(' : ');
            $printer->text($paymentMethod->paymentMethodName);
            $printer->feed(1);
        }

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);
    }

    private function printOrderDetail() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $itemTotal = 0;
        $total = 0;

        $printer->initialize();

        foreach ($this->salesMenu as $sales) {
            $tempMenuName = $sales['menuName'];
            $menuName = [];
            $loop = 0;
            $stringMenuName = $tempMenuName;
            while ($loop < strlen($stringMenuName)){
                $length = (strpos(wordwrap($tempMenuName, $charLength - 9), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 9), "\n") : strlen($tempMenuName);
                $menuName[] = trim(substr($tempMenuName, 0, $length));
                $tempMenuName = substr($tempMenuName,$length);
                $loop += $length;
            }

            $printer->text(str_pad($sales['qty'], 3, ' '));
            $printer->text(' ');

            $i = 0;
            foreach($menuName as $key => $val) {
                if($i != 0){
                    $printer->text(str_pad('', 4, ' '));
                }
                $printer->text(str_pad($val, $charLength - 9, ' '));
                $printer->feed(1);
                $i++;
            }

            if ($sales['notes']) {
                $notesString = $sales['notes'];
                if (strpos($notesString, "\n") !== false) {
                    $notesString = str_replace("\n", ", ", $notesString);
                }
                if (strlen($notesString) >= $charLength - 7) {
                    $printer->text(str_pad('', 4, ' '));
                    $printer->text('* ');
                    $printer->text(substr($notesString, 0, $charLength - 7));
                    $subString = substr($notesString, $charLength - 7);
                    do {
                        $printer->text(str_pad('', 7, ' '));
                        $printer->text(substr($subString, 0, $charLength - 7));
                        if (strlen($subString) >= ($charLength - 7)) {
                            $subString = substr($subString, $charLength - 7);
                        } else {
                            break;
                        }
                    } while (1);
                } else {
                    $printer->text(str_pad('', 4, ' '));
                    $printer->text('* ');
                    $printer->text($notesString);
                }
                $printer->feed(1);
            }

            $itemTotal += $sales['qty'];
            $total += $sales['total'];

            if ($sales['packages']) {
                foreach ($sales['packages'] as $package) {
                    $tempMenuNamePackage = $package['menuName'];
                    $menuNamePackage = [];
                    $loopPackage = 0;
                    $stringMenuNamePackage = $tempMenuNamePackage;
                    while ($loopPackage < strlen($stringMenuNamePackage)){
                        $lengthPackage = (strpos(wordwrap($tempMenuNamePackage, $charLength - 15), "\n") !== false) ? strpos(wordwrap($tempMenuNamePackage, $charLength - 15), "\n") : strlen($tempMenuNamePackage);
                        $menuNamePackage[] = trim(substr($tempMenuNamePackage, 0, $lengthPackage));
                        $tempMenuNamePackage = substr($tempMenuNamePackage,$lengthPackage);
                        $loopPackage += $lengthPackage;
                    }

                    $printer->text(str_pad('', 4, ' '));
                    $printer->text(str_pad($package['qty'], 3, ' '));
                    $printer->text(' ');

                    $j = 0;
                    foreach($menuNamePackage as $key => $val) {
                        if($j != 0){
                            $printer->text(str_pad('', 8, ' '));
                        }
                        $printer->text(str_pad($val, $charLength - 15, ' '));
                        $printer->feed(1);
                        $j++;
                    }

                    $itemTotal += $package['qty'];
                    $total += $package['total'];
                }
            }

            if ($sales['extras']) {
                foreach ($sales['extras'] as $extra) {
                    $tempMenuNameExtra = $extra['menuExtraName'];
                    $menuNameExtra = [];
                    $loopExtra = 0;
                    $stringMenuNameExtra = $tempMenuNameExtra;
                    while ($loopExtra < strlen($stringMenuNameExtra)){
                        $lengthExtra = (strpos(wordwrap($tempMenuNameExtra, $charLength - 15), "\n") !== false) ? strpos(wordwrap($tempMenuNameExtra - 15), "\n") : strlen($tempMenuNameExtra);
                        $menuNameExtra[] = trim(substr($tempMenuNameExtra, 0, $lengthExtra));
                        $tempMenuNameExtra = substr($tempMenuNameExtra,$lengthExtra);
                        $loopExtra += $lengthExtra;
                    }

                    $printer->text(str_pad('', 4, ' '));
                    $printer->text(str_pad($extra['qty'], 3, ' '));
                    $printer->text(' ');

                    $k = 0;
                    foreach($menuNameExtra as $key => $val) {
                        if($k != 0){
                            $printer->text(str_pad('', 8, ' '));
                        }
                        $printer->text(str_pad($val, $charLength - 15, ' '));
                        $printer->feed(1);
                        $k++;
                    }

                    $itemTotal += $extra['qty'];
                    $total += $extra['total'];
                }
            }
        }

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);

        $printer->text(str_pad($itemTotal, 3, ' '));
        $printer->text(' ');
        $printer->text('Items');
        
        $printer->feed(1); 
        $printer->text('Total:');
        $printer->text(' ' . str_pad($total, 3, ' ')); 
        $printer->feed(2); 
    }

}
