<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\Setting;
use app\models\ShiftLogCash;
use app\models\ShiftLogDetail;
use app\models\Station;
use Exception;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;

/**
 * @property int $shiftDetailID
 * @property int $stationID
 * @property boolean $reprint
 * 
 * PRIVATE
 * @property Printer $printer
 * @property array $settings
 * @property Station $stationModel
 * @property ShiftLogDetail $shiftLogCashModel
 * @property string $startDate
 * @property string $endDate
 */
class PrintEndShiftCash extends Model {
    public $shiftID;
    public $shiftDetailID;
    public $stationID;
    public $reprint = false;
    public $printer;
    public $settings;
    public $localSettings;
    public $printSettings;
    public $stationModel;
    public $shiftLogCashModel;
    public $startDate;
    public $endDate;
    public $endShiftPrinting;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;
    public $printResult;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['shiftID', 'stationID'], 'required'],
            [['shiftID', 'stationID'], 'integer'],
            [['reprint'], 'boolean'],
            [['shiftID'], 'validateShift'],
            [['endShiftPrinting'], 'safe']
        ];
    }

    public function validateShift($attribute) {
        $branchID = Setting::getCurrentBranch();
        $this->shiftDetailID = $this->shiftID;

        $this->shiftLogCashModel = ShiftLogCash::find()
            ->joinWith('shiftLog')
            ->with('shiftLog.branch')
            ->with(['shiftInUser','shiftOutUser'])
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['ID' => $this->shiftDetailID])
            ->one();
        if (!$this->shiftLogCashModel) {
            $this->addError($attribute, 'Invalid shift log cash ID');
        } else {
            $this->startDate = $this->shiftLogCashModel->shiftInTime;
            $this->endDate = $this->shiftLogCashModel->shiftOutTime;

            if ($this->shiftLogCashModel->shiftNumber > 1) {
                $shiftNumberPrev = $this->shiftLogCashModel->shiftNumber - 1;
                $shiftLogCashPrevModel = ShiftLogCash::find()
                    ->joinWith('shiftLog')
                    ->where(['tr_shiftlogcash.shiftID' => $this->shiftLogCashModel->shiftID])
                    ->andWhere(['shiftNumber' => $shiftNumberPrev])
                    ->one();

                if ($shiftLogCashPrevModel) {
                    $this->startDate = $shiftLogCashPrevModel->shiftOutTime;
                }
            }
        }
    }

    public function doPrint() {
        if (!$this->validate()) {
            return false;
        }

        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();
        if (!$this->stationModel) {
            return false;
        }

        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();

        if (!empty($this->endShiftPrinting)) {
            $endShiftPrintings = [];
            foreach ($this->endShiftPrinting as $data) {
                if ($data['value1'] == true) {
                    $endShiftPrintings[] = [
                        $data['key2'] => 1
                    ];
                } else {
                    $endShiftPrintings[] = [
                        $data['key2'] => 0
                    ];
                }
            }
            $this->printSettings = array_merge(...$endShiftPrintings);
        } else {
            $this->printSettings = Setting::getPrintShiftSetting();
        }
        $this->settings = Setting::getPrintingSettings();
        $this->settings['Other Tax Text'] = $branchModel->additionalTaxName;
        $this->settings['VAT Text'] = $branchModel->vatName;

        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        try {
            if ($this->reprint) {
                Logging::save(strval($this->shiftLogCashModel->shiftID),
                    Logging::REPRINT_END_SHIFT,
                    array_merge(['shiftDetailID' => $this->shiftDetailID],
                        $this->stationModel->toArray()));
            }

            $shiftReportModel = new ShiftCashReport();
            $shiftReportModel->scenario = ShiftCashReport::SCENARIO_BY_TIME;
            $shiftReportModel->startDate = $this->startDate;
            $shiftReportModel->endDate = $this->endDate;
            $shiftReport = [
                'shiftInfo' => $this->getEndShiftHead(),
                'salesRecap' => $shiftReportModel->getSalesRecap(),
                'salesType' => $shiftReportModel->getSalesType(),
                'salesByTableSection' => $shiftReportModel->getSalesByTableSection(),
                'salesPaymentPerPaymentMethod' => $shiftReportModel->getSalesPaymentPerPaymentMethod(),
                'salesPaymentPerCashier' => $shiftReportModel->getSalesPaymentPerCashier(),
                'depositPerPaymentMethod' => $shiftReportModel->getDepositPaymentPerPaymentMethod(),
                'nonSalesPaymentPerPaymentMethod' => $shiftReportModel->getNonSalesPaymentPerPaymentMethod(),
                'nonSalesPaymentPerCashier' => $shiftReportModel->getNonSalesPaymentPerCashier(),
                'cancelledMenuSummary' => $shiftReportModel->getCancelledMenuSummary(),
                'salesMenuPackage' => $shiftReportModel->getSalesMenuPackage(),
                'salesMenu' => $shiftReportModel->getSalesMenuByCategory(),
                'salesMenuRecap' => $shiftReportModel->getSalesMenu(),
                'salesMode' => $shiftReportModel->getSalesByMode(),
                'salesByVisitPurpose' => $shiftReportModel->getSalesByVisitPurpose(),
                'salesByMenuQtyValue' => $shiftReportModel->getSalesByMenuQtyValue(),
                'salesByMenuQty' => $shiftReportModel->getSalesByMenuQty(),
                'nonSalesByMenu' => $shiftReportModel->getNonSalesByMenu(),
                'customMenuSales' => $shiftReportModel->getCustomMenuSales(),
                'depositWithdrawalPerPaymentMethod' => $shiftReportModel->getDepositWithdrawalPerPaymentMethod(),
                'dailyMemberSummary' => $shiftReportModel->getDailyMemberSummary(),
                'stockBranchMenu' => $shiftReportModel->getBranchMenuReadyToSale()
            ];
            
            $connector = Station::getConnectorByModel($this->stationModel, $this->shiftDetailID);

            $isErrorConnector = false;
            if ($connector !== null) {
                $this->printer = new Printer($connector);
                $printer = $this->printer;
                $printEnd = false; //init printEnd
    
                // @Notes: Report summary
                $this->printPageHeader($shiftReport['shiftInfo']);
                if (isset($this->printSettings['Print Shift Summary']) && $this->printSettings['Print Shift Summary'] == 1) {
                    $this->printShiftSummary($shiftReport['salesRecap']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Sales by Type']) && $this->printSettings['Print Sales by Type'] == 1) {
                    $this->printSalesByType($shiftReport['salesType']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Sales By Table Section']) && $this->printSettings['Print Sales By Table Section'] == 1) {
                    $this->printSalesByTableSection($shiftReport['salesByTableSection']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Sales By Visit Purpose']) && $this->printSettings['Print Sales By Visit Purpose'] == 1) {
                    $this->printSalesByVisitPurpose($shiftReport['salesByVisitPurpose']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Sales by Mode']) && $this->printSettings['Print Sales by Mode'] == 1) {
                    $this->printSalesByMode($shiftReport['salesMode']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Payment Method Summary']) && $this->printSettings['Print Payment Method Summary'] == 1) {
                    $this->printPaymentByPaymentMethod($shiftReport['salesPaymentPerPaymentMethod']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Non Sales Payment Method Summary']) && $this->printSettings['Print Non Sales Payment Method Summary'] == 1) {
                    $this->printNonSalesPaymentByPaymentMethod($shiftReport['nonSalesPaymentPerPaymentMethod']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Payment by Cashier']) && $this->printSettings['Print Payment by Cashier'] == 1) {
                    $this->printPaymentByCashier($shiftReport['salesPaymentPerCashier']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Non Sales Payment by Cashier']) && $this->printSettings['Print Non Sales Payment by Cashier'] == 1) {
                    $this->printNonSalesPaymentByCashier($shiftReport['nonSalesPaymentPerCashier']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Deposit Summary']) && $this->printSettings['Print Deposit Summary'] == 1) {
                    $this->printDepositSummary($shiftReport['depositPerPaymentMethod']);
                    $printEnd = true;
                }
                
                if (isset($this->printSettings['Print Withdrawal Summary']) && $this->printSettings['Print Withdrawal Summary'] == 1) {
                    $this->printDepositWithdrawalSummary($shiftReport['depositWithdrawalPerPaymentMethod']);
                    $printEnd = true;
                }

                if (isset($this->printSettings['Print Stock Branch Menu']) && $this->printSettings['Print Stock Branch Menu'] == 1) {
                    $this->printStockBranchMenu($shiftReport['stockBranchMenu']);
                    $printEnd = true;
                }
    
                if ($printEnd) {
                    $this->printEnd();
                }
                $printEnd = false;
    
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
    
                // @Notes: Report detail
                $this->printPageHeader($shiftReport['shiftInfo']);
                if (isset($this->printSettings['Print Payment Method Detail']) && $this->printSettings['Print Payment Method Detail'] == 1) {
                    $this->printPaymentDetailByPaymentMethod($shiftReport['salesPaymentPerPaymentMethod']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Non Sales Payment Method Detail']) && $this->printSettings['Print Non Sales Payment Method Detail'] == 1) {
                    $this->printNonSalesPaymentDetailByPaymentMethod($shiftReport['nonSalesPaymentPerPaymentMethod']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Deposit Detail']) && $this->printSettings['Print Deposit Detail'] == 1) {
                    $this->printDepositDetail($shiftReport['depositPerPaymentMethod']);
                    $printEnd = true;
                }
                
                if (isset($this->printSettings['Print Withdrawal Detail']) && $this->printSettings['Print Withdrawal Detail'] == 1) {
                    $this->printDepositWithdrawalDetail($shiftReport['depositWithdrawalPerPaymentMethod']);
                    $printEnd = true;
                }
    
                if (isset($this->printSettings['Print Sales Menu Package']) && $this->printSettings['Print Sales Menu Package'] == 1) {
                    $this->printSalesMenuPackage($shiftReport['salesMenuPackage']);
                    $printEnd = true;
                }
                if ($printEnd) {
                    $this->printEnd();
                }
                $printEnd = false;
                
                if ($this->stationModel->printerTypeID == '4') {
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
                
                if (isset($this->printSettings['Print Sales by Menu Qty Value']) && $this->printSettings['Print Sales by Menu Qty Value'] == 1) {
                    $this->printSalesByMenuQtyValue($shiftReport['salesByMenuQtyValue']);
                    $printEnd = true;
                }
                
                if (isset($this->printSettings['Print Sales by Menu Qty']) && $this->printSettings['Print Sales by Menu Qty'] == 1) {
                    $this->printSalesByMenuQty($shiftReport['salesByMenuQty']);
                    $printEnd = true;
                }
                
                if (isset($this->printSettings['Print Non Sales By Menu']) && $this->printSettings['Print Non Sales By Menu'] == 1) {
                    $this->printNonSalesByMenu($shiftReport['nonSalesByMenu']);
                    $printEnd = true;
                }
                
                if (isset($this->printSettings['Print Shift Sales by Menu Value']) && $this->printSettings['Print Shift Sales by Menu Value'] == 1) {
                    $this->printSalesByMenuValue($shiftReport['salesMenuRecap']);
                    $printEnd = true;
                }
                if (isset($this->printSettings['Print Custom Menu Sales']) && $this->printSettings['Print Custom Menu Sales'] == 1) {
                    $this->printCustomMenuSales($shiftReport['customMenuSales']);
                    $printEnd = true;
                }
    
                if (isset($this->printSettings['Print Daily Member Summary']) && $this->printSettings['Print Daily Member Summary'] == 1) {
                    $this->printDailyMemberSummary($shiftReport['dailyMemberSummary']);
                    $printEnd = true;
                }
    
                if ($printEnd) {
                    $this->printEnd();
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
    
                if (isset($this->printSettings['Print Sales per Menu Category']) && $this->printSettings['Print Sales per Menu Category'] == 1) {
                    $this->printSalesMenuPerCategory($shiftReport['salesByMenuQtyValue']);
                }
    
                $printer->close();
            } else {
                $isErrorConnector = true;
            }

            if ($isErrorConnector) {
                $this->printResult = ['status' => false, 'message' => $this->stationModel->stationName];
            } else {
                $this->printResult = ['status' => true, 'message' => null];
            }
        } catch (Exception $ex) {
            Yii::warning($ex);
        }
    }

    private function printPageHeader($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $this->printLableTrialMode();
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $printer->text(' START ');
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'END SHIFT REPORT'));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad(Yii::t('app', 'Branch'), 18, ' '));
        $printer->text(' : ');
        $printer->text($data['branchName']);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Start Shift User'), 18, ' '));
        $printer->text(' : ');
        $printer->text($data['startCashierName']);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'End Shift User'), 18, ' '));
        $printer->text(' : ');
        $printer->text($data['endCashierName']);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Start Shift Date'), 18, ' '));
        $printer->text(' : ');
        $printer->text($data['shiftInTime']);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'End Shift Date'), 18, ' '));
        $printer->text(' : ');
        $printer->text($data['shiftOutTime']);
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Print Date'), 18, ' '));
        $printer->text(' : ');
        $printer->text(date('d-m-Y H:i:s'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printEnd() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', ($charLength - 5) / 2, '*', STR_PAD_LEFT));
        $printer->text(' END ');
        $printer->text(str_pad('', ($charLength - 5) / 2, '*', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }

        $this->printLableTrialMode();
    }

    private function printShiftSummary($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Shift Summary'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Pending Total'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['pendingSales'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Sales Total'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['salesTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $discountTotal = $data['discountTotal'];
        $printer->text(str_pad(Yii::t('app', 'Discount Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($discountTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12,
                ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Net Sales Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['netSales'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Delivery Cost Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['deliveryCostTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Order Fee Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['orderFee'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad($this->settings['Other Tax Text'] . ' Total',
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['otherTaxTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad($this->settings['Tax Text'] . ' Total',
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }        

        $printer->text(str_pad($this->settings['VAT Text'] . ' Total',
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4') {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Platform Fee Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['platformFee'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Voucher Sales Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['voucherSalesTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Rounding Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['roundingTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Gross Sales Total'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['grossSales'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Number of Pax'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['paxTotal'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        if ($charLength > 32) {
            $printer->text(str_pad(Yii::t('app', 'Avg. Net Sales per Pax'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($data['avgNetPax'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();

            $printer->text(str_pad(Yii::t('app', 'Avg. Sales per Pax'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($data['avgGrossPax'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        } else {
            $printer->text(Yii::t('app', 'Avg. Net Sales per Pax'));
            $printer->text(' : ');
            $this->printLineBreak();
            $printer->text(number_format($data['avgNetPax'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            $this->printLineBreak();

            $printer->text(Yii::t('app', 'Avg. Sales per Pax'));
            $printer->text(' : ');
            $this->printLineBreak();
            $printer->text(number_format($data['avgGrossPax'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            $this->printLineBreak();
        }
        $this->printLineBreak();
        
        $printer->text(str_pad(Yii::t('app', 'Number of Bills'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['numOfBills'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        if ($charLength > 32) {
            $printer->text(str_pad(Yii::t('app', 'Avg. Net Sales per Bill'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($data['avgNetBill'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();

            $printer->text(str_pad(Yii::t('app', 'Avg. Sales per Bill'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($data['avgGrossBill'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            $this->printLineBreak();
        } else {
            $printer->text(Yii::t('app', 'Avg. Net Sales per Bill'));
            $printer->text(' : ');
            $this->printLineBreak();
            $printer->text(number_format($data['avgNetBill'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            $this->printLineBreak();

            $printer->text(Yii::t('app', 'Avg. Sales per Bill'));
            $printer->text(' : ');
            $this->printLineBreak();
            $printer->text(number_format($data['avgGrossBill'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            $this->printLineBreak();
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Opening Balance'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['openingBalance'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        $printer->text(str_pad(Yii::t('app', 'Cash Sales'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['cashSales'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        $endingCash = $data['openingBalance'] + $data['cashSales'];
        $printer->text(str_pad(Yii::t('app', 'Ending Cash'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($endingCash, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        $printer->text(str_pad(Yii::t('app', 'Closing Balance'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($data['closingBalance'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        $difference = $data['closingBalance'] - $endingCash;
        $printer->text(str_pad(Yii::t('app', 'Difference'), $charLength - 15,
                ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($difference, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                12, ' ', STR_PAD_LEFT));
        $this->printLineBreak();

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printSalesByType($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Type'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $sales) {
            $total += $sales['netSales'];

            $printer->text(str_pad($sales['type'], $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['netSales'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printSalesByTableSection($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Table Section'));
        $printer->text(' | '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $sales) {
            $total += $sales['netSales'];

            $printer->text(str_pad($sales['type'], $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['netSales'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
    private function printPaymentByPaymentMethod($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Payment Method Summary'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            $billingCounter = 0;
            foreach ($paymentMethod['salesPayment'] as $salesPayment) {
                $billingCounter += 1;
            }

            $printer->text(str_pad($paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- Qty', $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($billingCounter, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- Total', $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printNonSalesPaymentByPaymentMethod($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Non Sales'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printPaymentByCashier($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $total = 0;
        foreach ($data as $cashier) {
            $total += $cashier['total'];

            $printer->text($cashier['cashierName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            foreach ($cashier['salesPayment'] as $paymentMethod) {
                $printer->text('  ');
                $printer->text(str_pad($paymentMethod['paymentMethodName'],
                        $charLength - 17, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printNonSalesPaymentByCashier($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $total = 0;
        foreach ($data as $cashier) {
            $total += $cashier['total'];

            $printer->text($cashier['cashierName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            foreach ($cashier['nonSalesPayment'] as $paymentMethod) {
                $printer->text('  ');
                $printer->text(str_pad($paymentMethod['paymentMethodName'],
                        $charLength - 17, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Non Sales'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printDepositSummary($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Member Deposit Summary'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }

    private function printPaymentDetailByPaymentMethod($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $printer->text($paymentMethod['paymentMethodName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }

            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $paymentMethodTotal = 0;
            foreach ($paymentMethod['salesPayment'] as $salesPayment) {
                $paymentMethodTotal += $salesPayment['paymentAmount'];

                $printer->text(str_pad($salesPayment['billNum'], $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($salesPayment['paymentAmount'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethodTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', $charLength, '='));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total All'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printNonSalesPaymentDetailByPaymentMethod($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $printer->text($paymentMethod['paymentMethodName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }

            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $paymentMethodTotal = 0;
            foreach ($paymentMethod['salesPayment'] as $salesPayment) {
                $paymentMethodTotal += $salesPayment['paymentAmount'];

                $printer->text(str_pad($salesPayment['salesNum'], $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($salesPayment['paymentAmount'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethodTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', $charLength, '='));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Non Sales'),
                $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printDepositDetail($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Member Deposit Detail'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $printer->text($paymentMethod['paymentMethodName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }

            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $paymentMethodTotal = 0;
            foreach ($paymentMethod['deposit'] as $deposit) {
                $paymentMethodTotal += (float) $deposit['depositTotal'];

                $printer->text(str_pad($deposit['memberDepositNum'], 20, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($deposit['depositTotal'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), ($charLength - 23), ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethodTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', $charLength, '='));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total All'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '*'));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }
    
    private function printSalesMenuPackage($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales Menu Package'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        foreach ($data as $package) {

            $printer->text(str_pad($package['packageName'], $charLength - 15,
                    ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($package['packageQtyTotal'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            foreach ($package['menus'] as $key => $menu) {
                $printer->text(str_pad('- ' . AppHelper::fromChinese($menu['menuName']),
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($menu['menuQtyTotal'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }
    
    private function printSalesByMenuQtyValue($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $printer->text(' START ');
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Menu Qty & Value'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        foreach ($data as $item) {
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);// | Printer::MODE_DOUBLE_HEIGHT
            }
            $menuCategoryDesc = substr($item['menuCategoryDesc'], 0, $charLength - 17);
            $printer->text(str_pad($menuCategoryDesc, $charLength - 15, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            foreach ($item['menuCategoryDetails'] as $categoryDetail) {
                $menuCategoryDetailDesc = substr($categoryDetail['menuCategoryDetailDesc'],
                    0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc,
                        $charLength - 15, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                foreach ($categoryDetail['menus'] as $menu) {
                    $menuName = strlen($menu['menuName']) > $charLength - 16 ? substr(AppHelper::fromChinese($menu['menuName']),
                            0, $charLength - 32) . '...' : AppHelper::fromChinese($menu['menuName']);
                    //$menuName = substr($menu['menuName'], 0, $charLength - 17);
                    $printer->text(str_pad('   - ' . $menuName,
                            $charLength - 15, ' '));
                    
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Qty',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));              
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Value',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($menu['price'],
                                0,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"), 12, ' ',
                            STR_PAD_LEFT));
                    
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
                $menuCategoryDetailDescSummary = strlen($categoryDetail['menuCategoryDetailDesc']) > $charLength - 30 ? substr($categoryDetail['menuCategoryDetailDesc'],
                        0, $charLength - 32) . '...' : $categoryDetail['menuCategoryDetailDesc'];
                $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary . ' Summary Total',
                        $charLength - 15, ' '));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Qty' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalQty'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Value' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalPrice'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            }
            $menuCategoryDescSummary = strlen($menuCategoryDesc) > $charLength - 30 ? substr($menuCategoryDesc,
                    0, $charLength - 32) . '...' : $menuCategoryDesc;
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }
            $printer->text(str_pad(Yii::t('app',
                        $menuCategoryDescSummary . ' Summary Total'),
                    $charLength - 15, ' '));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Qty',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryQty'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Value',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryPrice'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
    private function printSalesByMenuQty($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $printer->text(' START ');
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $this->printLineBreak();

        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Menu Qty'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        $this->printLineBreak();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        $this->printLineBreak();
        
        foreach ($data as $item) {
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);// | Printer::MODE_DOUBLE_HEIGHT
            }
            $menuCategoryDesc = substr($item['menuCategoryDesc'], 0, $charLength - 17);
            $printer->text(str_pad($menuCategoryDesc, $charLength - 15, ' '));
            $this->printLineBreak();
            
            $printer->initialize();
            foreach ($item['menuCategoryDetails'] as $categoryDetail) {
                $menuCategoryDetailDesc = substr($categoryDetail['menuCategoryDetailDesc'],
                    0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc,
                        $charLength - 15, ' '));
                $this->printLineBreak();

                foreach ($categoryDetail['menus'] as $menu) {
                    $menuName = strlen($menu['menuName']) > $charLength - 16 ? substr(AppHelper::fromChinese($menu['menuName']),
                            0, $charLength - 32) . '...' : AppHelper::fromChinese($menu['menuName']);
                    $printer->text(str_pad('   - ' . $menuName,
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));    
                    $this->printLineBreak();
                }
                $menuCategoryDetailDescSummary = strlen($categoryDetail['menuCategoryDetailDesc']) > $charLength - 30 ? substr($categoryDetail['menuCategoryDetailDesc'],
                        0, $charLength - 36) . '...' : $categoryDetail['menuCategoryDetailDesc'];
                
                if ($charLength > 32) {
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary . ' Summary Total',
                        $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($categoryDetail['subTotalQty']), 12, ' ', STR_PAD_LEFT));
                } else {
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary . ' Summary Total',
                        $charLength - 15, ' '));
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text('    ' . self::formatNumberValue($categoryDetail['subTotalQty']));
                }
                $this->printLineBreak();
            }
            $menuCategoryDescSummary = strlen($menuCategoryDesc) > $charLength - 30 ? substr($menuCategoryDesc,
                    0, $charLength - 36) . '...' : $menuCategoryDesc;
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }

            if ($charLength > 32) {
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary . ' Summary Total'),
                    $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(self::formatNumberValue($item['summaryCategoryQty']), 12, ' ', STR_PAD_LEFT));
            } else {
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary . ' Summary Total'),
                    $charLength - 15, ' '));
                $printer->text(' : ');
                $this->printLineBreak();
                $printer->text('  ' .self::formatNumberValue($item['summaryCategoryQty']));
            }
            
            $this->printLineBreak();
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
    private function printNonSalesByMenu($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }
        $this->printLableTrialMode();
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $printer->text(' START ');
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Non Sales Menu'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        foreach ($data as $item) {
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);// | Printer::MODE_DOUBLE_HEIGHT
            }
            $menuCategoryDesc = substr($item['menuCategoryDesc'], 0, $charLength - 17);
            $printer->text(str_pad($menuCategoryDesc, $charLength - 15, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            foreach ($item['menuCategoryDetails'] as $categoryDetail) {
                $menuCategoryDetailDesc = substr($categoryDetail['menuCategoryDetailDesc'],
                    0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc,
                        $charLength - 15, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                foreach ($categoryDetail['menus'] as $menu) {
                    $menuName = strlen($menu['menuName']) > $charLength - 16 ? substr(AppHelper::fromChinese($menu['menuName']),
                            0, $charLength - 32) . '...' : AppHelper::fromChinese($menu['menuName']);
                    //$menuName = substr($menu['menuName'], 0, $charLength - 17);
                    $printer->text(str_pad('   - ' . $menuName,
                            $charLength - 15, ' '));
                    
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Qty',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));
                    
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Value',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($menu['price'],
                                0,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"), 12, ' ',
                            STR_PAD_LEFT));
                    
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
                $menuCategoryDetailDescSummary = strlen($categoryDetail['menuCategoryDetailDesc']) > $charLength - 30 ? substr($categoryDetail['menuCategoryDetailDesc'],
                        0, $charLength - 36) . '...' : $categoryDetail['menuCategoryDetailDesc'];
                $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary . ' Summary Total',
                        $charLength - 15, ' '));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Qty' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalQty'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Value' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalPrice'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            }
            $menuCategoryDescSummary = strlen($menuCategoryDesc) > $charLength - 30 ? substr($menuCategoryDesc,
                    0, $charLength - 36) . '...' : $menuCategoryDesc;
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }
            $printer->text(str_pad(Yii::t('app',
                        $menuCategoryDescSummary . ' Summary Total'),
                    $charLength - 15, ' '));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Qty',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryQty'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Value',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryPrice'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
    private function printSalesByMenuValue($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Menu Value'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $menu) {
            $total += $menu['grandTotal'];

            $printer->text(AppHelper::fromChinese($menu['description']));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Qty'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Sales'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($menu['grandTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
     
    private function printSalesByVisitPurpose($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales By Visit Purpose'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $totalBillSummmary = 0;
        $subTotalSummmary = 0;
        foreach ($data as $sales) {
            $totalBillSummmary += $sales['visitBillTotal'];
            $subTotalSummmary += $sales['subtotal'];

            $printer->text($sales['visitPurposeName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Number Of Bill'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['visitBillTotal'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Sales'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['subtotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Bill'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($totalBillSummmary, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        $printer->text(str_pad(Yii::t('app', 'Total Sales'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($subTotalSummmary, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printSalesByMode($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Sales by Mode'));
        $printer->text(' | '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $totalBillSummmary = 0;
        $subTotalSummmary = 0;
        foreach ($data as $sales) {
            $totalBillSummmary += $sales['totalBill'];
            $subTotalSummmary += $sales['subTotal'];

            $printer->text($sales['visitPurposeName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Number Of Bill'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['totalBill'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"),
                    12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Sales'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($sales['subTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Bill'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($totalBillSummmary, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        $printer->text(str_pad(Yii::t('app', 'Total Sales'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($subTotalSummmary, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function getEndShiftHead() {
        return [
            'branchName' => $this->shiftLogCashModel->shiftLog->branch->branchName,
            'startCashierName' => $this->shiftLogCashModel->shiftInUser->fullName,
            'endCashierName' => $this->shiftLogCashModel->shiftOutUser->fullName,
            'shiftInTime' => date_format(date_create($this->startDate),
                'd-m-Y H:i:s'),
            'shiftOutTime' => date_format(date_create($this->endDate),
                'd-m-Y H:i:s')
        ];
    }

    private function printSalesMenuPerCategory($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        foreach ($data as $item) {
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $menuCategoryDesc = substr($item['menuCategoryDesc'], 0, $charLength - 17);
            $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
            $printer->text(' ' . $menuCategoryDesc);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }                        
            $printer->initialize();
            foreach ($item['menuCategoryDetails'] as $categoryDetail) {
                $menuCategoryDetailDesc = substr($categoryDetail['menuCategoryDetailDesc'],
                    0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc,
                        $charLength - 15, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
                } else {
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                }
                
                foreach ($categoryDetail['menus'] as $menu) {
                    $menuName = strlen($menu['menuName']) > $charLength - 16 ? substr($menu['menuName'],
                            0, $charLength - 32) . '...' : AppHelper::fromChinese($menu['menuName']);
                    //$menuName = substr($menu['menuName'], 0, $charLength - 17);
                    $printer->text(str_pad('   - ' . $menuName,
                            $charLength - 15, ' '));
                    
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Qty',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));
                    
                    $printer->feed(1);
                    
                    $printer->text(str_pad('     - Value',
                            $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($menu['price'],
                                0,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"), 12, ' ',
                            STR_PAD_LEFT));
                    
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
                $menuCategoryDetailDescSummary = strlen($categoryDetail['menuCategoryDetailDesc']) > $charLength - 30 ? substr($categoryDetail['menuCategoryDetailDesc'],
                        0, $charLength - 36) . '...' : $categoryDetail['menuCategoryDetailDesc'];
                $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary . ' Summary Total',
                        $charLength - 15, ' '));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Qty' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalQty'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                
                $printer->feed(1);
                
                $printer->text(str_pad('  - Value' ,
                        $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($categoryDetail['subTotalPrice'],
                            0,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            }
            $menuCategoryDescSummary = strlen($menuCategoryDesc) > $charLength - 30 ? substr($menuCategoryDesc,
                    0, $charLength - 36) . '...' : $menuCategoryDesc;
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }
            $printer->text(str_pad(Yii::t('app',
                        $menuCategoryDescSummary . ' Summary Total'),
                    $charLength - 15, ' '));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Qty',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryQty'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            $printer->feed(1);
            
            $printer->text(str_pad('  - Value',
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($item['summaryCategoryPrice'],
                        0, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4') {
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
    }
    
    private function printCustomMenuSales($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Custom Menu Sales'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        $grandTotal = 0;
        foreach ($data as $menu) {
            $total += $menu['qty'];
            $grandTotal += $menu['value'];

            $menuName = $menu['salesNum'] . ' - ' . $menu['customMenuName'];
            
            $printer->text($menuName);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Qty'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(self::formatNumberValue($menu['qty']), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('- ' . Yii::t('app', 'Total'),
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($menu['value'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(self::formatNumberValue($total), 12, ' ', STR_PAD_LEFT));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        $printer->text(str_pad(Yii::t('app', 'Grand Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($grandTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printDepositWithdrawalSummary($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Member Withdrawal Summary'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethod['paymentAmount'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }

    private function printDepositWithdrawalDetail($data) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Member Withdrawal Detail'));
        $printer->text('  |  '.date_format(date_create($this->shiftLogCashModel->shiftInTime),'d-m-Y'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        foreach ($data as $paymentMethod) {
            $total += $paymentMethod['paymentAmount'];

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $printer->text($paymentMethod['paymentMethodName']);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }

            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $paymentMethodTotal = 0;
            foreach ($paymentMethod['depositWithdrawal'] as $deposit) {
                $paymentMethodTotal += (float) $deposit['withdrawalTotal'];

                $printer->text(str_pad($deposit['depositWithdrawalNum'], 20, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($deposit['withdrawalTotal'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), ($charLength - 23), ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('Total ' . $paymentMethod['paymentMethodName'],
                    $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($paymentMethodTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', $charLength, '='));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '*'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total All'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '*'));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }

    private function printDailyMemberSummary($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $printer->text(Yii::t('app', 'Daily Member Summary'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $total = 0;
        $totalMember = 0;
        foreach ($data as $member) {
            $total += $member['depositTotal'];
            $totalMember++;

            $memberName = substr($member['memberName'], 0, $charLength - 17);
            $printer->text(str_pad($memberName, $charLength - 15, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($member['depositTotal'],
                        $this->salesDecimalSetting, "$this->salesDecimalSeparatorSetting", "$this->reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total Member'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad($totalMember, 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($total, $this->salesDecimalSetting, "$this->salesDecimalSeparatorSetting", "$this->reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', $charLength, '='));

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(2);
        }
    }

    private function printStockBranchMenu($data) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }
        
        $printer->text(str_pad('', ($charLength - 18) / 2, '=', STR_PAD_LEFT));
        $printer->text(Yii::t('app', 'Stock Branch Menu'));
        $printer->text(str_pad('', ($charLength - 18) / 2, '=', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        foreach ($data as $item) {
            foreach ($item['menuCategoryDetails'] as $categoryDetails) {
                $menuCategoryDetailDesc = substr($categoryDetails['menuCategoryDetailDesc'],
                    0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc, $charLength - 15, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                foreach($categoryDetails['menus'] as $menu) {
                    $menuName = strlen($menu['menuName']) > $charLength - 21 ? substr(AppHelper::fromChinese($menu['menuName']),0, $charLength - 24) . '...' : AppHelper::fromChinese($menu['menuName']);
                    $printer->text(str_pad('   - ' . $menuName, $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($menu['qty']), 6, ' ', STR_PAD_LEFT));
                    $printer->text(str_pad('[   ]', 6, ' ', STR_PAD_LEFT));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }

    }

    private function formatNumberValue($number){
        return AppHelper::formatNumberValue($number, null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator);
    }

    private function printLineBreak($feed = 1, $textSymbol = null) {
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

    private function printLableTrialMode() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');

        if (isset($trialMode)) {
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }
            
            if ($trialMode->value1 == 1) {
                $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));
                $printer->text(' TRIAL MODE ');
                $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));
            };

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(2);
            }
        }
    }
}
