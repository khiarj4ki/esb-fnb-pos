<?php
namespace app\models\forms;


use app\components\BixolonStickerPrinter;
use app\components\BrotherStickerPrinter;
use app\components\GStickerPrinter;
use app\components\ExtPrinter;
use app\components\SatoStickerPrinter;
use app\components\StickerPrinter;
use app\components\ZebraStickerPrinter;
use app\models\Branch;
use app\models\BranchMenu;
use app\models\MapBranchVisitPurpose;
use app\models\Menu;
use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\MenuExtra;
use app\models\MenuTemplateDetail;
use app\models\MenuTemplateHead;
use app\models\Setting;
use app\models\SpecialPriceDay;
use app\models\SpecialPriceHead;
use app\models\SpecialPriceMenu;
use app\models\SpecialPriceTime;
use app\models\Station;
use app\models\VisitPurpose;
use Exception;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;

/**
 * @property array $stationIDs
 */
class PrinterTest extends Model {
    const SCENARIO_PRINT_TEST = 'print test';
    const SCENARIO_OPEN_DRAWER = 'open drawer';
    const SCENARIO_TEST_PRINT_BILL = 'test print bill';
    const SCENARIO_TEST_PRINT_MENU = 'test print menu';

    public $stationIDs;
    public $enabledImage;
    public $visitPurposeID;
    public $type;
    public $settings;
    public $printer;
    public $printResult;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['stationIDs', 'visitPurposeID', 'type'], 'safe']
        ];
    }

    public function scenarios() {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_PRINT_TEST] = ['stationIDs'];
        $scenarios[self::SCENARIO_OPEN_DRAWER] = ['stationIDs'];
        $scenarios[self::SCENARIO_TEST_PRINT_BILL] = ['stationIDs'];
        $scenarios[self::SCENARIO_TEST_PRINT_MENU] = ['stationIDs'];

        return $scenarios;
    }

    public function runTest() {
        $stationModel = Station::findActive()
            ->andWhere(['IN', 'stationID', $this->stationIDs])
            ->all();
        if (!$stationModel) {
            return false;
        }

        $errorPrintResult = [];
        if ($this->scenario == self::SCENARIO_TEST_PRINT_MENU) {
            $this->printTestMenu($this->visitPurposeID, $this->type);
        } else {
            foreach ($stationModel as $station) {
                try {
                    if ($this->scenario == self::SCENARIO_PRINT_TEST) {
                        Logging::save('-', Logging::PRINT_TEST, $station);
                        $printTest = $this->printTest($station);
                        if (!$printTest['status']) {
                            $errorPrintResult[] = $station->stationName;
                        }
                    } else if ($this->scenario == self::SCENARIO_TEST_PRINT_BILL) {
                        Logging::save('-', Logging::PRINT_TEST, $station);
                        $this->printTestBill($station, $this->visitPurposeID, $this->type);
                    } else {
                        Logging::save('-', Logging::OPEN_DRAWER, $station);
                        $this->openDrawer($station);
                    }
                } catch (Exception $ex) {
                    Yii::warning($ex);
                }
            }
        }
        $errorStations = implode(', ', $errorPrintResult);
        if ($errorStations) {
            $this->printResult = ['status' => false, 'message' => $errorStations];
        } else {
            $this->printResult = ['status' => true, 'message' => null];
        }
    }

    public function openDrawer($stationModel) {
        try {
            $connector = Station::getConnectorByModel($stationModel, 'OpenDrawer', false);
            $printer = new ExtPrinter($connector);

            if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x07" . "\x1C");
            } else {
                $printer->pulse();
            }

            $printer->close();
        } catch (Exception $ex) {
            
        }
    }

    private function printTest($stationModel) {
        try {
            $charLength = $stationModel->characterPerLine;
            $stationName = $stationModel->stationName;
            if ($stationModel->printerTypeID == '2') {
                $host = $stationModel->printerName;
                $printer = new StickerPrinter($host);
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB ';
                $printer->addLine($text1, 9);
                $printer->addBlankLine();
                $printer->addLine($header, 9);
                $printer->addBlankLine();
                $printer->addBlankLine();
                $printer->addLine(date('d-M-Y H:i:s'), 7);
                $printer->sendToPrinter();
            } else if ($stationModel->printerTypeID == '7') {
                $host = $stationModel->printerName;
                $connectionType = $stationModel->printerConnectionID;
                $printer = new GStickerPrinter($host, $connectionType, $stationModel);
                $printer->clear();
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB ';
                $printer->write($this->getNumbers($charLength), 2);
                $printer->write(str_pad('', $charLength, '-'), 2);
                $printer->write($text1, 2);
                $printer->write($header, 2);
                $printer->write(str_pad('', $charLength, '-'), 2);
                $printer->write($this->getNumbers($charLength), 2);
                $printer->closeWrite();
                $printer->close(); 
            } else if ($stationModel->printerTypeID == '8') {
                $host = $stationModel->printerName;
                $connectionType = $stationModel->printerConnectionID;
                $printer = new SatoStickerPrinter($host, $connectionType, $stationModel);
                $printer->clear();
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB ';
                $printer->write($this->getNumbers($charLength));
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($text1);
                $printer->write($header);
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($this->getNumbers($charLength));
                $printer->closeWrite();
                $printer->close();
            } else if ($stationModel->printerTypeID == '9') {
                $host = $stationModel->printerName;
                $connectionType = $stationModel->printerConnectionID;
                $printer = new ZebraStickerPrinter($host, $connectionType, $stationModel);
                $printer->clear();
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB ';
                $printer->write($this->getNumbers($charLength));
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($text1);
                $printer->write($header);
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($this->getNumbers($charLength));
                $printer->closeWrite();
                $printer->close();
            } else if ($stationModel->printerTypeID == '10') {
                $host = $stationModel->printerName;
                $connectionType = $stationModel->printerConnectionID;
                $printer = new BixolonStickerPrinter($host, $connectionType, $stationModel);
                $printer->clear();
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB';
                $printer->write($this->getNumbers($charLength));
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($text1);
                $printer->write($header);
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($this->getNumbers($charLength));
                $printer->closeWrite();
                $printer->close();
            } else if ($stationModel->printerTypeID == '11') {
                $host = $stationModel->printerName;
                $connectionType = $stationModel->printerConnectionID;
                $printer = new BrotherStickerPrinter($host, $connectionType, $stationModel);
                $printer->clear();
                $text1 = ' ' . $stationName . ' ';
                $header = ' PRINTER TEST FOR ESB ';
                $printer->write($this->getNumbers($charLength));
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($text1);
                $printer->write($header);
                $printer->write(str_pad('', $charLength, '-'));
                $printer->write($this->getNumbers($charLength));
                $printer->closeWrite();
                $printer->close();
            } else {
                $connector = Station::getConnectorByModel($stationModel, ' ',
                        false);

                $printResult = ['status' => true, 'message' => null];
                if ($connector !== null) {
                    $printer = new ExtPrinter($connector);
    
                    $this->enabledImage = TRUE;
                    // @Notes: Printer Type 1:Thermal, 2:Sticker, 3:Dot Matrix, 4:MPOP, 6:Epson Sticker
                    // @Notes: Printer Connection 1:Network, 2:Windows, 3:Android
                    if (($stationModel->printerTypeID == 1 && $stationModel->printerConnectionID == 3) || $stationModel->printerTypeID == 2 || 
                        $stationModel->printerTypeID == 6) {
                        $this->enabledImage = FALSE;
                    }
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
                                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                                } else {
                                    $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
                                }
    
                                if ($stationModel->printerTypeID == '3') {
                                    $printer->bitImageColumnFormat($img,
                                    ExtPrinter::IMG_DOUBLE_WIDTH | ExtPrinter::IMG_DOUBLE_HEIGHT);
                                } elseif ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                                    $printer->bitImageMpop($img);
                                } else {
                                    $printer->bitImage($img);
                                }
    
                                if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                                    $printer->getPrintConnector()->write("\x0A");
                                } else {
                                    $printer->feed(1);
                                }
                            }
                        }
                    }
    
                    $printer->text($this->getNumbers($charLength));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $printer->text(str_pad('', $charLength, '-'));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $text1 = ' ' . $stationName . ' ';
                    $pad1Left = floor(($charLength - strlen($text1)) / 2);
                    $pad1Right = $charLength - $pad1Left - strlen($text1);
                    $printer->text(str_pad('', $pad1Left, '='));
                    $printer->text($text1);
                    $printer->text(str_pad('', $pad1Right, '='));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $text2 = ' PRINTER TEST FOR ESB ';
                    $pad2Left = floor(($charLength - strlen($text2)) / 2);
                    $pad2Right = $charLength - $pad2Left - strlen($text2);
                    $printer->text(str_pad('', $pad2Left, '='));
                    $printer->text($text2);
                    $printer->text(str_pad('', $pad2Right, '='));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $text3 = ' ' . date('d-M-Y H:i:s') . ' ';
                    $pad3Left = floor(($charLength - strlen($text3)) / 2);
                    $pad3Right = $charLength - $pad3Left - strlen($text3);
                    $printer->text(str_pad('', $pad3Left, '='));
                    $printer->text($text3);
                    $printer->text(str_pad('', $pad3Right, '='));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $printer->text(str_pad('', $charLength, '-'));
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
    
                    $printer->text($this->getNumbers($charLength));
    
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13' || $stationModel->printerTypeID == '15') {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(2);
                    }
    
                    if ($stationModel->printerTypeID == '4' || $stationModel->printerTypeID == '13') {
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
    
                    $printer->close();
                } else {
                    Logging::save('-', Logging::FAIL_OPEN_PRINTER, $stationModel);
                    $printResult['status'] = false;
                }
            }
        } catch (Exception $ex) {
            
        }
        return $printResult;
    }

    private function printTestBill($stationModel, $visitPurposeID, $type) {
        $this->settings = Setting::getPrintingSettings();
        $otherVat = Setting::getOtherVat();
        $taxName = Setting::getValue1('POS', 'Tax Text');
        $vatName = Branch::getVatName();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();

        $mapBranchVisitPurposeModel = (new Query)
            ->select([
                'mbvp.menuTemplateID',
                'mbvp.additionalTaxValue',
                'mbvp.taxValue',
                'mbvp.flagOtherTaxVat',
                'mbvp.vatSubject',
                'mth.flagInclusive'
            ])
            ->from(['mbvp' => MapBranchVisitPurpose::tableName()])
            ->innerJoin(['mth' => MenuTemplateHead::tableName()],  'mth.menuTemplateID = mbvp.menuTemplateID')
            ->where(['=', 'branchID', $branchID])
            ->andWhere(['=', 'visitPurposeID', $visitPurposeID])
            ->one();

        if ($mapBranchVisitPurposeModel && $mapBranchVisitPurposeModel['menuTemplateID']) {
            $menuTemplateID = $mapBranchVisitPurposeModel['menuTemplateID'];
            $additionalTaxValue = $mapBranchVisitPurposeModel['additionalTaxValue'];
            $taxValue = $mapBranchVisitPurposeModel['taxValue'];
            $flagOtherTaxVat = $mapBranchVisitPurposeModel['flagOtherTaxVat'];
            $vatSubject = $mapBranchVisitPurposeModel['vatSubject'];
            $flagInclusive = $mapBranchVisitPurposeModel['flagInclusive'];
        }

        $menuCategoryModel = (new Query)
            ->select([
                'ms_menucategorydetail.ID',
                'ms_menucategorydetail.menuCategoryDetailDesc',
                'ms_menucategory.menuCategoryID',
                'ms_menucategory.menuCategoryDesc',
            ])
            ->from("ms_menutemplatecategorydetail")
            ->innerJoin(
                "ms_menucategorydetail",
                "ms_menucategorydetail.ID = ms_menutemplatecategorydetail.menuCategoryDetailID"
            )
            ->innerJoin(
                "ms_menutemplatecategory",
                "ms_menutemplatecategory.menuCategoryID = ms_menucategorydetail.menuCategoryID"
            )
            ->innerJoin(
                "ms_menucategory",
                "ms_menucategory.menuCategoryID = ms_menucategorydetail.menuCategoryID"
            )
            ->where(['ms_menutemplatecategorydetail.menuTemplateID' => $menuTemplateID])
            ->andWhere(['ms_menutemplatecategory.menuTemplateID' => $menuTemplateID])
            ->andWhere(['ms_menucategorydetail.flagActive' => 1])
            ->orderBy([
                'menuCategoryDetailDesc' => SORT_ASC,
                'menuCategoryDesc' => SORT_ASC,
            ])
            ->groupBy([
                'ms_menucategorydetail.ID',
                'ms_menucategorydetail.menuCategoryDetailDesc',
                'ms_menucategory.menuCategoryID',
                'ms_menucategory.menuCategoryDesc',
            ])
            ->all();

        $menuTemplateModel = MenuTemplateDetail::find()
            ->select([
                'ms_menu.menuCategoryDetailID',
                'ms_menu.menuID',
                'ms_menu.menuShortName',
                'ms_menu.flagTax',
                'ms_menu.flagSeparateTaxCalculation',
                'price' => new Expression('IFNULL(ms_menutemplatedetail.price, ms_menu.price)'),
                'orderID' => new Expression('IFNULL(ms_menutemplatedetail.orderID, 0)'),
                'ms_menugroup.menuGroup',
                'menuGroupOrderID' => new Expression('IFNULL(ms_menugroup.orderID, 0)'),
                'ms_menugroup.menuGroupID',
            ])
            ->joinWith('menu.menuCategoryDetail')
            ->joinWith('menu.activeMenuGroups')
            ->where(['menuTemplateID' => $menuTemplateID])
            ->andWhere(['ms_menutemplatedetail.flagActive' => 1])
            ->andWhere(['ms_menu.flagActive' => 1]);

        $menuModel = (new Query)
            ->select([
                'data.*',
            ])
            ->from(['data' => $menuTemplateModel])
            ->orderBy([
                'data.menuCategoryDetailID' => SORT_ASC,
                'data.orderID' => SORT_ASC,
                'data.menuGroupOrderID' => SORT_ASC,
            ])
            ->all();

        $menuPackageModel = (new Query)
            ->select([
                'data.menuID',
                'data.menuGroupID',
                'data.menuGroup',
                'data.menuGroupOrderID',
                'ms_menu.flagTax',
                'data.flagSeparateTaxCalculation',
                'menuPackageMenuID' => new Expression('ms_menu.menuID'),
                'menuPackageMenuName' => new Expression('ms_menu.menuShortName'),
                'menuPackageOrderID' => new Expression('IFNULL(ms_menupackage.orderID, 0)'),
                'menuPackagePrice' => new Expression('IFNULL(ms_menupackage.price, ms_menu.price)'),
            ])
            ->from(['data' => $menuTemplateModel])
            ->leftJoin(
                "ms_menupackage",
                "ms_menupackage.menuGroupID = data.menuGroupID"
            )
            ->leftJoin(
                "ms_menu",
                "ms_menu.menuID = ms_menupackage.menuID"
            )
            ->orderBy([
                'data.menuCategoryDetailID' => SORT_ASC,
                'data.orderID' => SORT_ASC,
                'data.menuGroupOrderID' => SORT_ASC,
                'ms_menupackage.orderID' => SORT_ASC,
            ])
            ->all();

        $menuExtraModel = (new Query)
            ->select([
                'menuExtra.menuExtraID',
                'menuExtra.menuID',
                'menuExtra.menuExtraShortName',
                'menuExtra.price',
                'ms_menu.flagTax'
            ])
            ->from(['menuExtra' => MenuExtra::tableName()])
            ->innerJoin(["ms_menu" => $menuTemplateModel], "ms_menu.menuID = menuExtra.menuID")
            ->where(['flagActive' => 1])
            ->orderBy(['menuExtra.menuID' => SORT_ASC, 'menuExtra.menuExtraID' => SORT_ASC])
            ->all();
          
        $specialPriceMenuModel = SpecialPriceHead::find()
            ->select(['menuID', 'price'])
            ->from(SpecialPriceHead::tableName())
            ->leftJoin(
                SpecialPriceMenu::tableName(),
                "ms_specialpricemenu.specialPriceID = ms_specialpricehead.specialPriceID"
            )
            ->leftJoin(
                SpecialPriceDay::tableName(),
                "ms_specialpriceday.specialPriceID = ms_specialpricehead.specialPriceID"
            )
            ->leftJoin(
                SpecialPriceTime::tableName(),
                "ms_specialpricetime.specialPriceID = ms_specialpricehead.specialPriceID"
            )
            ->where(['ms_specialpricehead.menuTemplateID' => $menuTemplateID])
            ->andWhere(['ms_specialpricehead.flagActive' => 1])
            ->andWhere('CURRENT_DATE() BETWEEN startDate AND endDate')
            ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
            ->andWhere([
                'or',
                'startTime IS NULL AND endTime IS NULL',
                'TIME(NOW()) BETWEEN startTime AND endTIme'
            ])
            ->asArray()
            ->all();

        $specialPriceMenuArray = [];
        if ($specialPriceMenuModel) {
            foreach ($specialPriceMenuModel as $perSpecialPrice) {
                $specialPriceMenuArray[$perSpecialPrice['menuID']] = $perSpecialPrice['price'];
            }
        }

        $menuCategoryArray = [];
        foreach ($menuCategoryModel as $menuCategory) {
            if ($type === 'category') {
                $menuCategoryArray[$menuCategory['menuCategoryID']]['menuCategoryID'] = $menuCategory['menuCategoryID'];
                $menuCategoryArray[$menuCategory['menuCategoryID']]['menuCategoryDesc'] = substr($menuCategory['menuCategoryDesc'], 0, 40);
            } else {
                $menuCategoryArray[$menuCategory['ID']]['menuCategoryDetailID'] = $menuCategory['ID'];
                $menuCategoryArray[$menuCategory['ID']]['menuCategoryDetailDesc'] = substr($menuCategory['menuCategoryDetailDesc'], 0, 40);
            }

            $menuArray = [];
            foreach ($menuModel as $menu) {
                if ($menu['menuCategoryDetailID'] == $menuCategory['ID'] && !isset($menuArray[$menu['menuID']])) {
                    $menuPrice = isset($specialPriceMenuArray[$menu['menuID']]) ? intval($specialPriceMenuArray[$menu['menuID']]) : intval($menu['price']);
                    $menuArray[$menu['menuID']] = [
                        'menuID' => (string)$menu['menuID'],
                        'menuName' => substr($menu['menuShortName'], 0, 40),
                        'menuPrice' => $menuPrice,
                        'menuFlagTax' => $menu['flagTax'],
                        'menuFlagSeparateTaxCalculation' => $menu['flagSeparateTaxCalculation'],
                    ];

                    $menuPackageArray = [];
                    foreach ($menuPackageModel as $menuPackage) {
                        if ($menuPackage['menuID'] == $menu['menuID'] && $menuPackage['menuGroupID'] != null && $menuPackage['menuPackageMenuID'] != null) {
                            $menuPackagePrice = intval($menuPackage['menuPackagePrice']);
                            $menuPackageArray[$menuPackage['menuGroupID']]['packageItems'][] = [
                                "menuPackageID" => (string)$menuPackage['menuPackageMenuID'],
                                'menuPackageName' => substr($menuPackage['menuPackageMenuName'], 0, 40),
                                "menuPackagePrice" => $menuPackagePrice,
                                "menuPackageFlagTax" => intval($menuPackage['flagTax']),
                                "menuPackageflagSeparateTaxCalculation" => intval($menuPackage['flagSeparateTaxCalculation']),
                            ];
                        }

                    }
                    foreach ($menuPackageArray as $menuPackage) {
                        $menuArray[$menu['menuID']]['packageGroups'][] = $menuPackage;
                    }

                    $menuExtraArray = [];
                    foreach ($menuExtraModel as $extra) {
                        if ($extra['menuID'] == $menu['menuID']) {
                            $menuExtraPrice = intval($extra['price']);
                            $menuExtraArray[$extra['menuExtraID']] = [
                                "menuExtraID" => (string)$extra['menuExtraID'],
                                "menuExtraName" => substr($extra['menuExtraShortName'], 0, 40),
                                "menuExtraPrice" => $menuExtraPrice,
                                "menuExtraFlagTax" => $extra['flagTax']
                            ];
                        } 
                    }
                    foreach ($menuExtraArray as $menuExtras) {
                        $menuArray[$menu['menuID']]['extras'][] = $menuExtras;
                    }
                }
            }

            foreach ($menuArray as $menu) {
                if ($type == "category") {
                    $menuCategoryArray[$menuCategory['menuCategoryID']]['items'][] = $menu;
                } else {
                    $menuCategoryArray[$menuCategory['ID']]['items'][] = $menu;
                }
            }
        }

        $headMenuTaxAndOtherTax = [];
        $headMenuOtherVat = [];
        $packageMenuTaxAndOtherTax = [];
        $packageMenuOtherVat = [];
        foreach ($menuCategoryArray as $category) {
            if (isset($category['items'])) {
                foreach ($category['items'] as $menu) {
                    if ($menu['menuFlagTax'] == 2) {
                        if ($type === 'category') {
                            $headMenuOtherVat[$menu['menuID']]['menuCategoryID'] = $category['menuCategoryID'];
                            $headMenuOtherVat[$menu['menuID']]['menuCategoryDesc'] = $category['menuCategoryDesc'];
                        } else {
                            $headMenuOtherVat[$menu['menuID']]['menuCategoryDetailID'] = $category['menuCategoryDetailID'];
                            $headMenuOtherVat[$menu['menuID']]['menuCategoryDetailDesc'] = $category['menuCategoryDetailDesc'];
                        }

                        if (isset($menu['packageGroups'])) {
                            foreach ($menu['packageGroups'] as $packageGroup) {
                                foreach ($packageGroup['packageItems'] as $packageItem) {
                                    if (($menu['menuFlagTax'] != $packageItem['menuPackageFlagTax'] && $menu['menuFlagSeparateTaxCalculation'] == 1)) {
                                        if ($type === 'category') {
                                            $packageMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryID'] = $category['menuCategoryID'];
                                            $packageMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDesc'] = $category['menuCategoryDesc'];
                                        } else {
                                            $packageMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDetailID'] = $category['menuCategoryDetailID'];
                                            $packageMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDetailDesc'] = $category['menuCategoryDetailDesc'];
                                        }

                                        $packageMenuTaxAndOtherTax[$menu['menuID']]['items'][] = [
                                            'menuID' => (string)$packageItem['menuPackageID'],
                                            'menuName' => substr($packageItem['menuPackageName'], 0, 40),
                                            'menuPrice' => $packageItem['menuPackagePrice'],
                                            'menuFlagTax' => $packageItem['menuPackageFlagTax'],
                                            'menuFlagSeparateTaxCalculation' => $packageItem['menuPackageflagSeparateTaxCalculation'],
                                            "flagNotOrignMenu" => true,
                                        ];
                                    }
                                }
                            }
                        }
                    
                        $headMenuOtherVat[$menu['menuID']]['items'][] = $menu;
                    } else {
                        if ($type === 'category') {
                            $headMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryID'] = $category['menuCategoryID'];
                            $headMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDesc'] = $category['menuCategoryDesc'];
                        } else {
                            $headMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDetailID'] = $category['menuCategoryDetailID'];
                            $headMenuTaxAndOtherTax[$menu['menuID']]['menuCategoryDetailDesc'] = $category['menuCategoryDetailDesc'];
                        }

                        if (isset($menu['packageGroups'])) {
                            foreach ($menu['packageGroups'] as $packageGroup) {
                                foreach ($packageGroup['packageItems'] as $packageItem) {
                                    if (
                                        $menu['menuFlagTax'] != $packageItem['menuPackageFlagTax'] && 
                                        $menu['menuFlagSeparateTaxCalculation'] == 1 && 
                                        $packageItem['menuPackageFlagTax'] == 2
                                    ) {
                                        if ($type === 'category') {
                                            $packageMenuOtherVat[$menu['menuID']]['menuCategoryID'] = $category['menuCategoryID'];
                                            $packageMenuOtherVat[$menu['menuID']]['menuCategoryDesc'] = $category['menuCategoryDesc'];
                                        } else {
                                            $packageMenuOtherVat[$menu['menuID']]['menuCategoryDetailID'] = $category['menuCategoryDetailID'];
                                            $packageMenuOtherVat[$menu['menuID']]['menuCategoryDetailDesc'] = $category['menuCategoryDetailDesc'];
                                        }

                                        $packageMenuOtherVat[$menu['menuID']]['items'][] = [
                                            'menuID' => (string)$packageItem['menuPackageID'],
                                            'menuName' => substr($packageItem['menuPackageName'], 0, 40),
                                            'menuPrice' => $packageItem['menuPackagePrice'],
                                            'menuFlagTax' => $packageItem['menuPackageFlagTax'],
                                            'menuFlagSeparateTaxCalculation' => $packageItem['menuPackageflagSeparateTaxCalculation'],
                                            "flagNotOrignMenu" => true,
                                        ];
                                    }
                                }
                            }
                        }

                        $headMenuTaxAndOtherTax[$menu['menuID']]['items'][] = $menu;
                    }
                }
            }
        }

        $menuWithTaxAndOtherTax = array_merge($packageMenuTaxAndOtherTax, $headMenuTaxAndOtherTax);
        $menuWithOtherVat = array_merge($packageMenuOtherVat, $headMenuOtherVat);

        // 1: Thermal Printer, 3: Dot Matrix Printer, 4: Star mPOP Printer, 5: Thermal CX-58D, 10: Bixolon Printer, 15: Star TSP 650
        if (in_array($stationModel->printerTypeID, [1, 3, 4, 5, 10, 15])) {
            $this->runTestPrintBillWithTaxAndOtherTax(
                $menuCategoryArray,
                $menuWithTaxAndOtherTax, 
                $stationModel, 
                $type, 
                $visitPurposeID, 
                $additionalTaxValue, 
                $taxValue, 
                $taxName,
                $vatName,
                $flagOtherTaxVat, 
                $flagInclusive, 
                $branchModel->additionalTaxName,
                $vatSubject,
                $otherVat
            );
    
            $this->runTestPrintBillWithOtherVat(
                $menuCategoryArray,
                $menuWithOtherVat, 
                $stationModel, 
                $type, 
                $visitPurposeID, 
                $additionalTaxValue, 
                $taxValue, 
                $taxName,
                $vatName,
                $flagOtherTaxVat, 
                $flagInclusive, 
                $branchModel->additionalTaxName,
                $vatSubject,
                $otherVat
            );
        }
    }

    private function runTestPrintBillWithTaxAndOtherTax(
        $menuCategoryArray, 
        $menuWithTaxAndOtherTax, 
        $stationModel, 
        $type, 
        $visitPurposeID, 
        $additionalTaxValue, 
        $taxValue, 
        $taxName, 
        $vatName, 
        $flagOtherTaxVat, 
        $flagInclusive, 
        $additionalTaxName, 
        $vatSubject, 
        $otherVat
    ) {
        $connector = Station::getConnectorByModel($stationModel, ' ', false);
        $this->printer = new ExtPrinter($connector);
        $printer = $this->printer;
        $charLength = $stationModel->characterPerLine;
        
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $roundingMode = isset($this->settings['Rounding Mode']) ? $this->settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($this->settings['Rounding Nearest Value']) ? $this->settings['Rounding Nearest Value'] : 0;

        $this->printTestBillHeader($stationModel, $visitPurposeID, $additionalTaxValue, $taxValue, $flagOtherTaxVat);
        
        $flagTax = 0;
        $lastCategoryType = '';
        $subTotal = 0;
        $roundingValue = 0;
        $otherTaxValue = null;
        $vatValue = null;
        $otherVatValue = null;
        $menuCategoryNotOrignArr = [];
        $temporPrice = 0;

        foreach($menuWithTaxAndOtherTax as $menuData) {
            if ($type === 'category') {
                if ($lastCategoryType != $menuData['menuCategoryDesc']) {
                    $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceTaxAndOtherTax'] = 0;
                    $lastCategoryType = $menuData['menuCategoryDesc'];
                    $printer->text(str_pad($menuData['menuCategoryDesc'], $charLength - 14, ' '));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if (isset($menu['flagNotOrignMenu'])) {
                        $lastCategoryType = $menuData['menuCategoryDesc'];
                        $printer->text(str_pad($menuData['menuCategoryDesc'], $charLength - 14, ' '));
                        $this->printLineBreak(null, 1, $stationModel);
                    }
                }
            } else {
                if ($lastCategoryType != $menuData['menuCategoryDetailDesc']) {
                    $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceTaxAndOtherTax'] = 0;
                    $lastCategoryType = $menuData['menuCategoryDetailDesc'];
                    $printer->text(str_pad($menuData['menuCategoryDetailDesc'], $charLength - 14, ' '));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if (isset($menu['flagNotOrignMenu'])) {
                        $lastCategoryType = $menuData['menuCategoryDetailDesc'];
                        $printer->text(str_pad($menuData['menuCategoryDetailDesc'], $charLength - 14, ' '));
                        $this->printLineBreak(null, 1, $stationModel);
                    }
                }
            }

            if (isset($menuData['items'])) {
                foreach ($menuData['items'] as $menu) {
                    $flagTax = $menu['menuFlagTax'];
                    $flagSeparateTaxCalculation = $menu['menuFlagSeparateTaxCalculation'];
                    $subTotal += $menu['menuPrice'];
                    
                    $isApplyOtherVat = ($vatSubject == 1 && (isset($flagTax) && $flagTax == 2));
                    if ($isApplyOtherVat) {
                        $newVatValue = $otherVat ? $otherVat : 0;
                    } else {
                        $newVatValue = ($flagTax == 1) ? $taxValue : 0;
                    }
                    
                    if ($flagInclusive == 1) {
                        if ($flagOtherTaxVat == 1) {
                            $netPrice = ($menu['menuPrice'] * 100 / (100 + $newVatValue) * 100 / (100 + $additionalTaxValue));
                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                            }
                        } else { 
                            $netPrice = ($menu['menuPrice'] * 100 / (100 + $newVatValue + $additionalTaxValue));
                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice * $newVatValue / 100);
                            }
                        }
                    } else {
                        $netPrice = $menu['menuPrice'];
                        $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                        if ($flagOtherTaxVat == 1) {
                            if ($isApplyOtherVat) {
                                $otherVatValue += (($netPrice + $otherTaxValue) * $newVatValue / 100);
                            } else {
                                $vatValue += (($netPrice + $otherTaxValue) * $newVatValue / 100);
                            }
                        } else { 
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice * $newVatValue / 100);
                            }
                        }
                    }

                    $tempMenuName = isset($menu['flagNotOrignMenu']) ? '  ' . $menu['menuName'] : $menu['menuName'];
                    $menuName = $charLength > 32 ? substr($tempMenuName, 0, 34) : substr($tempMenuName, 0, 18);
                    $printer->text(str_pad($menuName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $menu['menuPrice'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                    
                     if ($type === "category") {
                        if (isset($menu['flagNotOrignMenu'])) {
                            $menuCategoryNotOrignArr[$menuData['menuCategoryID']] = [
                                'menuCategoryID' => $menuData['menuCategoryID'],
                                'menuPrice' => $temporPrice += $menu['menuPrice']
                            ];
                        }
                        $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceTaxAndOtherTax'] += $menu['menuPrice'];
                    } else {
                        if (isset($menu['flagNotOrignMenu'])) {
                            $menuCategoryNotOrignArr[$menuData['menuCategoryDetailID']] = [
                                'menuCategoryDetailID' => $menuData['menuCategoryDetailID'],
                                'menuPrice' => $temporPrice += $menu['menuPrice']
                            ];
                        }
                        $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceTaxAndOtherTax'] += $menu['menuPrice'];
                    }

                    if (isset($menu['packageGroups'])) {
                        foreach ($menu['packageGroups'] as $packageGroup) {
                            foreach ($packageGroup['packageItems'] as $packageItem) {
                                if ($flagTax == $packageItem['menuPackageFlagTax'] || $packageItem['menuPackageFlagTax'] != 2) {
                                    $flagTaxPackage = $packageItem['menuPackageFlagTax'];
                                    $subTotal += $packageItem['menuPackagePrice'];
                                    
                                    if ($flagSeparateTaxCalculation == 0) {
                                        $flagTaxPackage = $flagTax;
                                    }
    
                                    $isApplyOtherVat = ($vatSubject == 1 && (isset($flagTaxPackage) && $flagTaxPackage == 2));
                                    if ($isApplyOtherVat) {
                                        $newPckVatValue = $otherVat ? $otherVat : 0;
                                    } else {
                                        $newPckVatValue  = ($flagTaxPackage == 1) ? $taxValue : 0;
                                    }
    
                                    if ($flagInclusive == 1) {
                                        if ($flagOtherTaxVat == 1) {
                                            $netPrice = ($packageItem['menuPackagePrice'] * 100 / (100 + $newPckVatValue ) * 100 / (100 + $additionalTaxValue));
                                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                            if ($isApplyOtherVat && $flagSeparateTaxCalculation == 0) {
                                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            }
                                        } else { 
                                            $netPrice = ($packageItem['menuPackagePrice'] * 100 / (100 + $newPckVatValue  + $additionalTaxValue));
                                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                            if ($isApplyOtherVat && $flagSeparateTaxCalculation == 0) {
                                                $otherVatValue += ($netPrice * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice * $newPckVatValue / 100);
                                            }
                                        }
                                    } else {
                                        $netPrice = $packageItem['menuPackagePrice'];
                                        $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                        if ($flagOtherTaxVat == 1) {
                                            if ($isApplyOtherVat && $flagSeparateTaxCalculation == 0) {
                                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            }
                                        } else { 
                                            if ($isApplyOtherVat && $flagSeparateTaxCalculation == 0) {
                                                $otherVatValue += ($netPrice * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice * $newPckVatValue / 100);
                                            }
                                        }
                                    }
    
                                    $menuPackageName = $charLength > 32 ? substr($packageItem['menuPackageName'], 0, 34) : substr($packageItem['menuPackageName'], 0, 16);
                                    $printer->text(str_pad("  " . $menuPackageName, $charLength - 14, ' '));
                                    $printer->text(str_pad(number_format(
                                        $packageItem['menuPackagePrice'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator"
                                    ), 14, ' ', STR_PAD_LEFT));
                                    $this->printLineBreak(null, 1, $stationModel);
    
                                    if ($type === "category") {
                                        $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceTaxAndOtherTax'] += $packageItem['menuPackagePrice'];
                                    } else {
                                        $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceTaxAndOtherTax'] += $packageItem['menuPackagePrice'];
                                    }
                                } else {
                                    continue;
                                }
                            }
                        }
                    }

                    if (isset($menu['extras'])) {
                        foreach ($menu['extras'] as $extraItem) {
                            $subTotal += $extraItem['menuExtraPrice'];
                            $menuExtraFlagTax = $extraItem['menuExtraFlagTax'];
                            $isApplyOtherVat = ($vatSubject == 1 && (isset($menuExtraFlagTax) && $menuExtraFlagTax == 2));

                            if ($flagInclusive == 1) {
                                if ($flagOtherTaxVat == 1) {
                                    $netPrice = ($extraItem['menuExtraPrice'] * 100 / (100 + $newVatValue) * 100 / (100 + $additionalTaxValue));
                                    $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    }
                                } else { 
                                    $netPrice = ($extraItem['menuExtraPrice'] * 100 / (100 + $newVatValue + $additionalTaxValue));
                                    $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice * $newVatValue / 100);
                                    }
                                }
                            } else {
                                $netPrice = $extraItem['menuExtraPrice'];
                                $otherTaxValue += (($netPrice * $additionalTaxValue) / 100);
                                if ($flagOtherTaxVat == 1) {
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    }
                                } else { 
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice * $newVatValue / 100);
                                    }
                                }
                            }

                            $menuExtraName = $charLength > 32 ? substr($extraItem['menuExtraName'], 0, 34) : substr($extraItem['menuExtraName'], 0, 14);
                            $printer->text(str_pad("    " . $menuExtraName, $charLength - 14, ' '));
                            $printer->text(str_pad(number_format(
                                $extraItem['menuExtraPrice'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ), 14, ' ', STR_PAD_LEFT));
                            $this->printLineBreak(null, 1, $stationModel);

                            if ($type === "category") {
                                $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceTaxAndOtherTax'] += $extraItem['menuExtraPrice'];
                            } else {
                                $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceTaxAndOtherTax'] += $extraItem['menuExtraPrice'];
                            }
                        }
                    }
                }
            }
        }
        
        $this->printLineBreak("-", 1, $stationModel);
        foreach ($menuCategoryArray as $categoryType) {
            if (isset($categoryType['totalPriceTaxAndOtherTax'])) {
                if ($type === 'category') {
                    if(isset($menuCategoryNotOrignArr)) {
                        foreach($menuCategoryNotOrignArr as $menuCategory) {
                            if (($categoryType['menuCategoryID'] == $menuCategory['menuCategoryID']) && count($menuCategoryArray) == 1) {
                                continue;
                            } else {
                                if ($categoryType['menuCategoryID'] == $menuCategory['menuCategoryID']) {
                                    $categoryType['totalPriceTaxAndOtherTax'] += $menuCategory['menuPrice'];
                                }
                            }
                        }
                    }

                    $categoryName = $charLength > 32 ? substr($categoryType['menuCategoryDesc'], 0, 18) : substr($categoryType['menuCategoryDesc'], 0, 12);
                    $printer->text(str_pad("Total " . $categoryName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $categoryType['totalPriceTaxAndOtherTax'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if(isset($menuCategoryNotOrignArr)) {
                        foreach($menuCategoryNotOrignArr as $menuCategory) {
                            if ($menuCategory['menuCategoryDetailID'] && ($categoryType['menuCategoryDetailID'] == $menuCategory['menuCategoryDetailID'])) {
                                $categoryType['totalPriceTaxAndOtherTax'] += $menuCategory['menuPrice'];
                            }
                        }
                    }

                    $categoryDetailName = $charLength > 32 ? substr($categoryType['menuCategoryDetailDesc'], 0, 18) : substr($categoryType['menuCategoryDetailDesc'], 0, 12);
                    $printer->text(str_pad("Total " . $categoryDetailName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $categoryType['totalPriceTaxAndOtherTax'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                }
            }
        }
        $this->printLineBreak("-", 1, $stationModel);

        if ($roundingNearestValue != 0) {
            if ($roundingMode == 'DOWN') {
                $roundingValue = $subTotal - (floor($subTotal / $roundingNearestValue) * $roundingNearestValue);
            } elseif ($roundingMode == 'UP') {
                $roundingValue = $subTotal - (ceil($subTotal / $roundingNearestValue) * $roundingNearestValue);
            } elseif ($roundingMode == 'AUTO') {
                $roundingValue = $subTotal - ROUND($subTotal / $roundingNearestValue) * $roundingNearestValue;
            }
        }

        $this->printTestBillFooter(
            $stationModel, 
            $subTotal, 
            $roundingValue, 
            $otherTaxValue, 
            $vatValue, 
            $otherVatValue, 
            $flagInclusive, 
            $taxName, 
            $vatName, 
            $additionalTaxName, 
            $flagTax
        );

        if ($stationModel->flagAutocut == 1) {
            $printer->cut(Printer::CUT_PARTIAL);
        }
        $printer->close();
    }

    private function runTestPrintBillWithOtherVat(
        $menuCategoryArray, 
        $menuWithOtherVat, 
        $stationModel, 
        $type, 
        $visitPurposeID, 
        $additionalTaxValue, 
        $taxValue, 
        $taxName, 
        $vatName, 
        $flagOtherTaxVat, 
        $flagInclusive, 
        $additionalTaxName, 
        $vatSubject, 
        $otherVat
    ) {
        $connector = Station::getConnectorByModel($stationModel, ' ', false);
        $this->printer = new ExtPrinter($connector);
        $printer = $this->printer;
        $charLength = $stationModel->characterPerLine;
        
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $roundingMode = isset($this->settings['Rounding Mode']) ? $this->settings['Rounding Mode'] : 'AUTO';
        $roundingNearestValue = isset($this->settings['Rounding Nearest Value']) ? $this->settings['Rounding Nearest Value'] : 0;

        $this->printTestBillHeader($stationModel, $visitPurposeID, $additionalTaxValue, $taxValue, $flagOtherTaxVat);
        
        $flagTax = 0;
        $lastCategoryType = '';
        $subTotal = 0;
        $roundingValue = 0;
        $otherTaxValue = 0;
        $vatValue = null;
        $otherVatValue = null;
        $menuCategoryNotOrignArr = [];
        $temporPrice = 0;

        foreach($menuWithOtherVat as $menuData) {
            if ($type === 'category') {
                if ($lastCategoryType != $menuData['menuCategoryDesc']) {
                    $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceOtherTaxVat'] = 0;
                    $lastCategoryType = $menuData['menuCategoryDesc'];
                    $printer->text(str_pad($menuData['menuCategoryDesc'], $charLength - 14, ' '));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if (isset($menu['flagNotOrignMenu'])) {
                        $lastCategoryType = $menuData['menuCategoryDesc'];
                        $printer->text(str_pad($menuData['menuCategoryDesc'], $charLength - 14, ' '));
                        $this->printLineBreak(null, 1, $stationModel);
                    }
                }
            } else {
                if ($lastCategoryType != $menuData['menuCategoryDetailDesc']) {
                    $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceOtherTaxVat'] = 0;
                    $lastCategoryType = $menuData['menuCategoryDetailDesc'];
                    $printer->text(str_pad($menuData['menuCategoryDetailDesc'], $charLength - 14, ' '));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if (isset($menu['flagNotOrignMenu'])) {
                        $lastCategoryType = $menuData['menuCategoryDetailDesc'];
                        $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceOtherTaxVat'] = 0;
                        $printer->text(str_pad($menuData['menuCategoryDetailDesc'], $charLength - 14, ' '));
                        $this->printLineBreak(null, 1, $stationModel);
                    }
                }
            }

            if (isset($menuData['items'])) {
                foreach ($menuData['items'] as $menu) {
                    $flagTax = $menu['menuFlagTax'];
                    $flagSeparateTaxCalculation = $menu['menuFlagSeparateTaxCalculation'];
                    $subTotal += $menu['menuPrice'];
                    
                    $isApplyOtherVat = ($vatSubject == 1 && (isset($flagTax) && $flagTax == 2));
                    if ($isApplyOtherVat) {
                        $newVatValue = $otherVat ? $otherVat : 0;
                    } else {
                        $newVatValue = ($flagTax == 1) ? $taxValue : 0;
                    }
                    
                    if ($flagInclusive == 1) {
                        if ($flagOtherTaxVat == 1) {
                            $netPrice = ($menu['menuPrice'] * 100 / (100 + $newVatValue) * 100 / (100 + $additionalTaxValue));
                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                            }
                        } else { 
                            $netPrice = ($menu['menuPrice'] * 100 / (100 + $newVatValue + $additionalTaxValue));
                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice * $newVatValue / 100);
                            }
                        }
                    } else {
                        $netPrice = $menu['menuPrice'];
                        $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                        if ($flagOtherTaxVat == 1) {
                            if ($isApplyOtherVat) {
                                $otherVatValue += (($netPrice + $otherTaxValue) * $newVatValue / 100);
                            } else {
                                $vatValue += (($netPrice + $otherTaxValue) * $newVatValue / 100);
                            }
                        } else { 
                            if ($isApplyOtherVat) {
                                $otherVatValue += ($netPrice * $newVatValue / 100);
                            } else {
                                $vatValue += ($netPrice * $newVatValue / 100);
                            }
                        }
                    }

                    $tempMenuName = isset($menu['flagNotOrignMenu']) ? '  ' . $menu['menuName'] : $menu['menuName'];
                    $menuName = $charLength > 32 ? substr($tempMenuName, 0, 34) : substr($tempMenuName, 0, 18);
                    $printer->text(str_pad($menuName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $menu['menuPrice'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                    
                    if ($type === "category") {
                        if (isset($menu['flagNotOrignMenu'])) {
                            $menuCategoryNotOrignArr[$menuData['menuCategoryID']] = [
                                'menuCategoryID' => $menuData['menuCategoryID'],
                                'menuPrice' => $temporPrice += $menu['menuPrice']
                            ];
                        }
                        $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceOtherTaxVat'] += $menu['menuPrice'];
                    } else {
                        if (isset($menu['flagNotOrignMenu'])) {
                            $menuCategoryNotOrignArr[$menuData['menuCategoryDetailID']] = [
                                'menuCategoryDetailID' => $menuData['menuCategoryDetailID'],
                                'menuPrice' => $temporPrice += $menu['menuPrice']
                            ];
                        }
                        $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceOtherTaxVat'] += $menu['menuPrice'];
                    }

                    if (isset($menu['packageGroups'])) {
                        foreach ($menu['packageGroups'] as $packageGroup) {
                            foreach ($packageGroup['packageItems'] as $packageItem) {
                                if ($flagTax == $packageItem['menuPackageFlagTax']) {
                                    $flagTaxPackage = $packageItem['menuPackageFlagTax'];
                                    $subTotal += $packageItem['menuPackagePrice'];
                                    
                                    if ($flagSeparateTaxCalculation == 0) {
                                        $flagTaxPackage = $flagTax;
                                    }
                                    
                                    $isApplyOtherVat = ($vatSubject == 1 && (isset($flagTaxPackage) && $flagTaxPackage == 2));
                                    if ($isApplyOtherVat) {
                                        $newPckVatValue = $otherVat ? $otherVat : 0;
                                    } else {
                                        $newPckVatValue  = ($flagTaxPackage == 1) ? $taxValue : 0;
                                    }
    
                                    if ($flagInclusive == 1) {
                                        if ($flagOtherTaxVat == 1) {
                                            $netPrice = ($packageItem['menuPackagePrice'] * 100 / (100 + $newPckVatValue ) * 100 / (100 + $additionalTaxValue));
                                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                            if ($isApplyOtherVat) {
                                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            }
                                        } else { 
                                            $netPrice = ($packageItem['menuPackagePrice'] * 100 / (100 + $newPckVatValue  + $additionalTaxValue));
                                            $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                            if ($isApplyOtherVat) {
                                                $otherVatValue += ($netPrice * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice * $newPckVatValue / 100);
                                            }
                                        }
                                    } else {
                                        $netPrice = $packageItem['menuPackagePrice'];
                                        $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                        if ($flagOtherTaxVat == 1) {
                                            if ($isApplyOtherVat) {
                                                $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newPckVatValue / 100);
                                            }
                                        } else { 
                                            if ($isApplyOtherVat) {
                                                $otherVatValue += ($netPrice * $newPckVatValue / 100);
                                            } else {
                                                $vatValue += ($netPrice * $newPckVatValue / 100);
                                            }
                                        }
                                    }
    
                                    $menuPackageName = $charLength > 32 ? substr($packageItem['menuPackageName'], 0, 34) : substr($packageItem['menuPackageName'], 0, 16);
                                    $printer->text(str_pad("  " . $menuPackageName, $charLength - 14, ' '));
                                    $printer->text(str_pad(number_format(
                                        $packageItem['menuPackagePrice'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator"
                                    ), 14, ' ', STR_PAD_LEFT));
                                    $this->printLineBreak(null, 1, $stationModel);
    
                                    if ($type === "category") {
                                        $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceOtherTaxVat'] += $packageItem['menuPackagePrice'];
                                    } else {
                                        $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceOtherTaxVat'] += $packageItem['menuPackagePrice'];
                                    }
                                } else {
                                    continue;
                                }
                            }
                        }
                    }

                    if (isset($menu['extras'])) {
                        foreach ($menu['extras'] as $extraItem) {
                            $subTotal += $extraItem['menuExtraPrice'];
                            $menuExtraFlagTax = $extraItem['menuExtraFlagTax'];
                            $isApplyOtherVat = ($vatSubject == 1 && (isset($menuExtraFlagTax) && $menuExtraFlagTax == 2));

                            if ($flagInclusive == 1) {
                                if ($flagOtherTaxVat == 1) {
                                    $netPrice = ($extraItem['menuExtraPrice'] * 100 / (100 + $newVatValue) * 100 / (100 + $additionalTaxValue));
                                    $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    }
                                } else { 
                                    $netPrice = ($extraItem['menuExtraPrice'] * 100 / (100 + $newVatValue + $additionalTaxValue));
                                    $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice * $newVatValue / 100);
                                    }
                                }
                            } else {
                                $netPrice = $extraItem['menuExtraPrice'];
                                $otherTaxValue += ($netPrice * $additionalTaxValue / 100);
                                if ($flagOtherTaxVat == 1) {
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice + ($netPrice * $additionalTaxValue / 100) * $newVatValue / 100);
                                    }
                                } else { 
                                    if ($isApplyOtherVat) {
                                        $otherVatValue += ($netPrice * $newVatValue / 100);
                                    } else {
                                        $vatValue += ($netPrice * $newVatValue / 100);
                                    }
                                }
                            }

                            $menuExtraName = $charLength > 32 ? substr($extraItem['menuExtraName'], 0, 34) : substr($extraItem['menuExtraName'], 0, 14);
                            $printer->text(str_pad("    " . $menuExtraName, $charLength - 14, ' '));
                            $printer->text(str_pad(number_format(
                                $extraItem['menuExtraPrice'],
                                $salesDecimalSetting,
                                "$salesDecimalSeparatorSetting",
                                "$reverseDecimalSeparator"
                            ), 14, ' ', STR_PAD_LEFT));
                            $this->printLineBreak(null, 1, $stationModel);

                            if ($type === "category") {
                                $menuCategoryArray[$menuData['menuCategoryID']]['totalPriceOtherTaxVat'] += $extraItem['menuExtraPrice'];
                            } else {
                                $menuCategoryArray[$menuData['menuCategoryDetailID']]['totalPriceOtherTaxVat'] += $extraItem['menuExtraPrice'];
                            }
                        }
                    }
                }
            }
        }
        
        $this->printLineBreak("-", 1, $stationModel);
        foreach ($menuCategoryArray as $categoryType) {
            if (isset($categoryType['totalPriceOtherTaxVat'])) {
                if ($type === 'category') {
                    if(isset($menuCategoryNotOrignArr)) {
                        foreach($menuCategoryNotOrignArr as $menuCategory) {
                            if (($categoryType['menuCategoryID'] == $menuCategory['menuCategoryID']) && count($menuCategoryArray) == 1) {
                                continue;
                            } else {
                                if ($categoryType['menuCategoryID'] == $menuCategory['menuCategoryID']) {
                                    $categoryType['totalPriceOtherTaxVat'] += $menuCategory['menuPrice'];
                                }
                            }
                        }
                    }

                    $categoryName = $charLength > 32 ? substr($categoryType['menuCategoryDesc'], 0, 18) : substr($categoryType['menuCategoryDesc'], 0, 12);
                    $printer->text(str_pad("Total " . $categoryName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $categoryType['totalPriceOtherTaxVat'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                } else {
                    if(isset($menuCategoryNotOrignArr)) {
                        foreach($menuCategoryNotOrignArr as $menuCategory) {
                            if ($menuCategory['menuCategoryDetailID'] && ($categoryType['menuCategoryDetailID'] == $menuCategory['menuCategoryDetailID'])) {
                                $categoryType['totalPriceOtherTaxVat'] += $menuCategory['menuPrice'];
                            }
                        }
                    }

                    $categoryDetailName = $charLength > 32 ? substr($categoryType['menuCategoryDetailDesc'], 0, 18) : substr($categoryType['menuCategoryDetailDesc'], 0, 12);
                    $printer->text(str_pad("Total " . $categoryDetailName, $charLength - 14, ' '));
                    $printer->text(str_pad(number_format(
                        $categoryType['totalPriceOtherTaxVat'],
                        $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator"
                    ), 14, ' ', STR_PAD_LEFT));
                    $this->printLineBreak(null, 1, $stationModel);
                }
            }
        }
        $this->printLineBreak("-", 1, $stationModel);

        if ($roundingNearestValue != 0) {
            if ($roundingMode == 'DOWN') {
                $roundingValue = $subTotal - (floor($subTotal / $roundingNearestValue) * $roundingNearestValue);
            } elseif ($roundingMode == 'UP') {
                $roundingValue = $subTotal - (ceil($subTotal / $roundingNearestValue) * $roundingNearestValue);
            } elseif ($roundingMode == 'AUTO') {
                $roundingValue = $subTotal - ROUND($subTotal / $roundingNearestValue) * $roundingNearestValue;
            }
        }

        $this->printTestBillFooter(
            $stationModel, 
            $subTotal, 
            $roundingValue, 
            $otherTaxValue, 
            $vatValue, 
            $otherVatValue, 
            $flagInclusive, 
            $taxName, 
            $vatName, 
            $additionalTaxName, 
            $flagTax
        );

        if ($stationModel->flagAutocut == 1) {
            $printer->cut(Printer::CUT_PARTIAL);
        }
        $printer->close();
    }
    
    private function printTestBillHeader($stationModel, $visitPurposeID, $additionalTaxValue, $taxValue, $flagOtherTaxVat) {
        $printer = $this->printer;
        $charLength = $stationModel->characterPerLine;
        $visitPurposeModel = VisitPurpose::findOne($visitPurposeID);

        $visitPurposeName = $charLength > 32 ? substr($visitPurposeModel['visitPurposeName'], 0, 25) : substr($visitPurposeModel['visitPurposeName'], 0, 12);
        $printer->text(str_pad('Purpose', 16, ' '));
        $printer->text(' : ');
        $printer->text(str_pad($visitPurposeName, 11, ' '));
        $printer->feed(1);

        if ($charLength <= 32 && strlen($visitPurposeName) >= 12) {
            $visitPurposeName = substr($visitPurposeModel['visitPurposeName'], 12, 13);
            $printer->text(str_pad(' ', 16, ' '));
            $printer->text('  ');
            $printer->text(str_pad($visitPurposeName, 11, ' '));
            $printer->feed(1);
        }
        
        $otherTaxValue = floatval($additionalTaxValue) . " %";
        $printer->text(str_pad('Other Tax', 16, ' '));
        $printer->text(' : ');
        $printer->text(str_pad($otherTaxValue, 11, ' '));
        $printer->feed(1);

        $taxValue = floatval($taxValue) . " %";
        $printer->text(str_pad('Tax', 16, ' '));
        $printer->text(' : ');
        $printer->text(str_pad($taxValue, 11, ' '));
        $printer->feed(1);

        $otherTaxOnVat = $flagOtherTaxVat == 1 ? 'Yes' : 'No';
        $printer->text(str_pad('Other Tax On Vat', 16, ' '));
        $printer->text(' : ');
        $printer->text(str_pad($otherTaxOnVat, 11, ' '));
        $printer->feed(1);

        $this->printLineBreak("-", 1, $stationModel);
        $title = ' XX FOR TEST PURPOSE ONLY XX ';
        $pad2Left = floor(($charLength - strlen($title)) / 2);
        $pad2Right = $charLength - $pad2Left - strlen($title);
        $printer->text(str_pad('', $pad2Left, ' '));
        $printer->text($title);
        $printer->text(str_pad('', $pad2Right, ' '));

        $this->printLineBreak("-", 1, $stationModel);
    }

    private function printTestBillFooter(
        $stationModel, 
        $subTotal, 
        $roundingValue, 
        $otherTaxValue, 
        $vatValue, 
        $otherVatValue, 
        $flagInclusive, 
        $taxName, 
        $vatName, 
        $additionalTaxName, 
        $flagTax
    ) {
        $printer = $this->printer;
        $charLength = $stationModel->characterPerLine;
        $salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $roundingValue = $roundingValue * -1;

        $spacer = $charLength > 32 ? 15 : 26;
        if ($flagInclusive) {
            $printer->text(str_pad(
                'Total',
                $charLength - ($spacer ? 15 : 26),
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $subTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));

            $showBillingRounding = $this->settings['Show Billing Rounding'];
            if ($showBillingRounding) {
                $printer->text(str_pad(
                    'Rounding',
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $roundingValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            $this->printLineBreak("-", 1, $stationModel);
            
            $grandTotal = $subTotal + $roundingValue;
            $printer->text(str_pad(
                'Grand Total',
                $charLength - ($spacer ? 15 : 26),
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $grandTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));

            if ($otherTaxValue !== null) {
                $otherTaxName = $additionalTaxName . ' Incl';
                $otherTaxNameSub = $charLength > 32 ? $otherTaxName : substr($otherTaxName, 0, 11) ;
                $printer->text(str_pad(
                    $otherTaxNameSub ,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $otherTaxValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            if ($vatValue !== null) {
                $taxNameValue = $taxName .' Incl';
                $taxNameSub = $charLength > 32 ? $taxNameValue : substr($taxNameValue, 0, 11) ;
                $printer->text(str_pad(
                    $taxNameSub,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $vatValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            if ($otherVatValue !== null) {
                $vatNameValue = $vatName . " Incl";
                $otherVatName = $charLength > 32 ? $vatNameValue : substr($vatNameValue, 0, 11) ;
                $printer->text(str_pad(
                    $otherVatName,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $otherVatValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }
        } else {
            $printer->text(str_pad(
                'Sub Total',
                $charLength - ($spacer ? 15 : 26),
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $subTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));

            if ($otherTaxValue !== null) {
                $otherTaxNameSub = $charLength > 32 ? $additionalTaxName : substr($additionalTaxName, 0, 11) ;
                $printer->text(str_pad(
                    $otherTaxNameSub,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $otherTaxValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            if ($vatValue !== null) {
                $taxNameSub = $charLength > 32 ? $taxName : substr($taxName, 0, 11) ;
                $printer->text(str_pad(
                    $taxNameSub,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $vatValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

           if ($otherVatValue !== null) {
                $otherVatValueName = $charLength > 32 ? $vatName : substr($vatName, 0, 11) ;
                $printer->text(str_pad(
                    $otherVatValueName,
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $otherVatValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            $this->printLineBreak("-", 1, $stationModel);

            $total = $subTotal + $otherTaxValue + $vatValue + $otherVatValue;
            $printer->text(str_pad(
                'Total',
                $charLength - ($spacer ? 15 : 26),
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $total,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));

            $showBillingRounding = $this->settings['Show Billing Rounding'];
            if ($showBillingRounding) {
                $printer->text(str_pad(
                    'Rounding',
                    $charLength - ($spacer ? 15 : 26),
                    ' ',
                    STR_PAD_LEFT
                ));
                $printer->text(' : ');
                $printer->text(str_pad(number_format(
                    $roundingValue,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator"
                ), 12, ' ', STR_PAD_LEFT));
            }

            $this->printLineBreak("-", 1, $stationModel);

            $grandTotal = $total + $roundingValue;
            $printer->text(str_pad(
                'Grand Total',
                $charLength - ($spacer ? 15 : 26),
                ' ',
                STR_PAD_LEFT
            ));
            $printer->text(' : ');
            $printer->text(str_pad(number_format(
                $grandTotal,
                $salesDecimalSetting,
                "$salesDecimalSeparatorSetting",
                "$reverseDecimalSeparator"
            ), 12, ' ', STR_PAD_LEFT));
        }

        $flagTaxName = $flagTax == 2 ? $vatName : $taxName;
        $flagTaxModeName = $charLength > 32 ? $flagTaxName : substr($flagTaxName, 0, 11);
        $this->printLineBreak("-", 1, $stationModel);
        $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
        $noticeFooterTittle = 'Menu Tax Mode: ' . $flagTaxModeName;
        $pad2Left = floor(($charLength - strlen($noticeFooterTittle)) / 2);
        $pad2Right = $charLength - $pad2Left - strlen($noticeFooterTittle);
        $printer->text(str_pad('', $pad2Left, ' '));
        $printer->text($noticeFooterTittle);
        $printer->text(str_pad('', $pad2Right, ' '));

        $this->printLineBreak("-", 1, $stationModel);

        if ($charLength > 32) {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            $noticeFooterTittle = "Header information based on Visit Purpose \n settings, not menu specific settings";
            $pad2Left = floor(($charLength - strlen($noticeFooterTittle)) / 2);
            $pad2Right = $charLength - $pad2Left - strlen($noticeFooterTittle);
            $printer->text(str_pad('', $pad2Left, ' '));
            $printer->text($noticeFooterTittle);
            $printer->text(str_pad('', $pad2Right, ' '));
            $printer->feed(1);
        } else {
            $printer->setJustification(ExtPrinter::JUSTIFY_CENTER);
            $noticeFooterTittle = "Header information based on \n Visit Purpose settings, \n not menu specific settings";
            $pad2Left = floor(($charLength - strlen($noticeFooterTittle)) / 2);
            $pad2Right = $charLength - $pad2Left - strlen($noticeFooterTittle);
            $printer->text(str_pad('', $pad2Left, ' '));
            $printer->text($noticeFooterTittle);
            $printer->text(str_pad('', $pad2Right, ' '));
            $printer->feed(1);
        }

        
        $this->printLineBreak("-", 1, $stationModel);
    }

    public function printTestMenu($visitPurposeID, $type) {
        $query = (new Query)
            ->select([
                'mc.menuCategoryID', 
                'mc.menuCategoryDesc', 
                'mcd.ID', 
                'mcd.menuCategoryDetailDesc', 
                'menu.menuName',
                'st.stationName',
                'bm.stationID',
                'bm.checkerStationID'
            ])
            ->from(['mbvp' => MapBranchVisitPurpose::tableName()])
            ->innerJoin(['mth' => MenuTemplateHead::tableName()], ['AND', 'mth.menuTemplateID = mbvp.menuTemplateID', ['=', 'mbvp.visitPurposeID', $visitPurposeID]])
            ->innerJoin(['mtd' => MenuTemplateDetail::tableName()], 'mth.menuTemplateID = mtd.menuTemplateID and mtd.flagActive = 1')
            ->innerJoin(['menu' => Menu::tableName()], 'mtd.menuID = menu.menuID')
            ->innerJoin(['mcd' => MenuCategoryDetail::tableName()], 'menu.menuCategoryDetailID = mcd.ID and mcd.flagActive = 1')
            ->innerJoin(['mc' => MenuCategory::tableName()], 'mcd.menuCategoryID = mc.menuCategoryID and mc.flagActive = 1')
            ->innerJoin(['bm' => BranchMenu::tableName()], 'bm.menuID = menu.menuID and bm.stationID <> 0')
            ->innerJoin(['st' => Station::tableName()], 'bm.stationID = st.stationID and st.flagActive = 1')
            ->orderBy('mc.menuCategoryDesc, mcd.menuCategoryDetailDesc, menu.menuName')
            ->all();
        
        $stationIDs = [];
        $menuModels = [];
        foreach ($query as $data) {
            if (strlen($data['stationID']) > 0) {
                foreach ($this->stationIDs as $station) {
                    if (in_array($station, explode(",", $data['stationID']))) {
                        if (!isset($menuModels[$station])) {
                            $menuModels[$station]['stationID'] = $station;
                            array_push($stationIDs, $station);
                        }

                        if ($type === 'category') {
                            $menuModels[$station]['data'][$data['menuCategoryID']]['menuCategoryDesc'] = $data['menuCategoryDesc'];
                            $menuModels[$station]['data'][$data['menuCategoryID']]['menus'][] = $data['menuName'];
                        } else {
                            $menuModels[$station]['data'][$data['ID']]['menuCategoryDetailDesc'] = $data['menuCategoryDetailDesc'];
                            $menuModels[$station]['data'][$data['ID']]['menus'][] = $data['menuName'];
                        }
                    }
                }
            }
        }
        
        $stationModels = Station::findActive()
            ->andWhere(['IN', 'stationID', $stationIDs])
            ->all();

        foreach ($menuModels as $menu) {
            $stationModel = null;
            foreach ($stationModels as $station) {
                if ($menu['stationID'] === $station['stationID']) {
                    $stationModel = $station;
                    break;
                }
            }

            if ($stationModel) {
                $this->runTestPrintMenu($stationModel, $menu['data'], $type);
            }
        }
        Logging::save('-', Logging::PRINT_TEST, $station);
    }

    private function runTestPrintMenu($stationModel, $menuModels, $type) {
        // 1: Thermal Printer, 3: Dot Matrix Printer, 4: Star mPOP Printer, 5: Thermal CX-58D, 10: Bixolon Printer, 15: Star TSP 650
        if (in_array($stationModel->printerTypeID, [1, 3, 4, 5, 10, 15])) {
            foreach ($menuModels as $menuModel) {
                $connector = Station::getConnectorByModel($stationModel, ' ', false);
    
                $this->printer = new ExtPrinter($connector);
                $printer = $this->printer;
                $charLength = $stationModel->characterPerLine;
    
                $this->printTestMenuHeader($stationModel, $charLength);
    
                $stationName = $stationModel->stationName;
                $text1 = ' ' . $stationName . ' ';
                $pad1Left = floor(($charLength - strlen($text1)) / 2);
                $pad1Right = $charLength - $pad1Left - strlen($text1);
                $printer->text(str_pad('', $pad1Left, '-'));
                $printer->text($text1);
                $printer->text(str_pad('', $pad1Right, '-'));
                
                $this->printLineBreak("=", 1, $stationModel);
                
                if ($type === 'category') {
                    $menuCategoryDesc = $menuModel['menuCategoryDesc'];
                    $text1 = ' ' . $menuCategoryDesc . ' ';
                    $pad1Left = floor(($charLength - strlen($text1)) / 2);
                    $pad1Right = $charLength - $pad1Left - strlen($text1);
                    $printer->text(str_pad('', $pad1Left, ' '));
                    $printer->text($text1);
                    $printer->text(str_pad('', $pad1Right, ' '));
                } else {
                    $menuCategoryDesc = $menuModel['menuCategoryDetailDesc'];
                    $text1 = ' ' . $menuCategoryDesc . ' ';
                    $pad1Left = floor(($charLength - strlen($text1)) / 2);
                    $pad1Right = $charLength - $pad1Left - strlen($text1);
                    $printer->text(str_pad('', $pad1Left, ' '));
                    $printer->text($text1);
                    $printer->text(str_pad('', $pad1Right, ' '));
                }
    
                foreach ($menuModel['menus'] as $menu) {
                    $menuName = $charLength > 32 ? substr($menu, 0, 34) : substr($menu, 0, 18);
                    $printer->text(str_pad($menuName, $charLength - 14, ' '));
                    $printer->feed(1);
                }
    
                $this->printLineBreak("-", 1, $stationModel);
                if ($stationModel->flagAutocut == 1) {
                    $printer->cut(Printer::CUT_PARTIAL);
                }
                $printer->close();
            }
        }
    }

    public function printTestMenuHeader($stationModel, $charLength) {
        $printer = $this->printer;

        $this->printLineBreak("=", 1, $stationModel);

        $title = 'XX FOR TEST PURPOSE ONLY XX';
        $pad2Left = floor(($charLength - strlen($title)) / 2);
        $pad2Right = $charLength - $pad2Left - strlen($title);
        $printer->text(str_pad('', $pad2Left, ' '));
        $printer->text($title);
        $printer->text(str_pad('', $pad2Right, ' '));

        $this->printLineBreak("=", 1, $stationModel);
    }

    private function printLineBreak($textSymbol = null, $feed = 1, $stationModel) {
        $printer = $this->printer;
        
        if ($textSymbol !== null) {
            $printer->text(str_pad('', $stationModel->characterPerLine, $textSymbol));
        }
        
        if ($stationModel->printerTypeID == 4 || $stationModel->printerTypeID == 15) {
            for ($i=0; $i < $feed; $i++) { 
                $printer->getPrintConnector()->write("\x0A");
            }
        } else {
            $printer->feed($feed);
        }
    }

    private function getNumbers($charLength) {
        $text = '1234567890';
        for ($i = 0; $i <= 5; $i++) {
            $text .= $text;
        }

        return substr($text, 0, $charLength);
    }

}
