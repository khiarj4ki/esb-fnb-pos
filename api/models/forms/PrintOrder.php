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
use app\models\BranchMenu;
use app\models\BrandSetting;
use app\models\CustomerTransaction;
use app\models\Enums\EnumInterface;
use app\models\Enums\PrinterTypeInterface;
use app\models\KitchenOrder;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\Station;
use app\models\Table;
use app\models\TableSection;
use app\models\TableSectionStation;
use Exception;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $batchID
 * @property int $sourceTableID
 * @property string $sourceSalesNum
 * @property int $queueNum
 * 
 * PRIVATE
 * @property array $settings
 * @property SalesHead $salesModel
 * @property SalesMenu[] $salesMenusModel
 * @property Table $sourceTableModel
 */
class PrintOrder extends Model {
    const SCENARIO_CANCEL_ORDER = 'cancel order';
    const SCENARIO_MOVE_ITEM = 'move item';
    const SCENARIO_MOVE_TABLE = 'move table';
    const SCENARIO_SELF_ORDER = 'self order';
    const SCENARIO_SELF_ORDER_VOID = 'self order void';

    public $tableID;
    public $salesNum;
    public $batchID;
    public $sourceTableID; //used on SCENARIO_MOVE_ITEM
    public $sourceSalesNum; //used on SCENARIO_MOVE_ITEM
    public $settings;
    public $brandSettings;
    public $salesModel;
    public $salesMenusModel;
    public $sourceTableModel;
    public $queueNum;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;
    public $flagFireOrderIDs;
    public $customerInfo;
    public $printResult;
    public $errorStations;
    public $forceSeparateMenuNotes;
    public $testPrint;
    public $stationModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'batchID'], 'required'],
            [['flagFireOrderIDs', 'testPrint'], 'safe'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID', 'batchID', 'queueNum'], 'integer'],
            [['sourceTableID'], 'required', 'on' => self::SCENARIO_MOVE_ITEM],
            [['sourceSalesNum'], 'required', 'on' => self::SCENARIO_MOVE_ITEM, 'when' => function ($model) {
                    return $model->sourceTableID == 0;
                }],
            [['sourceTableID'], 'required', 'on' => self::SCENARIO_MOVE_TABLE],
            [['salesNum', 'sourceSalesNum'], 'string', 'max' => 20],
            [['tableID'], 'validateTable'],
            [['batchID'], 'validateBatch'],
            [['sourceTableID'], 'validateSourceTable']
        ];
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CANCEL_ORDER] = ['tableID', 'salesNum', 'batchID'];
        $scenarios[self::SCENARIO_SELF_ORDER] = ['tableID', 'salesNum', 'batchID'];
        $scenarios[self::SCENARIO_SELF_ORDER_VOID] = ['tableID', 'salesNum', 'batchID'];
        $scenarios[self::SCENARIO_MOVE_ITEM] = ['tableID', 'salesNum', 'batchID', 'sourceTableID', 'sourceSalesNum'];
        $scenarios[self::SCENARIO_MOVE_TABLE] = ['tableID', 'batchID', 'sourceTableID', 'sourceSalesNum'];

        return $scenarios;
    }

    public function validateTable($attribute) {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::find()
            ->where(['branchID' => $branchID])
            ->one();
        $printingSettings = Setting::getPrintingSettings();
        $printingAfterPayment = isset($printingSettings['Print Take Away Order After Payment']) ? $printingSettings['Print Take Away Order After Payment'] : 0;
        $this->forceSeparateMenuNotes = false;
        if ($this->tableID != 0) {
            if ($printingAfterPayment && $branchModel->posModeID == 2) {
                $this->salesModel = SalesHead::findFinished()
                    ->with('table.tableSection')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
            } else {
                if (isset($this->testPrint)) {
                    $outstandingOrderForTestPrint = SalesHead::find()
                        ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                        ->orderBy('salesDate, salesNum');
                
                    $this->salesModel = SalesHead::findOrderDetails($outstandingOrderForTestPrint)
                        ->with('table.tableSection')
                        ->andWhere(['OR',
                            [SalesHead::tableName() . '.salesNum' => $this->salesNum],
                            [SalesMergeTable::tableName() . '.salesNum' => $this->salesNum]
                        ])
                        ->one();
                } else {
                    $this->salesModel = SalesHead::findOutstandingOrder()
                        ->with('table.tableSection')
                        ->andWhere(['OR',
                            [SalesHead::tableName() . '.salesNum' => $this->salesNum],
                            [SalesMergeTable::tableName() . '.salesNum' => $this->salesNum]
                        ])
                        ->one();
                }
            }
        } else {
            if ($printingAfterPayment) {
                $this->salesModel = SalesHead::findFinished()
                    ->with('table.tableSection')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
                $this->forceSeparateMenuNotes = true;
            } else if ($this->scenario === self::SCENARIO_SELF_ORDER || $this->scenario === self::SCENARIO_SELF_ORDER_VOID) {
                $this->salesModel = SalesHead::findFinished()
                    ->with('table.tableSection')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
            } else {
                $this->salesModel = SalesHead::findOutstandingOrder()
                    ->with('table.tableSection')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
            }
        }
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }

        // @Notes: Get queue number
		$this->queueNum = $this->salesModel->queueNum;
    }
    public function validateBatch($attribute) {
        // @Notes: 19 = Print Cancelled, 13 = Preparing
        $statusID = $this->scenario == self::SCENARIO_CANCEL_ORDER ? 19 : 13;
        $batchID = $this->scenario == self::SCENARIO_MOVE_TABLE ? null : $this->batchID;
        if ($batchID == -1) {
            $salesMenu = SalesMenu::findMainMenus($this->salesModel->salesNum,
                    $statusID, $batchID)
                ->andWhere([SalesMenu::tableName(). '.flagPending' => 1])
                ->all();

            $this->salesMenusModel = SalesHead::groupingOrderForBilling($salesMenu, $this->salesModel->flagInclusive, $this->salesModel->branchID, false, false, $this->forceSeparateMenuNotes);
        } else {
            $this->salesMenusModel = SalesMenu::findMainMenus($this->salesModel->salesNum,
                    $statusID, $batchID, null, false, $this->flagFireOrderIDs)
                ->andWhere([SalesMenu::tableName(). '.flagPending' => 1])
                ->all();
        }

        if (!$this->salesMenusModel) {
            $this->addError($attribute, 'Batch ID not found');
        }
    }

    public function validateSourceTable($attribute) {
        if ($this->sourceTableID != 0) {
            $this->sourceTableModel = Table::find()
                ->andWhere(['tableID' => $this->sourceTableID])
                ->one();
            if (!$this->sourceTableModel) {
                $this->addError($attribute, 'Invalid source table ID');
            }
        }
    }

    public function doPrint() {
        if (!$this->validate()) {
            $this->printResult = ['status' => true, 'message' => null];
            return false;
        }

        $this->settings = Setting::getPrintingSettings();
        $this->brandSettings = BrandSetting::getBrandPosSetting();
        $this->customerInfo = CustomerTransaction::findOne(['salesNum' => $this->salesNum]);

        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $allowPrint = true;
        $allowPrintMoveItem = isset($this->settings['Print Move Item']) ? $this->settings['Print Move Item'] : true;
        if (!$allowPrintMoveItem && $this->scenario == self::SCENARIO_MOVE_ITEM) {
            $allowPrint = false;
        }

        $allowPrintMoveTable = isset($this->settings['Print Move Table']) ? $this->settings['Print Move Table'] : true;
        if (!$allowPrintMoveTable && $this->scenario == self::SCENARIO_MOVE_TABLE) {
            $allowPrint = false;
        }

        $printingAfterPayment = isset($this->settings['Print Take Away Order After Payment']) ? $this->settings['Print Take Away Order After Payment'] : 0;
        if ($printingAfterPayment == 1 && $this->scenario == self::SCENARIO_CANCEL_ORDER) {
            $allowPrint = false;
        }

        if ($this->flagFireOrderIDs != null && $this->scenario == self::SCENARIO_CANCEL_ORDER) {
            $allowPrint = false;
        } elseif ($this->scenario == self::SCENARIO_CANCEL_ORDER) {
            $allowPrint = true;
        }
        
        if ($allowPrint) {
            $stationOrderList = $this->groupingOrderForPrinting('stationID');
            $checkerOrderList = $this->groupingOrderForPrinting('checkerStationID');

            if ($this->scenario == self::SCENARIO_SELF_ORDER_VOID) {
                $this->printOrder($stationOrderList, false, false);
                $this->printOrder($checkerOrderList, true, false);
            } else {
                $this->printOrder($stationOrderList, false, null);
                $this->printOrder($checkerOrderList, true, null);
            }
        }

        if ($this->errorStations) {
            $this->printResult = ['status' => false, 'message' => $this->errorStations];
        } else {
            $this->printResult = ['status' => true, 'message' => null];
        }
    }

    private function groupingOrderForPrinting($field) {
        $salesMenusModel = $this->salesMenusModel;
        $orderList = [];
        $menuCategoryIDs = [];
        foreach($salesMenusModel as $salesMenu){
            $checkHoldStatus = ($field === 'checkerStationID' || $field === 'stationID') && $salesMenu->statusID === 46;
            if ($checkHoldStatus === false) {
                $menuCategoryID = $salesMenu->menu->menuCategoryDetailID;
                if(!in_array($menuCategoryID, $menuCategoryIDs)){
                    $menuCategoryIDs[] = $menuCategoryID;
                }
                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $package) {
                        $menuCategoryIDPackage = $package->menu->menuCategoryDetailID;
                        if(!in_array($menuCategoryIDPackage, $menuCategoryIDs)){
                            $menuCategoryIDs[] = $menuCategoryIDPackage;
                        }
                    }
                }
            }
        }

        $tableSectionStationModel = (new Query())
            ->select([
                'tableSectionStation.tableSectionID',
                'tableSectionStation.menuCategoryDetailID',
                'stationIDs' => new Expression("GROUP_CONCAT(DISTINCT tableSectionStation.stationID)")
            ])
            ->from(TableSectionStation::tableName() . ' as tableSectionStation')
            ->innerJoin(TableSection::tableName() . ' as tableSection', 'tableSection.tableSectionID = tableSectionStation.tableSectionID')
            ->innerJoin(Table::tableName(). ' as table', 'table.tableSectionID = tableSection.tableSectionID')
            ->where(['IN', 'tableSectionStation.menuCategoryDetailID', $menuCategoryIDs])
            ->andWhere(['tableSection.flagActive' => 1])
            ->groupBy(['tableSectionStation.tableSectionID', 'tableSectionStation.menuCategoryDetailID'])
        ->all();

        foreach ($salesMenusModel as $salesMenu) {
            $checkHoldStatus = ($field === 'checkerStationID' || $field === 'stationID') && $salesMenu->statusID === 46;
            if ($salesMenu->branchMenu && ($checkHoldStatus === false)) {
                $stationIDs = $salesMenu->branchMenu->$field != 0 && $salesMenu->branchMenu->$field != '' ? $salesMenu->branchMenu->$field : null;
                $tableID = $salesMenu->salesHead->tableID;
                if($field == "stationID" && $tableID != 0 && sizeof($tableSectionStationModel) > 0){
                    foreach($tableSectionStationModel as $tableSection){
                        if($salesMenu->salesHead->table->tableSectionID == $tableSection['tableSectionID']
                        && $tableSection['menuCategoryDetailID'] == $salesMenu->menu->menuCategoryDetailID){
                            $stationIDs = $tableSection['stationIDs'];
                            break;
                        }
                    }
                }

                $checkPckStationArr = [];
                if ($stationIDs && ($checkHoldStatus === false)) {
                    foreach(explode(',', $stationIDs) as $stationID) {
                        $menuName = $salesMenu->statusID === 46 ? $salesMenu->menu->menuName .' (Hold)' : $salesMenu->menu->menuName;
                        $menuShortName = $salesMenu->customMenuName ? $salesMenu->customMenuName : $salesMenu->menu->menuShortName;
                        $menuShortName = $salesMenu->statusID === 46 ? $menuShortName . ' (Hold)' : $menuShortName;

                        $orderList[$stationID][$salesMenu->ID]['ID'] = (float) $salesMenu->ID;
                        $orderList[$stationID][$salesMenu->ID]['qty'] = (float) $salesMenu->qty;
                        $orderList[$stationID][$salesMenu->ID]['menuID'] = $salesMenu->menuID;
                        $orderList[$stationID][$salesMenu->ID]['menuName'] = $menuName;
                        $orderList[$stationID][$salesMenu->ID]['menuShortName'] = $menuShortName;
                        $orderList[$stationID][$salesMenu->ID]['menuCategoryDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategory->menuCategoryDesc;
                        $orderList[$stationID][$salesMenu->ID]['menuCategoryDetailDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategoryDetailDesc;
                        $orderList[$stationID][$salesMenu->ID]['notes'] = $salesMenu->notes;
                        $orderList[$stationID][$salesMenu->ID]['flagSeparatePrintPackage'] = $salesMenu->menu->flagSeparatePrintPackage;
                        
                        if ($salesMenu->salesExtras) {
                            foreach ($salesMenu->salesExtras as $extra) {
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['qty'] = (float) $extra->qty;
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['menuExtraName'] = $extra->menuExtra->menuExtraName;
                                $orderList[$stationID][$salesMenu->ID]['extras'][$extra->ID]['menuExtraShortName'] = $extra->menuExtra->menuExtraShortName;
                            }
                        }

                        if (count($orderList[$stationID]) === 0) {
                            unset($orderList[$stationID]);
                        }
                    }

                }
                
                if ($salesMenu->childSalesMenus && ($checkHoldStatus === false)) {
                    foreach ($salesMenu->childSalesMenus as $package) {
                        if ($package->branchMenu) {
                            $packageStationIDs = $package->branchMenu->$field != 0 && $package->branchMenu->$field != '' ? $package->branchMenu->$field : null;
                            if($field == "stationID" &&  $tableID != 0 && sizeof($tableSectionStationModel) > 0){
                                foreach($tableSectionStationModel as $tableSection){
                                    if($salesMenu->salesHead->table->tableSectionID == $tableSection['tableSectionID']
                                    && $tableSection['menuCategoryDetailID'] == $package->menu->menuCategoryDetailID){
                                        $packageStationIDs = $tableSection['stationIDs'];
                                        break;
                                    }
                                }
                            }

                            if (!$packageStationIDs) {
                                $checkPckStationArr[] = $package->ID;
                            }
                            $unsetPackageStationIDs = null;
                            if (!$packageStationIDs && $stationIDs) {
                                $stationIdArray = explode(',', $stationIDs);
                                $stationIdModels = Station::find()
                                    ->select('stationID')
                                    ->where(['IN', 'stationID', $stationIdArray])
                                    ->andWhere(['printerTypeID' => 1])
                                    ->andWhere(['flagActive' => 1])
                                    ->column();
                                if ($stationIdModels) {
                                    if ($salesMenu->menu->flagSeparatePrintPackage == false) {
                                        $packageStationIDs = implode(',', $stationIdModels);
                                    } else {
                                        $unsetPackageStationIDs = implode(',', $stationIdModels);
                                    }
                                }
                            }
                            if ($packageStationIDs) {
                                foreach(explode(',', $packageStationIDs) as $packageStationID) {
                                    $menuName = $salesMenu->statusID === 46 ? $salesMenu->menu->menuName .' (Hold)' : $salesMenu->menu->menuName;
                                    $menuShortName = $salesMenu->customMenuName ? $salesMenu->customMenuName : $salesMenu->menu->menuShortName;
                                    $menuShortName = $salesMenu->statusID === 46 ? $menuShortName . ' (Hold)' : $menuShortName;

                                    $orderList[$packageStationID][$salesMenu->ID]['ID'] = (float) $salesMenu->ID;
                                    $orderList[$packageStationID][$salesMenu->ID]['qty'] = (float) $salesMenu->qty;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuID'] = $salesMenu->menuID;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuName'] = $menuName;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuShortName'] = $menuShortName;
                                    $orderList[$packageStationID][$salesMenu->ID]['notes'] = $salesMenu->notes;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuCategoryDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategory->menuCategoryDesc;
                                    $orderList[$packageStationID][$salesMenu->ID]['menuCategoryDetailDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategoryDetailDesc;
                                    $orderList[$packageStationID][$salesMenu->ID]['flagSeparatePrintPackage'] = $salesMenu->menu->flagSeparatePrintPackage;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['ID'] = (float) $package->ID;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['qty'] = (float) $package->qty;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuID'] = $package->menu->menuID;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuName'] = $package->menu->menuName;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['menuShortName'] = $package->menu->menuShortName;
                                    $orderList[$packageStationID][$salesMenu->ID]['packages'][$package->ID]['notes'] = $package->notes;

                                    if (count($orderList[$packageStationID]) === 0) {
                                        unset($orderList[$packageStationID]);
                                    }
                                }
                            }

                            if ($unsetPackageStationIDs) {
                                foreach (explode(',', $unsetPackageStationIDs) as $unsetPackageStationID) {
                                    $menuName = $salesMenu->statusID === 46 ? $salesMenu->menu->menuName .' (Hold)' : $salesMenu->menu->menuName;
                                    $menuShortName = $salesMenu->customMenuName ? $salesMenu->customMenuName : $salesMenu->menu->menuShortName;
                                    $menuShortName = $salesMenu->statusID === 46 ? $menuShortName . ' (Hold)' : $menuShortName;

                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['ID'] = (float) $salesMenu->ID;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['qty'] = (float) $salesMenu->qty;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['menuID'] = $salesMenu->menuID;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['menuName'] = $menuName;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['menuShortName'] = $menuShortName;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['menuCategoryDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategory->menuCategoryDesc;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['menuCategoryDetailDesc'] = $salesMenu->menu->menuCategoryDetail->menuCategoryDetailDesc;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['notes'] = $salesMenu->notes;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['flagSeparatePrintPackage'] = $salesMenu->menu->flagSeparatePrintPackage;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['unsetPackages'][$package->ID]['qty'] = (float) $package->qty;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['unsetPackages'][$package->ID]['menuName'] = $package->menu->menuName;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['unsetPackages'][$package->ID]['menuShortName'] = $package->menu->menuShortName;
                                    $orderList[$unsetPackageStationID][$salesMenu->ID]['unsetPackages'][$package->ID]['notes'] = $package->notes;

                                    if (count($orderList[$unsetPackageStationID]) === 0) {
                                        unset($orderList[$unsetPackageStationID]);
                                    }
                                }
                            }
                        }
                    }
                }

                if ($stationIDs && (count($checkPckStationArr) === count($salesMenu->childSalesMenus)) && ($salesMenu->menu->flagSeparatePrintPackage == true)) {
                    foreach(explode(',', $stationIDs) as $stationID) {
                        unset($orderList[$stationID]);
                    }
                }
            }
        }
        return $orderList;
    }

    private function getTextKitchenOrderSummaryByMenuCategoryCode() {
        $groupedData = [];
        foreach ($this->salesMenusModel as $sm) {
            $menuCategoryCode = $sm->menu->menuCategoryDetail->menuCategory->menuCategoryCode;
            if (!empty($menuCategoryCode)) {
                if (!isset($groupedData[$menuCategoryCode])) {
                    $groupedData[$menuCategoryCode] = [
                        "menuCategoryCode" => $menuCategoryCode,
                        "qty" => (float)$sm->qty
                    ];
                } else {
                    $groupedData[$menuCategoryCode]['qty'] += (float)$sm->qty;
                }
            }

            if ($sm->childSalesMenus) {
                foreach ($sm->childSalesMenus as $package) {
                    $menuCategoryCode = $package->menu->menuCategoryDetail->menuCategory->menuCategoryCode;
                    if (!empty($menuCategoryCode)) {
                        $totalQty = (float)$sm->qty * (float)$package->qty;
                        if (!isset($groupedData[$menuCategoryCode])) {
                            $groupedData[$menuCategoryCode] = [
                                "menuCategoryCode" => $menuCategoryCode,
                                "qty" => $totalQty
                            ];
                        } else {
                            $groupedData[$menuCategoryCode]['qty'] += $totalQty;
                        }
                    }
                }
            }
        }

        // Sort desc menuCategoryCode
        usort($groupedData, function($a, $b) {
            return strcmp($b['menuCategoryCode'], $a['menuCategoryCode']);
        });

        $text = "";
        foreach ($groupedData as $item) {
            $text .= $item['qty'] . $item['menuCategoryCode'];
        }
        return $text;
    }

    // @Notes: overrideSingleMenuPrint to override singgle menu print on printer setting
    private function printOrder($orderList, $checkerStation, $overrideSingleMenuPrint = null) {
        $tempStation = [];
        $odsBarcodeSetting = isset($this->settings['ODS Barcode']) ? $this->settings['ODS Barcode'] : 0;
        $qtyMenuPrinting = false;
        $singleMenuPrinting = false;
        $stickerMenuPrinting = false;
        
        foreach ($orderList as $stationID => $printOrders) {
            $totalStickerItems = 0;
            $stickerCounter = 1;
            $printData = [];
        
            $stationModel = Station::findActive()
                ->andWhere(['stationID' => $stationID])
                ->one();

            $printingModeID = isset($overrideSingleMenuPrint) ? $overrideSingleMenuPrint : $stationModel->printingModeID;

            $charLength = $stationModel->characterPerLine;
            
            if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                ($checkerStation == false) && ($stationModel->printerTypeID == 2 ||
                $stationModel->printerTypeID == 6 || $stationModel->printerTypeID == 7 ||
                $stationModel->printerTypeID == 8 || $stationModel->printerTypeID == 9 || 
                $stationModel->printerTypeID == 10 || $stationModel->printerTypeID == 11 || 
                $stationModel->printerTypeID == 12 || $stationModel->printerTypeID == 13)) {
                foreach ($printOrders as $printOrder) {
                    $totalStickerItems += $printOrder['qty'];
                    if(isset($printOrder['packages'])){
                        if(isset($printOrder['flagSeparatePrintPackage']) && $printOrder['flagSeparatePrintPackage'] == true){
                            $totalStickerItems = 0;
                            foreach($printOrder['packages'] as $key => $package){
                                if ($printingModeID == 3) {
                                    $totalStickerItems += $printOrder['qty'] * $package['qty'];
                                    $qtyPackage = intval($package['qty']);
                                    for ($x = 1; $x <= $qtyPackage; $x++) {
                                        $group = [
                                            'ID' => $printOrder['ID'],
                                            'qty' => $printOrder['qty'],
                                            'menuID' => $printOrder['menuID'],
                                            'menuName' => $printOrder['menuName'],
                                            'menuShortName' => $printOrder['menuShortName'],
                                            'menuCategoryDesc' => isset($printOrder['menuCategoryDesc']) ? $printOrder['menuCategoryDesc'] : '',
                                            'menuCategoryDetailDesc' => isset($printOrder['menuCategoryDetailDesc']) ? $printOrder['menuCategoryDetailDesc'] : '',
                                            'notes' => $printOrder['notes'],
                                            'flagSeparatePrintPackage' => 1
                                        ];
                                        $package['qty'] = 1;
                                        $group['packages'][] = $package;
                                        $printData[] = $group;
                                    }
                                } else {
                                    $totalStickerItems += $printOrder['qty'];
                                    $group = [
                                        'ID' => $printOrder['ID'],
                                        'qty' => $printOrder['qty'],
                                        'menuID' => $printOrder['menuID'],
                                        'menuName' => $printOrder['menuName'],
                                        'menuShortName' => $printOrder['menuShortName'],
                                        'menuCategoryDesc' => isset($printOrder['menuCategoryDesc']) ? $printOrder['menuCategoryDesc'] : '',
                                        'menuCategoryDetailDesc' => isset($printOrder['menuCategoryDetailDesc']) ? $printOrder['menuCategoryDetailDesc'] : '',
                                        'notes' => $printOrder['notes'],
                                        'flagSeparatePrintPackage' => 1
                                    ];
                                    $group['packages'][] = $package;
                                    $printData[$key] = $group;
                                }
                            }
                        } else {
                            $printData[] =  $printOrder;
                        }
                    } else {
                        $printData[] =  $printOrder;
                    }
                }
            }
            
            try {
				$flagStickerPrinter = in_array($stationModel->printerTypeID, [2, 7, 8, 9, 10, 11]) ? true : false;
                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == '2')) {
                    $host = $stationModel->printerName;
                    $port = $stationModel->printerPort;
                    $stationName = $stationModel->stationName;
                    foreach ($printData as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printSticker($stationName, $this->salesNum,
                                $printOrder, $stickerCounter,
                                $totalStickerItems, $host, $port, $charLength,
                                $this->queueNum);
                           $stickerCounter++;
                        }
                    }
                }
                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == 7)) {
                    $gStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    $printer = new GStickerPrinter($host, $connectionType, $stationModel);
                    foreach ($printData as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printGSticker($printer, $this->queueNum,
                                $printOrder, $gStickerCounter,
                                $totalStickerItems, $charLength);
                                $gStickerCounter++;
                        }
                    }
                    $printer->close();
                }

                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == 8)) {
                    $satoStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printData as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printSatoSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $satoStickerCounter, 
                                $totalStickerItems, 
                                $charLength,
                                $stationModel);
                            
                            $satoStickerCounter++;
                        }
                    }
                }

                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == 9)) {
                    $zebraStickerCounter = 1;
                    $host = $stationModel->printerName;
                    $connectionType = $stationModel->printerConnectionID;
                    foreach ($printData as $printOrder) {
                        for ($i = 1; $i <= $printOrder['qty']; $i++) {
                            $this->printZebraSticker(
                                $host, 
                                $connectionType,
                                $this->queueNum, 
                                $printOrder,
                                $zebraStickerCounter, 
                                $totalStickerItems, 
                                $charLength,
                                $stationModel);
                                
                                $zebraStickerCounter++;
                        }
                    }
                }
                
                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == 10)) {
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
                                $charLength,
                                $stationModel);
                            $bixolonStickerCounter += 1;
                        }
                    }
                }
                
                if ($this->scenario != self::SCENARIO_MOVE_TABLE && $this->scenario != self::SCENARIO_MOVE_ITEM && $this->scenario != self::SCENARIO_CANCEL_ORDER &&
                    ($checkerStation == false) && ($stationModel->printerTypeID == 11)) {
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
                                $charLength,
                                $stationModel);
                            $brotherStickerCounter += 1;
                        }
                    }
                }

                if ($stationModel->printerTypeID != 2 && !$flagStickerPrinter) {
                    $connector = Station::getConnectorByModel(
                        $stationModel,
                        $this->salesNum,
                        true,
                        null,
                        isset($this->testPrint) && !!$this->testPrint
                    );
                    
                    if ($connector !== null) {
                        $printer = new Printer($connector);
    
                        if (($checkerStation == false) && ($stationModel->printerTypeID == 6 || $stationModel->printerTypeID == 13)) {
                            if ($printingModeID != 3) {
                                $printingModeID = 3;
                            }
                            $epsonStickerCounter = 1;
                            $stationName = $stationModel->stationName;
                            if($printData){
                                foreach ($printData as $printOrder) {  
                                    for ($i = 1; $i <= $printOrder['qty']; $i++) {
                                        if ($odsBarcodeSetting == 1) {
                                            usleep(300*1000);
                                        }
                                        $this->epsonSticker($printer, $this->queueNum,
                                           $printOrder, $epsonStickerCounter,
                                           $totalStickerItems, $charLength,$stationModel,$printingModeID,$odsBarcodeSetting);
                                        $epsonStickerCounter++;
                                    }
                               }   
                            }
                        }
                        
                        if (($checkerStation == false) && ($stationModel->printerTypeID == 12)) {
                            $epsonStickerCounter = 1;
                            $stationName = $stationModel->stationName;
                            if($printData){
                                foreach ($printData as $printOrder) {  
                                    if ($odsBarcodeSetting == 1) {
                                        usleep(300*1000);
                                    }
                                    for ($i = 1; $i <= $printOrder['qty']; $i++) {
                             
                                        $this->epsonStickerTML90($printer, $this->queueNum,
                                           $printOrder, $epsonStickerCounter,
                                           $totalStickerItems, $charLength,$stationModel,$printingModeID,$odsBarcodeSetting);
                                       
                                        $epsonStickerCounter++;
                                    }
                               }   
                            }
                        }
    
                        if ($stationModel->printerTypeID != 6 && $stationModel->printerTypeID != 12 && $stationModel->printerTypeID != 13) {
                            // printingModeID greater than 1 = only single menu (posModeID=2) & qty menu printing (posModeID=3) use this condition 
                            if ($printingModeID > 1 && $this->scenario != self::SCENARIO_MOVE_TABLE) {
                                foreach ($printOrders as $printOrder) {
                                    // Qty Menu Printing
                                    if ($printingModeID == 3) {
                                        if (isset($printOrder['packages']) && $printOrder['flagSeparatePrintPackage'] == true) {
                                            foreach ($printOrder['packages'] as $package) {
                                                $newPrintOrder = [];
                                                for ($i=0; $i < $printOrder['qty']; $i++) { 
                                                    $newPrintOrder = $printOrder;
                                                    $newPrintOrder['qty'] = 1;
                                                    $newPrintOrder['packages'] = $package;
                                                    for ($ipck=0; $ipck < $newPrintOrder['packages']['qty']; $ipck++) {
                                                         $newPrintOrderPackage = $newPrintOrder;
                                                         $newPrintOrderPackage['packages']['qty'] = 1;
                                                        $this->printOrderInfo($printer,
                                                            $stationModel, $checkerStation);
                                                        $this->printOrderDetail($printer,
                                                            $newPrintOrderPackage, $stationModel, 3);
                                                        if($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                                                            $this->printFooterCancelOrder($printer, $stationModel);
                                                        }
                                                        if ($this->scenario != self::SCENARIO_CANCEL_ORDER && $checkerStation == false && $odsBarcodeSetting == 1) {
                                                            $qtyMenuPrinting = true;
                                                            $kitchenOrderQtyModel = new KitchenOrder();
                                                            $kitchenOrderQtyModel->getCode();
                                                            $kitchenOrderQtyModel->setData($printingModeID, $this->salesNum, $newPrintOrderPackage['packages']);
                                                            $kitchenOrderQtyModel->generateQR($printer, $stationModel);
                                                            $kitchenOrderQtyModel->saveModel();
                                                        }
                                                        $printer->initialize();
                                                        $printer->text(str_pad('', $charLength,
                                                                '-'));
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
                                                        $this->printLableTrialMode($printer, $stationModel);
                                                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                            $printer->getPrintConnector()->write("\x0A");
                                                        } else {
                                                            $printer->feed(1);
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
                                        } else{
                                            $newPrintOrder = [];
                                            $newPrintOrder = $printOrder;
                                            $newPrintOrder['qty'] = 1;
                                            for ($i = 1; $i <= $printOrder['qty']; $i++) {
                                                if (isset($printOrder['unsetPackages'])) {
                                                    foreach ($printOrder['unsetPackages'] as $unsetPackages) {
                                                        $this->printOrderInfo($printer,
                                                            $stationModel, $checkerStation);
                                                        $this->printOrderDetail($printer,
                                                            $newPrintOrder, $stationModel, 3);
            
                                                        $printer->initialize();
                                                        $printer->text(str_pad('', $charLength,
                                                                '-'));
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
                                                        $this->printLableTrialMode($printer, $stationModel);
                                                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                            $printer->getPrintConnector()->write("\x0A");
                                                        } else {
                                                            $printer->feed(1);
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
                                                } else {
                                                    $this->printOrderInfo($printer,
                                                        $stationModel, $checkerStation);
                                                    $this->printOrderDetail($printer,
                                                        $newPrintOrder, $stationModel, 3);
                                                    if($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                                                        $this->printFooterCancelOrder($printer, $stationModel);
                                                    }

                                                    if ($this->scenario != self::SCENARIO_CANCEL_ORDER && $checkerStation == false && $odsBarcodeSetting == 1) {
                                                        $qtyMenuPrinting = true;
                                                        $kitchenOrderQtyModel = new KitchenOrder();
                                                        $kitchenOrderQtyModel->getCode();
                                                        $branchMenu = BranchMenu::find()->where(['menuID' => $printOrder['menuID']])->one();
                                                        if ($branchMenu) {
                                                            $explodedMenu = explode(',',$branchMenu->stationID);
                                                            if (is_array($explodedMenu) && in_array($stationModel->stationID, $explodedMenu)) {
                                                                $kitchenOrderQtyModel->setData($printingModeID, $this->salesNum, $printOrder);
                                                            }
                                                        }

                                                        if (isset($printOrder['packages']) && $printOrder['flagSeparatePrintPackage'] == false) {
                                                            foreach ($printOrder['packages'] as $package) {
                                                                $kitchenOrderQtyModel->setData($printingModeID, $this->salesNum, $package);
                                                            }
                                                        }

                                                        $kitchenOrderQtyModel->generateQR($printer, $stationModel);
                                                        $kitchenOrderQtyModel->saveModel();
                                                    }

                                                    $printer->initialize();
                                                    $printer->text(str_pad('', $charLength,
                                                            '-'));
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
                                                    $this->printLableTrialMode($printer, $stationModel);
                                                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                        $printer->getPrintConnector()->write("\x0A");
                                                    } else {
                                                        $printer->feed(1);
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
                                        // Single menu printing
                                        if(isset($printOrder['packages']) && (isset($printOrder['flagSeparatePrintPackage']) && $printOrder['flagSeparatePrintPackage'] == true)){
                                            foreach ($printOrder['packages'] as $package) {
                                                $newPrintOrder = [];
                                                $newPrintOrder = $printOrder;
                                                $newPrintOrder['packages'] = $package;
                                                $barcodePrintOrder = $printOrder;
                                                $barcodePrintOrder['packages'] = $package;
                                                $barcodePrintOrder['packages']['qty'] = $barcodePrintOrder['packages']['qty'] * $printOrder['qty'];
                                                $this->printOrderInfo($printer,
                                                    $stationModel, $checkerStation);
                                                $this->printOrderDetail($printer,
                                                    $newPrintOrder, $stationModel, $printingModeID);
                                                if($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                                                    $this->printFooterCancelOrder($printer, $stationModel);
                                                }
                                                if ($this->scenario != self::SCENARIO_CANCEL_ORDER && $checkerStation == false && $odsBarcodeSetting == 1) {
                                                    $singleMenuPrinting = true;
                                                    $kitchenOrderSingleModel = new KitchenOrder();
                                                    $kitchenOrderSingleModel->getCode();
                                                    $kitchenOrderSingleModel->setData($printingModeID, $this->salesNum, $barcodePrintOrder['packages']);
                                                    $kitchenOrderSingleModel->generateQR($printer, $stationModel);
                                                    $kitchenOrderSingleModel->saveModel();
                                                }
                                                $printer->initialize();
                                                $printer->text(str_pad('', $charLength, '-'));
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
                                                $this->printLableTrialMode($printer, $stationModel);
                                                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                    $printer->getPrintConnector()->write("\x0A");
                                                } else {
                                                    $printer->feed(1);
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
                                            if (isset($printOrder['unsetPackages'])) {
                                                for ($i=0; $i < count($printOrder['unsetPackages']); $i++) { 
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
                                                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                                                    } else {
                                                        $printer->setJustification(Printer::JUSTIFY_CENTER);
                                                    }
                                                    $this->printLableTrialMode($printer, $stationModel);
                                                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                        $printer->getPrintConnector()->write("\x0A");
                                                    } else {
                                                        $printer->feed(1);
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
                                            } else {
                                                $this->printOrderInfo($printer,
                                                    $stationModel, $checkerStation);
                                                $this->printOrderDetail($printer,
                                                    $printOrder, $stationModel, $printingModeID);
                                                if($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                                                    $this->printFooterCancelOrder($printer, $stationModel);
                                                }

                                                if ($this->scenario != self::SCENARIO_CANCEL_ORDER && $checkerStation == false && $odsBarcodeSetting == 1) {
                                                    $singleMenuPrinting = true;
                                                    $kitchenOrderSingleModel = new KitchenOrder();
                                                    $kitchenOrderSingleModel->getCode();

                                                    $branchMenu = BranchMenu::find()->where(['menuID' => $printOrder['menuID']])->one();
                                                    if ($branchMenu) {
                                                        $explodedMenu = explode(',',$branchMenu->stationID);
                                                        if (is_array($explodedMenu) && in_array($stationModel->stationID, $explodedMenu)) {
                                                            $kitchenOrderSingleModel->setData($printingModeID, $this->salesNum, $printOrder);
                                                        }
                                                    }

                                                    if (isset($printOrder['packages']) && $printOrder['flagSeparatePrintPackage'] == false) {
                                                        foreach ($printOrder['packages'] as $package) {
                                                            $kitchenOrderSingleModel->setData($printingModeID, $this->salesNum, $package);
                                                        }
                                                    }

                                                    $kitchenOrderSingleModel->generateQR($printer, $stationModel);
                                                    $kitchenOrderSingleModel->saveModel();
                                                }

                                                $printer->initialize();
                                                $printer->text(str_pad('', $charLength, '-'));
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
                                                $this->printLableTrialMode($printer, $stationModel);
                                                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                                    $printer->getPrintConnector()->write("\x0A");
                                                } else {
                                                    $printer->feed(1);
                                                }
    
                                                if ($stationModel->printerTypeID == '4') {
                                                    $printer->feed(2);
                                                } else if ($stationModel->printerTypeID == 15) {
                                                    if ($stationModel->flagAutocut == '1') {
                                                        $printer->cut(Printer::CUT_PARTIAL);
                                                    }
                                                } else {
                                                    if ($stationModel->flagAutocut == '1') {
                                                        $printer->cut(Printer::CUT_PARTIAL);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $this->printOrderInfo($printer, $stationModel,
                                $checkerStation);
                                // Standard printing (posModeID 1)
                                
                                if ($this->scenario != self::SCENARIO_MOVE_TABLE) {
                                    $kitchenOrderModel = new KitchenOrder();
                                    $kitchenOrderModel->getCode();
                                    foreach ($printOrders as $printOrder) {
                                        $branchMenu = BranchMenu::find()->where(['menuID' => $printOrder['menuID']])->one();
                                        if ($branchMenu) {
                                            $explodedMenu = explode(',',$branchMenu->stationID);
                                            if (is_array($explodedMenu) && in_array($stationModel->stationID, $explodedMenu)) {
                                                $kitchenOrderModel->setData($printingModeID, $this->salesNum, $printOrder);
                                            }
                                        }
                                        
                                        if(isset($printOrder['packages']) && (isset($printOrder['flagSeparatePrintPackage']) && $printOrder['flagSeparatePrintPackage'] == true)){
                                            foreach ($printOrder['packages'] as $package) {
                                                $newPrintOrder = $printOrder;
                                                $newPrintOrder['packages'] = $package;
                                                $this->printOrderDetail($printer,
                                                    $newPrintOrder, $stationModel,
                                                    $printingModeID);

                                                $kitchenOrderModel->setData($printingModeID, $this->salesNum, $newPrintOrder['packages']);
                                            }
                                        } else {
                                            $this->printOrderDetail($printer,
                                                $printOrder, $stationModel,
                                                $printingModeID);

                                            if ($this->scenario != self::SCENARIO_SELF_ORDER_VOID && $this->scenario != self::SCENARIO_CANCEL_ORDER && 
                                                $checkerStation == false && $odsBarcodeSetting == 1) {
                                                $kitchenOrderModel->setData($printingModeID, $this->salesNum, $printOrder);
                                            }
                                        }
                                    }

                                    if ($checkerStation == false && !$stickerMenuPrinting && 
                                        !$qtyMenuPrinting && !$singleMenuPrinting && $odsBarcodeSetting == 1)
                                    {
                                        $kitchenOrderModel->generateQR($printer, $stationModel);
                                        $kitchenOrderModel->saveModel();
                                    }

                                    if ($this->scenario == self::SCENARIO_SELF_ORDER_VOID) {
                                        $this->printFooterVoidOrder($printer, $stationModel);
                                    } else if ($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                                        $this->printFooterCancelOrder($printer, $stationModel);
                                    }                                    
                                }
    
                                $printer->initialize();
                                $printer->text(str_pad('', $charLength, '-'));
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
                                $this->printLableTrialMode($printer, $stationModel);
                                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                    $printer->getPrintConnector()->write("\x0A");
                                } else {
                                    $printer->feed(1);
                                }
    
                                if (in_array($stationModel->printerTypeID, [1, 2, 3]) && $stationModel->flagCashDrawer === 1) {
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
                    
                    } else {
                        array_push($tempStation, $stationModel->stationName);
                    }
                }
                
                $this->stationModel = $stationModel;
                Logging::save($this->salesNum, Logging::PRINT_KITCHEN, $this->getAttributes());
            } catch (Exception $ex) {
                Yii::warning('Printing to ' . $stationModel->stationName);
                Yii::warning($ex);
            }
        }
        if(isset($tempStation)) {
            $this->errorStations = implode(", ", $tempStation);
        }
    }

    private function printOrderInfo(&$printer, $stationModel, $checkerStation = false) {
        $charLength = $stationModel->characterPerLine;
        $printerType = $stationModel->printerTypeID;
        $printTakeAwaySettings = array_key_exists('Print Quick Service Table Text',
            $this->settings) ? $this->settings['Print Quick Service Table Text'] : true;
        if ($this->scenario == self::SCENARIO_DEFAULT || $this->scenario == self::SCENARIO_CANCEL_ORDER) {
            if ($this->settings['Kitchen Checker Top Margin'] != 0) {
                $printer->feed(intval($this->settings['Kitchen Checker Top Margin']));
            }
        }
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
        }

        $this->printLableTrialMode($printer, $stationModel);

        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } else if ($printerType != 15 && $printerType != PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        } else if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        if ($this->scenario == self::SCENARIO_MOVE_ITEM) {
            $printer->text('*** MOVE ITEMS ***');
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
        if ($this->scenario == self::SCENARIO_MOVE_TABLE) {
            $printer->text('*** MOVE TABLE ***');
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
        if ($this->scenario == self::SCENARIO_CANCEL_ORDER) {
            $printer->text('XXX CANCEL ORDER XXX');
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

        if ($this->scenario == self::SCENARIO_SELF_ORDER_VOID) {
            $printer->text('XX VOID ORDER XX');
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

        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } elseif ($printerType != 15) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        } elseif ($printerType == 15) {
            if ($charLength > 32) {
                $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                $printer->getPrintConnector()->write("\x1B" . "\x57" . "1");
            }
            $printer->getPrintConnector()->write("\x1B" . "\x45");
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        if ($this->scenario == self::SCENARIO_DEFAULT ||
            $this->scenario == self::SCENARIO_CANCEL_ORDER ||
            $this->scenario == self::SCENARIO_SELF_ORDER ||
            $this->scenario == self::SCENARIO_SELF_ORDER_VOID) {

            if ($this->settings['Show Kitchen Sales Number']) {
                if ($stationModel->printerTypeID != 15) {
                    $printer->text('#' . $this->salesModel->salesNum);
                } else {
                    $printer->text($this->salesModel->salesNum);
                }
                
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        } else {
            $printer->text($this->sourceTableModel ? $this->sourceTableModel->tableName : $this->sourceSalesNum);
            $printer->text(' MOVE TO ');
            $printer->text($this->salesModel->table ? $this->salesModel->table->tableName : $this->salesModel->salesNum);
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
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
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();
        if(($branchModel->posModeID != 1 || ($printTakeAwaySettings && $this->salesModel->tableID == 0))
            && ($this->salesModel->transactionModeID !== 3 || $this->salesModel->transactionModeID !== 4)){
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
        }
        if ($this->scenario == self::SCENARIO_MOVE_ITEM) {
            $showPrintingSenderOrder = isset($this->settings['Show Printing Sender Order']) ? $this->settings['Show Printing Sender Order'] : 1;
            if ($showPrintingSenderOrder) {
                $printer->text(str_pad(Yii::t('app', 'Sender'), 6, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($this->salesMenusModel[0]->creator ? $this->salesMenusModel[0]->creator->fullName : "SELF ORDER",
                        ($charLength - 18) / 2, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        } else {
            $showPrintingTableOrder = isset($this->settings['Show Printing Table Order']) ? $this->settings['Show Printing Table Order'] : 1;
            if ($showPrintingTableOrder) {
                if ($this->salesModel->tableID != 0 || $this->salesModel->tableQuickService) {
                    $printer->text(str_pad(Yii::t('app', 'Table'), 6, ' '));
                    $printer->text(' : ');
                }

                $printTakeAwaySettings = array_key_exists('Print Quick Service Table Text',
                        $this->settings) ? $this->settings['Print Quick Service Table Text'] : true;
                if (($printTakeAwaySettings && $this->salesModel->tableID == 0) || $this->salesModel->tableID != 0) {
                    $tableNameText = 'Quick Service';
                    if ($this->salesModel->table) {
                        $tableNameText = $this->salesModel->table->tableName;
                    } else {
                        if ($this->salesModel->tableQuickService) {
                            $tableNameText = $this->salesModel->tableQuickService->value;
                        }
                    }
                    $printer->text(str_pad($tableNameText,
                            ($charLength - 18) / 2, ' '));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
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
                    $printer->feed(1);
                }
            }
            
            $printKitchenOrderSummaryByMenuCategoryCode = isset($this->brandSettings['Print Kitchen Order Summary by Menu Category Code'])
                ? $this->brandSettings['Print Kitchen Order Summary by Menu Category Code'] : false;
            if ($printKitchenOrderSummaryByMenuCategoryCode) {
                $textKitchenOrderSummaryByMenuCategoryCode = $this->getTextKitchenOrderSummaryByMenuCategoryCode();
                if ($textKitchenOrderSummaryByMenuCategoryCode != '') {
                    $printer->text(str_pad(
                        $this->getTextKitchenOrderSummaryByMenuCategoryCode(), ($charLength - 18) / 2, ' '
                    ));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                        $printer->feed(1);
                    }
                } else {
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
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
            if ($this->scenario == self::SCENARIO_DEFAULT || $this->scenario == self::SCENARIO_SELF_ORDER) {
                if ($this->salesModel->additionalInfo != '') {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
                    $showPrintingInfoOrder = isset($this->settings['Show Printing Info Order']) ? $this->settings['Show Printing Info Order'] : 1;
                    if ($showPrintingInfoOrder) {
                        $printer->text(str_pad(Yii::t('app', 'Info'), 11, ' ', STR_PAD_RIGHT));
                        $printer->text(' : ');
                        $additionalInfo = str_split(preg_replace("/\r|\n/","",$this->salesModel->additionalInfo), $charLength - 14);
                        $i = 0;
                        foreach ($additionalInfo as $value)  {
                            if ($i == 0) {
                                $printer->text($value);
                            } else {
                                $printer->text(str_pad("" , 14, ' '));
                                $printer->text(str_pad(ltrim($value), $charLength - 14, ' '));
                            }
                            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                            $i++;
                        };
                    }
                }
            }
            if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            $printer->initialize();
            $showPrintingWaiterOrder = isset($this->settings['Show Printing Waiter Order']) ? $this->settings['Show Printing Waiter Order'] : 1;
            if ($showPrintingWaiterOrder) {
                $printer->text(str_pad($this->salesMenusModel[0]->salesType == 'EZO FS' ? Yii::t('app', 'Customer') : Yii::t('app', 'Waiter'), 11, ' '));
                $printer->text(' : ');
                if ($this->salesMenusModel[0]->salesType == 'EZO FS') {
                    $printer->text(str_pad($this->salesMenusModel[0]->createdBy ? $this->salesMenusModel[0]->createdBy : "SELF ORDER",
                            $charLength - 14, ' '));
                } else {
                    $printer->text(str_pad($this->salesModel->editor ? $this->salesModel->editor->fullName : "SELF ORDER",
                            $charLength - 14, ' '));
                }
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            
            $showPrintingSenderOrder = isset($this->settings['Show Printing Sender Order']) ? $this->settings['Show Printing Sender Order'] : 1;
            if ($showPrintingSenderOrder) {
                $printer->text(str_pad(Yii::t('app', 'Sender'), 11, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($this->salesMenusModel[0]->creator ? $this->salesMenusModel[0]->creator->fullName : "SELF ORDER",
                        $charLength - 14, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }

            if ($this->scenario == self::SCENARIO_DEFAULT || $this->scenario == self::SCENARIO_SELF_ORDER) {
                $showPrintingBatchOrder = isset($this->settings['Show Printing Batch Order']) ? $this->settings['Show Printing Batch Order'] : 1;
                if ($showPrintingBatchOrder) {
                    $printer->text(str_pad(Yii::t('app', 'Batch'), 11, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad($this->batchID < 0 ? 'All' : $this->batchID,
                            $charLength - 14, ' '));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }

                $showPrintingPaxOrder = isset($this->settings['Show Kitchen Pax']) ? $this->settings['Show Kitchen Pax'] : 1;
                if ($showPrintingPaxOrder) {
                    $paxOrderQuery = SalesHead::find()->where(['salesNum' => $this->salesNum])->one();
                    $paxTotal = $paxOrderQuery ? $paxOrderQuery->paxTotal : 0;
                    $printer->text(str_pad(Yii::t('app', 'Pax'), 11, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad($paxTotal,
                            $charLength - 14, ' '));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }

            $customerName = SalesInfo::findBySalesNumKey($this->salesNum, 'Full Name');
            if ($customerName && $customerName != '') {
                $printer->text(str_pad(Yii::t('app', 'Customer'), 11, ' '));
                $printer->text(' : ');
                $printer->text(str_pad($customerName, $charLength - 14, ' '));
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
            //@enhance show checker sales info
            $settingEZO = setting::getEZOSetting();
            $showCheckerSalesInfo = isset($settingEZO['Show Checker Sales Info']) ? $settingEZO['Show Checker Sales Info'] : false;
            if ($showCheckerSalesInfo) {
                $customTemplate = SalesInfo::findBySalesNumPrinting($this->salesNum);
                if ($customTemplate) {
                    foreach ($customTemplate as $key => $data) {
                        $printer->text(str_pad(Yii::t('app', $data['key']), 11, ' '));
                        $printer->text(' : ');
                        $printer->text(str_pad($data['value'], $charLength - 14, ' '));
                        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                    }
                }
            }

        }

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

        $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
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
        if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
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
        $printerType = $stationModel->printerTypeID;
        $allowMenuSpacing = isset($this->settings['Menu Spacing']) ? $this->settings['Menu Spacing'] : 0;

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
        } else {
            if ($charLength > 32) {
                $printer->setTextSize(1,1);
            }
        }
        $printOrderQty = AppHelper::formatNumberValue($printOrder['qty'], null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator);
        $printer->text(str_pad($printingModeID == 3 ? 1 : $printOrderQty, 3,
                ' ', STR_PAD_LEFT));
        $printer->text(' ');
        $menuShortName = AppHelper::fromChinese($printOrder['menuShortName']);
        $printer->text($menuShortName);
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        // @notes: Menu Spacing Notes After Menu is Set
        if ($allowMenuSpacing) {   
            if(!$printOrder['notes']) {
                $printer->feed(intval($allowMenuSpacing));
            } else {
                $this->printNotes($printer, $printOrder, $stationModel, $allowMenuSpacing);
            }
        }

        if (isset($printOrder['packages'])) {
            if (isset($printOrder['flagSeparatePrintPackage']) && $printOrder['flagSeparatePrintPackage'] == true) {
                $this->printPackage($printer,$printOrder['packages'],$stationModel,$charLength, $printOrder['qty'],$printingModeID);
            } else {
                foreach ($printOrder['packages'] as $package) {
                    $this->printPackage($printer,$package,$stationModel,$charLength, $printOrder['qty'],$printingModeID);
                }
            }
        }
        if (isset($printOrder['extras'])) {
            foreach ($printOrder['extras'] as $extra) {
                $printer->text(str_pad('', 4, ' '));
                $printer->text(str_pad($printingModeID == 3 ? $extra['qty'] : $extra['qty'] * $printOrder['qty'], 3, ' ', STR_PAD_LEFT));
                $printer->text(' ');
                $menuExtraShortName = AppHelper::fromChinese($extra['menuExtraShortName']);
                $printer->text($menuExtraShortName);
                // @notes: Menu Spacing
                if ($allowMenuSpacing) {
                    $printer->feed(intval($allowMenuSpacing));
                }
                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }
        // @notes: Menu Spacing Notes After Menu is Unset the Default
        if (!$allowMenuSpacing) {
           $this->printNotes($printer, $printOrder, $stationModel, $allowMenuSpacing);
        }

        if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

    }

    private function printNotes(&$printer, $printOrder, $stationModel, $allowMenuSpacing){
        $charLength = $stationModel->characterPerLine;

        if ($printOrder['notes']) {
            $notesString = $printOrder['notes'];
            if (strpos($notesString, "\n") !== false) {
                $notesString = str_replace("\n", ", ", $notesString);
            }
            if (strlen($notesString) >= $charLength - 7) {
                $printer->text(str_pad('', 4, ' '));
                $printer->text('* ');
                $printer->text(substr($notesString, 0, $charLength - 7));
                // @notes: Menu Spacing
                if ($allowMenuSpacing) {
                    $printer->feed(intval($allowMenuSpacing));
                }
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
                // @notes: Menu Spacing
                if ($allowMenuSpacing) {
                    $printer->feed(intval($allowMenuSpacing));
                }
            }
            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

    }

    private function printPackage($printer,$package,$stationModel,$charLength, $qty = 1, $printingModeID){

        $allowMenuSpacing = isset($this->settings['Menu Spacing']) ? $this->settings['Menu Spacing'] : 0;

        $printer->text(str_pad('', 4, ' '));
        $packageQty = $printingModeID == 3 ? $package['qty'] : $package['qty'] * $qty;
        $printer->text(str_pad(AppHelper::formatNumberValue($packageQty, null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator), 3, ' ', STR_PAD_LEFT));
        $printer->text(' ');
        $menuShortName = AppHelper::fromChinese($package['menuShortName']);
        $printer->text($menuShortName);
 
        //set padding bottom print
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        // @notes: Menu Spacing Notes After Menu is Set
        if ($allowMenuSpacing) {
            if(!$package['notes']) {
                $printer->feed(intval($allowMenuSpacing));
            } else {
                $this->printNotespackage($printer,$package,$stationModel,$charLength, $allowMenuSpacing);
            }
        }
        // @notes: Menu Spacing Notes After Menu is Unset the Default
        if(!$allowMenuSpacing){
            $this->printNotespackage($printer,$package,$stationModel,$charLength, $allowMenuSpacing);
        }
    }

    private function printNotesPackage($printer,$package,$stationModel,$charLength, $allowMenuSpacing) {

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
                // @notes: Menu Spacing
                if ($allowMenuSpacing) {
                    $printer->feed(intval($allowMenuSpacing));
                }
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
                // @notes: Menu Spacing
                if ($allowMenuSpacing) {
                    $printer->feed(intval($allowMenuSpacing));
                }
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
            if($this->salesModel->tableID === 0) {
                $header = $queueNumber . " - " . $counterText;
            } else {
                $tableName = $this->salesModel->table->tableName;
                $header = $tableName . " - " . $counterText;
            }
            
            if ($this->salesModel->additionalInfo) {
                $header .= " - " . $this->salesModel->additionalInfo;
            }
            
            $printer->addLine($stationName, 9);
            $printer->addLine($header, 9);
            $printer->addLine($detail['menuShortName'], 9, true);

            if (isset($detail['packages'])) {
                foreach ($detail['packages'] as $package) {
                    $printer->addLine($package['qty'] . " " . $package['menuShortName'], 9, false, 30);
                }
            }

            if (isset($detail['extras'])) {
                foreach ($detail['extras'] as $extra) {
                    $printer->addLine($extra['qty'] . " " . $extra['menuExtraShortName'], 9, false, 30);
                }
            }

            if (isset($detail['notes']) != '') {
                $notes = substr($detail['notes'], 2);
                $printer->addLine($notes, 9, false);
            }

            $printer->addBlankLine();
            $printer->addBlankLine();

            $printer->addLine(date("d/m/Y") . " / " . ($this->salesModel->creator ? $this->salesModel->creator->fullName : "SELF ORDER"),
                7);

            $printer->sendToPrinter();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function epsonSticker($printer, $queueNumber, $detail, $counter, $totalItems, $charLength, $stationModel, $printingModeID, $odsBarcodeSetting) {
        $marginLeftValue = array_key_exists('Epson Sticker Margin Left',
                $this->settings) ? $this->settings['Epson Sticker Margin Left'] : 40;
        $paperWidthValue = array_key_exists('Epson Sticker Width',
                $this->settings) ? $this->settings['Epson Sticker Width'] : 500;
        $marginLeft = intval($marginLeftValue);
        $paperWidth = intval($paperWidthValue);
        
        $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems, 'Epson Sticker', $stationModel->printerTypeID);
        $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems, 'Epson Sticker');

        $lastFeed = 5;

        if ($stationModel->printerTypeID !== 13) {
            $printer->setPrintLeftMargin($marginLeft);
            $printer->setPrintWidth($paperWidth);
        } else {
            $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr($marginLeft));
        }
        
        if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $layoutHeaderPrintingBold = isset($this->settings['Layout Header Printing Bold']) ? $this->settings['Layout Header Printing Bold'] : 0;
            if ($layoutHeaderPrintingBold == 1) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
            } else {
                $printer->selectPrintMode(Printer::MODE_FONT_A);
            }
        }  elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        $printer->text($LayoutHeaderPrintLabel);

        if ($stationModel->printerTypeID === 13) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        } else {
            $printer->getPrintConnector()->write("\x1B" . "\x1C" ."\x70" . chr(Printer::MODE_EMPHASIZED) . chr(Printer::MODE_DOUBLE_HEIGHT));
        }

        $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
        if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $layoutHeaderMenuPrintingBold = isset($this->settings['Layout Header Menu Printing Bold']) ? $this->settings['Layout Header Menu Printing Bold'] : 0;
            if ($layoutHeaderMenuPrintingBold == 1) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
            } else {
                $printer->selectPrintMode(Printer::MODE_FONT_A);
            }
        }
        $printer->text($layoutHeaderMenuPrintLabel);

        if ($stationModel->printerTypeID === 13) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $layoutMainMenuPrintingBold = isset($this->settings['Layout Main Menu Printing Bold']) ? $this->settings['Layout Main Menu Printing Bold'] : 0;
            if ($layoutMainMenuPrintingBold == 1) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
            } else {
                $printer->selectPrintMode(Printer::MODE_FONT_A);
            }
        } elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        

        if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
            $menuNames = str_split((($printingModeID == 3)? 1 : $detail['qty'])." ".$detail['menuName'], $charLength);
            foreach($menuNames as $menu){
                $printer->text(ltrim($menu));
            }
        } else {
            $menuShortNames = str_split((($printingModeID == 3)? 1 : $detail['qty'])." ".$detail['menuShortName'], $charLength);
            foreach($menuShortNames as $menu){
                $printer->text(ltrim($menu));
            }
        }
     
        if ($stationModel->printerTypeID === 13) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        $printer->initialize();

        if ($stationModel->printerTypeID !== 13) {
            $printer->setPrintLeftMargin($marginLeft);
            $printer->setPrintWidth($paperWidth);
        } else {
            $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr($marginLeft));
        }

        if ($detail['notes']) {
            if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $layoutNotesPrintingBold = isset($this->settings['Layout Notes Printing Bold']) ? $this->settings['Layout Notes Printing Bold'] : 0;
                if ($layoutNotesPrintingBold == 1) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else {
                    $printer->selectPrintMode(Printer::MODE_FONT_A);
                }
            } else if ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }

            $wordingNotes = "* " . $detail['notes'];
            $l = 0;
            do{
                $notesCharLength = ($l > 0)? $charLength-4 : $charLength-2;
                $splitWords = str_split($wordingNotes,($notesCharLength));
                $partOfWords = explode(" ",$wordingNotes);

                $print = $this->trimByWord($splitWords,$partOfWords);

                // @note : re write wording menu until empty.
                $start = (strlen($print['word']));
                $end = (strlen($wordingNotes));
                $wordingNotes = ltrim(substr($wordingNotes,$start,$end));

                // @note : print word
                if ($stationModel->printerTypeID === 13) {
                    if($l > 0){
                        $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr(($marginLeft+4)));
                    }else{
                        $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr(($marginLeft+2)));
                    }
                }
                $printer->text(ltrim($print['word']));
                if($wordingNotes != ""){
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
                $l++;
            }while ($wordingNotes != "");

            if ($stationModel->printerTypeID === 13) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            --$lastFeed;
        }

        if (isset($detail['packages'])) {
            if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $layoutMenuPackageExtraPrintingBold = isset($this->settings['Layout Menu Package & Extra Printing Bold']) ? $this->settings['Layout Menu Package & Extra Printing Bold'] : 0;
                if ($layoutMenuPackageExtraPrintingBold == 1) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else {
                    $printer->selectPrintMode(Printer::MODE_FONT_A);
                }
            } elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }

            if($this->settings['Layout Menu Mode Print Label'] == 1){
                $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);

                foreach($menuArray as $menu){
                    $printer->text($menu);
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                    --$lastFeed;
                }

            }else if($this->settings['Layout Menu Mode Print Label'] == 2){
                
                $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);	

                foreach($menuArray as $menu){
                    $wordingMenu = $menu;
                    do{
                        $splitWords = str_split($wordingMenu,($charLength-2));
                        $partOfWords = explode(" ",$wordingMenu);

                        $print = $this->trimByWord($splitWords,$partOfWords);

                        // @note : re write wording menu until empty.
                        $start = (strlen($print['word']));
                        $end = (strlen($wordingMenu));
                        $wordingMenu = ltrim(substr($wordingMenu,$start,$end));

                        // @note : print word
                        if ($stationModel->printerTypeID === 13) {
                            $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr(($marginLeft+2)));
                        }
                        $printer->text(ltrim($print['word']));
                        if($wordingMenu != ""){
                            if ($stationModel->printerTypeID === 13) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }while ($wordingMenu != "");
                   
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
            else {

                foreach ($detail['packages'] as $package) {
                    $printer->text($package['qty'] . ' x ' . $package['menuShortName']);
                    $printer->feed(1);
                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->text($notes);
                        if ($stationModel->printerTypeID === 13) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                        --$lastFeed;
                    }
                }

            }
        }
        
        if (isset($detail['extras'])) {
            if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15) {
                $layoutMenuPackageExtraPrintingBold = isset($this->settings['Layout Menu Package & Extra Printing Bold']) ? $this->settings['Layout Menu Package & Extra Printing Bold'] : 0;
                if ($layoutMenuPackageExtraPrintingBold == 1) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else {
                    $printer->selectPrintMode(Printer::MODE_FONT_A);
                }
             
            } elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            if($this->settings['Layout Menu Mode Print Label'] == 1){
                $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);

                foreach($menuArray as $menu){
                    $printer->text($menu);
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                    --$lastFeed;
                }
            }
            else if($this->settings['Layout Menu Mode Print Label'] == 2){
                $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	

                foreach($menuArray as $menu){
                    $wordingMenu = $menu;
                    do{
                        $splitWords = str_split($wordingMenu,($charLength-2));
                        $partOfWords = explode(" ",$wordingMenu);

                        $print = $this->trimByWord($splitWords,$partOfWords);

                        // @note : re write wording menu until empty.
                        $start = (strlen($print['word']));
                        $end = (strlen($wordingMenu));
                        $wordingMenu = ltrim(substr($wordingMenu,$start,$end));

                        // @note : print word
                        if ($stationModel->printerTypeID === 13) {
                            $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr(($marginLeft+2)));
                        }
                        $printer->text(ltrim($print['word']));
                        if($wordingMenu != ""){
                            if ($stationModel->printerTypeID === 13) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }while ($wordingMenu != "");
                   
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }

                }
            }
            else {
                foreach ($detail['extras'] as $extra) {
                    $printer->text($extra['qty'] . ' x ' . $extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->text($notes);
                        --$lastFeed;
                    }
                    if ($stationModel->printerTypeID === 13) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }

        $printer->initialize();
        if ($stationModel->printerTypeID !== 13) {
            $printer->setPrintLeftMargin($marginLeft);
            $printer->setPrintWidth($paperWidth);
        } else {
            $printer->getPrintConnector()->write("\x1B" . "\x6C" . chr($marginLeft));
        }
        if ($stationModel->printerTypeID !== 13 && $stationModel->printerTypeID !== 15 && $stationModel->printerTypeID !== PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $layoutHeaderPrintingBold = isset($this->settings['Layout Footer Printing Bold']) ? $this->settings['Layout Footer Printing Bold'] : 0;
            if ($layoutHeaderPrintingBold == 1) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
            } else {
                $printer->selectPrintMode(Printer::MODE_FONT_A);
            }
        } elseif ($stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        $printer->text($LayoutFooterPrintLabel);
        if ($stationModel->printerTypeID === 13) {
            $printer->getPrintConnector()->write("\x0A");
        }

        if ($odsBarcodeSetting == 1) {
            $kitchenOrderStickerModel = new KitchenOrder();
            $kitchenOrderStickerModel->getCode();
            if (isset($detail['packages']) && $detail['packages']) {
                foreach ($detail['packages'] as $package) {
                    $kitchenOrderStickerModel->setData($printingModeID, $this->salesNum, $package);
                }
            } else {
                $branchMenu = BranchMenu::find()->where(['menuID' => $detail['menuID']])->one();
                if ($branchMenu) {
                    $explodedMenu = explode(',',$branchMenu->stationID);
                    if (is_array($explodedMenu) && in_array($stationModel->stationID, $explodedMenu)) {
                        $kitchenOrderStickerModel->setData($printingModeID, $this->salesNum, $detail);
                    }
                }
            }
            $kitchenOrderStickerModel->generateQR($printer, $stationModel);
            $kitchenOrderStickerModel->saveModel();
            $printer->feed(1);
        } else {
            $printer->feed(3);
        }

        if ($stationModel->flagAutocut == '1') {
            if ($stationModel->printerTypeID === 13) {
                $printer->getPrintConnector()->write("\x1B" . "\x64" . chr(Printer::CUT_PARTIAL));
            } else {
                $printer->cut(Printer::CUT_PARTIAL);
            }
        }
    } 

    private function printGSticker($printer, $queueNumber, $detail, $counter, $totalItems, $charLength) {
        try {
            $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems);
            $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems);
        
            $printer->clear();
            $arrHeader = explode('>><<', $LayoutHeaderPrintLabel);
            foreach($arrHeader as $header){
                $printer->write(trim($header), 2);
            }
            
            $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
            $arrMenuCategory = explode('>><<', $layoutHeaderMenuPrintLabel);
            foreach($arrMenuCategory as $menuCategory){
                $printer->write($menuCategory, 2);
            }

            if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
                $menuNames = str_split($detail['menuName'], $charLength);
                foreach($menuNames as $menu){
                    $printer->write($menu, 2);
                }
            } else {
                $menuShortNames = str_split($detail['menuShortName'], $charLength);
                foreach($menuShortNames as $menu){
                    $printer->write($menu, 2);
                }
            }
            
            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write(trim($notes), 2);
            }

            if (isset($detail['packages'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu, 2);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu, 2);
                        }
                    }
    
                }
                else {
                    foreach ($detail['packages'] as $package) {
                        $printer->write($package['qty'] . ' x ' . $package['menuShortName'], 2);

                        if ($package['notes']) {
                            $notes = "* " . $package['notes'];
                            $printer->write($notes, 2);
                        }
                    }
                }
            }

            if (isset($detail['extras'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu, 2);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	
    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu, 2);
                        }
                    }
                }
                else {
                    foreach ($detail['extras'] as $extra) {
                        $printer->write($package['qty'] . ' x ' . $package['menuExtraShortName'], 2);

                        if (isset($extra['notes'])) {
                            $notes = "* " . $extra['notes'];
                            $printer->write($notes, 2);
                        }
                    }
                }
            }
            
            $arrFooter = explode('>><<', $LayoutFooterPrintLabel);
            foreach($arrFooter as $footer){
                $printer->write(trim($footer), 8);
            }
            
            $printer->closeWrite();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function printSatoSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $charLength, $stationModel) {
        try {
            $printer = new SatoStickerPrinter($host, $connectionType, $stationModel);
            $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems);
            $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems);

            $printer->clear();
            $arrHeader = explode('>><<', $LayoutHeaderPrintLabel);
            foreach($arrHeader as $header){
                $printer->write($header);
            }
            
            $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
            $arrMenuCategory = explode('>><<', $layoutHeaderMenuPrintLabel);
            foreach($arrMenuCategory as $menuCategory){
                $printer->write($menuCategory);
            }

            if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
                $menuNames = str_split($detail['menuName'], $charLength);
                foreach($menuNames as $menu){
                    $printer->write($menu);
                }
            } else {
                $menuShortNames = str_split($detail['menuShortName'], $charLength);
                foreach($menuShortNames as $menu){
                    $printer->write($menu);
                }
            }
            
            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
    
                }
                else {
                   foreach ($detail['packages'] as $package) {
                        $printer->write($package['qty'] . ' x ' . $package['menuShortName']);

                        if ($package['notes']) {
                            $notes = "* " . $package['notes'];
                            $printer->write($notes);
                        }
                    } 
                }
            }

            if (isset($detail['extras'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	
    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
                }
                else {
                    foreach ($detail['extras'] as $extra) {
                        $printer->write($extra['qty'] . ' x ' . $extra['menuExtraShortName']);

                        if (isset($extra['notes'])) {
                            $notes = "* " . $extra['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }
            
            $arrFooter = explode('>><<', $LayoutFooterPrintLabel);
            foreach($arrFooter as $footer){
                $printer->write($footer);
            }
            
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function printZebraSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $charLength, $stationModel) {
        try {
            $printer = new ZebraStickerPrinter($host, $connectionType, $stationModel);
            $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems);
            $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems);

            $printer->clear();
            
            $arrHeader = explode('>><<', $LayoutHeaderPrintLabel);
            $layoutHeaderPrintingFontSize = isset($this->settings['Layout Header Printing Font Size']) ? $this->settings['Layout Header Printing Font Size'] : '2';
            foreach($arrHeader as $header){
                $printer->write($header, $layoutHeaderPrintingFontSize);
            }
            
            $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
            $arrMenuCategory = explode('>><<', $layoutHeaderMenuPrintLabel);
            $layoutHeaderMenuPrintingFontSize = isset($this->settings['Layout Header Menu Printing Font Size']) ? $this->settings['Layout Header Menu Printing Font Size'] : '2';
            foreach($arrMenuCategory as $menuCategory){
                $printer->write($menuCategory, $layoutHeaderMenuPrintingFontSize);
            }

            $layoutMainMenuPrintingFontSize = isset($this->settings['Layout Main Menu Printing Font Size']) ? $this->settings['Layout Main Menu Printing Font Size'] : '2';
            if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
                $menuNames = str_split($detail['menuName'], $charLength);
                foreach($menuNames as $menu){
                    $printer->write($menu, $layoutMainMenuPrintingFontSize);
                }
            } else {
                $menuShortNames = str_split($detail['menuShortName'], $charLength);
                foreach($menuShortNames as $menu){
                    $printer->write($menu, $layoutMainMenuPrintingFontSize);
                }
            }

            $layoutNotesPrintingFontSize = isset($this->settings['Layout Notes Printing Font Size']) ? $this->settings['Layout Notes Printing Font Size'] : '2';
            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes, $layoutNotesPrintingFontSize);
            }

            $layoutMenuPackageExtraPrintingFontSize = isset($this->settings['Layout Menu Package & Extra Printing Font Size']) ? $this->settings['Layout Menu Package & Extra Printing Font Size'] : '2';
            if (isset($detail['packages'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu, $layoutMenuPackageExtraPrintingFontSize);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu, $layoutMenuPackageExtraPrintingFontSize);
                        }
                    }
    
                }
                else {
                    foreach ($detail['packages'] as $package) {
                        $printer->write($package['qty'] . ' x ' . $package['menuShortName'], $layoutMenuPackageExtraPrintingFontSize);

                        if ($package['notes']) {
                            $notes = "* " . $package['notes'];
                            $printer->write($notes, $layoutMenuPackageExtraPrintingFontSize);
                        }
                    }
                }
            }

            if (isset($detail['extras'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu, $layoutMenuPackageExtraPrintingFontSize);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	
    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu, $layoutMenuPackageExtraPrintingFontSize);
                        }
                    }
                }
                else {
                    foreach ($detail['extras'] as $extra) {
                        $printer->write($extra['qty'] . ' x ' . $extra['menuExtraShortName'], $layoutMenuPackageExtraPrintingFontSize);

                        if (isset($extra['notes'])) {
                            $notes = "* " . $extra['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }
            
            $arrFooter = explode('>><<', $LayoutFooterPrintLabel);
            $layoutFooterPrintingFontSize = isset($this->settings['Layout Footer Printing Font Size']) ? $this->settings['Layout Footer Printing Font Size'] : '2';
            foreach($arrFooter as $footer){
                $printer->write($footer, $layoutFooterPrintingFontSize);
            }
            
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }
    
    private function printBixolonSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $charLength, $stationModel) {
        try {
            $printer = new BixolonStickerPrinter($host, $connectionType,$stationModel);
            $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems);
            $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems);

            $printer->clear();
            $arrHeader = explode('>><<', $LayoutHeaderPrintLabel);
            foreach($arrHeader as $header){
                $printer->write($header);
            }
            
            $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
            $arrMenuCategory = explode('>><<', $layoutHeaderMenuPrintLabel);
            foreach($arrMenuCategory as $menuCategory){
                $printer->write($menuCategory);
            }
            
            if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
                $menuNames = str_split($detail['menuName'], $charLength);
                foreach($menuNames as $menu){
                    $printer->write($menu);
                }
            } else {
                $menuShortNames = str_split($detail['menuShortName'], $charLength);
                foreach($menuShortNames as $menu){
                    $printer->write($menu);
                }
            }

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
    
                }
                else {
                    foreach ($detail['packages'] as $package) {
                        $printer->write($package['qty'] . ' x ' . $package['menuShortName']);

                        if ($package['notes']) {
                            $notes = "* " . $package['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }

            if (isset($detail['extras'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                } 
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	
    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
                }
                else {
                    foreach ($detail['extras'] as $extra) {
                        $printer->write($extra['qty'] . ' x ' . $extra['menuExtraShortName']);

                        if (isset($extra['notes'])) {
                            $notes = "* " . $extra['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }
            
            $arrFooter = explode('>><<', $LayoutFooterPrintLabel);
            foreach($arrFooter as $footer){
                $printer->write($footer);
            }
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }
    
    private function printBrotherSticker($host, $connectionType, $queueNumber, $detail, $counter, $totalItems, $charLength,$stationModel) {
        try {
            $printer = new BrotherStickerPrinter($host, $connectionType,$stationModel);
            $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems);
            $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems);

            $printer->clear();
            $arrHeader = explode('>><<', $LayoutHeaderPrintLabel);
            foreach($arrHeader as $header){
                $printer->write($header);
            }

            $layoutHeaderMenuPrintLabel = $this->layoutHeaderMenuPrintLabel($detail);
            $arrMenuCategory = explode('>><<', $layoutHeaderMenuPrintLabel);
            foreach($arrMenuCategory as $menuCategory){
                $printer->write($menuCategory);
            }
            
            if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
                $menuNames = str_split($detail['menuName'], $charLength);
                foreach($menuNames as $menu){
                    $printer->write($menu);
                }
            } else {
                $menuShortNames = str_split($detail['menuShortName'], $charLength);
                foreach($menuShortNames as $menu){
                    $printer->write($menu);
                }
            }

            if ($detail['notes']) {
                $notes = "* " . $detail['notes'];
                $printer->write($notes);
            }

            if (isset($detail['packages'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                }
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
    
                } 
                else {
                    foreach ($detail['packages'] as $package) {
                        $printer->write($package['qty'] . ' x ' . $package['menuShortName']);

                        if ($package['notes']) {
                            $notes = "* " . $package['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }

            if (isset($detail['extras'])) {
                if($this->settings['Layout Menu Mode Print Label'] == 1){
                    $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);
                    
                    foreach($menuArray as $menu){
                        $printer->write($menu);
                    }
                }
                else if($this->settings['Layout Menu Mode Print Label'] == 2){
                    $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	
    
                    foreach($menuArray as $menu){
                        $lineMenuArray = str_split($menu, $charLength);
                        foreach($lineMenuArray as $lineMenu){
                            $printer->write($lineMenu);
                        }
                    }
                }
                else {
                    foreach ($detail['extras'] as $extra) {
                        $printer->write($extra['qty'] . ' x ' . $extra['menuExtraShortName']);

                        if (isset($extra['notes'])) {
                            $notes = "* " . $extra['notes'];
                            $printer->write($notes);
                        }
                    }
                }
            }
            
            $arrFooter = explode('>><<', $LayoutFooterPrintLabel);
            foreach($arrFooter as $footer){
                $printer->write($footer);
            }
            $printer->closeWrite();
            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }
        
    private function epsonStickerTML90($printer, $queueNumber, $detail, $counter, $totalItems, $charLength, $stationModel, $printingModeID, $odsBarcodeSetting) {
                   
        $marginLeftValue = array_key_exists('Epson Sticker Margin Left',
                $this->settings) ? $this->settings['Epson Sticker Margin Left'] : 40;
        $paperWidthValue = array_key_exists('Epson Sticker Width',
                $this->settings) ? $this->settings['Epson Sticker Width'] : 500;
        $marginLeft = intval($marginLeftValue);
        $paperWidth = intval($paperWidthValue);
        
        $LayoutHeaderPrintLabel = $this->layoutHeaderPrintLabel($queueNumber, $counter, $totalItems, 'Epson Sticker');
        $LayoutFooterPrintLabel = $this->layoutFooterPrintLabel($queueNumber, $counter, $totalItems, 'Epson Sticker');

        $lastFeed = 5;

        $printer->setPrintLeftMargin($marginLeft);
        $printer->setPrintWidth($paperWidth);
        
        $printer->text($LayoutHeaderPrintLabel);
        $printer->feed(1);
        $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
        
        if(isset($this->settings['Layout Main Menu Mode Print Label']) && $this->settings['Layout Main Menu Mode Print Label'] == 1){
            $menuNames = str_split($detail['menuName'], $charLength);
            foreach($menuNames as $menu){
                $printer->text($menu);
            }
        } else {
            $menuShortNames = str_split($detail['menuShortName'], $charLength);
            foreach($menuShortNames as $menu){
                $printer->text($menu);
            }
        }
        
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
            if($this->settings['Layout Menu Mode Print Label'] == 1){
                $menuArray = $this->layoutWrapPrintLabelPackage($detail['packages'], $charLength);

                foreach($menuArray as $menu){
                    $printer->text($menu);
                    $printer->feed(1);
                    --$lastFeed;
                }
            } 
            else if($this->settings['Layout Menu Mode Print Label'] == 2){
                $menuArray = $this->layoutListPrintLabelPackage($detail['packages']);
                
                foreach($menuArray as $menu){
                    $lineMenuArray = str_split($menu, $charLength);
                    foreach($lineMenuArray as $lineMenu){
                        $printer->text($lineMenu);
                    }
                    $printer->feed(1);
                }

            }
            else {
                foreach ($detail['packages'] as $package) {
                    $printer->text($package['qty'] . ' x ' . $package['menuShortName']);

                    if ($package['notes']) {
                        $notes = "* " . $package['notes'];
                        $printer->text($notes);
                        $printer->feed(1);
                        --$lastFeed;
                    }
                }
            }
        }
        
        if (isset($detail['extras'])) {
            if($this->settings['Layout Menu Mode Print Label'] == 1){
                $menuArray = $this->layoutWrapPrintLabelExtra($detail['extras'], $charLength);

                foreach($menuArray as $menu){
                    $printer->text($menu);
                    $printer->feed(1);
                    --$lastFeed;
                }
            } 
            else if($this->settings['Layout Menu Mode Print Label'] == 2){
                $menuArray = $this->layoutListPrintLabelExtra($detail['extras']);	

                foreach($menuArray as $menu){
                    $lineMenuArray = str_split($menu, $charLength);
                    foreach($lineMenuArray as $lineMenu){
                        $printer->text($lineMenu);
                        if(count((array)$lineMenuArray) > 1){
                            $printer->feed(1);
                        }
                    }
                    $printer->feed(1);
                }
            }
            else {
                foreach ($detail['extras'] as $extra) {
                    $printer->text($extra['qty'] . ' x ' . $extra['menuExtraShortName']);

                    if (isset($extra['notes'])) {
                        $notes = "* " . $extra['notes'];
                        $printer->text($notes);
                        $printer->feed(1);
                        --$lastFeed;
                    }
                }
            }
        }

        $printer->text($LayoutFooterPrintLabel);
        if ($odsBarcodeSetting == 1) {
            $kitchenOrderStickerModel = new KitchenOrder();
            $kitchenOrderStickerModel->getCode();
            if (isset($detail['packages']) && $detail['packages']) {
                foreach ($detail['packages'] as $package) {
                    $kitchenOrderStickerModel->setData($printingModeID, $this->salesNum, $package);
                }
            } else {
                $branchMenu = BranchMenu::find()->where(['menuID' => $detail['menuID']])->one();
                if ($branchMenu) {
                    $explodedMenu = explode(',',$branchMenu->stationID);
                    if (is_array($explodedMenu) && in_array($stationModel->stationID, $explodedMenu)) {
                        $kitchenOrderStickerModel->setData($printingModeID, $this->salesNum, $detail);
                    }
                }
            }
            $kitchenOrderStickerModel->generateQR($printer, $stationModel);
            $kitchenOrderStickerModel->saveModel();
            $printer->feed(1);
        }
        if ($stationModel->flagAutocut == '1') {
            $printer->feed(5);
            $printer->getPrintConnector()->write("\x1b" . "\x69");
        } else {
            $printer->feed(1);
            $printer->getPrintConnector()->write(Printer::FS."(L".chr(2).chr(0).chr(65).chr(48));
        }
    }

    private function layoutHeaderPrintLabel($queueNumber, $counter, $totalItems, $printerType = null, $printerTypeID = null) {
        $LayoutHeaderPrintLabel = isset($this->settings['Layout Header Print Label']) ? $this->settings['Layout Header Print Label'] : '';
        if($this->salesModel->tableID === 0) {
            if ($printerTypeID === 13) {
                $queueNumber = "Queue#$queueNumber";
            }
            $LayoutHeaderPrintLabel =  str_replace("%tableName%", "$queueNumber", $LayoutHeaderPrintLabel);
        } else {
            $tableName = $this->salesModel->table->tableName;
            $LayoutHeaderPrintLabel =  str_replace("%tableName%", $tableName, $LayoutHeaderPrintLabel);
        }
        
        $LayoutHeaderPrintLabel =  str_replace("%salesNum%", $this->salesModel->salesNum, $LayoutHeaderPrintLabel);
        $LayoutHeaderPrintLabel =  str_replace("%tableName%", "$queueNumber", $LayoutHeaderPrintLabel);
        $LayoutHeaderPrintLabel =  str_replace("%salesDate%", date("d/m/Y"), $LayoutHeaderPrintLabel);
        $LayoutHeaderPrintLabel =  str_replace("%counter%", "$counter/$totalItems", $LayoutHeaderPrintLabel);

        $LayoutHeaderPrintLabel =  str_replace("%branchName%", $this->salesModel->branch->branchName, $LayoutHeaderPrintLabel);

        if ($this->customerInfo && ($this->salesMenusModel[0]->salesType == 'EZO FS' || $this->salesMenusModel[0]->salesType == 'EZO QS')) {
            $LayoutHeaderPrintLabel =  str_replace("%ESOCustomerName%", $this->customerInfo->fullName, $LayoutHeaderPrintLabel);
        } else {
            $LayoutHeaderPrintLabel = str_replace("%ESOCustomerName%", '', $LayoutHeaderPrintLabel);
        }
        
        if($printerType == 'Epson Sticker'){
            $LayoutHeaderPrintLabel =  str_replace(">><<", "\n", $LayoutHeaderPrintLabel);
        }
        
        $LayoutHeaderPrintLabel =  str_replace("%addtionalInfo%", $this->salesModel->additionalInfo, $LayoutHeaderPrintLabel);
        $LayoutHeaderPrintLabel =  str_replace("%visitPurpose%", $this->salesModel->visitPurpose ? $this->salesModel->visitPurpose->visitPurposeName : '', $LayoutHeaderPrintLabel);
        
        if($this->salesModel->salesDateIn){
            $LayoutHeaderPrintLabel =  str_replace("%salesTimeIn%", date("d/m/Y h:i:s", strtotime($this->salesModel->salesDateIn)), $LayoutHeaderPrintLabel);
        } else {
            $LayoutHeaderPrintLabel =  str_replace("%salesTimeIn%", "", $LayoutHeaderPrintLabel);
        }
        
        if($this->salesModel->salesDateOut){
            $LayoutHeaderPrintLabel =  str_replace("%salesTimeOut%", date("d/m/Y h:i:s", strtotime($this->salesModel->salesDateOut)), $LayoutHeaderPrintLabel);
        } else {
            $LayoutHeaderPrintLabel =  str_replace("%salesTimeOut%", "", $LayoutHeaderPrintLabel);
        }
        
        return $LayoutHeaderPrintLabel;
    }
    
    private function layoutFooterPrintLabel($queueNumber, $counter, $totalItems, $printerType = null) {
        $LayoutFooterPrintLabel = isset($this->settings['Layout Footer Print Label']) ? $this->settings['Layout Footer Print Label'] : '';
        if($this->salesModel->tableID === 0) {
            $LayoutFooterPrintLabel =  str_replace("%tableName%", "$queueNumber", $LayoutFooterPrintLabel);
        } else {
            $tableName = $this->salesModel->table->tableName;
            $LayoutFooterPrintLabel =  str_replace("%tableName%", $tableName, $LayoutFooterPrintLabel);
            }
        
        $LayoutFooterPrintLabel =  str_replace("%salesNum%", $this->salesModel->salesNum, $LayoutFooterPrintLabel);
        $LayoutFooterPrintLabel =  str_replace("%tableName%", "$queueNumber", $LayoutFooterPrintLabel);
        $LayoutFooterPrintLabel =  str_replace("%salesDate%", date("d/m/Y"), $LayoutFooterPrintLabel);
        $LayoutFooterPrintLabel =  str_replace("%counter%", "$counter/$totalItems", $LayoutFooterPrintLabel);
        $LayoutFooterPrintLabel =  str_replace("%branchName%", $this->salesModel->branch->branchName, $LayoutFooterPrintLabel);

        if ($this->customerInfo && ($this->salesMenusModel[0]->salesType == 'EZO FS' || $this->salesMenusModel[0]->salesType == 'EZO QS')) {
            $LayoutFooterPrintLabel =  str_replace("%ESOCustomerName%", $this->customerInfo->fullName, $LayoutFooterPrintLabel);
        } else {
            $LayoutFooterPrintLabel = str_replace("%ESOCustomerName%", '', $LayoutFooterPrintLabel);
        }
        
        if($printerType == 'Epson Sticker'){
            $LayoutFooterPrintLabel =  str_replace(">><<", "\n", $LayoutFooterPrintLabel);
        }
        
        $LayoutFooterPrintLabel =  str_replace("%addtionalInfo%", $this->salesModel->additionalInfo, $LayoutFooterPrintLabel);
        $LayoutFooterPrintLabel =  str_replace("%visitPurpose%", $this->salesModel->visitPurpose ? $this->salesModel->visitPurpose->visitPurposeName : '', $LayoutFooterPrintLabel);
        
        if($this->salesModel->salesDateIn){
            $LayoutFooterPrintLabel =  str_replace("%salesTimeIn%", date("d/m/Y h:i:s", strtotime($this->salesModel->salesDateIn)), $LayoutFooterPrintLabel);
        } else {
            $LayoutFooterPrintLabel =  str_replace("%salesTimeIn%", "", $LayoutFooterPrintLabel);
        }
        
        if($this->salesModel->salesDateOut){
            $LayoutFooterPrintLabel =  str_replace("%salesTimeOut%", date("d/m/Y h:i:s", strtotime($this->salesModel->salesDateOut)), $LayoutFooterPrintLabel);
        } else {
            $LayoutFooterPrintLabel =  str_replace("%salesTimeOut%", "", $LayoutFooterPrintLabel);
        }
        
        return $LayoutFooterPrintLabel;
    }

    private function layoutHeaderMenuPrintLabel($menu){
        $layoutHeaderMenuPrintLabel = isset($this->settings['Layout Header Menu Print Label']) ? $this->settings['Layout Header Menu Print Label'] : '';
        $layoutHeaderMenuPrintLabel = str_replace("%menuCategoryDesc%", $menu['menuCategoryDesc'], $layoutHeaderMenuPrintLabel);
        $layoutHeaderMenuPrintLabel = str_replace("%menuCategoryDetailDesc%", $menu['menuCategoryDetailDesc'], $layoutHeaderMenuPrintLabel);
        return $layoutHeaderMenuPrintLabel;
    }
    
    private function layoutWrapPrintLabelPackage($menus, $charLength) {
        $menuArr = [];
        foreach ($menus as $menu) {
            $LayoutMenuPrintLabel = isset($this->settings['Layout Menu Print Label']) ? $this->settings['Layout Menu Print Label'] : '';
            $LayoutMenuPrintLabel = str_replace("%qty%", $menu['qty'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%shortName%", $menu['menuShortName'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%fullName%", $menu['menuName'], $LayoutMenuPrintLabel);
           
            $menuArr[] = $LayoutMenuPrintLabel;
            if (isset($menu['notes']) && $menu['notes']) {
                $menuArr[] = " * " . $menu['notes'];
            }
        }
        $menuStr = implode(", ", $menuArr);
        $menuArray = str_split($menuStr, $charLength);
        
        return $menuArray;
    }

    private function layoutListPrintLabelPackage($menus) {
        $menuArr = [];
        foreach ($menus as $menu) {
            $LayoutMenuPrintLabel = isset($this->settings['Layout Menu Print Label']) ? $this->settings['Layout Menu Print Label'] : '';
            $LayoutMenuPrintLabel = str_replace("%qty%", $menu['qty'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%shortName%", $menu['menuShortName'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%fullName%", $menu['menuName'], $LayoutMenuPrintLabel);
           
            $menuArr[] = $LayoutMenuPrintLabel;
            if (isset($menu['notes']) && $menu['notes']) {
                $menuArr[] = " * " . $menu['notes'];
            }
        }
        
        return $menuArr;
    }
    
    private function layoutWrapPrintLabelExtra($menus, $charLength) {
        $menuArr = [];
        foreach ($menus as $menu) {
            $LayoutMenuPrintLabel = isset($this->settings['Layout Menu Print Label']) ? $this->settings['Layout Menu Print Label'] : '';
            $LayoutMenuPrintLabel = str_replace("%qty%", $menu['qty'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%shortName%", $menu['menuExtraShortName'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%fullName%", $menu['menuExtraName'], $LayoutMenuPrintLabel);
           
            $menuArr[] = $LayoutMenuPrintLabel;
            if (isset($menu['notes']) && $menu['notes']) {
                $menuArr[] = " * " . $menu['notes'];
            }
        }
        $menuStr = implode(", ", $menuArr);
        $menuArray = str_split($menuStr, $charLength);
        
        return $menuArray;
    }

    private function layoutListPrintLabelExtra($menus) {
        $menuArr = [];
        foreach ($menus as $menu) {
            $LayoutMenuPrintLabel = isset($this->settings['Layout Menu Print Label']) ? $this->settings['Layout Menu Print Label'] : '';
            $LayoutMenuPrintLabel = str_replace("%qty%", $menu['qty'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%shortName%", $menu['menuExtraShortName'], $LayoutMenuPrintLabel);
            $LayoutMenuPrintLabel = str_replace("%fullName%", $menu['menuExtraName'], $LayoutMenuPrintLabel);
           
            $menuArr[] = $LayoutMenuPrintLabel;
            if (isset($menu['notes']) && $menu['notes']) {
                $menuArr[] = " * " . $menu['notes'];
            }
        }
        
        return $menuArr;
    }

    private function trimByWord($splitWords,$partOfWords){
        foreach($splitWords as $i=>$splitWord){
            $partOfSplitWords = explode(" ",$splitWord);
            $isTrim = false;
            $trimWord = "";
            foreach($partOfSplitWords as $j=>$partOfSplitWord){
                if(!in_array($partOfSplitWord,$partOfWords)){
                    $isTrim = true;
                    $trimWord = $partOfSplitWord;
                    break;
                }
            }
      
            $finalWords = $splitWord;
            if($isTrim){
              $joinWord = [];
              foreach($partOfSplitWords as $j=>$partOfSplitWord){
                if($partOfSplitWord != $trimWord){
                    $joinWord[] = $partOfSplitWord;
                }
              }
              if(count($joinWord) > 0){
                  $finalWords = join(" ",$joinWord);
              }
            }
      
            return array(
              'word' => $finalWords,
              'lastWordTrimed' => $trimWord
            );
        }
    }

    private function printFooterVoidOrder($printer, $stationModel) {
        $charLength = $stationModel->characterPerLine;
        $printerType = $stationModel->printerTypeID;
        
        $printer->initialize();
        $printer->text(str_pad('', $charLength, '-'));
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }

        $printer->initialize();
        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } else if ($printerType != 15 && $printerType != PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        } elseif ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }
        $printer->text('XX VOID ORDER XX');
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
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
                } else if ($printerType != 15 && $printerType != PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                    $printer->selectPrintMode(Printer::MODE_FONT_B);
                }

                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
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

    private function printFooterCancelOrder($printer, $stationModel) {
        $printerType = $stationModel->printerTypeID;
        $charLength = $stationModel->characterPerLine;
        
        $printer->initialize();
        $printer->text(str_pad('', $charLength, '-'));
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
        
        if ($printerType == 3 || $printerType == 4) {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        } else if ($printerType != 15 && $printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT)  {
            $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        } else if ($printerType == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
            $printer->selectPrintMode(Printer::MODE_FONT_B);
        }

        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }
        
        $printer->text('XXX CANCEL ORDER XXX');
        if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }
}
