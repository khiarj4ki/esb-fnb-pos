<?php

namespace app\models\forms;

use app\components\ExtPrinter;
use app\models\Branch;
use app\models\BrandSetting;
use app\models\Enums\EnumInterface;
use app\models\Enums\PrinterTypeInterface;
use app\models\PaymentMethod;
use app\models\SalesHead;
use app\models\SalesPayment;
use app\models\Setting;
use app\models\Station;
use Exception;
use Mike42\Escpos\Printer;
use Yii;
use yii\db\Expression;

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
class PrintPaymentLhdnInvoice extends PrintPayment
{
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
        $LhdnEInvoiceSetting = isset($this->settings['LHDN eInvoice']) ? $this->settings['LHDN eInvoice'] : 0;

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

                    if ($LhdnEInvoiceSetting && (isset($this->eInvoicePrint) || $this->eInvoicePrint)) {
                        $this->printLhdnInvoiceInfo();
                    }

    
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

    private function printLhdnInvoiceInfo()
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $eInvoicePrintData = $this->eInvoicePrint;
        $qrisCaption = 'e-Invoice Scan Here';
        $informationInvoice = null;
        $qrisCodeInvoice = null;

        if ($this->scenario == self::SCENARIO_DEFAULT || $this->scenario == self::SCENARIO_REPRINT) {
            $qrisCodeInvoice = isset($eInvoicePrintData['eInvoiceUrl']) ? $eInvoicePrintData['eInvoiceUrl'] : null;
            $informationInvoice = isset($eInvoicePrintData['eInvoiceInfo']) ? $eInvoicePrintData['eInvoiceInfo'] : null;
        }

        if(!$qrisCodeInvoice || !$informationInvoice) {
            return false;
        }

        $printer->text(str_pad(
            'Buyer Tin',
            $charLength - 20,
            ' ',
            STR_PAD_RIGHT
        ));
        $printer->text(' : ');
        $buyerTin = isset($informationInvoice['buyerTin']) ? $informationInvoice['buyerTin'] : '';
        $printer->text(str_pad(
            $buyerTin,
            12,
            ' ',
            STR_PAD_RIGHT
        ));
        $this->printLineBreak();
        
        $printer->text(str_pad(
            'Buyer Name',
            $charLength - 20,
            ' ',
            STR_PAD_RIGHT
        ));
        $printer->text(' : ');
        $buyerName = isset($informationInvoice['buyerName']) ? $informationInvoice['buyerName'] : '';
        $printer->text(str_pad(
            $buyerName,
            12,
            ' ',
            STR_PAD_RIGHT
        ));
        $this->printLineBreak();

        $printer->text(str_pad(
            'Buyer ID',
            $charLength - 20,
            ' ',
            STR_PAD_RIGHT
        ));
        $printer->text(' : ');
        $buyerID = isset($informationInvoice['buyerID']) ? $informationInvoice['buyerID'] : '';
        $printer->text(str_pad(
            $buyerID,
            12,
            ' ',
            STR_PAD_RIGHT
        ));
        $this->printLineBreak();


        $printer->text(str_pad(
            'Buyer Address',
            $charLength - 20,
            ' ',
            STR_PAD_RIGHT
        ));
        $printer->text(' : ');
        $buyerAddressData = isset($informationInvoice['buyerAddress']) ? $informationInvoice['buyerAddress'] : '';
        $buyerAddress = strlen($buyerAddressData) > 100 ? substr($buyerAddressData, 0, strpos(wordwrap($buyerAddressData, 100), "\n")).'...' : $buyerAddressData;
        $printer->text(str_pad(
            $buyerAddress,
            12,
            ' ',
            STR_PAD_RIGHT
        ));
        $this->printLineBreak();

        $printer->text(str_pad(
            'Buyer Contact',
            $charLength - 20,
            ' ',
            STR_PAD_RIGHT
        ));
        $printer->text(' : ');
        $buyerContact = isset($informationInvoice['buyerContact']) ? $informationInvoice['buyerContact'] : '';
        $printer->text(str_pad(
            $buyerContact,
            12,
            ' ',
            STR_PAD_RIGHT
        ));

        $printer->feed(1);
        $this->printLineBreak();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }
        $this->generateQR($qrisCodeInvoice, $printer, true);
        $this->printLineBreak();
        $printer->text($qrisCaption);
        $this->printLineBreak();
        $printer->feed(1);

    }

}
