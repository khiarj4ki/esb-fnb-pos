<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\Menu;
use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesPayment;
use app\models\Setting;
use app\models\Station;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use Exception;

/**
 * @property array $stationIDs
 */
class PrintReporting extends Model {

    public $reportData;
    public $localSettings;
    public $branchID;
    public $stationModel;
    public $printer;
    public $tempString;
    public $settings;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['reportData', 'localSettings', 'branchID', 'stationModel', 'printer', 'tempString', 'settings'], 'safe']
        ];
    }

    public function runPrint() {
        $this->branchID = Setting::getCurrentBranch();
        $stationID = $this->reportData['stationID'];

        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $stationID])
            ->one();
        if (!$this->stationModel) {
            return false;
        }
        
        $shiftReport = [
            'salesMenuGroup' => $this->getSalesMenuGroup(),
            'salesMenuExtra' => $this->getSalesMenuExtra()
        ];

        $this->localSettings = Setting::getLocalSettings();
        $this->settings = Setting::getPrintingSettings();
        
        $connector = Station::getConnectorByModel($this->stationModel,
                    'Reporting');
                    
            if($connector == null){
                throw new Exception("Failed to print. Connector not found", 400);
            }

            $this->printer = new Printer($connector);
            $printer = $this->printer;

            if ($this->settings['Print Sales By Menu Group'] == 1) {
                $this->printSalesByMenuGroup($shiftReport['salesMenuGroup'], $shiftReport['salesMenuExtra']);
            }
            $this->printEnd();

            if ($this->stationModel->printerTypeID == '4') {
                $printer->feed(2);
            } else if ($this->stationModel->printerTypeID == '5') {
                $printer->feed(2);
            } else if ($this->stationModel->printerTypeID == 15) {
                $printer->feed(2);
            } else {
                if ($this->stationModel->flagAutocut == '1') {
                    $printer->cut(Printer::CUT_PARTIAL);
                }
            }

            $printer->close();
    }
    
    public function getSalesMenuGroup() {
        if (!$this->validate()) {
            return false;
        }
        
        $startDate = date('Y-m-d', strtotime($this->reportData['startDate']));
        $endDate = date('Y-m-d', strtotime($this->reportData['endDate']));
        // @Notes: statusID 8 = Finished, 13 = Preparing
        $salesMenu = (new Query())
            ->select([
                'e.menuCategoryDesc',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName',
                'qty' => new Expression('SUM(a.qty * COALESCE(packageHead.qty, 1))'),
                'value' => new Expression('SUM(a.price * a.qty * COALESCE(packageHead.qty, 1))')
            ])
            ->from(SalesMenu::tableName() . ' a')
            ->innerJoin(SalesHead::tableName() . ' b', 'a.salesNum = b.salesNum')
            ->innerJoin(Menu::tableName() . ' c', 'a.menuID = c.menuID')
            ->innerJoin(MenuCategoryDetail::tableName() . ' d', 'd.ID = c.menuCategoryDetailID')
            ->innerJoin(MenuCategory::tableName() . ' e', 'e.menuCategoryID = d.menuCategoryID')
            ->leftJoin(SalesMenu::tableName() . ' z','a.menuRefID = z.localID and a.salesNum = z.salesNum')
            ->leftJoin(SalesMenu::tableName() . ' packageHead', 'a.menuRefID = packageHead.localID AND a.salesNum = packageHead.salesNum AND a.menuGroupID > 0')
            ->where('CAST(b.salesDate AS DATE) BETWEEN "' . $startDate . '" AND "' . $endDate . '"')
            ->andWhere(['b.branchID' => $this->branchID])
            ->andWhere(['b.statusID' => 8])
            ->andWhere(['in', 'a.statusID', [13, 14, 34]])
            ->andWhere(['NOT IN', 'a.salesNum', SalesPayment::getNonSalesQuery($this->branchID,$startDate, $endDate)])
            ->groupBy([
                'e.menuCategoryDesc',
                'd.menuCategoryDetailDesc',
                'a.menuID',
                'c.menuName'
            ])
            ->orderBy('e.menuCategoryDesc, d.menuCategoryDetailDesc')
            ->all();
        
        $data = [];
        foreach ($salesMenu as $sales) {
            $key = $sales['menuCategoryDetailDesc'];
            $qty = $sales['qty'];
            $value = $sales['value'];
            $group = [
                'menuCategoryDetailDesc' => $key,
                'menuCategoryDesc' => $sales['menuCategoryDesc'],
                'subTotalQty' => (array_key_exists($key, $data) ? $data[$key]['subTotalQty'] : 0) + $qty,
                'subTotalValue' => (array_key_exists($key, $data) ? $data[$key]['subTotalValue'] : 0) + $value,
                'menus' => array_key_exists($key, $data) ? $data[$key]['menus'] : []
            ];

            $data[$key] = $group;
            
            $i = 0;
            if (in_array($key, $data)) {
                $data[$key]['menus'][$i]['subTotalQty'] = $data[$key]['menus'][$i]['subTotalQty'] + $qty;
                $data[$key]['menus'][$i]['subTotalValue'] = $data[$key]['menus'][$i]['subTotalValue'] + $value;
            } else {
                $data[$key]['menus'][] = [
                    'menuName' => $sales['menuName'],
                    'qty' => $qty,
                    'value' => $value,
                ];
            }   
        }
        
        $realData = [];
        foreach ($data as $obj) {
            $realData[$obj['menuCategoryDesc']]['categorys'][] = $obj;
        }
        
        return $realData;
    }

    public function getSalesMenuExtra() {
        $startDate = date('Y-m-d', strtotime($this->reportData['startDate']));
        $endDate = date('Y-m-d', strtotime($this->reportData['endDate']));

        $salesMenuExtraModel = SalesMenuExtra::find()
        ->select([
            'qty' => new Expression('SUM(tr_salesmenuextra.qty * tr_salesmenu.qty)'),
            'total' => new Expression('SUM((tr_salesmenuextra.price * tr_salesmenuextra.qty) * tr_salesmenu.qty)')
        ])
        ->joinWith('menuExtra')
        ->joinWith('salesHead')
        ->joinWith('salesMenu')
        ->where([
            'tr_saleshead.statusID' => '8',
        ])
        ->andWhere(['IN', 'tr_salesmenu.statusID', [13, 14, 34]])
        ->andWhere(['>=', 'tr_saleshead.salesDate', $startDate])
        ->andWhere(['<=', 'tr_saleshead.salesDate', $endDate])
        ->andFilterWhere(['in', 'tr_saleshead.branchID', $this->branchID])
        ->all();

        $salesMenuExtras = [];
        foreach($salesMenuExtraModel as $menuExtra) {
            $salesMenuExtras = [
                'description' => 'MENU EXTRA',
                'qty' => $menuExtra['qty'],
                'total' => $menuExtra['total']
            ];
        }

        return $salesMenuExtras;
    }

    private function printSalesByMenuGroup($data, $salesExtra) {
        $startDate = date('d-m-Y', strtotime($this->reportData['startDate']));
        $endDate = date('d-m-Y', strtotime($this->reportData['endDate']));
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

        $this->printLableTrialMode();

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
        } else {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        $printer->text(' START ');
        $printer->text(str_pad('', ($charLength - 7) / 2, '*', STR_PAD_LEFT));
        
        $printer->feed(1);
        
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }
        
        $printer->text(Yii::t('app', $startDate.' - '.$endDate) . $this->tempString);

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

        $printer->text(Yii::t('app', 'Sales by Menu Group') . $this->tempString);
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
        $totalQty = 0;
        $totalValue = 0;
        foreach ($data as $key => $detail) {
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }
            $menuCategoryDesc = substr($key, 0, $charLength - 17);
            $printer->text(str_pad($menuCategoryDesc, $charLength - 15, ' '));
             if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            $printer->initialize();
            $summarySubTotalQty = 0;
            $summarySubTotalValue = 0;
            foreach($detail['categorys'] as $category){
                $summarySubTotalQty += $category['subTotalQty'];
                $summarySubTotalValue += $category['subTotalValue'];
                $menuCategoryDetailDesc = substr($category['menuCategoryDetailDesc'], 0, $charLength - 17);
                $printer->text(str_pad('  ' . $menuCategoryDetailDesc, $charLength - 15, ' '));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                foreach($category['menus'] as $menu){
                    $printer->text(str_pad('    ' . $menu['menuName'], $charLength - 15, ' '));
                    $printer->feed(1);
                    $printer->text(str_pad('    - ' . 'Qty', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($menu['qty'], 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12,
                            ' ', STR_PAD_LEFT));
                    $printer->text(str_pad('    - ' . 'Value', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(number_format($menu['value'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12,
                            ' ', STR_PAD_LEFT));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
                $menuCategoryDetailDescSummary = strlen($category['menuCategoryDetailDesc']) > $charLength - 30
                ? substr($category['menuCategoryDetailDesc'], 0, $charLength - 36) . '...'
                : $category['menuCategoryDetailDesc'];

                if ($charLength > 32) {
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary.' Summary Qty', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(self::formatNumberValue($category['subTotalQty']), 12, ' ', STR_PAD_LEFT));
                    $this->printLineBreak();
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary.' Summary Value', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $printer->text(str_pad(
                        number_format($category['subTotalValue'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator")
                        , 12, ' ', STR_PAD_LEFT
                    ));
                } else {
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary.' Summary Qty', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text('    ' . self::formatNumberValue($category['subTotalQty']));
                    $this->printLineBreak();
                    $printer->text(str_pad('  ' . $menuCategoryDetailDescSummary.' Summary Value', $charLength - 15, ' '));
                    $printer->text(' : ');
                    $this->printLineBreak();
                    $printer->text('    ' . number_format($category['subTotalValue'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
                }
                $this->printLineBreak();
            }
            $totalQty += $summarySubTotalQty;
            $totalValue += $summarySubTotalValue;
            $menuCategoryDescSummary = strlen($menuCategoryDesc) > $charLength - 30 ? substr($menuCategoryDesc,
                0, $charLength - 36) . '...' : $menuCategoryDesc;
            if ($this->stationModel->printerTypeID != 15) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
            }

            if ($charLength > 32) {
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary.' Summary Qty'), $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(self::formatNumberValue($summarySubTotalQty), 12, ' ', STR_PAD_LEFT));
                $this->printLineBreak();
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary.' Summary Value'), $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($summarySubTotalValue, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            } else {
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary.' Summary Qty'), $charLength - 15, ' '));
                $printer->text(' : ');
                $this->printLineBreak();
                $printer->text('  ' . self::formatNumberValue($summarySubTotalQty));
                $this->printLineBreak();
                $printer->text(str_pad(Yii::t('app', $menuCategoryDescSummary.' Summary Value'), $charLength - 15, ' '));
                $printer->text(' : ');
                $this->printLineBreak();
                $printer->text('  ' . number_format($summarySubTotalValue, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            }
            
            $this->printLineBreak();
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
        }

        if (isset($salesExtra['qty']) && $salesExtra['qty'] > 0) {
            $printer->text(str_pad($salesExtra['description'], $charLength - 15, ' '));
            $this->printLineBreak();

            if ($charLength > 32) {
                $printer->text(str_pad(Yii::t('app', 'Extra'.' Summary Qty'), $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(self::formatNumberValue($salesExtra['qty']), 12, ' ', STR_PAD_LEFT));
                $this->printLineBreak();
                $printer->text(str_pad(Yii::t('app', 'Extra'.' Summary Value'), $charLength - 15, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(number_format($salesExtra['total'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ', STR_PAD_LEFT));
            } else {
                $printer->text(str_pad(Yii::t('app', 'Extra'.' Summary Qty'), $charLength - 15, ' '));
                $printer->text(' : ');
                $this->printLineBreak();
                $printer->text('  ' . self::formatNumberValue($salesExtra['qty']));
                $this->printLineBreak();
                $printer->text(str_pad(Yii::t('app', 'Extra'.' Summary Value'), $charLength - 15, ' '));
                $printer->text(' : ');
                $this->printLineBreak();
                $printer->text('  ' . number_format($salesExtra['total'], $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"));
            }

            $this->printLineBreak();
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
        }
        
        $totalQtySales = $salesExtra['qty'] > 0 ? $totalQty + $salesExtra['qty'] : $totalQty;
        $totalValueSales = $salesExtra['total'] > 0 ? $totalValue + $salesExtra['total'] : $totalValue;
        $printer->text(str_pad(Yii::t('app', 'Total Qty'), $charLength - 15, ' '));
                $printer->text(' : ');
        $printer->text(str_pad(number_format($totalQtySales, 0, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
                STR_PAD_LEFT));
        $printer->text(str_pad(Yii::t('app', 'Total Value'), $charLength - 15, ' '));
                $printer->text(' : ');
        $printer->text(str_pad(number_format($totalValueSales, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator"), 12, ' ',
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
            $printer->feed(1);
        }

        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }

        $this->printLableTrialMode();
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
        $printerType = $this->stationModel->printerTypeID;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');

        if (isset($trialMode)) {
            if ($trialMode->value1 == 1) {
                if ($printerType == 3 || $printerType == 4) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                } else if ($printerType != 15) {
                    $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                }

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }

                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));
                $printer->text(' TRIAL MODE ');
                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(2);
                }
            };
        }
    }
}
