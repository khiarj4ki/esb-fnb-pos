<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\components\BixolonStickerPrinter;
use app\components\BrotherStickerPrinter;
use app\components\GStickerPrinter;
use app\components\SatoStickerPrinter;
use app\components\StickerPrinter;
use app\components\ZebraStickerPrinter;
use app\models\Branch;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesInfo;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\Station;
use Exception;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\helpers\ArrayHelper;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $batchID
 * @property int $queueNum
 * 
 * PRIVATE
 * @property array $settings
 * @property SalesHead $salesModel
 * @property SalesMenu[] $salesMenusModel
 */
class PrintOdsOrder extends Model {
    public $batchID;
    public $finishedQty;
    public $tableID;
    public $salesNum;
    public $salesMenuID;
    public $stationIDs;
    public $settings;
    public $salesModel;
    public $salesMenusModel;
    public $queueNum;
    public $completedTime;
    public $viewMode;
	public $printOnlyPackage;
    public $salesMenuIDs;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['batchID', 'stationIDs'], 'required'],
            [['tableID', 'batchID', 'salesMenuID', 'queueNum', 'viewMode'], 'integer'],
            [['finishedQty'], 'number'],
			[['printOnlyPackage', 'salesMenuIDs'], 'safe'],
            [['salesNum', 'completedTime'], 'string', 'max' => 20],
            [['salesNum'], 'validateTable'],
            [['batchID'], 'validateBatch'],
        ];
    }

    public function validateTable($attribute) {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::find()
            ->where(['branchID' => $branchID])
            ->one();
        $printingSettings = Setting::getPrintingSettings();
        $this->tableID = SalesHead::findOne(['salesNum' => $this->salesNum])->tableID;
        $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;

        $this->salesModel = SalesHead::findOrder()
        ->with('table.tableSection')
        ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
        ->one();

        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }

        // @Notes: Get queue number
        $this->queueNum = $this->salesModel->queueNum;
    }

    public function validateBatch($attribute) {
        // @Notes: 19 = Print Cancelled, 13 = Preparing
        $statusID = 13;
        $batchID = $this->batchID;
        if ($batchID == -1) {
            $salesMenu = SalesMenu::findMainMenus($this->salesModel->salesNum,
                    $statusID, $batchID, null, $this->printOnlyPackage)
                ->andWhere(['IN', SalesMenu::tableName() .'.ID',  $this->salesMenuIDs])
                ->all();

            $this->salesMenusModel = SalesHead::groupingOrderForBilling($salesMenu);
        } else {
            if ($this->salesMenuID) {
                $this->salesMenusModel = SalesMenu::findMainMenus($this->salesModel->salesNum,
                        $statusID, $batchID, $this->salesMenuID, $this->printOnlyPackage)
                    ->all();
            } else {
                $this->salesMenusModel = SalesMenu::findMainMenus($this->salesModel->salesNum,
                        $statusID, $batchID, null, $this->printOnlyPackage)
                    ->andWhere(['IN', SalesMenu::tableName() .'.ID',  $this->salesMenuIDs])
                    ->all();
            }
        }

        if (!$this->salesMenusModel) {
            $this->addError($attribute, 'Batch ID not found');
        }
    }

    public function doPrint() {
        if (!$this->validate()) {
            Yii::error($this->getErrors());
            return false;
        }

        $this->settings = Setting::getPrintingSettings();

        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $stationOrderList = $this->groupingOrderForPrinting('stationID');
        $this->printOrder($stationOrderList, false, null, $this->printOnlyPackage);
    }

    private function groupingOrderForPrinting($field) {
        $salesMenusModel = $this->salesMenusModel;
        $orderList = [];

        foreach ($salesMenusModel as $salesMenu) {
            $printDataVal = true;
            $processedQty = 0;
            if ($this->finishedQty == -1) {
                $resHeader = $this->checkSalesQty($salesMenu);
                $printDataVal = $resHeader['printDataVal'];
                $processedQty = $resHeader['processedQty'];
            }
            if ($printDataVal) {
                if ($this->stationIDs) {
                    foreach(explode(',', $this->stationIDs) as $stationID) {
                        $orderList[$stationID][$salesMenu->ID]['qty'] = $this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQty : (float) $this->finishedQty;
                        $orderList[$stationID][$salesMenu->ID]['menuName'] = $salesMenu->menu->menuName;
                        $orderList[$stationID][$salesMenu->ID]['menuShortName'] = $salesMenu->menu->menuShortName;
                        $orderList[$stationID][$salesMenu->ID]['customMenuName'] = $salesMenu->customMenuName;
                        $orderList[$stationID][$salesMenu->ID]['notes'] = $salesMenu->notes;

                        if ($salesMenu->salesExtras) {
                            foreach ($salesMenu->salesExtras as $extra) {
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['qty'] = (float) $extra->qty * ($this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQty : (float) $this->finishedQty);
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['menuExtraName'] = $extra->menuExtra->menuExtraName;
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['menuExtraShortName'] = $extra->menuExtra->menuExtraShortName;
                            }
                        }
                    }

                }

                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $package) {
                        $packageStationIDs = $this->stationIDs;
                        if ($packageStationIDs) {
                            foreach(explode(',', $packageStationIDs) as $packageStationID) {
                                if ($this->printOnlyPackage) {
                                    if ($this->salesMenuID == $package->ID) {
                                        $orderList[$packageStationID][$salesMenu->ID]['qty'] = $this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQty : (float) $this->finishedQty;
                                        $orderList[$packageStationID][$salesMenu->ID]['menuName'] = $salesMenu->menu->menuName;
                                        $orderList[$packageStationID][$salesMenu->ID]['menuShortName'] = $salesMenu->menu->menuShortName;
                                        $orderList[$packageStationID][$salesMenu->ID]['customMenuName'] = $salesMenu->customMenuName;
                                        $orderList[$packageStationID][$salesMenu->ID]['notes'] = $salesMenu->notes;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['qty'] = (float) $this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQty : (float) $this->finishedQty;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuName'] = $package->menu->menuName;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuShortName'] = $package->menu->menuShortName;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['notes'] = $package->notes;
                                    }
                                } else {
                                    $processedQtyPck = 0;
                                    if ($this->finishedQty == -1) {
                                        $resHeader = $this->checkSalesQty($package);
                                        $processedQtyPck = $resHeader['processedQty'];
                                    }
                                    $orderList[$packageStationID][$salesMenu->ID]['qty'] = $this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQty : (float) $this->finishedQty;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuName'] = $salesMenu->menu->menuName;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuShortName'] = $salesMenu->menu->menuShortName;
                                    $orderList[$packageStationID][$salesMenu->ID]['customMenuName'] = $salesMenu->customMenuName;
                                    $orderList[$packageStationID][$salesMenu->ID]['notes'] = $salesMenu->notes;
                                    if (((float) $salesMenu->qty - $processedQtyPck) > 0) {
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['qty'] = (float) $package->qty * ($this->finishedQty == -1 ? (float) $salesMenu->qty - $processedQtyPck : (float) $this->finishedQty);
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuName'] = $package->menu->menuName;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuShortName'] = $package->menu->menuShortName;
                                        $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['notes'] = $package->notes;
                                    }
                                }
                            }

                        }
                    }
                }
            }
        }

        return $orderList;
    }

    private function checkSalesQty($salesMenu) {
        $printDataVal = true;
        $processedQty = 0;
        $processedModel = SalesMenuCompletion::find()
            ->select([
                'qty' => new Expression('SUM(qty)')
            ])
            ->where(['salesMenuID' => $salesMenu->ID])
            ->andWhere(['<', 'completedDate', $this->completedTime])
            ->andWhere(['typeID' => $this->viewMode])
            ->one();
        
        if ($processedModel) {
            $processedQty = (float) $processedModel->qty;
            
            if (($salesMenu->qty - $processedQty) <= 0) {
                $printDataVal = false;
            }
        }

        return [
            'processedQty' => $processedQty,
            'printDataVal' => $printDataVal
        ];
    }

    // @Notes: overrideSingleMenuPrint to override singgle menu print on printer setting
    private function printOrder($orderList, $checkerStation, $overrideSingleMenuPrint = null, $printOnlyPackage = false) {
        $totalStickerItems = 0;
        $stickerCounter = 1;
        foreach ($orderList as $stationID => $printOrders) {
            $stationModel = Station::findActive()
                ->andWhere(['stationID' => $stationID])
                ->one();

            $printingModeID = isset($overrideSingleMenuPrint) ? $overrideSingleMenuPrint : $stationModel->printingModeID;

            $charLength = $stationModel->characterPerLine;

            if (($checkerStation == false) && ($stationModel->printerTypeID == 2 ||
                $stationModel->printerTypeID == 6 || $stationModel->printerTypeID == 7 ||
                $stationModel->printerTypeID == 8 || $stationModel->printerTypeID == 9 || 
                $stationModel->printerTypeID == 10 || $stationModel->printerTypeID == 11)) {
                foreach ($printOrders as $printOrder) {
                    $totalStickerItems += $printOrder['qty'];
                }
            }

            try {
                if (($checkerStation == false) && ($stationModel->printerTypeID == '2')) {
                    $host = $stationModel->printerName;
                    $port = $stationModel->printerPort;
                    $stationName = $stationModel->stationName;
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printSticker($stationName, $this->salesNum,
                                $printOrder, $stickerCounter,
                                $totalStickerItems, $host, $port, $charLength,
                                $this->queueNum);
                            $stickerCounter += 1;
                        }
                    }
                }
                if (($checkerStation == false) && ($stationModel->printerTypeID == 7)) {
                    $gStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    $printer = new GStickerPrinter($host, $connectionType, $stationModel);
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printGSticker($printer, $this->queueNum,
                                $printOrder, $gStickerCounter,
                                $totalStickerItems);
                            $gStickerCounter += 1;
                        }
                    }
                    $printer->close();
                }

                if (($checkerStation == false) && ($stationModel->printerTypeID == 8)) {
                    $satoStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printSatoSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $satoStickerCounter, 
                                $totalStickerItems,
                                $stationModel);
                            $satoStickerCounter += 1;
                        }
                    }
                }

                if (($checkerStation == false) && ($stationModel->printerTypeID == 9)) {
                    $zebraStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printZebraSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $zebraStickerCounter, 
                                $totalStickerItems,
                                $stationModel);
                            $zebraStickerCounter += 1;
                        }
                    }
                }
                
                if (($checkerStation == false) && ($stationModel->printerTypeID == 10)) {
                    $bixolonStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printBixolonSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $bixolonStickerCounter, 
                                $totalStickerItems,
                                $stationModel);
                            $bixolonStickerCounter += 1;
                        }
                    }
                }
                
                if (($checkerStation == false) && ($stationModel->printerTypeID == 11)) {
                    $brotherStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printOrders as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printBrotherSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $brotherStickerCounter, 
                                $totalStickerItems,
                                $stationModel);
                            $brotherStickerCounter += 1;
                        }
                    }
                }

                if ($stationModel->printerTypeID != 2) {
                    $connector = Station::getConnectorByModel($stationModel,
                            $this->salesNum);

                    if($connector == null){
                        throw new Exception("Failed to print. Connector not found", 400);
                    }
                    
                    $printer = new Printer($connector);

                    if (($checkerStation == false) && ($stationModel->printerTypeID == 6)) {
                        $epsonStickerCounter = 1;
                        $stationName = $stationModel->stationName;
                        foreach ($printOrders as $printOrder) {
                            for ($i = 1; $i <= $printOrder['qty']; $i++) {
                                $this->epsonSticker(
                                    $printer, $this->queueNum,
                                    $printOrder, $epsonStickerCounter,
                                    $totalStickerItems,
                                    $stationModel);
                                $epsonStickerCounter += 1;
                            }
                        }
                    }

                    if ($stationModel->printerTypeID != 6) {
                        if ($printingModeID > 1) {
                            foreach ($printOrders as $printOrder) {
                                if ($printingModeID == 3) {
                                    for ($i = 1; $i <= $printOrder['qty']; $i++) {
                                        if(isset($printOrder['packages'])){
                                            $newPrintOrder = [];
                                            foreach ($printOrder['packages'] as $package) {
                                                $qtyNow = $package['qty'];
                                                for ($iPackages = 1; $iPackages <= $qtyNow; $iPackages++){
                                                    $newPrintOrder = $printOrder;
                                                    $package['qty'] = 1;
                                                    $newPrintOrder['packages'] = $package;
                                                    $this->printOrderInfo($printer,
                                                        $stationModel, $checkerStation);
                                                    $this->printOrderDetail($printer,
                                                        $newPrintOrder, $stationModel, 3);

                                                    $printer->initialize();
                                                    $printer->text(str_pad('', $charLength,
                                                            '-'));
                                                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15 || $stationModel->printerTypeID == 15) {
                                                        $printer->getPrintConnector()->write("\x0A");
                                                    } else {
                                                        $printer->feed(1);
                                                    }
                                                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15 || $stationModel->printerTypeID == 15) {
                                                        $printer->getPrintConnector()->write("\x07" . "\x1C");
                                                    } else {
                                                        $printer->pulse(1);
                                                    }

                                                    if ($stationModel->printerTypeID == '4') {
                                                        $printer->feed(2);
                                                    } else if ($stationModel->printerTypeID == 15) {
                                                        if ($stationModel->flagAutocut == '1') {
                                                            $printer->feed(2);
                                                        }
                                                    } else {
                                                        if ($stationModel->flagAutocut == '1') {
                                                            $printer->cut(Printer::CUT_PARTIAL);
                                                        }
                                                    }
                                                }
                                                
                                            }
                                        }
                                        else{
                                            $this->printOrderInfo($printer,
                                                $stationModel, $checkerStation);
                                            $this->printOrderDetail($printer,
                                                $printOrder, $stationModel, 3);

                                            $printer->initialize();
                                            $printer->text(str_pad('', $charLength,
                                                    '-'));
                                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                $printer->getPrintConnector()->write("\x0A");
                                            } else {
                                                $printer->feed(1);
                                            }
                                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                $printer->getPrintConnector()->write("\x07" . "\x1C");
                                            } else {
                                                $printer->pulse(1);
                                            }

                                            if ($stationModel->printerTypeID == '4') {
                                                $printer->feed(2);
                                            } else if ($stationModel->printerTypeID == 15) {
                                                if ($stationModel->flagAutocut == '1') {
                                                    $printer->feed(2);
                                                }
                                            } else {
                                                if ($stationModel->flagAutocut == '1') {
                                                    $printer->cut(Printer::CUT_PARTIAL);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    if(isset($printOrder['packages'])){
                                        $newPrintOrder = [];
                                        foreach ($printOrder['packages'] as $package) {
                                            $newPrintOrder = $printOrder;
                                            $newPrintOrder['packages'] = $package;
                                            $this->printOrderInfo($printer,
                                                $stationModel, $checkerStation);
                                            $this->printOrderDetail($printer,
                                                $newPrintOrder, $stationModel, $printingModeID);
                                            $printer->initialize();
                                            $printer->text(str_pad('', $charLength, '-'));
                                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                $printer->getPrintConnector()->write("\x0A");
                                            } else {
                                                $printer->feed(1);
                                            }
                                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                $printer->getPrintConnector()->write("\x07" . "\x1C");
                                            } else {
                                                $printer->pulse(1);
                                            }

                                            if ($stationModel->printerTypeID == '4') {
                                                $printer->feed(2);
                                            } else if ($stationModel->printerTypeID == 15) {
                                                if ($stationModel->flagAutocut == '1') {
                                                    $printer->feed(2);
                                                }
                                            } else {
                                                if ($stationModel->flagAutocut == '1') {
                                                    $printer->cut(Printer::CUT_PARTIAL);
                                                }
                                            }
                                        }
                                    }
                                    else{
                                        $this->printOrderInfo($printer,
                                            $stationModel, $checkerStation);
                                        $this->printOrderDetail($printer,
                                            $printOrder, $stationModel, $printingModeID);
                                        $printer->initialize();
                                        $printer->text(str_pad('', $charLength, '-'));
                                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                            $printer->getPrintConnector()->write("\x0A");
                                        } else {
                                            $printer->feed(1);
                                        }
                                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                            $printer->getPrintConnector()->write("\x07" . "\x1C");
                                        } else {
                                            $printer->pulse(1);
                                        }

                                        if ($stationModel->printerTypeID == '4') {
                                            $printer->feed(2);
                                        } else if ($stationModel->printerTypeID == 15) {
                                            if ($stationModel->flagAutocut == '1') {
                                                $printer->feed(2);
                                            }
                                        } else {
                                            if ($stationModel->flagAutocut == '1') {
                                                $printer->cut(Printer::CUT_PARTIAL);
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $this->printOrderInfo($printer, $stationModel,
                                $checkerStation);
                            
                            foreach ($printOrders as $printOrder) {
                                $this->printOrderDetail($printer,
                                    $printOrder, $stationModel,
                                    $printingModeID);
                            }

                            $printer->initialize();
                            $printer->text(str_pad('', $charLength, '-'));
                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }

                            if (in_array($stationModel->printerTypeID, [1, 2, 3])) {
                                $printer->pulse(1);
                            }

                            if ($stationModel->printerTypeID == '4') {
                                $printer->feed(2);
                            } else if ($stationModel->printerTypeID == '5') {
                                $printer->feed(2);
                            } else if ($stationModel->printerTypeID == 15) {
                                if ($stationModel->flagAutocut == '1') {
                                    $printer->feed(2);
                                }
                            } else {
                                if ($stationModel->flagAutocut == '1') {
                                    $printer->cut(Printer::CUT_PARTIAL);
                                }
                            }
                        }
                    }
                    if ($stationModel->printerTypeID !== 8 && $stationModel->printerTypeID !== 9) {
                        // printer Sato error karena close dua kali
                        $printer->close();
                    }
                }
            } catch (Exception $ex) {
                Yii::warning('Printing to ' . $stationModel->stationName);
                Yii::warning($ex);
            }
        }
    }

    private function printTableName($printer, $stationModel, int $varLength){
        //@NOTES: For ESB Order Transaction
        $settingEZO = setting::getEZOSetting();
        $showCheckerSalesInfo = isset($settingEZO['Show Checker Sales Info']) ? $settingEZO['Show Checker Sales Info'] : false;
        $salesInfoTableName = SalesInfo::findBySalesNumKey( $this->salesModel->salesNum, 'Table Name');
        if($showCheckerSalesInfo && !empty($salesInfoTableName)){
            $printer->text(str_pad(Yii::t('app', 'Table'), $varLength, ' '));
            $printer->text(' : ');
            $printer->text($salesInfoTableName);
            if (in_array($stationModel->printerTypeID, [4, 15])) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printOrderInfo(&$printer, $stationModel, $checkerStation = false) {
        $charLength = $stationModel->characterPerLine;
        $printerType = $stationModel->printerTypeID;
        if ($this->scenario == self::SCENARIO_DEFAULT) {
            if ($this->settings['Kitchen Checker Top Margin'] != 0) {
                $printer->feed(intval($this->settings['Kitchen Checker Top Margin']));
            }
        }
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $this->printLableTrialMode($printer, $stationModel);

        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } else {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } else {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        if ($this->scenario == self::SCENARIO_DEFAULT) {

            if ($this->settings['Show Kitchen Sales Number']) {
                $printer->text('#' . $this->salesModel->salesNum);
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $printFormatString = '';
        $allowPrintKitchenDate = isset($this->settings['Show Printing Kitchen Date']) ? $this->settings['Show Printing Kitchen Date'] : true;
        $allowPrintKitchenTime = isset($this->settings['Show Printing Kitchen Time']) ? $this->settings['Show Printing Kitchen Time'] : true;
        if ($allowPrintKitchenDate) {
            $printFormatString .= date_format(date_create($this->salesModel->editedDate),
                'd-m-Y ');
        }
        if ($allowPrintKitchenTime) {
            $printFormatString .= date_format(date_create($this->salesModel->editedDate),
                'H:i');
        }

        if ($allowPrintKitchenDate || $allowPrintKitchenTime) {
            $printer->text(str_pad($printFormatString, ($charLength - 18) / 2,
                    ' '));
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if (array_key_exists('Queue Number', $this->settings)) {
            if ($this->settings['Queue Number'] == 1) {
                $printer->text(str_pad(Yii::t('app', 'Queue'), 6, ' '));
                $printer->text(' : ');
                $printer->text($this->queueNum);
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $this->printTableName($printer, $stationModel, 6);

        $showPrintingTableOrder = isset($this->settings['Show Printing Table Order']) ? $this->settings['Show Printing Table Order'] : 1;
        if ($showPrintingTableOrder) {
            if ($this->salesModel->tableID != 0) {
                $printer->text(str_pad(Yii::t('app', 'Table'), 6, ' '));
                $printer->text(' : ');
            }

            $printTakeAwaySettings = array_key_exists('Print Quick Service Table Text',
                    $this->settings) ? $this->settings['Print Quick Service Table Text'] : true;
            if (($printTakeAwaySettings && $this->salesModel->tableID == 0) || $this->salesModel->tableID != 0) {
                $printer->text(str_pad($this->salesModel->table ? $this->salesModel->table->tableName : 'Quick Service',
                        ($charLength - 18) / 2, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            $showPrintingTableSectionOrder = isset($this->settings['Show Printing Table Section Order']) ? $this->settings['Show Printing Table Section Order'] : 1;
            if ($showPrintingTableSectionOrder) {
                if ($this->salesModel->tableID != 0) {
                    $printer->text($this->salesModel->table ? $this->salesModel->table->tableSection->tableSectionName : 'Quick Service');
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                } else {
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }

            $allowPrintKitchenSalesMode = isset($this->settings['Show Printing Sales Mode Order']) ? $this->settings['Show Printing Sales Mode Order'] : true;
            if ($allowPrintKitchenSalesMode) {
                $printer->text(str_pad($this->salesModel->visitPurpose->visitPurposeName,
                        ($charLength - 18) / 2, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(2);
                }
            } else {
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            $printer->initialize();
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            $salesInfoPickupTime = SalesInfo::find()
                ->where(['salesNum' => $this->salesModel->salesNum])
                ->andWhere(['IN', 'key', ['Pickup Time', 'Delivery Time']])
                ->one();

            if ($salesInfoPickupTime != null) {
                $printer->text(str_pad(Yii::t('app', 'Pickup Time'), 11, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($salesInfoPickupTime->value,
                        $charLength - 14, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(2);
                }
            }

            $printer->initialize();
            if ($this->scenario == self::SCENARIO_DEFAULT) {
                $printingInfo = (isset($this->salesModel->customer['fullName']) && $this->salesModel->customer['fullName']) ? $this->salesModel->customer['fullName'] : $this->salesModel->additionalInfo;
                if ($printingInfo != null) {
                    $showPrintingInfoOrder = isset($this->settings['Show Printing Info Order']) ? $this->settings['Show Printing Info Order'] : 1;
                    if ($showPrintingInfoOrder) {
                        $printer->text(str_pad(Yii::t('app', 'Info'), 8, ' ', STR_PAD_RIGHT));
                        $printer->text(' : ');
                        $printer->text(str_pad($printingInfo, $charLength - 11, ' '));
                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                    }
                }
            }

            $printer->initialize();
            $showPrintingWaiterOrder = isset($this->settings['Show Printing Waiter Order']) ? $this->settings['Show Printing Waiter Order'] : 1;
            if ($showPrintingWaiterOrder) {
                $printer->text(str_pad(Yii::t('app', 'Waiter'), 8, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($this->salesModel->creator ? $this->salesModel->creator->fullName : "SELF ORDER",
                        $charLength - 11, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            $showPrintingSenderOrder = isset($this->settings['Show Printing Sender Order']) ? $this->settings['Show Printing Sender Order'] : 1;
            if ($showPrintingSenderOrder) {
                $printer->text(str_pad(Yii::t('app', 'Sender'), 8, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($this->salesMenusModel[0]->creator ? $this->salesMenusModel[0]->creator->fullName : "SELF ORDER",
                        $charLength - 11, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            if ($this->scenario == self::SCENARIO_DEFAULT) {
                $showPrintingBatchOrder = isset($this->settings['Show Printing Batch Order']) ? $this->settings['Show Printing Batch Order'] : 1;
                if ($showPrintingBatchOrder) {
                    $printer->text(str_pad(Yii::t('app', 'Batch'), 8, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad($this->batchID < 0 ? 'All' : $this->batchID,
                            $charLength - 11, ' '));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }

        $showCheckerCustomerInfo = isset($this->settings['Show Checker Customer Info']) ? $this->settings['Show Checker Customer Info'] : false;

        if ($showCheckerCustomerInfo) {
            $salesInfosFullName = SalesInfo::findBySalesNumKey($this->salesModel->salesNum, 'Full Name');
            $printer->text(str_pad(Yii::t('app', 'Customer'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($salesInfosFullName, $charLength - 11, ' '));
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $this->printTableName($printer, $stationModel, 8);

        $printer->initialize();
        $printer->text(str_pad('', $charLength, '='));
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        if ($stationModel->printerTypeID != 15) {
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
        }
        if ($checkerStation) {
            $printer->text(Yii::t('app', 'Main Checker - ') . $stationModel->stationName);
        } else {
            $printer->text($stationModel->stationName);
        }
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        $printer->initialize();
        $printer->text(str_pad('', $charLength, '='));
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printOrderDetail(&$printer, $printOrder, $stationModel, $printingModeID = 1) {
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }
        $charLength = $stationModel->characterPerLine;
        if ($stationModel->printerTypeID != 15) {
            if ($stationModel->printerTypeID == 3) {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            } else {
                $printer->setTextSize(1,2);
            }
        }
        if(!$this->printOnlyPackage) {
            $printOrderQty = AppHelper::formatNumberValue($printOrder['qty'], null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator);
			$printer->text(str_pad($printingModeID == 3 ? 1 : $printOrderQty, 3,
                ' ', STR_PAD_LEFT));
			$printer->text(' ');
			$printer->text($printOrder['customMenuName'] ? $printOrder['customMenuName'] : AppHelper::fromChinese($printOrder['menuShortName']));
		} else {
			$printer->text($printOrder['customMenuName'] ? '<'.$printOrder['customMenuName'].'>' : '<'. AppHelper::fromChinese($printOrder['menuShortName']).'>');
		}
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if (isset($printOrder['packages'])) {
            if($printingModeID > 1){
                $this->printPackage($printer,$printOrder['packages'],$stationModel,$charLength);
            }
            else{
                foreach ($printOrder['packages'] as $package) {
                    $this->printPackage($printer,$package,$stationModel,$charLength);
                }
            }
        }
        if (isset($printOrder['extras'])) {
            foreach ($printOrder['extras'] as $extra) {
                $printer->text(str_pad('', 4, ' '));
                $printer->text(str_pad($extra['qty'], 3, ' ', STR_PAD_LEFT));
                $printer->text(' ');
                $printer->text(AppHelper::fromChinese($extra['menuExtraShortName']));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }
        if ($printOrder['notes']) {
            $notesString = $printOrder['notes'];
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
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printPackage($printer,$package,$stationModel,$charLength){
        $printer->text(str_pad('', 4, ' '));
        $printer->text(str_pad(AppHelper::formatNumberValue($package['qty'], null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator), 3, ' ', STR_PAD_LEFT));
        $printer->text(' ');
        $printer->text($package['menuShortName']);
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($package['notes']) {
            $notesPackageString = $package['notes'];
            if (strpos($notesPackageString, "\n") !== false) {
                $notesPackageString = str_replace("\n", ", ",
                    $notesPackageString);
            }
            if (strlen($notesPackageString) >= $charLength - 13) {
                $printer->text(str_pad('', 10, ' '));
                $printer->text('* ');
                $printer->text(substr($notesPackageString, 0,
                        $charLength - 13));
                $subPackageString = substr($notesPackageString,
                    $charLength - 13);
                do {
                    $printer->text(str_pad('', 13, ' '));
                    $printer->text(substr($subPackageString, 0,
                            $charLength - 13));
                    if (strlen($subPackageString) >= ($charLength - 13)) {
                        $subPackageString = substr($subPackageString,
                            $charLength - 13);
                    } else {
                        break;
                    }
                } while (1);
            } else {
                $printer->text(str_pad('', 10, ' '));
                $printer->text('* ');
                $printer->text($notesPackageString);
            }
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printSticker($stationName, $salesNum, $detail, $counter, $totalItems, $host, $port, $charLength, $queueNumber) {
        try {
            $printer = new StickerPrinter($host, $port, $charLength);
            //$queueNumber = substr($salesNum, -4);
            $counterText = "$counter / $totalItems";
            $header = $queueNumber . " - " . $counterText;

            if ($this->salesModel->additionalInfo) {
                $header .= " - " . $this->salesModel->additionalInfo;
            }

            $printer->addLine($stationName, 9);
            $printer->addLine($header, 9);
            $printer->addLine($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName'], 9, true);

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->addLine($package['menuShortName'], 9, false, 30);
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->addLine($extra['menuExtraShortName'], 9, false, 30);
                }
            }

            if (isset($detail['notes']) != '') {
                $notes = substr($detail['notes'], 2);
                $printer->addLine($notes, 9, false);
            }

            $printer->addBlankLine();
            $printer->addBlankLine();

            $printer->addLine(date("d/m/Y") . " / " . $this->salesModel->creator->fullName,
                7);

            $printer->sendToPrinter();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function epsonSticker($printer, $queueNumber, $detail, $counter, $totalItems,$stationModel) {
        $marginLeftValue = array_key_exists('Epson Sticker Margin Left',
                $this->settings) ? $this->settings['Epson Sticker Margin Left'] : 40;
        $paperWidthValue = array_key_exists('Epson Sticker Width',
                $this->settings) ? $this->settings['Epson Sticker Width'] : 500;
        $marginLeft = intval($marginLeftValue);
        $paperWidth = intval($paperWidthValue);
        $counterText = "$counter / $totalItems";
        $header = $queueNumber . " - " . $counterText;

        $lastFeed = 5;

        if ($this->salesModel->additionalInfo) {
            $header .= " - " . $this->salesModel->additionalInfo;
        }

        $printer->setPrintLeftMargin($marginLeft);
        $printer->setPrintWidth($paperWidth);

        $printer->feed(1);
        $printer->text($header);
        $printer->feed(1);

        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        $printer->text($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName']);
        $printer->feed(1);
        $printer->initialize();

        $printer->setPrintLeftMargin($marginLeft);
        $printer->setPrintWidth($paperWidth);

        if ($detail['notes']) {
            $notes = "* " . $detail['notes'];
            $printer->text($notes);
            $printer->feed(1);
            --$lastFeed;
        }

        if (isset($detail['packages'])) {
            foreach ($detail['packages'] as $package) {
                $printer->text($package['menuShortName']);
                $printer->feed(1);
                --$lastFeed;

                if ($package['notes']) {
                    $notes = "* " . $package['notes'];
                    $printer->text($notes);
                    $printer->feed(1);
                    --$lastFeed;
                }
            }
        }

        if (isset($detail['extras'])) {
            foreach ($detail['extras'] as $extra) {
                $printer->text($extra['menuShortName']);
                $printer->feed(1);
                --$lastFeed;

                if ($extra['notes']) {
                    $notes = "* " . $extra['notes'];
                    $printer->text($notes);
                    $printer->feed(1);
                    --$lastFeed;
                }
            }
        }

        $printer->text(date("d/m/Y") . " / " . $this->salesModel->creator->fullName);
        $lastFeed > 0 ? $printer->feed($lastFeed) : null;
        if ($stationModel->flagAutocut == '1') {
            $printer->cut(Printer::CUT_PARTIAL);
        }
    }

    private function printGSticker($printer, $queueNumber, $detail, $counter, $totalItems) {
        try {
            $counterText = "$queueNumber - $counter/$totalItems";

            if ($this->salesModel->additionalInfo) {
                $header = $this->salesModel->additionalInfo;
            } else {
                $header = $this->salesModel->salesNum;
            }

            $printer->clear();
            $printer->write($counterText, 8);
            $printer->write($header, 8);
            $printer->write($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName'], 2);

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes, 2);
            }

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->write($package['menuShortName'], 2);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->write($notes, 2);
                    }
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->write($extra['menuExtraShortName'], 2);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->write($notes, 2);
                    }
                }
            }

            $printer->write(date("d/m/Y"), 8);
            $printer->closeWrite();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function printSatoSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $stationModel) {
        try {
            $printer = new SatoStickerPrinter($host, $connectionType, $stationModel);
            $counterText = "$queueNumber - $counter/$totalItems";

            if ($this->salesModel->additionalInfo) {
                $header = $this->salesModel->additionalInfo;
            } else {
                $header = $this->salesModel->salesNum;
            }
            $printer->clear();
            $printer->write($counterText);
            $printer->write($header);
            $printer->write($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName']);

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->write($package['menuShortName']);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->write($notes);
                    }
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->write($extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->write($notes);
                    }
                }
            }
            $printer->write(date("d/m/Y"));
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function printZebraSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $stationModel) {
        try {
            $printer = new ZebraStickerPrinter($host, $connectionType, $stationModel);
            $counterText = "$queueNumber - $counter/$totalItems";

            if ($this->salesModel->additionalInfo) {
                $header = $this->salesModel->additionalInfo;
            } else {
                $header = $this->salesModel->salesNum;
            }
            $printer->clear();
            $printer->write($counterText);
            $printer->write($header);
            $printer->write($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName']);

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->write($package['menuShortName']);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->write($notes);
                    }
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->write($extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->write($notes);
                    }
                }
            }
            $printer->write(date("d/m/Y"));
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }
    
    private function printBixolonSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $stationModel) {
        try {
            $printer = new BixolonStickerPrinter($host, $connectionType, $stationModel);
            if($this->salesModel->tableID === 0) {
                $counterText = "$queueNumber - $counter/$totalItems";
            } else {
                $tableName = $this->salesModel->table->tableName;
                $counterText = "$tableName - $counter/$totalItems";
            }
            
            if ($this->salesModel->additionalInfo) {
                $header = $this->salesModel->additionalInfo;
            } else {
                $header = $this->salesModel->salesNum;
            }
            $printer->clear();
            $printer->write($counterText);
            $printer->write($header);
            $printer->write($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName']);

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->write($package['menuShortName']);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->write($notes);
                    }
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->write($extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->write($notes);
                    }
                }
            }
            $printer->write(date("d/m/Y"));
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }
    
    private function printBrotherSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $stationModel) {
        try {
            $printer = new BrotherStickerPrinter($host, $connectionType, $stationModel);
            if($this->salesModel->tableID === 0) {
                $counterText = "$queueNumber - $counter/$totalItems";
            } else {
                $tableName = $this->salesModel->table->tableName;
                $counterText = "$tableName - $counter/$totalItems";
            }
            
            if ($this->salesModel->additionalInfo) {
                $header = $this->salesModel->additionalInfo;
            } else {
                $header = $this->salesModel->salesNum;
            }
            $printer->clear();
            $printer->write($counterText);
            $printer->write($header);
            $printer->write($detail['customMenuName'] ? $detail['customMenuName'] : $detail['menuShortName']);

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->write($package['menuShortName']);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->write($notes);
                    }
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->write($extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->write($notes);
                    }
                }
            }
            $printer->write(date("d/m/Y"));
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    private function printLableTrialMode($printer, $stationModel) {
        $charLength = $stationModel->characterPerLine;
        $printerType = $stationModel->printerTypeID;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');

        if (isset($trialMode)) {
            if ($trialMode->value1 == 1) {
                if ($printerType == 3 || $printerType == 4) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                }

                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));
                $printer->text(' TRIAL MODE ');
                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));

                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(2);
                }
            };
            
        }

    }

}
