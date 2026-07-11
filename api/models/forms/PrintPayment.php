<?php

namespace app\models\forms;

use app\components\ExtPrinter;
use app\components\AESEncryption;
use app\components\AppHelper;
use app\components\PC1;
use app\models\Branch;
use app\models\BrandSetting;
use app\models\CustomNumber;
use app\models\Enums\EnumInterface;
use app\models\Enums\PrinterTypeInterface;
use app\models\LkExternalMemberShipType;
use app\models\Menu;
use app\models\PaymentMethod;
use app\models\ReceiptTextHead;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesPayment;
use app\models\Setting;
use app\models\Station;
use app\models\VisitPurpose;
use app\models\VoucherTemplate;
use app\models\MenuExtra;
use app\models\SalesPaymentStiReader;
use app\models\ShiftLog;
use app\models\Voucher;
use app\services\http_helper\HttpHelperService;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\httpclient\Client;
use yii\web\NotFoundHttpException;

/**
 * @property string $salesNum
 * @property int $stationID
 * 
 * PRIVATE
 * @property Printer $printer
 * @property array $settings
 * @property Station $stationModel
 * @property array $orderPayment
 */
class PrintPayment extends Model
{
    const SCENARIO_REPRINT = 'reprint';
    const SCENARIO_EDITED = 'edited';
    const SCENARIO_VOID = 'void';
    const PAYMENT_NON_SALES = 7;

    public $salesNum;
    public $stationID;
    public $printer;
    public $settings;
    public $stationModel;
    public $orderPayment;
    public $enabledImage;
    public $stringTableText;
    public $externalMemberTransaction;
    public $employeeInfo;
    public $billingTotal;
    public $flagSelfOrder;
    public $email;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;
    public $brandSetting;
    public $printResult;
    public $voucherOnlineCashback;
    public $isErrorGenerateVoucher = false;
    public $isErrorGenerateParkingVoucher = false;
    public $testPrint;
    public $eInvoicePrint;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['salesNum', 'stationID'], 'required'],
            [['salesNum'], 'string', 'max' => 20],
            [['stationID'], 'integer'],
            [['salesNum'], 'validateSales'],
            [['externalMemberTransaction', 'flagSelfOrder', 'email', 'testPrint', 'voucherOnlineCashback','eInvoicePrint'], 'safe']
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_REPRINT] = ['salesNum', 'stationID'];
        $scenarios[self::SCENARIO_EDITED] = ['salesNum', 'stationID'];
        $scenarios[self::SCENARIO_VOID] = ['salesNum', 'stationID'];

        return $scenarios;
    }

    public function validateSales($attribute)
    {
        $this->orderPayment = SalesHead::findOrderPaymentAsArray(
            null,
            $this->salesNum,
            true
        );
        if (!$this->orderPayment || !$this->orderPayment['salesPayment']) {
            $this->addError($attribute, 'Invalid sales number');
        }

        if ($this->flagSelfOrder && ($this->orderPayment['stationID'] !== null && $this->orderPayment['stationID'] !== 0)) {
            if ($this->orderPayment['stationID']) {
                $this->stationID = $this->orderPayment['stationID'];
            }
        }
    }

    public function getStringStructure($charLength)
    {
        $newStringTableText = '';
        $flagger = 0;
        do {
            $string = substr(
                $this->stringTableText,
                0,
                strpos($this->stringTableText, ', ')
            );
            if (strlen($string) + strlen($newStringTableText) <= $charLength) {
                if ($flagger == 0) {
                    $flagger = 1;
                } else {
                    $newStringTableText .= ', ';
                }
                $newStringTableText .= $string;
                $this->stringTableText = substr(
                    $this->stringTableText,
                    strlen($string) + 2
                );
            } else {
                $flagger = 2;
            }
        } while ($flagger != 2);
        return $newStringTableText;
    }

    public function getLongStringToEnterStructure($charLength)
    {
        $string = substr($this->stringTableText, 0, $charLength);
        $this->stringTableText = substr($this->stringTableText, $charLength);
        return $string;
    }

    public function doPrint()
    {
        if (!$this->validate()) {
            $this->printResult = ['status' => true, 'message' => null];
            return false;
        }
        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();
        if (!$this->stationModel) {
            $this->printResult = ['status' => true, 'message' => null];
            return false;
        }

        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();

        $this->settings = Setting::getPrintingSettings();
        $this->brandSetting = BrandSetting::getBrandPosSetting();
        $this->settings['Other Tax Text'] = $branchModel->additionalTaxName;
        $this->settings['VAT Text'] = $branchModel->vatName;

        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $printOrderList = array_merge(
            [$this->orderPayment['order']],
            $this->orderPayment['salesLink']
        );

        $printTakeAwaySettings = array_key_exists(
            'Print Quick Service Table Text',
            $this->settings
        ) ? $this->settings['Print Quick Service Table Text'] : true;

        try {
            $testPrint = isset($this->testPrint) && !!$this->testPrint;
            if ($this->scenario == self::SCENARIO_REPRINT && !$testPrint) {
                Logging::save(
                    $this->salesNum,
                    Logging::REPRINT_PAYMENT,
                    $this->stationModel
                );
            }

            $connector = Station::getConnectorByModel(
                $this->stationModel,
                $this->salesNum,
                true,
                null,
                $testPrint
            );

            $isErrorConnector = false;
            if ($connector !== null) {
                $this->printer = new ExtPrinter($connector);
                $printer = $this->printer;
                $charLength = $this->stationModel->characterPerLine;
                $printingCount = 1;
                $salesPaymentModel = SalesPayment::find()->select(['paymentMethodID'])->where(['salesNum' => $printOrderList[0]['salesNum']]);
                if ($this->scenario == self::SCENARIO_DEFAULT) {
                    $printingCountModel = PaymentMethod::find()
                        ->select([
                            'printedCount' => new Expression('MAX(printedCount)')
                        ])
                        ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                        ->one();
    
                    $printingCount = (int) $printingCountModel->printedCount;
                }
    
                if ($this->scenario == self::SCENARIO_DEFAULT) {
                    $flagOpenCashdrawer = PaymentMethod::find()
                        ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                        ->andWhere(['flagOpenCashdrawer' => true])
                        ->one();
    
                    if ($flagOpenCashdrawer) {
                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x07" . "\x1C");
                        } else {
                            $printer->pulse();
                        }
    
                        $dataLogging = [
                            'stationID' => $this->stationModel->stationID,
                            'stationName' => $this->stationModel->stationName
                        ];

                        if (!$testPrint) {
                            Logging::save($this->salesNum, Logging::OPEN_DRAWER, $dataLogging);
                        }
                        
                    }
                }
    
                $printByMenuCategorySetting = array_key_exists(
                    'Print by Menu Category',
                    $this->settings
                ) ? $this->settings['Print by Menu Category'] : false;
    
                $linkTablePrintSetting = array_key_exists(
                    'Simplify Link Table Print',
                    $this->settings
                ) ? $this->settings['Simplify Link Table Print'] : false;
    
                if (count($printOrderList) > 1) {
                    for ($i = 1; $i < count($printOrderList); $i++) {
                        $printOrderList[$i]['simplifyLinkTablePrint'] = $linkTablePrintSetting;
                    }
                }
    
                for ($i = 0; $i < $printingCount; $i++) {
                    $allBillingGrandTotal = 0;
                    $queueNum = 0;
                    $k = 0;
                    $lastPrintOrderList = false;
                    $lastFlagInclusive = false;
                    $lastTaxVal = 0;
                    $lastOtherTaxVal = 0;
                    $firstPrintingCount = $i == 0 ? true : false;
    
                    
                    if (
                        $printByMenuCategorySetting && in_array($branchModel->posModeID, [1, 2])
                        && count($this->orderPayment['salesLink']) == 0 && $printOrderList[0]['flagInclusive'] == 0
                    ) {
                        foreach ($printOrderList as $printOrder) {
                            $k += 1;
                            $lastPrintOrderList = (count($printOrderList) === $k) ? true : false;
                            $flagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
                            $allBillingGrandTotal += $printOrder['grandTotal'] - $printOrder['roundingTotal'];

                            $this->printLableTrialMode();
                            
                            foreach ($printOrder['menuCategoryGroup'] as $mcg) {
                                $this->printByMenuCategory($printOrder, $mcg, $printOrder['salesMenu'], $flagInclusive);
                            }
                            
                            $this->printHeader($branchModel);
                            $this->printBillInfo($printOrder);
    
                            if (array_key_exists(
                                'Print Category Subtotal',
                                $this->settings
                            )) {
                                if ($this->settings['Print Category Subtotal']) {
                                    foreach ($printOrder['menuCategoryGroup'] as $mcg) {
                                        $menuCategoryDesc = substr($mcg['menuCategoryDesc'], 0, $charLength);
                                        $printer->text(str_pad($menuCategoryDesc, $charLength, ' '));
                                    }
                                    $this->printLineBreak(1, "-");
                                }
                            }
    
                            $this->printBillSummary(
                                $printOrderList,
                                $printOrder,
                                0,
                                $lastPrintOrderList,
                                $firstPrintingCount,
                                true
                            );
                            
                            if (array_key_exists(
                                'Queue Number',
                                $this->settings
                            ) && array_key_exists(
                                'Hide Queue Number',
                                $this->settings
                            )) {
                                if ($this->settings['Queue Number'] == 1 && $this->settings['Hide Queue Number'] == 0) {
                                    $queueNum = $printOrder['queueNum'];
                                }
                            }
        
                            $lastFlagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
                            $lastTaxVal = $printOrder['vatTotal'];
                            $lastOtherTaxVal = $printOrder['otherTaxTotal'];
        
                            if (!$lastPrintOrderList) {
                                $this->printQueue($queueNum, $printOrder);
                            }
                        }
                    } else {
                        $this->printLableTrialMode();
                        $this->printHeader($branchModel);
                        foreach ($printOrderList as $printOrder) {
                            $k += 1;
                            $lastPrintOrderList = (count($printOrderList) === $k) ? true : false;
                            $flagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
                            $allBillingGrandTotal += $printOrder['grandTotal'] - $printOrder['roundingTotal'];
        
                            $this->printBillInfo($printOrder);
        
                            $totalQty = 0;
                            foreach ($printOrder['salesMenu'] as $salesMenu) {
                                $totalQty += $salesMenu['qty'];
        
                                $this->printBillDetail($salesMenu, $flagInclusive);
                            }
        
                             if (array_key_exists(
                                'Print Category Subtotal',
                                $this->settings
                            )) {
                                if ($this->settings['Print Category Subtotal']) {
                                    $this->printHeaderGroupMenuCategory();
        
                                    foreach ($printOrder['menuCategory'] as $menuCategory) {
                                        $this->printGroupMenuCategory($menuCategory);
                                    }
                                }
                            }
        
                            $this->printBillSummary(
                                $printOrderList,
                                $printOrder,
                                $totalQty,
                                $lastPrintOrderList,
                                $firstPrintingCount
                            );
        
                            if (array_key_exists(
                                'Queue Number',
                                $this->settings
                            ) && array_key_exists(
                                'Hide Queue Number',
                                $this->settings
                            )) {
                                if ($this->settings['Queue Number'] == 1 && $this->settings['Hide Queue Number'] == 0) {
                                    $queueNum = $printOrder['queueNum'];
                                }
                            }
        
                            $lastFlagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
                            $lastTaxVal = $printOrder['vatTotal'];
                            $lastOtherTaxVal = $printOrder['otherTaxTotal'];
        
                            if (!$lastPrintOrderList) {
                                $this->printQueue($queueNum, $printOrder);
                            }
                        }
                    }
    
                    // @Notes: Extra summary for linked bills
                    if (count($printOrderList) > 1) {
                        if ($this->stationModel->printerTypeID != 3 && $this->stationModel->printerTypeID != 15) {
                            $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                        }
                        $printer->text(str_pad(
                            Yii::t('app', 'Grand Total'),
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(
                            number_format(
                                $allBillingGrandTotal,
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ),
                            12,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $this->printLineBreak(2);
                        // @reset for edot mode
                        if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                            $printer->selectPrintMode(Printer::MODE_FONT_B);
                        }
            
                        $printer->initialize();
                    }
    
                    $this->printPayment(
                        $allBillingGrandTotal,
                        $this->orderPayment['salesPayment'],
                        $this->orderPayment['availableDepositTotal']
                    );
    
                    if (!$lastPrintOrderList) {
                        $this->printQueue($queueNum, $printOrder);
                    }
    
                    if (($i == 0) && ($this->scenario == self::SCENARIO_DEFAULT)) {
                        $nonSalesPaymentMethodModel = PaymentMethod::find()
                            ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                            ->andWhere(['=', 'paymentMethodTypeID', 7])
                            ->one();
    
                        if (!$nonSalesPaymentMethodModel) {
                            $this->printQR(
                                $this->orderPayment['order'],
                                Setting::getLocalSettings()
                            );
                        }
                    }
                    // @Notes: QR for guest comment
                    $isPrintQrGuestComment = false;
                    if (isset($this->settings['Print Guest Comment QR']) && $this->settings['Print Guest Comment QR'] == '1') {
                        $QRCaption = '';
                        if (isset($this->settings['Guest Comment QR Text'])) {
                            $QRCaption = $this->settings['Guest Comment QR Text'];
                        }
    
                        $guestCommentUrl = array_key_exists('Guest Comment Url', $this->settings) ? $this->settings['Guest Comment Url'] : true;
                        if ($this->settings['Guest Comment Url Default'] == '1') {
                            $externalCode = $guestCommentUrl . "/" . $branchModel->companyCode . "/" . $this->salesNum;
                        } else {
                            if (strpos($guestCommentUrl, '%salesNum%') !== false) {
                                $guestCommentUrl = str_replace("%salesNum%", $this->salesNum, $guestCommentUrl);
                            }
                            $externalCode = $guestCommentUrl;
                        }
                        
                        $printPerInterval= 0;
                        if (array_key_exists('Time Interval QR & Text In Bill', $this->settings)) {
                            $printPerInterval= $this->settings['Time Interval QR & Text In Bill'];
                        }
    
                        if ($printPerInterval > 0){
                            $salesHeadByShiftModel = SalesHead::find()
                                ->where(['salesDate' => $printOrder['salesDate']])
                                ->andWhere(['IN', 'statusID', [8, 24]])
                                ->andWhere(['<=', 'salesNum', $printOrder['salesNum']]);
                            $printInterval = $salesHeadByShiftModel->count();
                            $isPrintQrGuestComment = ($printInterval % $printPerInterval) == 0 ? true : false;
                           
                            if($isPrintQrGuestComment){
                                $this->printQRGuestComment(
                                    $externalCode,
                                    $QRCaption
                                );
                            }
                        } else {
                            $this->printQRGuestComment(
                                $externalCode,
                                $QRCaption
                            );
                        }
    
                        if ($this->externalMemberTransaction && (!isset($this->settings['Customer Code Type']) || $this->settings['Customer Code Type'] == '0')) {
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }
    
                    if (isset($this->settings['Customer Code Type']) && $this->settings['Customer Code Type'] != '0') {
                        if ($this->settings['Customer Code Type'] == '1') {
                            if ($printPerInterval != 0) {
                                if ($isPrintQrGuestComment) {
                                    $this->printBillCustomerCode($printOrder);
                                }
                            } else {
                                $this->printBillCustomerCode($printOrder);
                                $printer->feed(1);
                            }
        
                            if ($this->externalMemberTransaction) {
                                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                    $printer->getPrintConnector()->write("\x0A");
                                } else {
                                    $printer->feed(1);
                                }
                            }
                        } else if (
                            $this->settings['Customer Code Type'] == '2' &&
                            $printOrder['terminalID'] !== '' && $printOrder['terminalID'] !== null && 
                            isset($this->settings['Principle MBA ID']) && 
                            isset($this->brandSetting['MBA First Code']) && 
                            isset($this->brandSetting['MBA Last Code'])
                            ) {
                            if ($printPerInterval != 0) {
                                if ($isPrintQrGuestComment) {
                                    $this->printBillCustomerCodeMBA($printOrder);
                                }
                            } else {
                                $this->printBillCustomerCodeMBA($printOrder);
                                $printer->feed(1);
                            }
        
                            if ($this->externalMemberTransaction) {
                                $this->printLineBreak();
                            }
                        }
                    }

                    if (isset($this->brandSetting['Custom Number']) && $this->brandSetting['Custom Number'] != '0') {
                        if ($isPrintQrGuestComment) {
                            $this->printBillCustomNumber($printOrder);
                        } else {
                            $this->printBillCustomNumber($printOrder);
                            $printer->feed(1);
                        }

                        if ($this->externalMemberTransaction) {
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }
                    
                    // @Notes: QR for Marugame
                    if (isset($this->brandSetting['Loyalty Secret Key']) && 
                        isset($this->brandSetting['Receipt QR Code Encryption']) &&
                        isset($this->brandSetting['Encryption Key Code Loyalty']) &&
                        ($i == 0) && ($this->scenario == self::SCENARIO_DEFAULT)) {
                        $this->printQRMarugame($printOrder);
                    }

                    // @Notes: QR for Lippo parking
                    $nonSalesPaymentMethodModel = PaymentMethod::find()
                        ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                        ->andWhere(['=', 'paymentMethodTypeID', 7])
                        ->one();
                    if (isset($this->settings['Print Parking Voucher QR']) &&
                        ($this->settings['Print Parking Voucher QR'] == 1) &&
                        ($i == 0) && $this->scenario == self::SCENARIO_DEFAULT &&
                        !$nonSalesPaymentMethodModel && ($printOrder['transactionModeID'] === null  || 
                        in_array($printOrder['transactionModeID'], [0, 1, 2, 12, 13]))) {
                        $this->printQrParking($this->orderPayment['order']);
                    }
    
                    //@notes: print data point MAP jika tersedia external member transaction
                    if ($this->externalMemberTransaction) {
                        $printer->text(str_pad(
                            Yii::t('app', 'Point Earned'),
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(
                            number_format(
                                $this->externalMemberTransaction['earnedPoints'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ),
                            12,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $this->printLineBreak();
    
                        $printer->text(str_pad(
                            Yii::t('app', 'Point Burned'),
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(
                            number_format(
                                $this->externalMemberTransaction['burnedPoints'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ),
                            12,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $this->printLineBreak();
    
                        $printer->text(str_pad(
                            Yii::t('app', 'Available Points'),
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(
                            number_format(
                                $this->externalMemberTransaction['totalAvailablePoints'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ),
                            12,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $this->printLineBreak();
                    }
    
                    $this->printFooter(
                        $branchModel,
                        $printOrder['tableID'],
                        $queueNum,
                        $lastFlagInclusive,
                        $lastOtherTaxVal,
                        $lastTaxVal,
                        $printOrder
                    );
    
                    if ($i == 0 && $this->scenario == self::SCENARIO_DEFAULT) {
                        $nonSalesPaymentMethodModel = PaymentMethod::find()
                            ->where(['IN', 'paymentMethodID', $salesPaymentModel])
                            ->andWhere(['=', 'paymentMethodTypeID', 7])
                            ->one();
    
                        if (!$nonSalesPaymentMethodModel) {
                            $this->printVoucherTemplate();
                        }
    
                        $this->printLoopLiteQR();
                    }

                    $this->printLableTrialMode();
    
                    if ($this->stationModel->printerTypeID == '4') {
                        $printer->feed(2);
                    } else if ($this->stationModel->printerTypeID == '5') {
                        $printer->feed(2);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        if ($this->stationModel->flagAutocut == '1') {
                            $printer->feed(2);
                        }
                    } else {
                        if ($this->stationModel->flagAutocut == '1') {
                            $printer->cut(Printer::CUT_PARTIAL);
                        }
                    }
                }
    
                $printer->close();
            } else {
                $isErrorConnector = true;
            }

            if ($isErrorConnector) {
                $this->printResult = ['status' => false, 'message' => $this->stationModel->stationName];
            } else if ($this->isErrorGenerateParkingVoucher || $this->isErrorGenerateVoucher) {
                $this->printResult = $this->printResult;
            } else {
                $this->printResult = ['status' => true, 'message' => null];
            }
        } catch (Exception $ex) {
            Yii::warning($ex);
        }
    }

    protected function printHeader($branchModel)
    {
        $printer = $this->printer;
        $this->enabledImage = FALSE;
        // @Notes: Printer Type 1:Thermal, 2:Sticker, 3:Dot Matrix, 4:MPOP
        // @Notes: Printer Connection 1:Network, 2:Windows, 3:Android
        if (
            $this->stationModel->printerTypeID == '1' || $this->stationModel->printerTypeID == '3' || $this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15 ||
            $this->stationModel->printerConnectionID == '1' || $this->stationModel->printerConnectionID == '2' || $this->stationModel->printerTypeID == 16
        ) {
            $this->enabledImage = TRUE;
        }
        $charLength = $this->stationModel->characterPerLine;        

        //@Notes: inserting image at header
        $branchID = (Yii::$app->user->identity ? Yii::$app->user->identity->branchID : null );
        if (!$branchID) {
            $branchID = Setting::getCurrentBranch();
        }
        if ($this->enabledImage) {
            $branchModel = Branch::find()
                ->andWhere([
                    Branch::tableName() . '.flagActive' => 1,
                    Branch::tableName() . '.branchID' => $branchID
                ])->one();
            if ($branchModel) {
                $filename = 'pic-' . $branchModel->branchCode . '.png';
                $inputFileName = Yii::$app->basePath . '/web/images/' . $filename;
                if (file_exists($inputFileName)) {
                    $img = EscposImage::load($inputFileName);

                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                    } else {
                        $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                    }

                    if ($this->stationModel->printerTypeID == '3') {
                        $printer->bitImageColumnFormat(
                            $img,
                            ExtPrinter::IMG_DOUBLE_WIDTH | ExtPrinter::IMG_DOUBLE_HEIGHT
                        );
                    } elseif ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->bitImageMpop($img);
                    } else {
                        $printer->bitImage($img);
                    }
                    $this->printLineBreak();
                }
            }
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        foreach (explode('>><<', $branchModel->printingHeader) as $lineHeader) {
            $printer->text($lineHeader);
            $this->printLineBreak();
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();

        if ($this->scenario == self::SCENARIO_REPRINT || $this->scenario == self::SCENARIO_EDITED || $this->scenario == self::SCENARIO_VOID) {

            if ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_WIDTH | ExtPrinter::MODE_DOUBLE_HEIGHT);
            } else if ($this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x57" . "1");
                $printer->getPrintConnector()->write("\x1B" . "\x45");
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }

            $printer->text(Yii::t('app', ucfirst($this->scenario)));
            if ($this->scenario == self::SCENARIO_REPRINT) {
                $printer->text(' ' . $this->orderPayment['order']['paymentPrintCount']);
            }
            $this->printLineBreak(2);
            if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            $printer->initialize();
        }
    }

    protected function printBillInfo($printOrder)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        if ($printOrder['memberID'] !== 0 || $printOrder['externalMemberName'] && $printOrder['externalMemberName'] != "null") {
            $strPadLen = 14;
            $strPadValLen = 17;
        } else {
            $strPadLen = 11;
            $strPadValLen = 14;
        }

        $simplifyTableLinkPrintSetting = (isset($printOrder['simplifyLinkTablePrint']) && $printOrder['simplifyLinkTablePrint']);
        if ($simplifyTableLinkPrintSetting) {
            $showBillingTable = array_key_exists(
                'Show Billing Table',
                $this->settings
            ) ? $this->settings['Show Billing Table'] : true;
            if ($showBillingTable) {
                $this->stringTableText = $printOrder['tableName'] . $printOrder['mergeTableNames'];
                $flagger = 0;
                $printTakeAwaySettings = array_key_exists(
                    'Print Quick Service Table Text',
                    $this->settings
                ) ? $this->settings['Print Quick Service Table Text'] : true;
                if (($printTakeAwaySettings && $printOrder['tableID'] == 0) || $printOrder['tableID'] != 0) {
                    $printer->text(str_pad(Yii::t('app', 'Table'), $strPadLen, ' '));
                    $printer->text(' : ');
                    do {
                        if (strlen($this->stringTableText) > $charLength - $strPadValLen) {
                            $printer->text(str_pad(
                                $this->getStringStructure($charLength - $strPadValLen),
                                $charLength - $strPadValLen,
                                ' '
                            ));
                            $flagger = 1;
                        } else if ($flagger == 1) {
                            $printer->text(str_pad('', $strPadValLen, ' '));
                            $printer->text($this->stringTableText);
                            $flagger = 2;
                        } else {
                            $printer->text(str_pad(
                                $this->stringTableText,
                                $charLength - $strPadValLen,
                                ' '
                            ));
                            $flagger = 2;
                        }
                    } while ($flagger != 2);
                    $this->printLineBreak();
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            $this->printLineBreak();
            return;
        }

        $showBillingNumber = array_key_exists(
            'Show Billing Number',
            $this->settings
        ) ? $this->settings['Show Billing Number'] : true;
        if ($showBillingNumber) {
            $printer->text(str_pad(Yii::t('app', 'No'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['billNum'], $charLength - $strPadValLen, ' '));
            $this->printLineBreak();
        }

        $showSalesNumber = array_key_exists('Show Sales Number', $this->settings) ? $this->settings['Show Sales Number'] : true;
        if ($showSalesNumber) {
            $printer->text(str_pad(Yii::t('app', 'Sales No'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(
                $printOrder['salesNum'],
                $charLength - $strPadValLen,
                ' '
            ));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $printer->text(str_pad(Yii::t('app', 'Date'), $strPadLen, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(
            date_format(
                date_create($printOrder['salesDateOut']),
                $this->settings['Show Billing Time'] ? 'd-m-Y H:i' : 'd-m-Y'
            ),
            $charLength - $strPadValLen,
            ' '
        ));
        $this->printLineBreak();

        $showPrintingTimeIn = array_key_exists(
            'Show Printing Time In',
            $this->settings
        ) ? $this->settings['Show Printing Time In'] : false;
        if ($showPrintingTimeIn) {
            $printer->text(str_pad(Yii::t('app', 'Time In'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(date_format(
                date_create($printOrder['salesDateIn']),
                'd-m-Y H:i'
            ), $charLength - $strPadValLen, ' '));
            $this->printLineBreak();
        }
        // check member internal
        if ($printOrder['memberID'] !== 0) {
            $printer->text(str_pad(Yii::t('app', 'Regular Member'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['memberName'], $charLength - $strPadValLen,
                    ' '));
            $this->printLineBreak();
            $showMemberAddress = array_key_exists('Show Member Address',
                $this->settings) ? $this->settings['Show Member Address'] : false;
            if ($showMemberAddress) {
                $printer->text(str_pad(Yii::t('app', 'Address'), $strPadLen, ' '));
                $printer->text(' : ');
                $flagger=0;
                $memberAddress = str_split(preg_replace("/\r|\n/","",$printOrder['memberAddress']), $charLength - $strPadValLen);
                $i = 0;
                foreach ($memberAddress as $value)  {
                    if ($i == 0) {
                        $printer->text($value);
                    } else {
                        $printer->text(str_pad("" , $strPadValLen, ' '));
                        $printer->text(str_pad(ltrim($value), $charLength - $strPadValLen, ' '));
                    }
                    $printer->feed(1);
                    $i++;
                };           
            }
        }
        // check member external
        if ($printOrder['externalMemberName'] && $printOrder['externalMemberName'] != "null") {
            $printer->text(str_pad(Yii::t('app', 'Loyalty Member'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['externalMemberName'], $charLength - $strPadValLen,
                    ' '));
            $this->printLineBreak();
        }
        // check employee
        if ($printOrder['employeeCode'] && $printOrder['employeeCode'] !== '') {
		    $employeeCode = substr($printOrder['employeeCode'], -4);
			$employeeCode = ' - xxx'.$employeeCode;
            $employeeIdentificationMode = isset($this->settings['Employee Identification Mode']) ?: '';
            if ($employeeIdentificationMode == 'ONLINE') {
                $employeeModel = new Employee();
                $employeeModel->employeeCode = $printOrder['employeeCode'];

                $this->employeeInfo = $employeeModel->getBalance();
                if ($this->employeeInfo) {
                    $employeeCode .= ' - ' . $this->employeeInfo['employeeName'];
                }
            }
            $employeeName = $printOrder['employeeName'];
            if ($printOrder['employeeType'] === 'map') {
                $employeeBrandType = 'Member (MAP';
            } else {
                $employeeBrandType = 'Member (In';
            }
            $printer->text(str_pad(Yii::t('app', $employeeBrandType), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($employeeName.$employeeCode, $charLength - $strPadValLen,
                    ' '));
            $this->printLineBreak();

            if ($printOrder['employeeType'] === 'map') {
                $printer->text(str_pad(Yii::t('app', 'Employee)'), $strPadLen, ' '));
            } else {
                $printer->text(str_pad(Yii::t('app', 'ternal)'), $strPadValLen, ' '));
            }

            $this->printLineBreak();
        }

        $customerName = SalesInfo::findBySalesNumKey($this->salesNum, 'Full Name');
        if (!$printOrder['externalMemberName'] && $customerName != '') {
            $printer->text(str_pad(Yii::t('app', 'Customer'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(
                $customerName,
                $charLength - $strPadValLen,
                ' '
            ));
            $this->printLineBreak();
        }

        //show email member and validate email
        $validateEmail = $printOrder['flagExternalCardID'];
        $isEmail = $this->validateEmail($validateEmail);
        $showEmailReceipt = array_key_exists('Auto Email Receipt', $this->brandSetting) ? $this->brandSetting['Auto Email Receipt'] : false;
        if ($showEmailReceipt && $isEmail && $printOrder['externalMembershipTypeID'] == "memberid") {
            $emailResending = $printOrder['flagExternalCardID'];
            $printer->text(str_pad(Yii::t('app', 'Resending Email'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(
                $emailResending,
                $charLength - $strPadValLen,
                ' '
            ));
            $this->printLineBreak();
        }

        $showPrintingPaymentInfo = array_key_exists(
            'Show Printing Payment Info',
            $this->settings
        ) ? $this->settings['Show Printing Payment Info'] : false;
        if ($showPrintingPaymentInfo && $printOrder['additionalInfo'] != '') {
            $printer->text(str_pad(Yii::t('app', 'Info'), $strPadLen, ' '));
            $printer->text(' : ');
            $additionalInfo = str_split(preg_replace("/\r|\n/", "", $printOrder['additionalInfo']), $charLength - $strPadValLen);
            $i = 0;
            foreach ($additionalInfo as $value) {
                if ($i == 0) {
                    $printer->text($value);
                } else {
                    $printer->text(str_pad("", $strPadValLen, ' '));
                    $printer->text(str_pad(ltrim($value), $charLength - $strPadValLen, ' '));
                }
                $this->printLineBreak();
                $i++;
            };
        }

        $showPhoneNumber = array_key_exists(
            'Flag Input Phone Num',
            $this->brandSetting
        ) ? $this->brandSetting['Flag Input Phone Num'] : false;
        if ($showPhoneNumber && $printOrder['customerPhoneNum']) {
            $printer->text(str_pad(Yii::t('app', 'Phone No'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(substr($printOrder['customerPhoneNum'], 0, 8) . 'XXX', $charLength - $strPadValLen, ' '));
            $this->printLineBreak();
        }

        $showBillingServer = array_key_exists(
            'Show Billing Server',
            $this->settings
        ) ? $this->settings['Show Billing Server'] : true;
        if ($showBillingServer) {
            $printer->text(str_pad(Yii::t('app', 'Server'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['creator'], $charLength - $strPadValLen, ' '));
            $this->printLineBreak();
        }

        $showBillingTable = array_key_exists(
            'Show Billing Table',
            $this->settings
        ) ? $this->settings['Show Billing Table'] : true;
        if ($showBillingTable) {
            $this->stringTableText = $printOrder['tableName'] . $printOrder['mergeTableNames'];
            $flagger = 0;
            $printTakeAwaySettings = array_key_exists(
                'Print Quick Service Table Text',
                $this->settings
            ) ? $this->settings['Print Quick Service Table Text'] : true;
            if (($printTakeAwaySettings && $printOrder['tableID'] == 0) || $printOrder['tableID'] != 0) {
                $printer->text(str_pad(Yii::t('app', 'Table'), $strPadLen, ' '));
                $printer->text(' : ');
                do {
                    if (strlen($this->stringTableText) > $charLength - $strPadValLen) {
                        $printer->text(str_pad(
                            $this->getStringStructure($charLength - $strPadValLen),
                            $charLength - $strPadValLen,
                            ' '
                        ));
                        $flagger = 1;
                    } else if ($flagger == 1) {
                        $printer->text(str_pad('', $strPadValLen, ' '));
                        $printer->text($this->stringTableText);
                        $flagger = 2;
                    } else {
                        $printer->text(str_pad(
                            $this->stringTableText,
                            $charLength - $strPadValLen,
                            ' '
                        ));
                        $flagger = 2;
                    }
                } while ($flagger != 2);
                $this->printLineBreak();
            }
        }

        $showBillingVisitPurpose = array_key_exists(
            'Show Billing Visit Purpose',
            $this->settings
        ) ? $this->settings['Show Billing Visit Purpose'] : false;
        if ($showBillingVisitPurpose) {
            if ($this->settings['Show Billing Visit Purpose'] == 1) {
                $visitPurposeModel = VisitPurpose::find()
                    ->andWhere(['visitPurposeID' => $printOrder['visitPurposeID']])
                    ->one();
                $printer->text(str_pad(Yii::t('app', 'Purpose'), $strPadLen, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(
                    $visitPurposeModel['visitPurposeName'],
                    $charLength - $strPadValLen,
                    ' '
                ));
                $this->printLineBreak();
            }
        }

        $showBillingPax = array_key_exists('Show Billing Pax', $this->settings) ? $this->settings['Show Billing Pax'] : true;
        if ($showBillingPax) {
            $printer->text(str_pad(Yii::t('app', 'Pax'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(
                $printOrder['paxTotal'],
                $charLength - $strPadValLen,
                ' '
            ));
            $this->printLineBreak();
        }

        $showBillingCashier = array_key_exists(
            'Show Billing Cashier',
            $this->settings
        ) ? $this->settings['Show Billing Cashier'] : true;
        if ($showBillingCashier) {
            $printer->text(str_pad(Yii::t('app', 'Cashier'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text($printOrder['editor']);
            $this->printLineBreak();
        }

        $showBillingPrintCounter = array_key_exists(
            'Show Billing Print Counter',
            $this->settings
        ) ? $this->settings['Show Billing Print Counter'] : true;
        if ($showBillingPrintCounter) {
            $printer->text(str_pad(Yii::t('app', 'Print'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text($printOrder['billingPrintCount']);
            $this->printLineBreak();
        }

        $showPrintingSalesInfo = array_key_exists(
            'Show Printing Sales Info',
            $this->settings
        ) ? $this->settings['Show Printing Sales Info'] : false;
        if ($showPrintingSalesInfo) {
            $salesInfos = SalesInfo::findBySalesNum($this->salesNum);
            if ($salesInfos && $salesInfos != '') {
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                foreach ($salesInfos as $salesInfo) {
                    if ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15  && $this->stationModel->printerTypeID != 3) {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED);
                    }
                    $printer->text(str_pad($salesInfo->key, $strPadLen, ' '));
                    $this->printLineBreak();
                    if ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15 && $this->stationModel->printerTypeID != 3) {
                        $printer->selectPrintMode(ExtPrinter::MODE_FONT_A);
                    }
                    if ($salesInfo->key == 'Pickup Time') {
                        $printer->text(str_pad($salesInfo->value ? $salesInfo->value : 'Now', $strPadLen, ' '));
                    } else {
                        $printer->text(str_pad($salesInfo->value, $strPadLen, ' '));
                    }
                    $this->printLineBreak();
                }
                $this->printLineBreak();
            }
        }

        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();
    }

    protected function printBillDetail($salesMenu, $flagInclusive)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesArr = $salesMenu;

        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $receiptLayoutMode = isset($this->settings['Receipt Layout']) ? $this->settings['Receipt Layout'] : 0;
        $printReceiptWrapMenuName = isset($this->settings['Print Receipt Wrap Menu Name']) ? $this->settings['Print Receipt Wrap Menu Name'] : 0;
        $printingReceiptMode = isset($this->settings['Printing Receipt Mode']) ? $this->settings['Printing Receipt Mode'] : 0;

        $displayPrice = $this->getDisplayPriceValue(
            $flagInclusive,
            $salesArr['total'],
            $salesArr['discountValue'],
            $salesArr['qty'],
            $salesArr['price'],
            $salesArr['inclusivePrice']
        );
        $menuPriceTotal = $displayPrice == 0 ? $salesMenu['zeroValueText'] : number_format(
            $salesArr['qty'] * $displayPrice,
            $salesDecimalSetting,
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        );
        if ($printingReceiptMode == "0") {
            $childTotal = 0;
            if ($salesArr['packages']) {
                foreach ($salesArr['packages'] as $package) {
                    $packageModel = Menu::find()
                        ->andWhere(['menuID' => $package['menuID']])
                        ->one();
                    $displayPackagePrice = $this->getDisplayPriceValue(
                        $flagInclusive,
                        $package['total'],
                        $package['discountValue'],
                        $package['qty'],
                        $package['price'],
                        $package['inclusivePrice']
                    );
                    $childTotal += $salesArr['qty'] * $package['qty'] * $displayPackagePrice;
                }
            }
            if ($salesArr['extras']) {
                foreach ($salesArr['extras'] as $extra) {
                    $displayExtraPrice = $this->getDisplayPriceValue(
                        $flagInclusive,
                        $extra['total'],
                        $extra['discountValue'],
                        $extra['qty'],
                        $extra['price'],
                        $extra['inclusivePrice']
                    );
                    $childTotal += $salesArr['qty'] * $extra['qty'] * $displayExtraPrice;
                }
            }
            $newTotal = ($salesArr['qty'] * $displayPrice) + $childTotal;
            $menuPriceTotal = number_format(
                $newTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );
        }
        
        $displayQty = self::formatNumberValue($salesArr['qty']);
        
        if($receiptLayoutMode){

            $menuPrice = number_format($displayPrice, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

            if($printReceiptWrapMenuName){
                $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : AppHelper::fromChinese($salesArr['menuName']);
                $menuName = [];
                $loop = 0;
                $stringMenuName = $tempMenuName;
                while($loop < strlen($stringMenuName)){
                    $length = (strpos(wordwrap($tempMenuName, $charLength - 11), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 11), "\n") : strlen($tempMenuName);
                    $menuName[] = trim(substr($tempMenuName, 0, $length));
                    $tempMenuName = substr($tempMenuName,$length);
                    $loop += $length;
                }
            } else {
                $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : AppHelper::fromChinese($salesArr['menuName']);
                $menuName = strlen($tempMenuName) > $charLength - 11 ? substr($tempMenuName, 0, strpos(wordwrap($tempMenuName, $charLength - 14), "\n")).'...' : substr($tempMenuName,0,$charLength);
            }
            
            if(is_array($menuName)){
                foreach($menuName as $key => $val){
                    $printer->text(str_pad($val, $charLength - 11, ' '));
                    $this->printLineBreak();
                }
            }else{
                $printer->text(str_pad($menuName, $charLength - 11, ' '));
                $this->printLineBreak();
            }

            $printer->text(str_pad($displayQty.'x', 5, ' '));
            $printer->text(' ');
            $printer->text(str_pad('@'.$menuPrice, $charLength - 17, ' '));
            $printer->text(' ');
            $printer->text(str_pad($menuPriceTotal, 10, ' ',STR_PAD_LEFT));
            $this->printLineBreak();

        }else{
        
            if($printReceiptWrapMenuName){
                $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : AppHelper::fromChinese($salesArr['menuName']);
                $menuName = [];
                $loop = 0;
                $stringMenuName = $tempMenuName;
                while($loop < strlen($stringMenuName)){
                    $length = (strpos(wordwrap($tempMenuName, $charLength - 17), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 17), "\n") : strlen($tempMenuName);
                    $menuName[] = trim(substr($tempMenuName, 0, $length));
                    $tempMenuName = substr($tempMenuName,$length);
                    $loop += $length;
                }
            } else {
                $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : AppHelper::fromChinese($salesArr['menuName']);
                $menuName = substr((strlen($tempMenuName) > $charLength - 17 ? substr($tempMenuName, 0, strpos(wordwrap($tempMenuName, $charLength - 20), "\n")).'...': $tempMenuName),0,$charLength - 17);
            }

            $printer->text(str_pad($displayQty, 5, ' '));
            $printer->text(' ');

            if(is_array($menuName)){
                $i = 0;
                foreach($menuName as $key => $val){
                    if($i != 0){
                        $printer->text(str_pad('', 6, ' '));
                    }
                    $printer->text(str_pad($val, $charLength - 17, ' '));
                    
                    if($i == 0){
                        $printer->text(' ');
                        $printer->text(str_pad($menuPriceTotal, 10, ' ', STR_PAD_LEFT));
                    }

                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                    $i++;
                }
            }else{
                $printer->text(str_pad($menuName, $charLength - 17, ' '));
                $printer->text(' ');
                $printer->text(str_pad($menuPriceTotal, 10, ' ', STR_PAD_LEFT));
                $this->printLineBreak();
            }
        }

        $packageDiscountValueTotal = 0;
        if ($salesArr['packages']) {
            foreach ($salesArr['packages'] as $package) {
                $packageModel = Menu::find()
                    ->andWhere(['menuID' => $package['menuID']])
                    ->one();
                $printPackageContent = $packageModel ? $packageModel->flagCustomerPrint : 0;
                $packageName = strlen($package['menuName']) > $charLength - 21 ? substr(
                    AppHelper::fromChinese($package['menuName']),
                    0,
                    $charLength - 24
                ) . '...' : AppHelper::fromChinese($package['menuName']);
                $displayPackagePrice = $this->getDisplayPriceValue(
                    $flagInclusive,
                    $package['total'],
                    $package['discountValue'],
                    $package['qty'],
                    $package['price'],
                    $package['inclusivePrice']
                );
                $packagePriceTotal = $displayPackagePrice == 0 ? ($packageModel ? $packageModel->zeroValueText : 0) : number_format(
                    $salesArr['qty'] * $package['qty'] * $displayPackagePrice,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                );
                if ($packagePriceTotal > 0 || $printPackageContent) {
                    $qtyPackage = $package['qty'] * $salesArr['qty'];
                    if($receiptLayoutMode){
                        if($printReceiptWrapMenuName){
                            $tempPackageName = AppHelper::fromChinese($package['menuName']);
                            $tempMenuNotes = $package['notes'];
                            if (strpos($tempMenuNotes, "\n") !== false) {
                                $tempMenuNotes = str_replace("\n", ", ", $tempMenuNotes);
                            }
                            $packageName = [];
                            $notes = [];
                            $loop = 0;
                            $loopNotes = 0;
                            $stringPackageName = $tempPackageName;
                            $stringMenuNotes = $tempMenuNotes;
                            $packageName['menuName'] = trim(wordwrap($stringPackageName, $charLength - 11, "|", true));
                           
                            if ($this->settings['Show Printing Menu Notes']) {
                                while($loopNotes < strlen($stringMenuNotes)){
                                    $length = (strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") !== false) ? strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") : strlen($tempMenuNotes);
                                    if ($loopNotes == 0) {
                                        $notes[] = '*' . trim(substr($tempMenuNotes, 0, $length));
                                    } else {
                                        $notes[] = trim(substr($tempMenuNotes, 0, $length));
                                    }
                                    $tempMenuNotes = substr($tempMenuNotes, 0, $length);
                                    $loopNotes += $length;
                                }
                                $packageName = array_merge($packageName, $notes);
                            }
                        }else{
                            $packageName = substr((strlen($package['menuName']) > $charLength - 17 ?  substr($package['menuName'], 0, strpos(wordwrap($package['menuName'], $charLength - 20), "\n")).'...' :$package['menuName']), 0, $charLength - 17);
                        }
                        $pricePackageMenu = number_format($displayPackagePrice,$salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
                        if(is_array($packageName)){
                            foreach($packageName as $key => $val){
                                if(strpos($val, '|') !== false){
                                    $texts = explode('|', $val);
                                    foreach($texts as $data => $text){
                                        $printer->text(str_pad('', 6, ' '));
                                        $printer->text(str_pad($text, $charLength - 17, ' '));
                                        $this->printLineBreak();
                                    }
                                } else {
                                    $printer->text(str_pad('', 6, ' '));
                                    $printer->text(str_pad($val, $charLength - 17, ' '));
                                    $this->printLineBreak();
                                }
                            }
                        }else{
                            $printer->text(str_pad('', 6, ' '));
                            $printer->text(str_pad($packageName, $charLength - 17, ' '));
                            $this->printLineBreak();
                        }

                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad(self::formatNumberValue($qtyPackage).'x', 4, ' '));
                        $printer->text(' ');
                        $printer->text(str_pad('@'.$pricePackageMenu, $charLength - 22, ' '));

                        if($printingReceiptMode == "1"){
                            $printer->text(' ');
                            $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        }

                        $this->printLineBreak();
                    }else{
                        if($printReceiptWrapMenuName){
                            $tempMenuName = AppHelper::fromChinese($package['menuName']);
                            $tempMenuNotes = $package['notes'];
                            if (strpos($tempMenuNotes, "\n") !== false) {
                                $tempMenuNotes = str_replace("\n", ", ", $tempMenuNotes);
                            }
                            $packageName = [];
                            $notes = [];
                            $loop = 0;
                            $loopNotes = 0;
                            $stringMenuName = $tempMenuName;
                            $stringMenuNotes = $tempMenuNotes;
                            while($loop < strlen($stringMenuName)){
                                $length = (strpos(wordwrap($tempMenuName, $charLength - 21), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 21), "\n") : strlen($tempMenuName);
                                $packageName[] = trim(substr($tempMenuName, 0, $length));
                                $tempMenuName = substr($tempMenuName,$length);
                                $loop += $length;
                            }
                            if ($this->settings['Show Printing Menu Notes']) {
                                while($loopNotes < strlen($stringMenuNotes)){
                                    $length = (strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") !== false) ? strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") : strlen($tempMenuNotes);
                                    if ($loopNotes == 0) {
                                        $notes[] = '*' . trim(substr($tempMenuNotes, 0, $length));
                                    } else {
                                        $notes[] = trim(substr($tempMenuNotes, 0, $length));
                                    }
                                    $tempMenuNotes = substr($tempMenuNotes, 0, $length);
                                    $loopNotes += $length;
                                }
                                $packageName = array_merge($packageName, $notes);
                            }
                        }else{
                            $packageName = substr((strlen($package['menuName']) > $charLength - 21 ? substr(AppHelper::fromChinese($package['menuName']), 0, strpos(wordwrap($package['menuName'], $charLength - 24), "\n")).'...': AppHelper::fromChinese($package['menuName'])),0,$charLength - 21);
                        }

                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad(self::formatNumberValue($qtyPackage), 3, ' '));
                        $printer->text(' ');

                        if(is_array($packageName)){
                            $i = 0;
                            foreach($packageName as $key => $val){
                                if($i != 0){
                                    $printer->text(str_pad('', (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' '));
                                }
                                $printer->text(str_pad($val, $charLength - 21, ' '));
                                if($i == 0){
                                    if($printingReceiptMode == "1"){
                                        $printer->text(' ');
                                        $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                                    }
                                }
                                $this->printLineBreak();
                                $i++;
                            }
                        }else{
                            $printer->text(str_pad($packageName, $charLength - 21, ' '));
                            if($printingReceiptMode == "1"){
                                $printer->text(' ');
                                $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                            }
                            $this->printLineBreak();
                        }
                    }

                }

                $packageDiscountValueTotal += $package['discountValue'] * $salesArr['qty'];
            }
        }

        $extraDiscountValueTotal = 0;
        if ($salesArr['extras']) {
            foreach ($salesArr['extras'] as $extra) {
                $extraName = strlen($extra['menuExtraName']) > $charLength - 21 ? substr(
                    AppHelper::fromChinese($extra['menuExtraName']),
                    0,
                    $charLength - 24
                ) . '...' : AppHelper::fromChinese($extra['menuExtraName']);
                
                $displayExtraPrice = $this->getDisplayPriceValue(
                    $flagInclusive,
                    $extra['total'],
                    $extra['discountValue'],
                    $extra['qty'],
                    $extra['price'],
                    $extra['inclusivePrice']
                );
                $extraModel = MenuExtra::find()
                    ->with('menu')
                    ->andWhere(['menuExtraID' => $extra['menuExtraID']])
                    ->asArray()->one();
                $extraPriceTotal = $displayExtraPrice == 0 ? ($extraModel ? ($extraModel['menu'] == null ? 0 : $extraModel['menu']['zeroValueText']) : 0) : number_format(
                    $salesArr['qty'] * $extra['qty'] * $displayExtraPrice,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                );

                $qtyExtra = $extra['qty'] * $salesArr['qty'];

                if($receiptLayoutMode){
                    if($printReceiptWrapMenuName){
                        $tempExtraName = AppHelper::fromChinese($extra['menuExtraName']);
                        $extraName = [];
                        $loop = 0;
                        $stringExtraName = $tempExtraName;
                        while($loop < strlen($stringExtraName)){
                            $length = (strpos(wordwrap($tempExtraName, $charLength - 17), "\n") !== false) ? strpos(wordwrap($tempExtraName, $charLength - 17), "\n") : strlen($tempExtraName);
                            $extraName[] = trim(substr($tempExtraName, 0, $length));
                            $tempExtraName = substr($tempExtraName,$length);
                            $loop += $length;
                        } 
                    }else{
                        $extraName = substr((strlen($extra['menuExtraName']) > $charLength - 17 ?  substr($extra['menuExtraName'], 0, strpos(wordwrap($extra['menuExtraName'], $charLength - 20), "\n")).'...' :AppHelper::fromChinese($extra['menuExtraName'])), 0, $charLength - 17);
                    }

                    $priceExtraMenu = number_format($displayExtraPrice,$salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

                    if(is_array($extraName)){
                        foreach($extraName as $key => $val){
                            $printer->text(str_pad('', 6, ' '));
                            $printer->text(str_pad($val, $charLength - 6, ' '));
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }else{
                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad($extraName, $charLength - 17, ' '));
                        $this->printLineBreak();
                    }

                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad(self::formatNumberValue($qtyExtra).'x', 4, ' '));
                    $printer->text(' ');
                    $printer->text(str_pad('@'.$priceExtraMenu, $charLength - 22, ' '));

                    if($printingReceiptMode == "1"){
                        $printer->text(' ');
                        $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                    }

                    $this->printLineBreak();
                }else{
                    if($printReceiptWrapMenuName){
                        $tempMenuName = AppHelper::fromChinese($extra['menuExtraName']);
                        $extraName = [];
                        $loop = 0;
                        $stringMenuName = $tempMenuName;
                        while($loop < strlen($stringMenuName)){
                            $length = (strpos(wordwrap($tempMenuName, $charLength - 21), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 21), "\n") : strlen($tempMenuName);
                            $extraName[] = trim(substr($tempMenuName, 0, $length));
                            $tempMenuName = substr($tempMenuName,$length);
                            $loop += $length;
                        } 
                    }else{
                        $extraName = substr((strlen($extra['menuExtraName']) > $charLength - 21 ? substr($extra['menuExtraName'], 0, strpos(wordwrap($extra['menuExtraName'], $charLength - 24), "\n")).'...': AppHelper::fromChinese($extra['menuExtraName'])),0,$charLength - 21);
                    }

                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad(self::formatNumberValue($qtyExtra), 3, ' '));
                    $printer->text(' ');

                    if(is_array($extraName)){
                        $i = 0;
                        foreach($extraName as $key => $val){
                            if($i != 0){
                                $printer->text(str_pad('', (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' '));
                            }
                            $printer->text(str_pad($val, $charLength - 21, ' '));
                            if($i == 0){
                                if($printingReceiptMode == "1"){
                                    $printer->text(' ');
                                    $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                                }
                            }
                            $this->printLineBreak();
                            $i++;
                        }
                    }else{
                        $printer->text(str_pad($extraName, $charLength - 21, ' '));
                        if($printingReceiptMode == "1"){
                            $printer->text(' ');
                            $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        }
                        $this->printLineBreak();
                    }
                }

                $extraDiscountValueTotal += $extra['discountValue'] * $salesArr['qty'];
            }
        }
        if ($salesArr['notes'] != '' && $this->settings['Show Printing Menu Notes']) {
            $tempMenuNotes = $salesArr['notes'];
            $notesString = $tempMenuNotes;
            if (strpos($notesString, "\n") !== false) {
                $notesString = str_replace("\n", ", ", $notesString);
            }
            if(strlen($notesString) >= $charLength - 7){
                $printer->text(str_pad('', 4, ' '));
                $printer->text('* ');
                $printer->text(substr($notesString,0,$charLength - 7));
                $subString = substr($notesString, $charLength - 7);
                do {
                    $printer->text(str_pad('', 7, ' '));
                    $printer->text(substr($subString,0,$charLength - 7));
                    if(strlen($subString) >= ($charLength - 7)){
                        $subString = substr($subString, $charLength - 7);
                    } else break;
                } while (1);
            } else {
                $printer->text(str_pad('', 4, ' '));
                $printer->text('* ');
                $printer->text($notesString);
            }
            $this->printLineBreak();
        }
        if ($salesArr['allMenuDiscountTotal'] != 0 && $this->settings['Show Menu Promotion Text'] != 0) {
            $promotionDetailName = strlen($salesArr['promotionDetailName']) > $charLength - 20 ? substr(
                $salesArr['promotionDetailName'],
                0,
                $charLength - 23
            ) . '...' : $salesArr['promotionDetailName'];
            $discountText = $promotionDetailName;
            $discountValuetotal = $salesArr['discountValue'] + $packageDiscountValueTotal + $extraDiscountValueTotal;
            $discountTotal = $flagInclusive ? '' : number_format(
                -$discountValuetotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );

            $printer->text(str_pad('', 6, ' '));
            $printer->text(str_pad($discountText, $charLength - 18, ' '));
            $printer->text(str_pad($discountTotal, 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }
        if (($salesArr['price'] == 0 && $salesArr['discount'] == 0 || $salesArr['promotionTypeID'] == 7) && $salesArr['promotionDetailID'] > 0 && ($salesArr['promotionTypeID'] != 9 && $salesArr['promotionTypeID'] != 1)) {
            $printer->text(str_pad('', 4, ' '));
            $printer->text(str_pad($salesArr['promotionDetailName'], $charLength - 16, ' '));
            $printer->text(str_pad('', 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }
    }

    protected function printBillSummary($printOrderList, $printOrder, $totalQty, $lastPrintOrderList, $firstPrintingCount = false, $printByMenuCategory = false)
    {
        $flagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $printerType = $this->stationModel->printerTypeID;

        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printDetailInclusive = array_key_exists('Print Detailed Inclusive Receipt', $this->settings) 
            ? $this->settings['Print Detailed Inclusive Receipt'] : false;

        if (!$printByMenuCategory) {
            $printer->text(str_pad('', $charLength, '-'));
            $this->printLineBreak();
            if ($this->settings['Show Total Item']) {
                $displayQty = self::formatNumberValue($totalQty);
                if ($this->stationModel->printerTypeID == 15) {
                    if ($charLength > 32) {
                        $printer->text(str_pad($displayQty . ($totalQty > 1 ? ' items' : ' item'),
                            11, ' '));
                    } else {
                        $printer->text(str_pad($displayQty . ($totalQty > 1 ? ' items' : ' item'),
                            9, ' '));
                    }
                } else {
                    $printer->text(str_pad($displayQty . ($totalQty > 1 ? ' items' : ' item'),
                        11, ' '));
                }
                if (!$flagInclusive) {
                    $this->printLineBreak();
                }
            }
        }
        
        if (!$flagInclusive || $printDetailInclusive) {
            $printer->text(str_pad(
                Yii::t('app', 'Subtotal'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['subtotal'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }
        $this->printLineBreak();

        if ($printOrder['deliveryCost'] != 0) {
            $printer->text(str_pad(
                Yii::t('app', 'Delivery Cost'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['deliveryCost'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($printOrder['orderFee'] != 0) {
            $changeLabel = ($padType = $printOrder['transactionModeID'] == 5 ? STR_PAD_RIGHT : STR_PAD_LEFT) ? 'Charge' : 'Order Fee';
            if ($printOrder['transactionModeID'] == 5) {
                $printer->text(str_pad(
                    Yii::t('app', 'Restaurant Packaging'),
                    $charLength - 15,
                    ' ',
                    STR_PAD_RIGHT
                ));
                $this->printLineBreak();
            }
            $printer->text(str_pad(
                Yii::t('app', $changeLabel),
                $charLength - 15,
                ' ',
                $padType
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['orderFee'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($printOrder['menuDiscountTotal'] != 0) {
            $printer->text(str_pad(
                Yii::t('app', 'Menu Discount'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['menuDiscountTotal'] * -1,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($printOrder['discountTotal'] != 0) {
            $promotionName = strlen($printOrder['promotionName']) > $charLength - 15 ? substr(
                $printOrder['promotionName'],
                0,
                $charLength - 18
            ) . '...' : $printOrder['promotionName'];

            $printer->text(str_pad(
                $promotionName,
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['discountTotal'] * -1,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($printOrder['otherTaxTotal'] != 0) {
            if ($printOrder['otherTaxTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                $printer->text(str_pad(
                    $this->settings['Other Tax Text'],
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $printOrder['otherTaxTotal'],
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
                $this->printLineBreak();
            }
        }

        if ($this->settings['Show Tax & VAT Amount Detail'] == 0) { 
            if ($printOrder['vatTotal'] != 0) {
                if ($printOrder['vatTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                    $printer->text(str_pad(
                        $this->settings['Tax Text'],
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format(
                        $printOrder['vatTotal'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 12, ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                }
            }
    
            if ($printOrder['otherVatTotal'] != 0) {
                if ($printOrder['otherVatTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                    $printer->text(str_pad(
                        $this->settings['VAT Text'],
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format(
                        $printOrder['otherVatTotal'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 12, ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                }
            }
        } else {
            if (!$flagInclusive || $printDetailInclusive) {
                $printer->text(str_pad(
                    "Tax",
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $printOrder['vatTotal'] + $printOrder['otherVatTotal'],
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
                $this->printLineBreak();
            }
        }

        if (isset($printOrder['platformFee']) && count($printOrder['platformFee']) > 0) {
            foreach ($printOrder['platformFee'] as $row) {
                if ($row['amount'] > 0 && $row['platformFeeTypeID'] == 1) {
                    $printer->text(str_pad(
                        $row['feeNameEN'],
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format(
                        $row['amount'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 12, ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                }
            }
        }

        if (!$flagInclusive || $printDetailInclusive) {
            $printer->text(str_pad('', 8, ' '));
            $printer->text(str_pad('', $charLength - 8, '-'));
            $this->printLineBreak();
        }

        $showBillingRounding = $this->settings['Show Billing Rounding'];
        if ($printOrder['voucherTotal'] > 0 || ($showBillingRounding && $printOrder['roundingTotal'] != 0)) {
            $printer->text(str_pad(
                Yii::t('app', 'Total'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['grandTotal'] - $printOrder['voucherTotal'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($printOrder['voucherTotal'] > 0) {
            $printer->text(str_pad(
                Yii::t('app', 'Voucher Purchase'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['voucherTotal'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        if ($showBillingRounding && $printOrder['roundingTotal'] != 0) {

            $printer->text(str_pad(
                Yii::t('app', 'Rounding'),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $printOrder['roundingTotal'] * -1,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();

            $printer->text(str_pad('', 8, ' '));
            $printer->text(str_pad('', $charLength - 8, '-'));
            $this->printLineBreak();
        }

        if (count($printOrderList) == 1) {
            if ($printerType != 3 && $printerType != 15) {
                $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
            }
        }
        $printer->text(str_pad(
            Yii::t(
                'app',
                count($printOrderList) > 1 ? 'Billing Total' : 'Grand Total'
            ),
            $charLength - 15,
            ' ',
            STR_PAD_LEFT
        ));
        $printer->text(' : ');
        $printer->text(str_pad(number_format(
            $printOrder['grandTotal'] - $printOrder['roundingTotal'],
            $salesDecimalSetting,
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        ), 12, ' ', STR_PAD_LEFT));

        $this->billingTotal = $printOrder['grandTotal'] - $printOrder['roundingTotal'];
        $this->printLineBreak(2);

        // @reset for edot mode
        if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }
        $printer->initialize();
        if (!$lastPrintOrderList) {
            // @Notes: QR for Marugame
            if (isset($this->brandSetting['Loyalty Secret Key']) && 
                isset($this->brandSetting['Receipt QR Code Encryption']) &&
                isset($this->brandSetting['Encryption Key Code Loyalty']) &&
                $firstPrintingCount && ($this->scenario == self::SCENARIO_DEFAULT)) {
                $this->printQRMarugame($printOrder);
            }
            $printer->initialize();
            $showInclusiveTaxSetting = 1;
            $showInclusiveOtherTaxSetting = 1;
            $showInclusiveVATSetting = 1;
            if (array_key_exists('Show Inclusive Tax', $this->settings)) {
                $showInclusiveTaxSetting = $this->settings['Show Inclusive Tax'];
            }
            if (array_key_exists('Show Inclusive Other Tax', $this->settings)) {
                $showInclusiveOtherTaxSetting = $this->settings['Show Inclusive Other Tax'];
            }
            if (array_key_exists('Show Inclusive VAT', $this->settings)) {
                $showInclusiveVATSetting = $this->settings['Show Inclusive VAT'];
            }
            $showInclusiveTax = $showInclusiveTaxSetting == 1 ? true : false;
            $showInclusiveOtherTax = $showInclusiveOtherTaxSetting == 1 ? true : false;
            $showInclusiveVAT = $showInclusiveVATSetting == 1 ? true : false;
            if ($flagInclusive) {
                if ($printOrder['otherTaxTotal'] != 0) {
                    if ($showInclusiveOtherTax && !$printDetailInclusive) {
                        $this->printLineBreak(2);
                        $printer->initialize();
                        $textPrinted = 'Price inclusive of ' . $this->settings['Other Tax Text'];
                        if ($charLength > 32) {
                            $printer->text(str_pad(
                                $textPrinted,
                                $charLength - 15,
                                ' ',
                                STR_PAD_LEFT
                            ));
                            $printer->text(' : ');
                            $printer->text(str_pad(
                                number_format(
                                    $printOrder['otherTaxTotal'],
                                    $salesDecimalSetting,
                                    "$salesDecimalSeparatorSetting",
                                    "$reverseDecimalSeparator" 
                                ),
                                12,
                                ' ',
                                STR_PAD_LEFT
                            ));
                        } else {
                            $printer->text($textPrinted);
                            $printer->text(' : ');
                            $this->printLineBreak();
                            $printer->text(number_format(
                                $printOrder['otherTaxTotal'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ));
                        }
                        $this->printLineBreak();
                    }
                }

                if ($this->settings['Show Tax & VAT Amount Detail'] == 0) {
                    if ($printOrder['vatTotal'] != 0) {
                        if ($showInclusiveTax && !$printDetailInclusive) {
                            $textPrinted = 'Price inclusive of ' . $this->settings['Tax Text'];
                            if ($charLength > 32) {
                                $printer->text(str_pad(
                                    $textPrinted,
                                    $charLength - 15,
                                    ' ',
                                    STR_PAD_LEFT
                                ));
                                $printer->text(' : ');
                                $printer->text(str_pad(
                                    number_format(
                                        $printOrder['vatTotal'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator"
                                    ),
                                    12,
                                    ' ',
                                    STR_PAD_LEFT
                                ));
                            } else {
                                $printer->text($textPrinted);
                                $printer->text(' : ');
                                $this->printLineBreak();
                                $printer->text(number_format(
                                    $printOrder['vatTotal'],
                                    $salesDecimalSetting,
                                    "$salesDecimalSeparatorSetting",
                                    "$reverseDecimalSeparator"
                                ));
                            }
                            $this->printLineBreak(2);
                        }
                    }
                    if ($printOrder['otherVatTotal'] != 0) {
                        if ($showInclusiveVAT && !$printDetailInclusive) {
                            $textPrinted = 'Price inclusive of ' . $this->settings['VAT Text'];
                            if ($charLength > 32) {
                                $printer->text(str_pad(
                                    $textPrinted,
                                    $charLength - 15,
                                    ' ',
                                    STR_PAD_LEFT
                                ));
                                $printer->text(' : ');
                                $printer->text(str_pad(
                                    number_format(
                                        $printOrder['otherVatTotal'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator"
                                    ),
                                    12,
                                    ' ',
                                    STR_PAD_LEFT
                                ));
                            } else {
                                $printer->text($textPrinted);
                                $printer->text(' : ');
                                $this->printLineBreak();
                                $printer->text(number_format(
                                    $printOrder['otherVatTotal'],
                                    $salesDecimalSetting,
                                    "$salesDecimalSeparatorSetting",
                                    "$reverseDecimalSeparator"
                                ));
                            }
                            $this->printLineBreak(2);
                        }
                    }
                } else {
                    $textPrinted = 'Price inclusive of Tax';
                    if ($charLength > 32) {
                        $printer->text(str_pad(
                            $textPrinted,
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(number_format(
                            $printOrder['vatTotal'] + $printOrder['otherVatTotal'],
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ), 12, ' ', STR_PAD_LEFT));
                    } else {
                        $printer->text($textPrinted);
                        $printer->text(' : ');
                        $this->printLineBreak();
                        $printer->text(number_format(
                            $printOrder['vatTotal'] + $printOrder['otherVatTotal'],
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ));
                    }
                    $this->printLineBreak();
                }
            }

            if ($this->settings['Show Tax & VAT Amount Detail'] == 1) {
                $textPrinted = 'Tax included ' . $this->settings['Tax Text'] . ' = ' . number_format($printOrder['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator") . ' and ' . 
                    $this->settings['VAT Text'] . ' = ' . number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
                $printer->text(str_pad($textPrinted,
                        $charLength, ' ', STR_PAD_LEFT));
                $this->printLineBreak(2);
            }
        }

        $printer->initialize();
    }

    protected function printPayment($allBillingGrandTotal, $payments, $availableDepositTotal = null)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $printDepositMemberSetting = isset($this->settings['Print Member Deposit Balance']) ? $this->settings['Print Member Deposit Balance'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $showEcrPrintPaymentInfo = isset($this->settings['Print Card Number & Approval Code']) ? $this->settings['Print Card Number & Approval Code'] : 0;

        $totalPayment = 0;
        $totalPaymentMember = 0;
        $issetNonSalesPaymentMethod = false;
        $isShowRePrint = false;
        foreach ($payments as $payment) {
            $paymentArr = $payment;
            $totalPayment += $paymentArr['paymentAmount'];
            $paymentAmount = $paymentArr['paymentAmount'];

            $fullPaymentAmount = number_format($paymentArr['fullPaymentAmount'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            $paymentMethodName = $paymentArr['flagUseEmployeeLimit'] ? 'Settle By ' . $paymentArr['paymentMethodName'] : $paymentArr['paymentMethodName'];
            if (!empty($paymentArr['accountName']) && trim($paymentArr['accountName']) != '') {
                $paymentMethodName .= " - " . $paymentArr['accountName'];
            }

            if (!empty($paymentArr['selfOrderID']) && $paymentArr['selfOrderID'] != '') {
                if ($paymentArr['paymentMethodTypeID'] === 4 || $paymentArr['paymentMethodTypeID'] === 5) {
                    $paymentMethodName .= "";
                } else {
                    $paymentMethodName .= " - " . $paymentArr['selfOrderID'];
                }
            }

            if ($paymentArr['paymentMethodTypeID'] === 6) {
                $totalPaymentMember = $paymentAmount;

                if(!$isShowRePrint) {
                    $depositSourceID = $paymentArr['depositSourceID'];
                    $check = $this->orderPayment['order']['memberID'] > 0
                    && ($this->orderPayment['order']['externalMembershipTypeID'] === null || $this->orderPayment['order']['externalMembershipTypeID'] === '');
        
                    $isShowRePrint = $check && $depositSourceID != 3;
                }
            }

            $paymentMethodSubStrName = strlen($paymentMethodName) > $charLength - 15 ? substr(
                $paymentMethodName,
                0,
                $charLength - 15
            ) : $paymentMethodName;

            if ($showEcrPrintPaymentInfo && $paymentArr['traceNumber'] != '') {
                $printer->text(str_pad(
                    $paymentMethodSubStrName,
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $paymentAmount,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));

                $printer->feed(1);
                if ($paymentArr['cardNumber']) {
                    $printer->text(str_pad(
                        '(' . $paymentArr['cardNumber'] . ' - ' . $paymentArr['traceNumber'] . ')',
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                } else {
                    $printer->text(str_pad(
                        '(' . $paymentArr['traceNumber'] . ')',
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                }
            } else {
                $printer->text(str_pad(
                    $paymentMethodSubStrName,
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $paymentAmount,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));

                if (!empty($paymentArr['selfOrderID']) && $paymentArr['selfOrderID'] != '') {
                    if ($paymentArr['paymentMethodTypeID'] === 4 || $paymentArr['paymentMethodTypeID'] === 5) {
                        $selfOrderVoucherCode = $paymentArr['voucherCode'];
                        $selfOrderVoucherPrint = 'XXX' . substr($selfOrderVoucherCode, strlen($selfOrderVoucherCode) - 5, strlen($selfOrderVoucherCode));
                        
                        $selfOrderVoucherPrint .= " - " . $fullPaymentAmount;
                        $printer->text(str_pad(
                            $selfOrderVoucherPrint,
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                    }
                }
            }

            // @ Showing voucher code for Other Voucher; And hide ESB Voucher Code (paymentMethodID => -1)
            if (self::showVoucherCodeWithPaymentInPos($paymentArr)) {

                if($paymentArr['voucherSourceID'] == 14){
                    $voucherCode = "(" . $paymentArr['verificationCode'] . ")";
                    $voucherCodePrint = $voucherCode;

                    $printer->text(str_pad(
                        $voucherCodePrint,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));

                    $voucherCodePrintPayment = " : " . $fullPaymentAmount;
                    $printer->text(str_pad(
                        $voucherCodePrintPayment,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));

                } else if($paymentArr['posExternalPaymentID'] === 'uvlpoint'){
                    $voucherCode = "(" . $paymentArr['verificationCode'] . ")";
                    $voucherCodePrint = $voucherCode;

                    $printer->text(str_pad(
                        $voucherCodePrint,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                } else {

                    $voucherCode = "(" . $paymentArr['voucherCode'] . ")";
                    $voucherCodeLength = strlen($voucherCode);
    
                    if ($charLength > 32) {
                        $voucherCodePrint = $voucherCodeLength <= 12 ? $voucherCode : substr_replace($voucherCode, '...', 6, - 6);
                    } else {
                        $voucherCodePrint = $voucherCodeLength <= 7 ? $voucherCode : substr_replace($voucherCode, '...', 1, - 6);
                    }

                    $voucherCodePrint .= " - " . $fullPaymentAmount;
                    $printer->text(str_pad(
                        $voucherCodePrint,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                
                }
            }

            if ($this->employeeInfo && $paymentArr['flagUseEmployeeLimit']) {
                $employeeBalance = null;
                foreach ($this->employeeInfo['employeeLimit'] as $data) {
                    if ($data['paymentMethodID'] == $paymentArr['paymentMethodID']) {
                        $employeeBalance = floatval($data['limitBalance']);
                    }
                }
                if ($employeeBalance) {
                    $this->printLineBreak();
                    $printer->text(str_pad(
                        'Balance ' . $paymentArr['paymentMethodName'],
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(
                        number_format(
                            $employeeBalance,
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ),
                        12,
                        ' ',
                        STR_PAD_LEFT
                    ));
                }
            }

            // @notes: void message jika voucher external
            if ($this->scenario == self::SCENARIO_VOID && $paymentArr['flagExternalVoucherAPI'] == 1) {
                $voucherCode = $paymentArr['voucherCode'];

                $printer->text(str_pad(
                    $voucherCode,
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
                $this->printLineBreak();

                $messageVoid = "Please void this voucher number from your head office";
                $printer->text(str_pad(
                    $messageVoid,
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
            } else if ($paymentArr['flagExternalVoucherAPI'] == 1) {
                $voucherCode = $paymentArr['voucherCode'];
                $voucherCodeLength = strlen($voucherCode);
                $voucherPrint = 'XXXXXX' . substr($voucherCode, $voucherCodeLength - 6, $voucherCodeLength);

                $voucherPrint .= " - " . $fullPaymentAmount;
                $printer->text(str_pad(
                    $voucherPrint,
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
            }
            $this->printLineBreak();

            if ($paymentArr['paymentMethodTypeID'] == self::PAYMENT_NON_SALES) {
                $issetNonSalesPaymentMethod = true;
                $printer->text(str_pad(
                    $paymentArr['notes'],
                    $charLength - 15,
                    ' ',
                    STR_PAD_LEFT
                ));
            }
        }

        if($this->scenario != self::SCENARIO_REPRINT
            && $this->scenario != self::SCENARIO_EDITED
            && $this->scenario != self::SCENARIO_VOID
            && $printDepositMemberSetting 
            && $isShowRePrint
        ) {
            $checkConditionMember = isset($this->orderPayment['order']['memberCode']) && $this->orderPayment['order']['memberCode'];

            if ($checkConditionMember) {
                $availableDepositTotal = MemberDepositWithdrawalOnline::getActiveMemberBalance($this->orderPayment['order']['memberCode'], $availableDepositTotal);
            }

            $this->printLineBreak();

            $textPrinted = 'Previous Balance';
            $availabelDeposit = $availableDepositTotal + $totalPaymentMember;
            $printer->text(str_pad(
                $textPrinted,
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $availabelDeposit,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
    
            $textPrinted = 'Current Balance';
            $availabelDeposit = $availableDepositTotal;
            $printer->text(str_pad(
                $textPrinted,
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $availabelDeposit,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));

            $this->printLineBreak();
        }

        // @notes: remaining balance sti reader
        if ( $payment['posExternalPaymentID'] === 'emoney') {
            $paddintText = $charLength > 32 ? 35 : 20;
            $this->printLineBreak();
            $textPrinted = 'Balance';
            $modelSTIpayment = SalesPaymentStiReader::getRemainingBalance($payment['salesNum']);
            $availabelDeposit = $modelSTIpayment ? $modelSTIpayment->remainBalance : 0;
            $printer->text(str_pad(
                $textPrinted,
                $charLength - $paddintText,
                ' ',
                STR_PAD_RIGHT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $availabelDeposit,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 15 , ' ', STR_PAD_RIGHT));

            $this->printLineBreak();
            $textPrinted = 'Card Number';
            $cardNumber = str_replace('.','', $payment['cardNumber']);
            $printer->text(str_pad(
                $textPrinted,
                $charLength - $paddintText,
                ' ',
                STR_PAD_RIGHT
            ));
            $printer->text(' : ');
            $printer->text($cardNumber);
            $this->printLineBreak();
            $textPrinted = 'TID Number';
            $tidNumber = $modelSTIpayment ? $modelSTIpayment->TID : 0;
           $printer->text(str_pad(
                $textPrinted,
                $charLength - $paddintText,
                ' ',
                STR_PAD_RIGHT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(
                $tidNumber,
                $charLength - $paddintText,
                ' ',
                STR_PAD_RIGHT
            ));
            $this->printLineBreak();
        }

        $allBillingGrandTotal = round($allBillingGrandTotal, 2);
        $totalPayment = round($totalPayment, 2);
        if ($allBillingGrandTotal != $totalPayment) {
            $printer->text(str_pad('', 8, ' '));
            $printer->text(str_pad('', $charLength - 8, '-'));
            $this->printLineBreak();

            $changeLabel = $issetNonSalesPaymentMethod ? 'Menu Discount' : 'Change';
            $printer->text(str_pad(
                Yii::t('app', $changeLabel),
                $charLength - 15,
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $totalPayment - $allBillingGrandTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        $this->printLineBreak();
    }

    protected function printFooter($branchModel, $tableID, $queueNum, $flagInclusive, $otherTax, $taxVal, $printOrder = null)
    {
        $printer = $this->printer;
        $printer->initialize();
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printDetailInclusive = array_key_exists('Print Detailed Inclusive Receipt', $this->settings) 
            ? $this->settings['Print Detailed Inclusive Receipt'] : false;

        $showInclusiveTaxSetting = 1;
        $showInclusiveOtherTaxSetting = 1;
        $showInclusiveVATSetting = 1;
        if (array_key_exists('Show Inclusive Tax', $this->settings)) {
            $showInclusiveTaxSetting = $this->settings['Show Inclusive Tax'];
        }
        if (array_key_exists('Show Inclusive Other Tax', $this->settings)) {
            $showInclusiveOtherTaxSetting = $this->settings['Show Inclusive Other Tax'];
        }
        if (array_key_exists('Show Inclusive VAT', $this->settings)) {
            $showInclusiveVATSetting = $this->settings['Show Inclusive VAT'];
        }
        $showInclusiveTax = $showInclusiveTaxSetting == 1 ? true : false;
        $showInclusiveOtherTax = $showInclusiveOtherTaxSetting == 1 ? true : false;
        $showInclusiveVAT = $showInclusiveVATSetting == 1 ? true : false;
        if ($flagInclusive) {
            if (($showInclusiveOtherTax && !$printDetailInclusive) && $otherTax > 0) {
                $printer->text('');
                $printer->feed(1);

                $printer = $this->printer;
                $textPrinted = 'Price inclusive of ' . $this->settings['Other Tax Text'];
                if ($charLength > 32) {
                    $printer->text(str_pad(
                        $textPrinted,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(
                        number_format(
                            $printOrder['otherTaxTotal'],
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ),
                        12,
                        ' ',
                        STR_PAD_LEFT
                    ));
                } else {
                    $printer->text($textPrinted);
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text(number_format(
                        $printOrder['otherTaxTotal'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ));
                }
                $this->printLineBreak();
            }
            if ($this->settings['Show Tax & VAT Amount Detail'] == 0) {
                if (($showInclusiveTax && !$printDetailInclusive) && $taxVal > 0) {
                    $textPrinted = 'Price inclusive of ' . $this->settings['Tax Text'];
                    if ($charLength > 32) {
                        $printer->text(str_pad(
                            $textPrinted,
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(number_format(
                            $taxVal,
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ), 12, ' ', STR_PAD_LEFT));
                    } else {
                        $printer->text($textPrinted);
                        $printer->text(' : ');
                        $this->printLineBreak();
                        $printer->text(number_format(
                            $taxVal,
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ));
                    }
                    $this->printLineBreak();
                }
                if (($showInclusiveVAT && !$printDetailInclusive) && $printOrder['otherVatTotal'] > 0) {
                    $textPrinted = 'Price inclusive of ' . $this->settings['VAT Text'];
                    if ($charLength > 32) {
                        $printer->text(str_pad(
                            $textPrinted,
                            $charLength - 15,
                            ' ',
                            STR_PAD_LEFT
                        ));
                        $printer->text(' : ');
                        $printer->text(str_pad(number_format(
                            $printOrder['otherVatTotal'],
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ), 12, ' ', STR_PAD_LEFT));
                    } else {
                        $printer->text($textPrinted);
                        $printer->text(' : ');
                        $this->printLineBreak();
                        $printer->text(number_format(
                            $printOrder['otherVatTotal'],
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"
                        ));
                    }
                    $this->printLineBreak();
                }
            } else {
                $textPrinted = 'Price inclusive of Tax';
                if ($charLength > 32) {
                    $printer->text(str_pad(
                        $textPrinted,
                        $charLength - 15,
                        ' ',
                        STR_PAD_LEFT
                    ));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format(
                        $taxVal + $printOrder['otherVatTotal'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 12, ' ', STR_PAD_LEFT));
                } else {
                    $printer->text($textPrinted);
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text(number_format(
                        $taxVal + $printOrder['otherVatTotal'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ));
                }
                $this->printLineBreak();
            }
            $this->printLineBreak();
        }

        if ($this->settings['Show Tax & VAT Amount Detail'] == 1) {
            $textPrinted = 'Tax included ' . $this->settings['Tax Text'] . ' = ' . number_format($taxVal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator") . ' and ' . 
                $this->settings['VAT Text'] . ' = ' . number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            $printer->text(str_pad($textPrinted,
                    $charLength, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }

        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $textFooters = explode('>><<', $branchModel->printingFooter);
        $countLinteFooter = sizeof($textFooters);
        for ($i=0; $i < $countLinteFooter; $i++) { 
            $printer->text($textFooters[$i]);
            if ($i < $countLinteFooter - 1) {
                $this->printLineBreak();
            }
        }

        if (!$this->orderPayment['salesLink']) {
            if (array_key_exists('Queue Number', $this->settings) && array_key_exists('Hide Queue Number', $this->settings)) {
                $transactionModeID = isset($this->orderPayment['transactionModeID']) ? $this->orderPayment['transactionModeID'] : 1;
                $visitPurposeModel = VisitPurpose::find()
                    ->andWhere(['visitPurposeID' => $printOrder['visitPurposeID']])
                    ->one();
                $showQueueNum = $visitPurposeModel->flagShowQueue ? $visitPurposeModel->flagShowQueue : 0;

                if ($this->settings['Queue Number'] == 1 && $this->settings['Hide Queue Number'] == 0 && $showQueueNum == 1 && ($transactionModeID !== 3 || $transactionModeID !== 4)) {
                    $charLength = $this->stationModel->characterPerLine;
                    $this->printLineBreak(2);
                    if ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15) {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        if ($charLength > 32) {
                            $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                        }
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                    $printer->text('Your queue number');
                    $this->printLineBreak();

                    if ($this->stationModel->printerTypeID == '4') {  
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                    } elseif ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15) {                        
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT | ExtPrinter::MODE_DOUBLE_WIDTH);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x57" . "2");
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                    
                    $printer->text($queueNum);
                    $this->printLineBreak();
                    $printer->initialize();
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                    } else {
                        $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                    }
                    $printer->text('Please wait until your number is called');
                    $printer->feed(1);
                }
            }
        }

        if ($branchModel) {
            $filename = 'picfoot-' . $branchModel->branchCode . '.png';
            $inputFileName = Yii::$app->basePath . '/web/images/' . $filename;
            if (file_exists($inputFileName)) {
                $printer->feed(1);
                $img = EscposImage::load($inputFileName);

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                } else {
                    $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                }

                if ($this->stationModel->printerTypeID == '3') {
                    $printer->bitImageColumnFormat(
                        $img,
                        ExtPrinter::IMG_DOUBLE_WIDTH | ExtPrinter::IMG_DOUBLE_HEIGHT
                    );
                } elseif ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->bitImageMpop($img);
                } else {
                    $printer->bitImage($img);
                }
            }
        }
        $this->printLineBreak();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $receiptText = '';
        if ($this->scenario != self::SCENARIO_REPRINT && $this->scenario != self::SCENARIO_EDITED && $this->scenario != self::SCENARIO_VOID) {
            $receiptText = ReceiptTextHead::getReceiptText($this->billingTotal);
        }
        if ($receiptText != '') {
            $printer->text($receiptText);
            $this->printLineBreak();
        }
    }

    protected function printBillCustomerCode($printOrder)
    {
        $printer = $this->printer;
        $branchID = Setting::getCurrentBranch();

        $printer->initialize();
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $printer->text('   Customer Code : ');
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $digit1_2 = "16";
        $digit3_5 = str_pad(substr($branchID,0,3),3,"0",STR_PAD_LEFT);
        $digit6 = " ";
        $digit7_9 = substr($printOrder['billNum'], -3) ;
        $terminalID = $this->orderPayment['order']['terminalID'];
        $digit1_2_afterT_ID = str_pad(date("m"),2,"0",STR_PAD_LEFT);
        $digit3_4_afterT_ID = str_pad(date("d"),2,"0",STR_PAD_LEFT);
        $digit5_afterT_ID = " ";
        $digit6_7_afterT_ID = str_pad(date("h"),2,"0",STR_PAD_LEFT);
        $digit8_9_afterT_ID = "16";
        $printer->text(
                        $digit1_2.
                        $digit3_5.
                        $digit6.
                        $digit7_9.
                        $terminalID.
                        $digit1_2_afterT_ID.
                        $digit3_4_afterT_ID.
                        $digit5_afterT_ID.
                        $digit6_7_afterT_ID.
                        $digit8_9_afterT_ID
                    );
                    
        $this->printLineBreak();
    }

    protected function printBillCustomerCodeMBA($printOrder) {
        $printer = $this->printer;
        $printer->initialize();
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $printer->text('   Customer Code : ');
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $principleMBAID = null;
        if (strlen($this->settings['Principle MBA ID']) === 1) {
            $principleMBAID = '000' . $this->settings['Principle MBA ID'];
        } else if (strlen($this->settings['Principle MBA ID']) === 2) {
            $principleMBAID = '00' . $this->settings['Principle MBA ID'];
        } else if (strlen($this->settings['Principle MBA ID']) === 3) {
            $principleMBAID = '0' . $this->settings['Principle MBA ID'];
        } else if (strlen($this->settings['Principle MBA ID']) === 4) {
            $principleMBAID = $this->settings['Principle MBA ID'];
        }
 
        $digit1_2 = substr($this->brandSetting['MBA First Code'], -2);
        $digit3_6 = $principleMBAID;
        $ditit7_9 = substr($printOrder['billNum'], -3);
        $digit10 = substr($printOrder['terminalID'], -1);
        $digit11_12 = substr($printOrder['salesDateOut'], 5, 2);
        $digit13_14 = substr($printOrder['salesDateOut'], 8, 2);
        $digit15_16 = substr($printOrder['salesDateOut'], 11, 2);
        $digit17_18 = substr($this->brandSetting['MBA Last Code'], -2);
        $printer->text(
            $digit1_2.
            $digit3_6.
            $ditit7_9.
            $digit10.
            $digit11_12.
            $digit13_14.
            $digit15_16.
            $digit17_18
        );
        $this->printLineBreak();
    }

    protected function printQueue($queueNum, $printOrder = null)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $visitPurposeModel = VisitPurpose::find()
            ->andWhere(['visitPurposeID' => $printOrder['visitPurposeID']])
            ->one();
        $showQueueNum = $visitPurposeModel->flagShowQueue ? $visitPurposeModel->flagShowQueue : 0;

        if ($showQueueNum == 1) {
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }
    
            if (array_key_exists('Queue Number', $this->settings) && array_key_exists('Hide Queue Number', $this->settings)) {
                $charLength = $this->stationModel->characterPerLine;
                if ($this->settings['Queue Number'] == 1 && $this->settings['Hide Queue Number'] == 0) {
                    if ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15) {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        if ($charLength > 32) {
                            $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                        }
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                    $printer->text('Your queue number');
                    $this->printLineBreak();
    
                    if ($this->stationModel->printerTypeID == '4') {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                    } elseif ($this->stationModel->printerTypeID != '4' && $this->stationModel->printerTypeID != 15) {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT | ExtPrinter::MODE_DOUBLE_WIDTH);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x57" . "2");
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                    
                    $printer->text($queueNum);
                    $this->printLineBreak();
                    $printer->initialize();
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                    } else {
                        $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                    }
                    $printer->text('Please wait until your number is called');
                }
            }
    
            $this->printLineBreak();
            $printer->text(str_pad('', $charLength, '-'));
            $printer->initialize();
        }
    }

    public function printQR($order, $localSettings)
    {
        if (array_key_exists('GF Application Directory', $localSettings)) {
            $gfAppDir = $localSettings['GF Application Directory'];
            $gfOutletCode = $localSettings['GF Outlet Code'];
            $rewardSalesDate = AppHelper::convertDateTimeFormat(
                $order['salesDateOut'],
                'Y-m-d H:i:s',
                'd/m/Y'
            );
            $rewardSalesTime = AppHelper::convertDateTimeFormat(
                $order['salesDateOut'],
                'Y-m-d H:i:s',
                'H:i:s'
            );
            $rewardSalesNum = $order['billNum'];
            $rewardSubtotal = intval($order['subtotal'] - $order['discountTotal'] - $order['menuDiscountTotal']);
            $externalCode = shell_exec("$gfAppDir $gfOutletCode $rewardSalesDate $rewardSalesTime $rewardSalesNum $rewardSubtotal");

            if ($externalCode != '') {
                $printer = $this->printer;
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                } else {
                    $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                }
                $this->generateQR($externalCode, $printer);
                $this->printLineBreak();
                $printer->text('SCAN ME NOW');
                $this->printLineBreak();
                $printer->text($localSettings['GF Printing Text'] . date_format(
                    date_create($order['salesDateOut']),
                    'H:i'
                ));
                $this->printLineBreak(2);
            }
        }
    }

    public function printQRGuestComment($externalCode, $QRCaption)
    {

        if ($externalCode != '') {
            $printer = $this->printer;
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }
            $this->generateQR($externalCode, $printer);
            $this->printLineBreak();
            $printer->text($QRCaption);
            $this->printLineBreak();
        }
    }

    public function printQRMarugame($printOrder)
    {
        $encryptedText = AppHelper::generateQrText($printOrder['salesNum'], $printOrder, $this->brandSetting);
        if ($encryptedText != null) {
            $QRCaption = 'Please Scan Here';
            if (isset($this->settings['Receipt Custom QR Text'])) {
                $QRCaption = $this->settings['Receipt Custom QR Text'];
            }
            $printer = $this->printer;
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }
            $this->generateQR($encryptedText, $printer, true);
            $this->printLineBreak();
            $printer->text($QRCaption);
            $this->printLineBreak();
        }
    }

    protected function generateQR($externalCode, $printer, $forceQrSmallSize = false)
    {
        $charLength = $this->stationModel->characterPerLine;
        $localSettings = Setting::getLocalSettings();
        $qrSize = 6;
        if (array_key_exists('Receipt QR Code Encryption', $localSettings)) {
            if ($localSettings['Receipt QR Code Encryption'] === 'AES') {
                if ($charLength > 37) {
                    $qrSize = 8;
                } else if ($charLength >= 33 && $charLength < 38) {
                    $qrSize = 7;
                }

                if ($forceQrSmallSize === true) {
                    $qrSize = 7;
                }

            }
        }

        $filename = Yii::$app->basePath . '/web/assets_b/images/' . md5(uniqid(
            rand(),
            true
        )) . '.png';
        require_once(Yii::$app->basePath . '/web/phpqrcode/qrlib.php');
        \QRcode::png($externalCode, $filename, 'L', $qrSize, 0);
        $img = EscposImage::load($filename);

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->bitImageMpop($img);
        } else {
            $printer->bitImage($img);
        }
        unlink($filename);
    }

    protected function printHeaderGroupMenuCategory()
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();
    }

    protected function printGroupMenuCategory($menuCategory)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesArr = $menuCategory;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $menuCategoryDesc = substr(
            'Total' . ' ' . $salesArr['menuCategoryDesc'],
            0,
            $charLength - 16
        );
        $total = number_format(
            $salesArr['total'],
            $salesDecimalSetting,
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        );

        $printer->text(str_pad($menuCategoryDesc, $charLength - 14, ' '));
        $printer->text(str_pad($total, 14, ' ', STR_PAD_LEFT));
        $this->printLineBreak();
    }

    public function getDisplayPriceValue($flagInclusive, $total, $discountValue, $qty, $price, $inclusivePrice)
    {
        if ($flagInclusive) {
            $price = $inclusivePrice;
        }
        return $price;
    }

    public function getVoucherCashback()
    {
        try {
            $voucherCashbackUsed = Voucher::getVoucherCashbackUsedBySalesNum($this->salesNum);

            // @refactor http_helper
            $order = $this->orderPayment['order'];
            $httpService = new HttpHelperService();
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl();
            $url = $apiUrl . '/esb_api/voucher/claim-voucher-template';
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $datas =   [
                'branchID' => $order['branchID'],
                'subtotal' => $order['subtotal'],
                'grandTotal' => $order['grandTotal'],
                'voucherCashbackUsed' => $voucherCashbackUsed,
                'salesNum' => $this->salesNum
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
            
            $voucherCode = '';
            $additionalInfo = '';
            $voucher = '';
            if ($response->getData()['status'] == '00') {
                $voucherCode = $response->getData()['voucherID'];
                $additionalInfo = $response->getData()['additionalInfo'];
                $voucherLength = $response->getData()['voucherLength'];
                $voucher = [
                    'voucherCode' => $voucherCode,
                    'additionalInfo' => $additionalInfo,
                    'voucherLength' => $voucherLength
                ];
            }
            
            return $voucher;
        } catch (Exception $ex) {
            Yii::error($ex);
            return '';
        }
    }

    public function printVoucherTemplate()
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $voucher = VoucherTemplate::generateModel($this->salesNum, $this->voucherOnlineCashback);
        if ($voucher && isset($voucher['voucherCode'])) {
            $printer->text(str_pad('', $charLength, '-'));
            $this->printLineBreak();


            if ($this->stationModel->printerTypeID != 3 && $this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED);
            }
            $this->generateQR($voucher['voucherCode'], $printer);
            $printer->feed(1);
            $printer->text('Voucher Code :');
            $this->printLineBreak();
            $printer->text($voucher['voucherCode']);
            $this->printLineBreak();

            $printer->initialize();
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_LEFT);
            }

            $printer->text(str_pad(
                Yii::t('app', 'Voucher Amount'),
                $charLength - 15,
                ' ',
                STR_PAD_RIGHT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $voucher['voucherAmount'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();

            $printer->text(str_pad(
                Yii::t('app', 'Min. Purchase'),
                $charLength - 15,
                ' ',
                STR_PAD_RIGHT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $voucher['minimumSalesPrice'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();

    
            $printer->feed(1);
            $printer->text('*S&K');
            $printer->feed(1);
            foreach (explode('>><<', $voucher['additionalInfo']) as $lineAdditionalInfo) {
                $printer->text($lineAdditionalInfo);
                $this->printLineBreak();
            }

            $printer->initialize();
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }

            $this->printResult = ['status' => true, 'message' => null];
        } else {
            $this->isErrorGenerateVoucher = true; 
            $this->printResult = ($voucher && isset($voucher['status']) && $voucher['status'] == false) ? 
            ['status' => $voucher['status'], 'message' => $voucher['message']] : ($this->printResult === null ? ['status' => true, 'message' => null] : $this->printResult);
        }
    }

    private function getHttpClientVoucher($action)
    {
        $client = new Client();
        $apiKey = Setting::getApiKey();
        $apiUrl = Setting::getApiUrl();
        return $client->post($apiUrl . "/esb_api/employee/" . $action)
            ->addHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey
            ]);
    }

    public function validateEmployee($employeeCode)
    {
        if (!$this->validate()) {
            return false;
        }
        try {
            $branchID = Setting::getCurrentBranch();
            $client = $this->getHttpClientVoucher('validate');
            $response = $client->addData([
                'branchID' => $branchID,
                'employeeCode' => $employeeCode
            ])
                ->send();
            if ($response->getData()['status'] == '00') {
               
                $result = $response->getData()['result'];
            } else {
                $result = [];
                throw new NotFoundHttpException();
            }
            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

    public function doSendEmail() {
        try {
            $apiKey = Setting::getApiKey();
            $rootApiUrl = Setting::getApiUrl();
            $apiVersion = 'esb_api';
            $apiUrl = $rootApiUrl . '/'. $apiVersion . '/sales/send-receipt-email';

            $taxName = Setting::getValue1('POS', 'Tax Text');
            $taxName = !isset($taxName) ? "-" : $taxName;
            
            $orderPayment = SalesHead::findOrderPaymentAsArray(null, $this->salesNum, false, true);
            $mainBill = $orderPayment['order'];

            $mainSalesModel = SalesHead::findMainSales(null, $this->salesNum);
            $branchModel = Branch::find()
                ->andWhere([
                    Branch::tableName() . '.flagActive' => 1,
                    Branch::tableName() . '.branchID' => $mainSalesModel->branchID
                ])->one();
            
            $vatName = $branchModel && $branchModel->vatName ? $branchModel->vatName : '-';
            
            $salesData = [];
            $customerName = 'Customer';
            $paymentPrintCount = $mainBill['paymentPrintCount'];
            $salesHeadData = [
                'salesNum' => $mainBill['salesNum'],
                'billNum' => $mainBill['billNum'],
                'salesDateOut' => $mainBill['salesDateOut'],
                'tableName' => $mainBill['tableName'] . $mainBill['mergeTableNames'],
                'subtotal' => (float) $mainBill['subtotal'],
                'discountTotal' => (float) $mainBill['discountTotal'],
                'menuDiscountTotal' => (float) $mainBill['menuDiscountTotal'],
                'otherTaxTotal' => (float) $mainBill['otherTaxTotal'],
                'vatTotal' => (float) $mainBill['vatTotal'],
                'otherVatTotal' => (float) $mainBill['otherVatTotal'],
                'grandTotal' => (float) $mainBill['grandTotal'],
                'voucherTotal' => (float) $mainBill['voucherTotal'],
                'roundingTotal' => (float) $mainBill['roundingTotal'],
                'flagInclusive' => $mainBill['flagInclusive'],
                'orderFee' => (float) $mainBill['orderFee'],
                'deliveryCost' => (float) $mainBill['deliveryCost'],
            ];

            $salesMenuData = [];
                if (isset($mainBill['salesMenu'])) {
                    foreach ($mainBill['salesMenu'] as $salesMenu) {
                        $packages = [];
                        if (isset($salesMenu['packages'])) {
                            foreach ($salesMenu['packages'] as $package) {
                                $packages[] = [
                                    'menuName' => $package['menuName'],
                                    'qty' => (float) $package['qty'],
                                    'inclusivePrice' => (float) $package['inclusivePrice'],
                                    'price' => (float) $package['price'],
                                    'notes' => $package['notes'],
                                    'packages' => (array)[],
                                    'extras' => (array)[]
                                ];
                            }
                        }
        
                        $extras = [];
                        if (isset($salesMenu['extras'])) {
                                foreach ($salesMenu['extras'] as $extra) {
                                    $extras[] = [
                                        'menuName' => $extra['menuName'],
                                        'qty' => (float) $extra['qty'],
                                        'inclusivePrice' => (float) $extra['inclusivePrice'],
                                        'price' => (float) $extra['price'],
                                        'packages' => (array)[],
                                        'extras' => (array)[]
                                    ];
                                }
                        }
        
                        $salesMenuData[] = [
                            'menuName' => $salesMenu['menuName'],
                            'qty' => (float) $salesMenu['qty'],
                            'price' => (float) $salesMenu['price'],
                            'inclusivePrice' => (float) $salesMenu['inclusivePrice'],
                            'notes' => $salesMenu['notes'],
                            'packages' => count($packages) > 0 ? (object) $packages : (array)[],
                            'extras' => count($extras) > 0 ? (object) $extras : (array)[]
                        ];
                    }
                }

            $salesData = [
                'salesHead' => $salesHeadData,
                'salesMenus' => $salesMenuData
            ];
            
            $salesPaymentData = [];
            if (isset($orderPayment['salesPayment'])){
                foreach ($orderPayment['salesPayment'] as $salesPayment) {
                    $salesPaymentData[] = [
                        'paymentMethodName' => $salesPayment['paymentMethodName'],
                        'paymentAmount' => (float) $salesPayment['paymentAmount']
                    ];
                }
            }

            $linkedSales = $orderPayment['salesLink'];
            $salesLinkData = [];
            foreach ($linkedSales as $bill) {
                $customerName = 'Customer';
                $salesHeadData = [
                    'salesNum' => $bill['salesNum'],
                    'billNum' => $bill['billNum'],
                    'salesDateOut' => $bill['salesDateOut'],
                    'tableName' => $bill['tableName'],
                    'subtotal' => (float) $bill['subtotal'],
                    'discountTotal' => (float) $bill['discountTotal'],
                    'menuDiscountTotal' => (float) $bill['menuDiscountTotal'],
                    'otherTaxTotal' => (float) $bill['otherTaxTotal'],
                    'vatTotal' => (float) $bill['vatTotal'],
                    'otherVatTotal' => (float) $bill['otherVatTotal'],
                    'grandTotal' => (float) $bill['grandTotal'],
                    'voucherTotal' => (float) $bill['voucherTotal'],
                    'roundingTotal' => (float) $bill['roundingTotal'],
                    'flagInclusive' => $bill['flagInclusive'],
                    'orderFee' => (float) $bill['orderFee'],
                    'deliveryCost' => (float) $bill['deliveryCost'],
                ];

                $salesMenuData = [];
                if (isset($bill['salesMenu'])){
                    foreach ($bill['salesMenu'] as $salesMenu) {
                        $packages = [];
                        if (isset($salesMenu['packages'])){
                            foreach ($salesMenu['packages'] as $package) {
                                $packages[] = [
                                    'menuName' => $package['menuName'],
                                    'qty' => (float) $package['qty'],
                                    'inclusivePrice' => (float) $package['inclusivePrice'],
                                    'price' => (float) $package['price'],
                                    'notes' => $package['notes'],
                                    'packages' => (array)[],
                                    'extras' => (array)[]
                                ];
                            }
                        }
    
                        $extras = [];
                        if(isset($salesMenu['extras'])){
                            foreach ($salesMenu['extras'] as $extra) {
                                $extras[] = [
                                    'menuName' => $extra['menuName'],
                                    'qty' => (float) $extra['qty'],
                                    'inclusivePrice' => (float) $extra['inclusivePrice'],
                                    'price' => (float) $extra['price'],
                                    'packages' => (array)[],
                                    'extras' => (array)[]
                                ];
                            }
                        }
    
                        $salesMenuData[] = [
                            'menuName' => $salesMenu['menuName'],
                            'qty' => (float) $salesMenu['qty'],
                            'price' => (float) $salesMenu['price'],
                            'inclusivePrice' => (float) $salesMenu['inclusivePrice'],
                            'notes' => $salesMenu['notes'],
                            'packages' => count($packages) > 0 ? (object) $packages : (array)[],
                            'extras' => count($extras) > 0 ? (object) $extras : (array)[]
                        ];
                    }
                }

                $salesLinkData[] = [
                    'salesHead' => $salesHeadData,
                    'salesMenus' => (object) $salesMenuData
                ];
            }

            $salesPaymentData = (object) $salesPaymentData;
            $salesLinkData = count($salesLinkData) > 0 ? (object) $salesLinkData : (array)[];

            $branchModel->printingHeader = str_replace('>><<', '<br>', $branchModel->printingHeader);
            $branchModel->printingFooter = str_replace('>><<', '<br>', $branchModel->printingFooter);
            $payload = [
                'printingHeader' => $branchModel->printingHeader,
                'printingFooter' => $branchModel->printingFooter,
                'branchName' => $branchModel->branchName,
                'guestName' => $customerName,
                'createdBy' => Yii::$app->user->identity->username,
                'emailTo' => $this->email,
                'subject' => 'Receipt from ' . $branchModel->branchName . ($mainSalesModel->paymentPrintCount > 1 ? ' - Reprint ' . $mainSalesModel->paymentPrintCount : ''),
                'taxName' => $taxName,
                'vatName' => $vatName,
                'additionalTaxName' => $branchModel->additionalTaxName,
                'salesData' => $salesData,
                'salesLinks' => $salesLinkData,
                'salesPayments' => $salesPaymentData,
                'paymentPrintCount' => $paymentPrintCount
            ];

            $client = new Client();
            $response = $client->createRequest()
                ->setUrl($apiUrl)
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->setData($payload)
                ->setFormat(Client::FORMAT_JSON)
                ->setOptions([
                    'timeOut' => 300
                ])
                ->send();

            if ($response->getIsOk()) {
                return $response->getData();
            } else {
                Yii::error('Cannot connect to ' . $apiUrl);
                throw new Exception('Failed to send email');
            }
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public function printQrParking($printOrder) 
    {
        $data = LippoVoucher::saveVoucher($printOrder);
        if ($data != null && !isset($data['status'])) {
            $QrData = $data['QRData'];
            $expiredCaption = date('d-m-Y H:i', strtotime($data['ExpireOn']));
            $QrCaption = $data['Description'];
            $printer = $this->printer;
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }
            $this->generateQR($QrData, $printer);
            $this->printLineBreak();
            $printer->text($QrCaption);
            $this->printLineBreak();
            $printer->text('Expired on');
            $this->printLineBreak();
            $printer->text($expiredCaption);
            $this->printLineBreak();

            $this->printResult = ['status' => true, 'message' => null];
        } else {
            $this->isErrorGenerateParkingVoucher = true;
            if ($data && isset($data['status'])) {
                $this->printResult = ['status' => $data['status'], 'message' => $data['message']];
            } else {
                $this->printResult = ['status' => true, 'message' => null];
            }
        }
    }

    private function formatNumberValue($number){
        return AppHelper::formatNumberValue($number, null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator);
    }
    
    public function printLoopLiteQR()
    {
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $printQRReinstatementPointESBLoop = Setting::getValue1('POS', 'QR Reinstatement Point ESB Loop');

        if ($externalMemberSetting['Membership Type'] != 'looplite') 
            return;

        if ($printQRReinstatementPointESBLoop != 1)
            return;

        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID != 3) {
            $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED);
        }
        $this->generateQR($this->salesNum, $printer);
        $printer->feed(1);

        $textQR = 'Please Scan Here';
        if (isset($this->settings['Receipt Custom QR Text']) && !empty($this->settings['Receipt Custom QR Text'])) {
            $textQR = $this->settings['Receipt Custom QR Text'];
        }

        $printer->text($textQR);
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->initialize();
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }
    }

    protected function printLineBreak($feed = 1, $textSymbol = null) {
        $printer = $this->printer;
        
        if ($textSymbol !== null) {
            $printer->text(str_pad('', $this->stationModel->characterPerLine, $textSymbol));
        }
        
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            for ($i=0; $i < $feed; $i++) { 
                $printer->getPrintConnector()->write("\x0A");
            }
        } else {
            $printer->feed($feed);
        }
    }

    protected function printByMenuCategory($printOrder, $menuCategory, $salesMenu, $flagInclusive) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = $this->salesDecimalSetting;
        $salesDecimalSeparatorSetting = $this->salesDecimalSeparatorSetting;
        $reverseDecimalSeparator = $this->reverseDecimalSeparator;

        $title = substr($menuCategory['menuCategoryDesc'], 0, $charLength);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }
        $printer->text($title);
        $this->printLineBreak();
        $this->printLineBreak(1, '-');
        $printer->initialize();

        $this->printBillInfo($printOrder);
        foreach($menuCategory['salesMenus'] as $menuCategorySalesMenu){
            $currentSalesMenu = null;
            if($menuCategorySalesMenu['salesMenuType'] == "main"){
                foreach($salesMenu as $sm){
                    if($sm['ID'] == $menuCategorySalesMenu['ID']){
                        $currentSalesMenu = $sm;
                        break;
                    }
                }
            } else if($menuCategorySalesMenu['salesMenuType'] == "package") {
                foreach($salesMenu as $sm){
                    if($sm['ID'] == $menuCategorySalesMenu['parentSalesMenuID']){
                        foreach($sm['packages'] as $package){
                            if($package['ID'] == $menuCategorySalesMenu['ID']){
                                $currentSalesMenu = $package;
                                break;
                            }
                        }
                        break;
                    }
                }
            } else if($menuCategorySalesMenu['salesMenuType'] == "extra") {
                foreach($salesMenu as $sm){
                    if($sm['ID'] == $menuCategorySalesMenu['parentSalesMenuID']){
                        foreach($sm['extras'] as $extra){
                            if($extra['ID'] == $menuCategorySalesMenu['ID']){
                                $currentSalesMenu = $extra;
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            if($currentSalesMenu != null){
                $this->printByMenuCategoryBillDetail($currentSalesMenu, $menuCategorySalesMenu, $flagInclusive);
            }
        }

        $this->printLineBreak();
        $printer->text(str_pad(Yii::t('app', 'Subtotal'), $charLength - 15, ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $printer->text(str_pad(number_format(
            $menuCategory['subTotal'],
            $salesDecimalSetting,
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        ), 12, ' ', STR_PAD_LEFT));

        if ($menuCategory['menuDiscount'] > 0) {
            $printer->text(str_pad(Yii::t('app', 'Menu Discount'), $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $menuCategory['menuDiscount'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }

        if ($menuCategory['otherTax'] > 0) {
            $printer->text(str_pad(Yii::t('app', $this->settings['Other Tax Text']), $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $menuCategory['otherTax'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }

        if ($menuCategory['vat'] > 0) {
            $printer->text(str_pad(Yii::t('app', $this->settings['Tax Text']), $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $menuCategory['vat'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }

        if ($menuCategory['otherVat'] > 0) {
            $printer->text(str_pad(Yii::t('app', $this->settings['VAT Text']), $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $menuCategory['otherVat'],
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }

        $printer->text(str_pad('', 8, ' '));
        $printer->text(str_pad('', $charLength - 8, '-'));
        if ($this->stationModel->printerTypeID != 3 && $this->stationModel->printerTypeID != 15) {
            $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
        }
        $printer->text(str_pad(Yii::t('app', 'Grand Total'), $charLength - 15, ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $printer->text(str_pad(number_format(
            $menuCategory['grandTotal'],
            $salesDecimalSetting,
            "$salesDecimalSeparatorSetting",
            "$reverseDecimalSeparator"
        ), 12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();
        $printer->initialize();
        $this->printLineBreak();
        $this->printLineBreak(2, '-');
        // @reset for edot mode
        if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }
        if ($this->stationModel->printerTypeID == '4') {
            $printer->feed(2);
        } else if ($this->stationModel->printerTypeID == '5') {
            $printer->feed(2);
        } else if ($this->stationModel->printerTypeID == 15) {
            if ($this->stationModel->flagAutocut == '1') {
                $printer->feed(2);
            }
        } else {
            if ($this->stationModel->flagAutocut == '1') {
                $printer->cut(Printer::CUT_PARTIAL);
            }
        }
    }

    private function printByMenuCategoryBillDetail($salesMenu, $menuCategorySalesMenu, $flagInclusive)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesMenuType = $menuCategorySalesMenu['salesMenuType'];
        $salesArr = $salesMenu;

        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $receiptLayoutMode = isset($this->settings['Receipt Layout']) ? $this->settings['Receipt Layout'] : 0;
        $printReceiptWrapMenuName = isset($this->settings['Print Receipt Wrap Menu Name']) ? $this->settings['Print Receipt Wrap Menu Name'] : 0;

        if($salesMenuType == "main"){
            $displayPrice = $this->getDisplayPriceValue(
                $flagInclusive,
                $salesArr['total'],
                $salesArr['discountValue'],
                $salesArr['qty'],
                $salesArr['price'],
                $salesArr['inclusivePrice']
            );
            $menuPriceTotal = $displayPrice == 0 ? $salesMenu['zeroValueText'] : number_format(
                $salesArr['qty'] * $displayPrice,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );
            
            $displayQty = self::formatNumberValue($salesArr['qty']);
            
            if($receiptLayoutMode){
                $menuPrice = number_format($displayPrice, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
                
                if($printReceiptWrapMenuName){
                    $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : $salesArr['menuName'];
                    $menuName = [];
                    $loop = 0;
                    $stringMenuName = $tempMenuName;
                    while($loop < strlen($stringMenuName)){
                        $length = (strpos(wordwrap($tempMenuName, $charLength - 11), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 11), "\n") : strlen($tempMenuName);
                        $menuName[] = trim(substr($tempMenuName, 0, $length));
                        $tempMenuName = substr($tempMenuName,$length);
                        $loop += $length;
                    }
                } else {
                    $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : $salesArr['menuName'];
                    $menuName = strlen($tempMenuName) > $charLength - 11 ? substr($tempMenuName, 0, strpos(wordwrap($tempMenuName, $charLength - 14), "\n")).'...' : substr($tempMenuName,0,$charLength);
                }
                
                if(is_array($menuName)){
                    foreach($menuName as $key => $val){
                        $printer->text(str_pad($val, $charLength - 11, ' '));
                        $this->printLineBreak();
                    }
                }else{
                    $printer->text(str_pad($menuName, $charLength - 11, ' '));
                    $this->printLineBreak();
                }

                $printer->text(str_pad($displayQty.'x', 5, ' '));
                $printer->text(' ');
                $printer->text(str_pad('@'.$menuPrice, $charLength - 17, ' '));
                $printer->text(' ');
                $printer->text(str_pad($menuPriceTotal, 10, ' ',STR_PAD_LEFT));
                $this->printLineBreak();
            }else{
                if($printReceiptWrapMenuName){
                    $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : $salesArr['menuName'];
                    $menuName = [];
                    $loop = 0;
                    $stringMenuName = $tempMenuName;
                    while($loop < strlen($stringMenuName)){
                        $length = (strpos(wordwrap($tempMenuName, $charLength - 17), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 17), "\n") : strlen($tempMenuName);
                        $menuName[] = trim(substr($tempMenuName, 0, $length));
                        $tempMenuName = substr($tempMenuName,$length);
                        $loop += $length;
                    }
                } else {
                    $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : $salesArr['menuName'];
                    $menuName = substr((strlen($tempMenuName) > $charLength - 17 ? substr($tempMenuName, 0, strpos(wordwrap($tempMenuName, $charLength - 20), "\n")).'...': $tempMenuName),0,$charLength - 17);
                }

                $printer->text(str_pad($displayQty, 5, ' '));
                $printer->text(' ');

                if(is_array($menuName)){
                    $i = 0;
                    foreach($menuName as $key => $val){
                        if($i != 0){
                            $printer->text(str_pad('', 6, ' '));
                        }
                        $printer->text(str_pad($val, $charLength - 17, ' '));
                        
                        if($i == 0){
                            $printer->text(' ');
                            $printer->text(str_pad($menuPriceTotal, 10, ' ', STR_PAD_LEFT));
                        }

                        $this->printLineBreak();
                        $i++;
                    }
                }else{
                    $printer->text(str_pad($menuName, $charLength - 17, ' '));
                    $printer->text(' ');
                    $printer->text(str_pad($menuPriceTotal, 10, ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                }
            }
        }

        $packageDiscountValueTotal = 0;
        if ($salesMenuType == "package") {
            $package = $salesArr;
            $packageModel = Menu::find()
                ->andWhere(['menuID' => $package['menuID']])
                ->one();
            $printPackageContent = $packageModel ? $packageModel->flagCustomerPrint : 0;
            $packageName = strlen($package['menuName']) > $charLength - 21 ? substr(
                $package['menuName'],
                0,
                $charLength - 24
            ) . '...' : $package['menuName'];
            $displayPackagePrice = $this->getDisplayPriceValue(
                $flagInclusive,
                $package['total'],
                $package['discountValue'],
                $package['qty'],
                $package['price'],
                $package['inclusivePrice']
            );
            $packagePriceTotal = $displayPackagePrice == 0 ? ($packageModel ? $packageModel->zeroValueText : 0) : number_format(
                $menuCategorySalesMenu['parentQty'] * $package['qty'] * $displayPackagePrice,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );
            if ($packagePriceTotal > 0 || $printPackageContent) {
                $qtyPackage = $package['qty'] * $menuCategorySalesMenu['parentQty'];
                if($receiptLayoutMode){
                    if($printReceiptWrapMenuName){
                        $tempPackageName = $package['menuName'];
                        $tempMenuNotes = $package['notes'];
                        if (strpos($tempMenuNotes, "\n") !== false) {
                            $tempMenuNotes = str_replace("\n", ", ", $tempMenuNotes);
                        }
                        $packageName = [];
                        $notes = [];
                        $loop = 0;
                        $loopNotes = 0;
                        $stringPackageName = $tempPackageName;
                        $stringMenuNotes = $tempMenuNotes;
                        while($loop < strlen($stringPackageName)){
                            $length = (strpos(wordwrap($tempPackageName, $charLength - 17), "\n") !== false) ? strpos(wordwrap($tempPackageName, $charLength - 17), "\n") : strlen($tempPackageName);
                            $packageName['menuName'] = trim(substr($tempPackageName, 0, $length));
                            $tempPackageName = substr($tempPackageName,$length);
                            $loop += $length;
                        }
                        if ($this->settings['Show Printing Menu Notes']) {
                            while($loopNotes < strlen($stringMenuNotes)){
                                $length = (strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") !== false) ? strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") : strlen($tempMenuNotes);
                                if ($loopNotes == 0) {
                                    $notes[] = '*' . trim(substr($tempMenuNotes, 0, $length));
                                } else {
                                    $notes[] = trim(substr($tempMenuNotes, 0, $length));
                                }
                                $tempMenuNotes = substr($tempMenuNotes, 0, $length);
                                $loopNotes += $length;
                            }
                            $packageName = array_merge($packageName, $notes);
                        }
                    }else{
                        $packageName = substr((strlen($package['menuName']) > $charLength - 17 ?  substr($package['menuName'], 0, strpos(wordwrap($package['menuName'], $charLength - 20), "\n")).'...' :$package['menuName']), 0, $charLength - 17);
                    }
                    $pricePackageMenu = number_format($displayPackagePrice,$salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

                    if(is_array($packageName)){
                        foreach($packageName as $key => $val){
                            $printer->text(str_pad('', 6, ' '));
                            $printer->text(str_pad($val, $charLength - 17, ' '));
                            $this->printLineBreak();
                        }
                    }else{
                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad($packageName, $charLength - 17, ' '));
                        $this->printLineBreak();
                    }

                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad(self::formatNumberValue($qtyPackage).'x', 4, ' '));
                    $printer->text(' ');
                    $printer->text(str_pad('@'.$pricePackageMenu, $charLength - 22, ' '));

                    $printer->text(' ');
                    $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));

                    $this->printLineBreak();
                }else{
                    if($printReceiptWrapMenuName){
                        $tempMenuName = $package['menuName'];
                        $tempMenuNotes = $package['notes'];
                        if (strpos($tempMenuNotes, "\n") !== false) {
                            $tempMenuNotes = str_replace("\n", ", ", $tempMenuNotes);
                        }
                        $packageName = [];
                        $notes = [];
                        $loop = 0;
                        $loopNotes = 0;
                        $stringMenuName = $tempMenuName;
                        $stringMenuNotes = $tempMenuNotes;
                        while($loop < strlen($stringMenuName)){
                            $length = (strpos(wordwrap($tempMenuName, $charLength - 21), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 21), "\n") : strlen($tempMenuName);
                            $packageName[] = trim(substr($tempMenuName, 0, $length));
                            $tempMenuName = substr($tempMenuName,$length);
                            $loop += $length;
                        }
                        if ($this->settings['Show Printing Menu Notes']) {
                            while($loopNotes < strlen($stringMenuNotes)){
                                $length = (strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") !== false) ? strpos(wordwrap($tempMenuNotes, $charLength - 10), "\n") : strlen($tempMenuNotes);
                                if ($loopNotes == 0) {
                                    $notes[] = '*' . trim(substr($tempMenuNotes, 0, $length));
                                } else {
                                    $notes[] = trim(substr($tempMenuNotes, 0, $length));
                                }
                                $tempMenuNotes = substr($tempMenuNotes, 0, $length);
                                $loopNotes += $length;
                            }
                            $packageName = array_merge($packageName, $notes);
                        }
                    }else{
                        $packageName = substr((strlen($package['menuName']) > $charLength - 21 ? substr($package['menuName'], 0, strpos(wordwrap($package['menuName'], $charLength - 24), "\n")).'...': $package['menuName']),0,$charLength - 21);
                    }

                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad(self::formatNumberValue($qtyPackage), 3, ' '));
                    $printer->text(' ');

                    if(is_array($packageName)){
                        $i = 0;
                        foreach($packageName as $key => $val){
                            if($i != 0){
                                $printer->text(str_pad('', (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' '));
                            }
                            $printer->text(str_pad($val, $charLength - 21, ' '));
                            if($i == 0){
                                $printer->text(' ');
                                $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                            }
                            $this->printLineBreak();
                            $i++;
                        }
                    }else{
                        $printer->text(str_pad($packageName, $charLength - 21, ' '));
                        $printer->text(' ');
                        $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        $this->printLineBreak();
                    }
                }
            }
            $packageDiscountValueTotal += $package['discountValue'] * $salesArr['qty'];
        }

        $extraDiscountValueTotal = 0;
        if ($salesMenuType == "extra") {
            $extra = $salesArr;
            $extraName = strlen($extra['menuExtraName']) > $charLength - 21 ? substr(
                $extra['menuExtraName'],
                0,
                $charLength - 24
            ) . '...' : $extra['menuExtraName'];
            $displayExtraPrice = $this->getDisplayPriceValue(
                $flagInclusive,
                $extra['total'],
                $extra['discountValue'],
                $extra['qty'],
                $extra['price'],
                $extra['inclusivePrice']
            );
            $extraModel = MenuExtra::find()
                ->with('menu')
                ->andWhere(['menuExtraID' => $extra['menuExtraID']])
                ->asArray()->one();
            $extraPriceTotal = $displayExtraPrice == 0 ? ($extraModel ? ($extraModel['menu'] == null ? 0 : $extraModel['menu']['zeroValueText']) : 0) : number_format(
                $menuCategorySalesMenu['parentQty'] * $extra['qty'] * $displayExtraPrice,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );

            $qtyExtra = $extra['qty'] * $menuCategorySalesMenu['parentQty'];

            if($receiptLayoutMode){
                if($printReceiptWrapMenuName){
                    $tempExtraName = $extra['menuExtraName'];
                    $extraName = [];
                    $loop = 0;
                    $stringExtraName = $tempExtraName;
                    while($loop < strlen($stringExtraName)){
                        $length = (strpos(wordwrap($tempExtraName, $charLength - 17), "\n") !== false) ? strpos(wordwrap($tempExtraName, $charLength - 17), "\n") : strlen($tempExtraName);
                        $extraName[] = trim(substr($tempExtraName, 0, $length));
                        $tempExtraName = substr($tempExtraName,$length);
                        $loop += $length;
                    } 
                }else{
                    $extraName = substr((strlen($extra['menuExtraName']) > $charLength - 17 ?  substr($extra['menuExtraName'], 0, strpos(wordwrap($extra['menuExtraName'], $charLength - 20), "\n")).'...' :$extra['menuExtraName']), 0, $charLength - 17);
                }

                $priceExtraMenu = number_format($displayExtraPrice,$salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");                    

                if(is_array($extraName)){
                    foreach($extraName as $key => $val){
                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad($val, $charLength - 6, ' '));
                        $this->printLineBreak();
                    }
                }else{
                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad($extraName, $charLength - 17, ' '));
                    $this->printLineBreak();
                }

                $printer->text(str_pad('', 6, ' '));
                $printer->text(str_pad(self::formatNumberValue($qtyExtra).'x', 4, ' '));
                $printer->text(' ');
                $printer->text(str_pad('@'.$priceExtraMenu, $charLength - 22, ' '));

                $printer->text(' ');
                $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));

                $this->printLineBreak();
            }else{
                if($printReceiptWrapMenuName){
                    $tempMenuName = $extra['menuExtraName'];
                    $extraName = [];
                    $loop = 0;
                    $stringMenuName = $tempMenuName;
                    while($loop < strlen($stringMenuName)){
                        $length = (strpos(wordwrap($tempMenuName, $charLength - 21), "\n") !== false) ? strpos(wordwrap($tempMenuName, $charLength - 21), "\n") : strlen($tempMenuName);
                        $extraName[] = trim(substr($tempMenuName, 0, $length));
                        $tempMenuName = substr($tempMenuName,$length);
                        $loop += $length;
                    } 
                }else{
                    $extraName = substr((strlen($extra['menuExtraName']) > $charLength - 21 ? substr($extra['menuExtraName'], 0, strpos(wordwrap($extra['menuExtraName'], $charLength - 24), "\n")).'...': $extra['menuExtraName']),0,$charLength - 21);
                }

                $printer->text(str_pad('', 6, ' '));
                $printer->text(str_pad(self::formatNumberValue($qtyExtra), 3, ' '));
                $printer->text(' ');

                if(is_array($extraName)){
                    $i = 0;
                    foreach($extraName as $key => $val){
                        if($i != 0){
                            $printer->text(str_pad('', (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' '));
                        }
                        $printer->text(str_pad($val, $charLength - 21, ' '));
                        if($i == 0){
                            $printer->text(' ');
                            $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        }
                        $this->printLineBreak();
                        $i++;
                    }
                }else{
                    $printer->text(str_pad($extraName, $charLength - 21, ' '));
                    $printer->text(' ');
                    $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                }
            }
            $extraDiscountValueTotal += $extra['discountValue'] * $salesArr['qty'];
        }
        if ($salesMenuType != "extra" && $salesArr['notes'] != '' && $this->settings['Show Printing Menu Notes']) {
            $tempMenuNotes = $salesArr['notes'];
            $notesString = $tempMenuNotes;
            if (strpos($notesString, "\n") !== false) {
                $notesString = str_replace("\n", ", ", $notesString);
            }
            if(strlen($notesString) >= $charLength - 7){
                $printer->text(str_pad('', 4, ' '));
                $printer->text('* ');
                $printer->text(substr($notesString,0,$charLength - 7));
                $subString = substr($notesString, $charLength - 7);
                do {
                    $printer->text(str_pad('', 7, ' '));
                    $printer->text(substr($subString,0,$charLength - 7));
                    if(strlen($subString) >= ($charLength - 7)){
                        $subString = substr($subString, $charLength - 7);
                    } else break;
                } while (1);
            } else {
                $printer->text(str_pad('', 4, ' '));
                $printer->text('* ');
                $printer->text($notesString);
            }
            $this->printLineBreak();
        }

        $allMenuDiscountTotal = isset($salesArr['allMenuDiscountTotal']) ? $salesArr['allMenuDiscountTotal'] : $menuCategorySalesMenu['menuDiscount'];
        if ($allMenuDiscountTotal != 0 && $this->settings['Show Menu Promotion Text'] != 0) {
            $promotionDetailNameText = $menuCategorySalesMenu['promotionDetailName'];
            $promotionDetailName = strlen($promotionDetailNameText) > $charLength - 20 ? substr(
                $promotionDetailNameText,
                0,
                $charLength - 23
            ) . '...' : $promotionDetailNameText;
            $discountText = $promotionDetailName;
            $discountValuetotal = $menuCategorySalesMenu['menuDiscount'];
            $discountTotal = $flagInclusive ? '' : number_format(
                -$discountValuetotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            );

            if($salesMenuType != 'main'){
                $printer->text(str_pad('', 6, ' '));
                $printer->text(str_pad($discountText, $charLength - 18, ' '));
                $printer->text(str_pad($discountTotal, 12, ' ', STR_PAD_LEFT));
            } else {
                $printer->text(str_pad($discountText, $charLength - 14, ' '));
                $printer->text(str_pad($discountTotal, 14, ' ', STR_PAD_LEFT));
            }
            $this->printLineBreak();
        }

        if (($salesArr['price'] == 0 && $salesArr['discount'] == 0 || $menuCategorySalesMenu['promotionTypeID'] == 7) && $menuCategorySalesMenu['promotionDetailID'] > 0 && ($menuCategorySalesMenu['promotionTypeID'] != 9 && $menuCategorySalesMenu['promotionTypeID'] != 1)) {
            $printer->text(str_pad('', 4, ' '));
            $printer->text(str_pad($menuCategorySalesMenu['promotionDetailName'], $charLength - 16, ' '));
            $printer->text(str_pad('', 12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        }
    }

    private static function showVoucherCodeWithPaymentInPos($paymentArr) {
        return  (
                    $paymentArr['paymentMethodTypeID'] === 5 && $paymentArr['paymentMethodID'] != -1 && empty($paymentArr['selfOrderID'])
                ) || (
                    ($paymentArr['paymentMethodTypeID'] === 4 && $paymentArr['flagExternalVoucherAPI'] === null && empty($paymentArr['selfOrderID'])) || 
                    (isset($paymentArr['voucherCode']) && empty($paymentArr['selfOrderID']) && ($paymentArr['paymentMethodTypeID'] === 4 || $paymentArr['paymentMethodTypeID'] === 5) && $paymentArr['paymentMethodID'] == -1)
                ) || ($paymentArr['posExternalPaymentID'] == 'uvlpoint');
    }

    protected function printLableTrialMode() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');

        if ($trialMode != null && $trialMode->value1 == 1) {
            $printer->initialize();
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            }

            $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));
            $printer->text(' TRIAL MODE ');
            $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(2);
            }
            $printer->initialize();
        }
    }

    protected function printBillCustomNumber($printOrder)
    {
        $printer = $this->printer;
        $printer->initialize();
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $printer->text('   Survey Number : ');
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $customNumber = CustomNumber::findBySalesNum($printOrder['salesNum']);
        $printer->text($customNumber);

        $this->printLineBreak();

        $this->printResult = ['status' => true, 'message' => null];
    }

    private function validateEmail($email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        } else {
            return false;
        }
    }
}
