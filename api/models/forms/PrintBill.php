<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\components\ExtPrinter;
use app\models\Branch;
use app\models\Enums\PrinterTypeInterface;
use app\models\LkExternalMemberShipType;
use app\models\Menu;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\Setting;
use app\models\Station;
use app\models\VisitPurpose;
use app\models\SalesMergeTable;
use app\models\MenuExtra;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $stationID
 * 
 * PRIVATE
 * @property Printer $printer
 * @property array $settings
 * @property Station $stationModel
 * @property array $orderPayment
 */
class PrintBill extends Model {
    public $tableID;
    public $salesNum;
    public $stationID;
    public $printer;
    public $settings;
    public $stationModel;
    public $orderPayment;
    public $enabledImage;
    public $stringTableText;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;
    public $printResult;
    public $testPrint;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'stationID'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID', 'stationID'], 'integer'],
            [['testPrint'], 'safe'],
            [['tableID'], 'validateTable'],
        ];
    }

    public function validateTable($attribute) {
        $this->orderPayment = SalesHead::findOrderPaymentAsArray(null,
                    $this->salesNum, false, true);
        if (!$this->orderPayment) {
            $this->addError($attribute, 'Invalid sales number');
        }
    }

    public function getStringStructure($charLength){
        $newStringTableText = '';
        $flagger=0;
        do{
            $string = substr($this->stringTableText, 0, strpos($this->stringTableText, ', '));
            if(strlen($string)+strlen($newStringTableText) <= $charLength){
                if($flagger==0){
                    $flagger=1;
                }
                else{
                    $newStringTableText .= ', ';
                }
                $newStringTableText .= $string;
                $this->stringTableText = substr($this->stringTableText, strlen($string)+2);
            }
            else{
                $flagger=2;
            }
        }while($flagger!=2);
        return $newStringTableText;
    }

    public function getLongStringToEnterStructure($charLength){
        $string = substr($this->stringTableText, 0, $charLength);
        $this->stringTableText = substr($this->stringTableText, $charLength);
        return $string;
    }

    public function doPrint() {
        if (!$this->validate()) {
            $this->printResult = ['status' => true, 'message' => null];
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

        $this->settings = Setting::getPrintingSettings();
        $this->settings['Other Tax Text'] = $branchModel->additionalTaxName;
        $this->settings['VAT Text'] = $branchModel->vatName;
        
        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $printOrderList = array_merge([$this->orderPayment['order']],
            $this->orderPayment['salesLink']);

        $availableDepositTotal = $this->orderPayment['availableDepositTotal'];
        try {
            $testPrint = isset($this->testPrint) && !!$this->testPrint;
            if (!$testPrint) {
                Logging::save($this->salesNum, Logging::PRINT_BILL,
                    $this->stationModel);
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
    
                $this->printHeader($branchModel);
    
                $linkTablePrintSetting = array_key_exists(
                    'Simplify Link Table Print',
                    $this->settings
                ) ? $this->settings['Simplify Link Table Print'] : false;
    
                if (count($printOrderList) > 1) {
                    for ($i = 1; $i < count($printOrderList); $i++) {
                        $printOrderList[$i]['simplifyLinkTablePrint'] = $linkTablePrintSetting;
                    }
                }
    
                $allBillingGrandTotal = 0;
                foreach ($printOrderList as $printOrder) {
                    $flagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
                    $allBillingGrandTotal += $printOrder['grandTotal'] - $printOrder['roundingTotal'];
    
                    $this->printBillInfo($printOrder);
    
                    $totalQty = 0;
                    foreach ($printOrder['salesMenu'] as $salesMenu) {
                        $totalQty += $salesMenu['qty'];
    
                        $this->printBillDetail($salesMenu,
                            $flagInclusive);
                    }
                    if (array_key_exists('Print Category Subtotal', $this->settings)) {
                        if ($this->settings['Print Category Subtotal']) {
                            $this->printHeaderGroupMenuCategory();
                            foreach ($printOrder['menuCategory'] as $menuCategory) {
                                $this->printGroupMenuCategory($menuCategory);
                            }
                        }
                    }
    
                    $this->printBillSummary($printOrderList, $printOrder, $totalQty,
                        $availableDepositTotal);
                }
    
                // @Notes: Extra summary for linked bills
                if (count($printOrderList) > 1) {
                    $salesDecimalSetting = $this->salesDecimalSetting;
                    $salesDecimalSeparatorSetting = $this->salesDecimalSeparatorSetting;
                    $reverseDecimalSeparator = $this->reverseDecimalSeparator;
    
                    if ($this->stationModel->printerTypeID != 3 && $this->stationModel->printerTypeID != 15) {
                        $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
                    }
    
                    $printer->text(str_pad(Yii::t('app', 'Grand Total'),
                            $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($allBillingGrandTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    if ($availableDepositTotal > 0) {
                        $this->printDepositInfo($availableDepositTotal, $charLength,
                            $allBillingGrandTotal);
                    }
    
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(3);
                    }
                }
    
                $printer->initialize();
    
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                } else {
                    $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                }
    
                $printer->text('--- ' . Yii::t('app', 'NOT PAID') . ' ---');
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }

                $this->printLableTrialMode();
    
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == '5') {
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

    private function printHeader($branchModel) {
        $printer = $this->printer;
        $this->enabledImage = FALSE;
        // @Notes: Printer Type 1:Thermal, 2:Sticker, 3:Dot Matrix, 4:MPOP
        // @Notes: Printer Connection 1:Network, 2:Windows, 3:Android
        if ($this->stationModel->printerTypeID == '1' || $this->stationModel->printerTypeID == '3' || $this->stationModel->printerTypeID == '4' ||
            $this->stationModel->printerConnectionID == '1' || $this->stationModel->printerConnectionID == '2' || $this->stationModel->printerTypeID == 15 || 
            $this->stationModel->printerTypeID == 16) {
            $this->enabledImage = TRUE;
        }
        $charLength = $this->stationModel->characterPerLine;
        
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        }

        $this->printLableTrialMode();

        //@Notes: inserting image at header
        if ($this->enabledImage) {
            $branchModel = Branch::find()
                    ->andWhere([
                        Branch::tableName() . '.flagActive' => 1,
                        Branch::tableName() . '.branchID' => Yii::$app->user->identity->branchID
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
                        $printer->bitImageColumnFormat($img,
                        ExtPrinter::IMG_DOUBLE_WIDTH | ExtPrinter::IMG_DOUBLE_HEIGHT);
                    } elseif ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->bitImageMpop($img);
                    } else {
                        $printer->bitImage($img);
                    }
                    
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
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
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printBillInfo($printOrder) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        if ($printOrder['memberID'] !== 0 || $printOrder['externalMemberName'] && $printOrder['externalMemberName'] != "null") {
            $strPadLen = 14;
            $strPadValLen = 17;
        } else {
            $strPadLen = 11;
            $strPadValLen = 14;
        }

//        if ($this->settings['Show Billing Number']) {
//            $printer->text(str_pad(Yii::t('app', 'No'), 11, ' '));
//            $printer->text(' : ');
//            $printer->text(str_pad($printOrder['salesNum'], $charLength - 14,
//                    ' '));
//            if ($this->stationModel->printerTypeID == '4') {$printer->getPrintConnector()->write("\x0A");}else {$printer->feed(1);}
//        }

        $simplifyTableLinkPrintSetting = (isset($printOrder['simplifyLinkTablePrint']) && $printOrder['simplifyLinkTablePrint']);
        if ($simplifyTableLinkPrintSetting) {
            if ($this->settings['Show Billing Table']) {   
                $this->stringTableText = $printOrder['tableName'].$printOrder['mergeTableNames'];
                $flagger=0;
                $printTakeAwaySettings = array_key_exists('Print Quick Service Table Text',
                        $this->settings) ? $this->settings['Print Quick Service Table Text'] : true;
                if (($printTakeAwaySettings && $printOrder['tableID'] == 0) || $printOrder['tableID'] != 0) {
                    $printer->text(str_pad(Yii::t('app', 'Table'), $strPadLen, ' '));
                    $printer->text(' : ');
                    do {
                        if(strlen($this->stringTableText) > $charLength - $strPadValLen){
                            $printer->text(str_pad($this->getStringStructure($charLength - $strPadValLen), $charLength - $strPadValLen, ' '));
                            $flagger=1;
                        }else if($flagger==1){
                            $printer->text(str_pad('', $strPadValLen, ' '));
                            $printer->text($this->stringTableText);
                            $flagger=2;
                        }else{
                            $printer->text(str_pad($this->stringTableText, $charLength - $strPadValLen, ' '));
                            $flagger=2;
                        }
                    } while ($flagger!=2);
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
            $printer->text(str_pad('', $charLength, '-'));
            $this->printLineBreak();
            return;
        }

        $printer->text(str_pad(Yii::t('app', 'Date'), $strPadLen, ' '));
        $printer->text(' : ');
        $printer->text(str_pad(date_format(date_create(date('d-m-Y H:i')),
                    $this->settings['Show Billing Time'] ? 'd-m-Y H:i' : 'd-m-Y'),
                $charLength - $strPadValLen, ' '));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }


        //add Date In
        if($this->settings['Show Billing Time']){
            $printer->text(str_pad(Yii::t('app', 'Time In'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(date("d-m-Y H:i",strtotime($printOrder['salesDateIn'])), $charLength - $strPadValLen,' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        // check member internal
        if ($printOrder['memberID'] !== 0) {
            $printer->text(str_pad(Yii::t('app', 'Regular Member'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['memberName'], $charLength - $strPadValLen,
                    ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
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
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        // check employee
        if ($printOrder['employeeCode'] && $printOrder['employeeCode'] !== '') {
		    $employeeCode = substr($printOrder['employeeCode'], -4);
			$employeeCode = ' - xxx'.$employeeCode;
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
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            if ($printOrder['employeeType'] === 'map') {
                $printer->text(str_pad(Yii::t('app', 'Employee)'), $strPadLen, ' '));
            } else {
                $printer->text(str_pad(Yii::t('app', 'ternal)'), $strPadValLen, ' '));
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $customerName = SalesInfo::findBySalesNumKey($this->salesNum, 'Full Name');
        if ($customerName != '') {
            $printer->text(str_pad(Yii::t('app', 'Customer'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($customerName,
                    $charLength - $strPadValLen, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        
        $showPrintingPaymentInfo = array_key_exists('Show Printing Payment Info',
                $this->settings) ? $this->settings['Show Printing Payment Info'] : false;
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

        if ($this->settings['Show Billing Server']) {
            $printer->text(str_pad(Yii::t('app', 'Server'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['creator'], $charLength - $strPadValLen, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->settings['Show Billing Table']) {   
            $this->stringTableText = $printOrder['tableName'].$printOrder['mergeTableNames'];
            $flagger=0;
            $printTakeAwaySettings = array_key_exists('Print Quick Service Table Text',
                    $this->settings) ? $this->settings['Print Quick Service Table Text'] : true;
            if (($printTakeAwaySettings && $printOrder['tableID'] == 0) || $printOrder['tableID'] != 0) {
                $printer->text(str_pad(Yii::t('app', 'Table'), $strPadLen, ' '));
                $printer->text(' : ');
                do {
                    if(strlen($this->stringTableText) > $charLength - $strPadValLen){
                        $printer->text(str_pad($this->getStringStructure($charLength - $strPadValLen), $charLength - $strPadValLen, ' '));
                        $flagger=1;
                    }else if($flagger==1){
                        $printer->text(str_pad('', $strPadValLen, ' '));
                        $printer->text($this->stringTableText);
                        $flagger=2;
                    }else{
                        $printer->text(str_pad($this->stringTableText, $charLength - $strPadValLen, ' '));
                        $flagger=2;
                    }
                } while ($flagger!=2);
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }
        
        $showBillingVisitPurpose = array_key_exists('Show Billing Visit Purpose',
            $this->settings) ? $this->settings['Show Billing Visit Purpose'] : false;
        if ($showBillingVisitPurpose) {
            if($this->settings['Show Billing Visit Purpose'] == 1) {
                $visitPurposeModel = VisitPurpose::find()
                    ->andWhere(['visitPurposeID' => $printOrder['visitPurposeID']])
                    ->one();
                $printer->text(str_pad(Yii::t('app', 'Purpose'), $strPadLen, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($visitPurposeModel['visitPurposeName'], $charLength - $strPadValLen,
                        ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                } 
            }
        }

        if ($this->settings['Show Billing Pax']) {
            $printer->text(str_pad(Yii::t('app', 'Pax'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($printOrder['paxTotal'], $charLength - $strPadValLen,
                    ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->settings['Show Billing Cashier']) {
            $printer->text(str_pad(Yii::t('app', 'Cashier'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text(Yii::$app->user->identity->fullName);
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->settings['Show Billing Print Counter']) {
            $printer->text(str_pad(Yii::t('app', 'Print'), $strPadLen, ' '));
            $printer->text(' : ');
            $printer->text($printOrder['billingPrintCount']);
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
    }

    private function printBillDetail($salesMenu, $flagInclusive) {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesArr = $salesMenu;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printingReceiptMode = isset($this->settings['Printing Receipt Mode']) ? $this->settings['Printing Receipt Mode'] : 0;
        $receiptLayoutMode = isset($this->settings['Receipt Layout']) ? $this->settings['Receipt Layout'] : 0;
        $printReceiptWrapMenuName = isset($this->settings['Print Receipt Wrap Menu Name']) ? $this->settings['Print Receipt Wrap Menu Name'] : 0;
        $displayPrice = $this->getDisplayPriceValue($flagInclusive, $salesArr['total'], $salesArr['discountValue'], $salesArr['qty'], $salesArr['price'], $salesArr['inclusivePrice']);
        $menuPriceTotal = $displayPrice == 0 ? $salesMenu['zeroValueText'] : number_format($salesArr['qty'] * $displayPrice,
                $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
        if($printingReceiptMode == "0"){
            $childTotal = 0;
            if ($salesArr['packages']) {
                foreach ($salesArr['packages'] as $package) {
                    $packageModel = Menu::find()
                        ->andWhere(['menuID' => $package['menuID']])
                        ->one();
                    $displayPackagePrice = $this->getDisplayPriceValue($flagInclusive, $package['total'], $package['discountValue'], $package['qty'], $package['price'], $package['inclusivePrice']);
                    $childTotal += $salesArr['qty'] * $package['qty'] * $displayPackagePrice;
                }
            }
            if ($salesArr['extras']) {
                foreach ($salesArr['extras'] as $extra) {
                    $displayExtraPrice = $this->getDisplayPriceValue($flagInclusive, $extra['total'], $extra['discountValue'], $extra['qty'], $extra['price'], $extra['inclusivePrice']);
                    $childTotal += $salesArr['qty'] * $extra['qty'] * $displayExtraPrice;
                }
            }
            $newTotal = ($salesArr['qty'] * $displayPrice) + $childTotal;
            $menuPriceTotal = number_format($newTotal, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            
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
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }else{
                $printer->text(str_pad($menuName, $charLength - 11, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            $printer->text(str_pad($displayQty.'x', 5, ' '));
            $printer->text(' ');
            $printer->text(str_pad('@'.$menuPrice, $charLength - 17, ' '));
            $printer->text(' ');
            $printer->text(str_pad($menuPriceTotal, 10, ' ',STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

        }else{

            if($printReceiptWrapMenuName){
                $tempMenuName = $salesArr['customMenuName'] ? $salesArr['customMenuName'] : AppHelper::fromChinese($salesArr['menuName']);
                $menuName = [];
                $loop = 0;
                $loopNotes = 0;
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
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

        }
        
        $packageDiscountValueTotal = 0;
        if ($salesArr['packages']) {
            foreach ($salesArr['packages'] as $package) {
                $packageModel = Menu::find()
                    ->andWhere(['menuID' => $package['menuID']])
                    ->one();
                $printPackageContent = $packageModel ? $packageModel->flagCustomerPrint : 0;
                $displayPackagePrice = $this->getDisplayPriceValue($flagInclusive, $package['total'], $package['discountValue'], $package['qty'], $package['price'], $package['inclusivePrice']);
                $packagePriceTotal = $displayPackagePrice == 0 ? ($packageModel ? $packageModel->zeroValueText : 0) : number_format($salesArr['qty'] * $package['qty'] * $displayPackagePrice,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
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
                            $packageName[] = trim(wordwrap($stringPackageName, $charLength - 11, "|", true));
                         
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
                            $packageName = substr((strlen($package['menuName']) > $charLength - 17 ?  substr(AppHelper::fromChinese($package['menuName']), 0, strpos(wordwrap($package['menuName'], $charLength - 20), "\n")).'...' :AppHelper::fromChinese($package['menuName'])), 0, $charLength - 17);
                        }
                        $pricePackageMenu = number_format($displayPackagePrice,$salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
                        if(is_array($packageName)){
                            foreach($packageName as $key => $val){
                                if(strpos($val, '|') !== false){
                                    $texts = explode('|', $val);
                                    foreach($texts as $data => $text){
                                        $printer->text(str_pad('', 6, ' '));
                                        $printer->text(str_pad($text, $charLength - 17, ' '));
                                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                            $printer->getPrintConnector()->write("\x0A");
                                        } else {
                                            $printer->feed(1);
                                        }
                                    }
                                } else {
                                    $printer->text(str_pad('', 6, ' '));
                                    $printer->text(str_pad($val, $charLength - 17, ' '));
                                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                        $printer->getPrintConnector()->write("\x0A");
                                    } else {
                                        $printer->feed(1);
                                    }
                                }
                            }
                        }else{
                            $printer->text(str_pad('', 6, ' '));
                            $printer->text(str_pad($packageName, $charLength - 17, ' '));
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }

                        $printer->text(str_pad('', 6, ' '));

                        $printer->text(str_pad(self::formatNumberValue($qtyPackage).'x', 4, ' '));
                        $printer->text(' ');
                        $printer->text(str_pad('@'.$pricePackageMenu, $charLength - 22, ' '));

                        if($printingReceiptMode == "1"){
                            $printer->text(' ');
                            $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        }

                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }

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
                                    $printer->text(str_pad('', 10, ' '));
                                }
                                $printer->text(str_pad($val, $charLength - 21, ' '));
                                if($i == 0){
                                    if($printingReceiptMode == "1"){
                                        $printer->text(' ');
                                        $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                                    }
                                }
                                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                    $printer->getPrintConnector()->write("\x0A");
                                } else {
                                    $printer->feed(1);
                                }
                                $i++;
                            }
                        }else{
                            $printer->text(str_pad($packageName, $charLength - 21, ' '));
                            if($printingReceiptMode == "1"){
                                $printer->text(' ');
                                $printer->text(str_pad($packagePriceTotal, (fmod($qtyPackage, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                            }
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }
                }

                $packageDiscountValueTotal += $package['discountValue'] * $salesArr['qty'];
            }
        }

        $extraDiscountValueTotal = 0;
        if ($salesArr['extras']) {
            foreach ($salesArr['extras'] as $extra) {
                $extraModel = MenuExtra::find()
                    ->with('menu')
                    ->andWhere(['menuExtraID' => $extra['menuExtraID']])
                    ->asArray()->one();
                $displayExtraPrice = $this->getDisplayPriceValue($flagInclusive, $extra['total'], $extra['discountValue'], $extra['qty'], $extra['price'], $extra['inclusivePrice']);
                $extraPriceTotal = $displayExtraPrice == 0 ? ($extraModel ? ($extraModel['menu'] == null ? 0 : $extraModel['menu']['zeroValueText']) : 0) : number_format($salesArr['qty'] * $extra['qty'] * $displayExtraPrice,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
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
                        $extraName = substr((strlen($extra['menuExtraName']) > $charLength - 17 ?  substr($extra['menuExtraName'], 0, strpos(wordwrap($extra['menuExtraName'], $charLength - 20), "\n")).'...' : AppHelper::fromChinese($extra['menuExtraName'])), 0, $charLength - 17);
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
                        
                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                    }

                    $printer->text(str_pad('', 6, ' '));
                    $printer->text(str_pad(self::formatNumberValue($qtyExtra).'x', 4, ' '));
                    $printer->text(' ');
                    $printer->text(str_pad('@'.$priceExtraMenu, $charLength - 22, ' '));

                    if($printingReceiptMode == "1"){
                        $printer->text(' ');
                        $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                    }

                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                
                }else{

                    if($printReceiptWrapMenuName){
                        $tempMenuName = AppHelper::fromChinese($extra['menuExtraName']);;
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
                                $printer->text(str_pad('', 10, ' '));
                            }
                            $printer->text(str_pad($val, $charLength - 21, ' '));
                            if($i == 0){
                                if($printingReceiptMode == "1"){
                                    $printer->text(' ');
                                    $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                                }
                            }
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                            $i++;
                        }
                    }else{
                        $printer->text(str_pad($extraName, $charLength - 21, ' '));
                        if($printingReceiptMode == "1"){
                            $printer->text(' ');
                            $printer->text(str_pad($extraPriceTotal, (fmod($qtyExtra, 1) !== 0.00 ? 8 : 10), ' ', STR_PAD_LEFT));
                        }
                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
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
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        
        if ($salesArr['allMenuDiscountTotal'] != 0 && $this->settings['Show Menu Promotion Text'] !=0) {
            $promotionDetailName = strlen($salesArr['promotionDetailName']) > $charLength - 20 ? substr($salesArr['promotionDetailName'],
                        0, $charLength - 23) . '...' : $salesArr['promotionDetailName'];
            $discountText = $promotionDetailName;

            $discountTotalValue = $salesArr['discountValue'] + $packageDiscountValueTotal + $extraDiscountValueTotal;
            $discountTotal = $flagInclusive ? '' : number_format(-$discountTotalValue,
                    $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

            $printer->text(str_pad('', 6, ' '));
            $printer->text(str_pad($discountText, $charLength - 18, ' '));
            $printer->text(str_pad($discountTotal, 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        if (($salesArr['price'] == 0 && $salesArr['discount'] == 0 || $salesArr['promotionTypeID'] == 7) && $salesArr['promotionDetailID'] > 0 && ($salesArr['promotionTypeID'] != 9 && $salesArr['promotionTypeID'] != 1)) {
            $printer->text(str_pad('', 4, ' '));
            $printer->text(str_pad($salesArr['promotionDetailName'],
                    $charLength - 16, ' '));
            $printer->text(str_pad('', 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printBillSummary($printOrderList, $printOrder, $totalQty, $availableDepositTotal) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printDetailInclusive = array_key_exists('Print Detailed Inclusive Receipt', $this->settings) 
            ? $this->settings['Print Detailed Inclusive Receipt'] : false;
        
        $flagInclusive = $printOrder['flagInclusive'] > 0 ? true : false;
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $printerType = $this->stationModel->printerTypeID;
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
        if (!$flagInclusive || $printDetailInclusive) {
            $printer->text(str_pad(Yii::t('app', 'Subtotal'), $charLength - 15, ' ',
                    STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['subtotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        }
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        if ($printOrder['deliveryCost'] != 0) {
            $printer->text(str_pad(Yii::t('app', 'Delivery Cost'),
                    $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['deliveryCost'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        
        if ($printOrder['orderFee'] != 0) {
            $printer->text(str_pad(Yii::t('app', 'Order Fee'),
                    $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['orderFee'],
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($printOrder['menuDiscountTotal'] != 0) {
            $printer->text(str_pad(Yii::t('app', 'Menu Discount'),
                    $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['menuDiscountTotal'] * -1,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($printOrder['discountTotal'] != 0) {
            $promotionName = strlen($printOrder['promotionName']) > $charLength - 15 ? substr($printOrder['promotionName'],
                    0, $charLength - 18) . '...' : $printOrder['promotionName'];

            $printer->text(str_pad(Yii::t('app', $promotionName),
                    $charLength - 15, ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['discountTotal'] * -1,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($printOrder['otherTaxTotal'] !== 0) {
            if ($printOrder['otherTaxTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                $printer->text(str_pad($this->settings['Other Tax Text'],
                        $charLength - 15, ' ', STR_PAD_LEFT));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($printOrder['otherTaxTotal'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        if ($this->settings['Show Tax & VAT Amount Detail'] == 0) {
            if ($printOrder['vatTotal'] !== 0) {
                if ($printOrder['vatTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                    $printer->text(str_pad($this->settings['Tax Text'],
                            $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($printOrder['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
            
            if ($printOrder['otherVatTotal'] !== 0) {
                if ($printOrder['otherVatTotal'] != 0 && (!$flagInclusive || $printDetailInclusive)) {
                    $printer->text(str_pad($this->settings['VAT Text'],
                            $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        } else {
            if ((!$flagInclusive || $printDetailInclusive)) {
                $printer->text(str_pad("Tax",
                        $charLength - 15, ' ', STR_PAD_LEFT));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($printOrder['vatTotal'] + $printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        if (isset($printOrder['platformFee']) && count($printOrder['platformFee']) > 0) {
            foreach ($printOrder['platformFee'] as $row) {
                if ($row['amount'] > 0 && $row['platformFeeTypeID'] == 1) {
                    $printer->text(str_pad($row['feeNameEN'],
                        $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($row['amount'],
                            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }
        

        if ((!$flagInclusive || $printDetailInclusive)) {
            $printer->text(str_pad('', 8, ' '));
            $printer->text(str_pad('', $charLength - 8, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
        
        $roundingTotal = number_format($printOrder['roundingTotal'] * -1,
            $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

        if ($this->settings['Show Billing Rounding'] && $printOrder['roundingTotal'] != 0) {
            $printer->text(str_pad(Yii::t('app', 'Total'), $charLength - 15,
                    ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad(number_format($printOrder['grandTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad(Yii::t('app', 'Rounding'), $charLength - 15,
                    ' ', STR_PAD_LEFT));
            $printer->text(' : ');
            $printer->text(str_pad($roundingTotal, 12, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }

            $printer->text(str_pad('', 8, ' '));
            $printer->text(str_pad('', $charLength - 8, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if (count($printOrderList) == 1) {
            if ($printerType != 3 && $printerType != 15) {
                $printer->selectPrintMode(ExtPrinter::MODE_EMPHASIZED | ExtPrinter::MODE_DOUBLE_HEIGHT);
            }
        }
        $printer->text(str_pad(Yii::t('app',
                    count($printOrderList) > 1 ? 'Billing Total' : 'Grand Total'),
                $charLength - 15, ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($printOrder['grandTotal'] - $printOrder['roundingTotal'],
                    $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        if (count($printOrderList) == 1 && $availableDepositTotal > 0) {
            $this->printDepositInfo($availableDepositTotal, $charLength,
                $printOrder['grandTotal'] - $printOrder['roundingTotal']);
        }
        
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(3);
        }
        // @reset for edot mode
        if (count($printOrderList) == 1) {
            if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
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
            if (($showInclusiveOtherTax && !$printDetailInclusive) && $printOrder['otherTaxTotal'] > 0) {
                $textPrinted = 'Price inclusive of ' . $this->settings['Other Tax Text'];
                if ($charLength > 32) {
                    $printer->text(str_pad($textPrinted, $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($printOrder['otherTaxTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                } else {
                    $printer->text($textPrinted);
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text(number_format($printOrder['otherTaxTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
                }
                $this->printLineBreak();
            }
            if ($this->settings['Show Tax & VAT Amount Detail'] == 0) {
                if (($showInclusiveTax && !$printDetailInclusive) && $printOrder['vatTotal'] > 0) {
                    $textPrinted = 'Price inclusive of ' . $this->settings['Tax Text'];
                    if ($charLength > 32) {
                        $printer->text(str_pad($textPrinted, $charLength - 15, ' ', STR_PAD_LEFT));
                        $printer->text(' : ');
                        $printer->text(str_pad(number_format($printOrder['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    } else {
                        $printer->text($textPrinted);
                        $printer->text(' : ');
                        $this->printLineBreak();
                        $printer->text(number_format($printOrder['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
                    }
                    $this->printLineBreak();
                }
                if (($showInclusiveVAT && !$printDetailInclusive) && $printOrder['otherVatTotal'] > 0) {
                    $textPrinted = 'Price inclusive of ' . $this->settings['VAT Text'];
                    if ($charLength > 32) {
                        $printer->text(str_pad($textPrinted, $charLength - 15, ' ', STR_PAD_LEFT));
                        $printer->text(' : ');
                        $printer->text(str_pad(number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                    } else {
                        $printer->text($textPrinted);
                        $printer->text(' : ');
                        $this->printLineBreak();
                        $printer->text(number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
                    }
                    $this->printLineBreak();
                }
            } else {
                $textPrinted = 'Price inclusive of Tax';
                if ($charLength > 32) {
                    $printer->text(str_pad($textPrinted, $charLength - 15, ' ', STR_PAD_LEFT));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($printOrder['vatTotal'] + $printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
                } else {
                    $printer->text($textPrinted);
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text(number_format($printOrder['vatTotal'] + $printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
                }
                $this->printLineBreak();
            }
            $this->printLineBreak();
        }

        if ($this->settings['Show Tax & VAT Amount Detail'] == 1) {
            $textPrinted = 'Tax included ' . $this->settings['Tax Text'] . ' = ' . number_format($printOrder['vatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator") . ' and ' . 
                $this->settings['VAT Text'] . ' = ' . number_format($printOrder['otherVatTotal'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            $printer->text(str_pad($textPrinted,
                    $charLength, ' ', STR_PAD_LEFT));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $this->printLineBreak();
        }

        $printer->initialize();
    }

    private function printDepositInfo($availableDepositTotal, $charLength, $billingTotal) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        $printer->initialize();
        $printer->text(str_pad('', 8, ' '));
        $printer->text(str_pad(Yii::t('app', 'Deposit Total'), $charLength - 23,
                ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $printer->text(str_pad(number_format($availableDepositTotal * -1, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', 8, ' '));
        $printer->text(str_pad('', $charLength - 8, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->text(str_pad('', 8, ' '));
        $printer->text(str_pad(Yii::t('app', 'Outstanding Total'),
                $charLength - 23, ' ', STR_PAD_LEFT));
        $printer->text(' : ');
        $outstandingTotal = $billingTotal - $availableDepositTotal < 0 ? 0 : $billingTotal - $availableDepositTotal;
        $printer->text(str_pad(number_format($outstandingTotal,
                    $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
    }

    private function printHeaderGroupMenuCategory() {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $printer->text(str_pad('', $charLength, '-'));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printGroupMenuCategory($menuCategory) {
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $salesArr = $menuCategory;
        $menuCategoryDesc = substr('Total' . ' ' . $salesArr['menuCategoryDesc'],
            0, $charLength - 16);
        $total = number_format($salesArr['total'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");

        $printer->text(str_pad($menuCategoryDesc, $charLength - 14, ' '));
        $printer->text(str_pad($total, 14, ' ', STR_PAD_LEFT));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
    
    public function getDisplayPriceValue($flagInclusive, $total, $discountValue, $qty, $price, $inclusivePrice) {
        if ($flagInclusive) {
            $price = $inclusivePrice;
        }
        return $price;
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

        if ($trialMode != null && $trialMode->value1 == 1) {
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            
            $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));
            $printer->text(' TRIAL MODE ');
            $printer->text(str_pad('', ($charLength - 14) / 2, '*', STR_PAD_LEFT));

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(2);
            }
        }
    }

}
