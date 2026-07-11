<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\Enums\PrinterTypeInterface;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\Station;
use app\models\Table;
use Exception;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $stationID
 * @property int $batchID
 * @property int $queueNum
 * 
 * PRIVATE
 * @property Printer $printer
 * @property Station $stationModel
 * @property array $settings
 * @property SalesHead $salesModel
 * @property SalesMenu[] $salesMenusModel
 */
class PrintChecker extends Model
{
    const SCENARIO_CANCEL_ORDER = 'cancel order';
    const SCENARIO_MOVE_ITEM = 'move item';
    const SCENARIO_SELF_ORDER = 'self order';
    const SCENARIO_CANCEL_TABLE = 'cancel table';
    const SCENARIO_PRINT_CHECKER_AFTER_PAYMENT = 'print checker after payment';

    public $tableID;
    public $salesNum;
    public $stationID;
    public $batchID;
    public $sourceTableID; //used on SCENARIO_MOVE_ITEM
    public $sourceSalesNum; //used on SCENARIO_MOVE_ITEM
    public $sourceTableModel; //used on SCENARIO_MOVE_ITEM
    public $printer;
    public $settings;
    public $stationModel;
    public $salesModel;
    public $salesMenusModel;
    public $queueNum;
    public $flagSelfOrder;
    public $salesDecimalSetting;
    public $salesDecimalSeparatorSetting;
    public $reverseDecimalSeparator;
    public $flagFireOrderIDs;
    public $printResult;
    public $testPrint;
    public $shouldPrintAfterPayment;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tableID', 'stationID'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                return $model->tableID == 0;
            }],
            [['tableID', 'stationID', 'batchID', 'queueNum'], 'integer'],
            [['sourceTableID'], 'required', 'on' => self::SCENARIO_MOVE_ITEM],
            [['sourceSalesNum'], 'required', 'on' => self::SCENARIO_MOVE_ITEM, 'when' => function ($model) {
                return $model->sourceTableID == 0;
            }],
            [['flagSelfOrder', 'flagFireOrderIDs', 'testPrint', 'shouldPrintAfterPayment'], 'safe'],
            [['tableID'], 'validateTable'],
            [['sourceTableID'], 'validateSourceTable']
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CANCEL_ORDER] = ['tableID', 'salesNum', 'stationID', 'batchID'];
        $scenarios[self::SCENARIO_MOVE_ITEM] = ['tableID', 'salesNum', 'batchID', 'sourceTableID', 'sourceSalesNum'];
        $scenarios[self::SCENARIO_SELF_ORDER] = ['tableID', 'salesNum', 'stationID', 'batchID'];
        $scenarios[self::SCENARIO_CANCEL_TABLE] = ['tableID', 'salesNum', 'stationID', 'batchID'];
        $scenarios[self::SCENARIO_PRINT_CHECKER_AFTER_PAYMENT] = ['tableID', 'salesNum', 'stationID', 'batchID'];
        return $scenarios;
    }

    public function validateTable($attribute)
    {
        // @Notes: 19 = Print Cancelled, 13 = Preparing
        $statusID = $this->scenario == self::SCENARIO_CANCEL_ORDER || $this->scenario == self::SCENARIO_CANCEL_TABLE ? 19 : 13;
        $branchID = Setting::getCurrentBranch();
        $testPrint = isset($this->testPrint) && !!$this->testPrint;

        if ($this->tableID != 0) {
            if ($this->scenario == self::SCENARIO_CANCEL_TABLE) {
                $this->salesModel = SalesHead::findFinished()
                    ->with('table.tableSection')
                    ->with('visitPurpose')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
            } else {
                if ($testPrint) {
                    $outstandingOrderForTestPrint = SalesHead::find()
                        ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                        ->orderBy('salesDate, salesNum');

                    $this->salesModel = SalesHead::findOrderDetails($outstandingOrderForTestPrint)
                        ->with('visitPurpose')
                        ->andWhere([
                            'OR',
                            [SalesHead::tableName() . '.salesNum' => $this->salesNum],
                            [SalesMergeTable::tableName() . '.salesNum' => $this->salesNum]
                        ])
                        ->one();
                } else {
                    $this->salesModel = SalesHead::findOutstandingOrder()
                        ->with('visitPurpose')
                        ->andWhere([
                            'OR',
                            [SalesHead::tableName() . '.salesNum' => $this->salesNum],
                            [SalesMergeTable::tableName() . '.salesNum' => $this->salesNum]
                        ])
                        ->one();
                }
            }

            if ($this->flagSelfOrder && ($this->salesModel->table->stationID !== null && $this->salesModel->table->stationID !== 0)) {
                $this->stationID = $this->salesModel->table->stationID;
            }
        } else {
            if ($this->scenario == self::SCENARIO_SELF_ORDER || $this->scenario == self::SCENARIO_CANCEL_TABLE || $this->scenario == self::SCENARIO_PRINT_CHECKER_AFTER_PAYMENT) {
                $this->salesModel = SalesHead::findFinished()
                    ->with('table.tableSection')
                    ->with('visitPurpose')
                    ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                    ->one();
            } else {
                if ($testPrint) {
                    $outstandingOrderForTestPrint = SalesHead::find()
                        ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                        ->orderBy('salesDate, salesNum');

                    $this->salesModel = $outstandingOrderForTestPrint
                        ->with('visitPurpose')
                        ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                        ->one();
                } else {
                    $this->salesModel = SalesHead::findOutstandingOrder()
                        ->with('visitPurpose')
                        ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                        ->one();
                }
            }
        }

        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }

        // @Notes: bacthID null = print all batch
        $this->salesMenusModel = SalesMenu::findMainMenus(
            $this->salesModel->salesNum,
            $statusID,
            $this->batchID,
            null,
            false
        )
            ->andWhere([SalesMenu::tableName() . '.flagPending' => 1])
            ->all();
        if (!$this->salesMenusModel) {
            $this->addError($attribute, 'Batch ID not found');
        }

        // @Notes: Get queue number
        $this->queueNum = $this->salesModel->queueNum;
    }

    public function validateSourceTable($attribute)
    {
        if ($this->sourceTableID != 0) {
            $this->sourceTableModel = Table::find()
                ->andWhere(['tableID' => $this->sourceTableID])
                ->one();
            if (!$this->sourceTableModel) {
                $this->addError($attribute, 'Invalid source table ID');
            }
        }
    }

    public function doPrint()
    {
        if (!$this->validate()) {
            self::returnErrorMessage();
            return false;
        }

        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();
        if (!$this->stationModel) {
            self::returnErrorMessage();
            return false;
        }

        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();

        $this->settings = Setting::getPrintingSettings();

        $this->salesDecimalSetting = isset($this->settings['Sales Decimal Setting']) ? $this->settings['Sales Decimal Setting'] : 0;
        $this->salesDecimalSeparatorSetting = isset($this->settings['Sales Decimal Separator Setting']) ? $this->settings['Sales Decimal Separator Setting'] : ',';
        $this->reverseDecimalSeparator = $this->salesDecimalSeparatorSetting == '.' ? ',' : '.';

        if (!$this->settings['Print Cancel Table Checker'] && $this->scenario == self::SCENARIO_CANCEL_ORDER) {
            self::returnErrorMessage();
            return false;
        }
        if (!$this->settings['Print Cancel Table Checker'] && $this->scenario == self::SCENARIO_CANCEL_TABLE) {
            self::returnErrorMessage();
            return false;
        }

        if ($this->tableID > 0 && isset($this->settings['Dine In Print Checker']) && $this->settings['Dine In Print Checker'] == 0) {
            self::returnErrorMessage();
            return false;
        }

        if ($this->tableID === 0 && isset($this->settings['Take Away Print Checker']) && $this->settings['Take Away Print Checker'] == 0) {
            self::returnErrorMessage();
            return false;
        }

        $allowPrintMoveItem = isset($this->settings['Print Move Item']) ? $this->settings['Print Move Item'] : true;
        if (!$allowPrintMoveItem && $this->scenario == self::SCENARIO_MOVE_ITEM) {
            self::returnErrorMessage();
            return false;
        }

        $printOrderList = [];
        foreach ($this->salesMenusModel as $salesMenu) {
            $flagFireCondition = ($this->flagFireOrderIDs !== null) && count($this->flagFireOrderIDs) > 0 && in_array($salesMenu->localID, $this->flagFireOrderIDs);
            if ($flagFireCondition) {
                if ($salesMenu->statusID === 46) {
                    $printOrderList[$salesMenu->batchID][] = $salesMenu;
                }
            } else {
                $printOrderList[$salesMenu->batchID][] = $salesMenu;
            }
        }

        $testPrint = isset($this->testPrint) && !!$this->testPrint;
        $isErrorConnector = false;
        foreach ($printOrderList as $printOrder) {
            try {
                if ($this->scenario === self::SCENARIO_SELF_ORDER || $this->flagSelfOrder) {
                    if (!isset($this->testPrint)) {
                        Logging::save(
                            $this->salesNum,
                            Logging::PRINT_CHECKER_SELFORDER,
                            $this->getAttributes()
                        );
                    }

                    $connector = Station::getConnectorByModel(
                        $this->stationModel,
                        $this->salesNum,
                        true,
                        self::SCENARIO_SELF_ORDER,
                        $testPrint
                    );
                } else {
                    if (!isset($this->testPrint)) {
                        Logging::save(
                            $this->salesNum,
                            Logging::PRINT_CHECKER,
                            $this->getAttributes()
                        );
                    }

                    $connector = Station::getConnectorByModel(
                        $this->stationModel,
                        $this->salesNum,
                        true,
                        $this->scenario,
                        $testPrint
                    );
                }

                if ($connector !== null) {
                    $this->printer = new Printer($connector);
                    $printer = $this->printer;

                    if ($this->settings['Table Checker Top Margin'] != 0) {
                        $printer->feed(intval($this->settings['Table Checker Top Margin']));
                    }

                    $this->printHeader();
                    $this->printCheckerInfo($printOrder[0], $branchModel);
                    if ($this->scenario !== self::SCENARIO_CANCEL_TABLE) {
                        $this->printCheckerDetail($printOrder);
                    }
                    $this->printFooter($branchModel);

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

                    $printer->close();
                } else {
                    $isErrorConnector = true;
                }
            } catch (Exception $ex) {
                Yii::warning($ex);
            }
        }
        if ($isErrorConnector) {
            $this->printResult = ['status' => false, 'message' => $this->stationModel->stationName];
        } else {
            $this->printResult = ['status' => true, 'message' => null];
        }
    }

    private function printHeader()
    {

        $allowShowCheckerHeader = true;
        $allowShowCheckerHeader = isset($this->settings['Show Checker Header']) ? $this->settings['Show Checker Header'] : true;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');
        if (!$allowShowCheckerHeader) {
            $allowShowCheckerHeader = false;
        }

        if ($allowShowCheckerHeader) {
            $printer = $this->printer;
            $charLength = $this->stationModel->characterPerLine;

            // @Notes: Print header


            if ($this->stationModel->printerTypeID == '4') {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else if ($this->stationModel->printerTypeID == 15) {
                if ($charLength > 32) {
                    $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                    $printer->getPrintConnector()->write("\x1B" . "\x45");
                }
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
            } else {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
            }

            $this->printLableTrialMode();

            if ($this->scenario == self::SCENARIO_CANCEL_ORDER) {
                $printer->text(Yii::t('app', 'XXX CANCEL - Table Checker XXX'));
            } else if ($this->scenario == self::SCENARIO_MOVE_ITEM) {
                $printer->text(Yii::t('app', '*** MOVE ITEMS - Table Checker ***'));
            } else if ($this->scenario == self::SCENARIO_CANCEL_TABLE) {
                $printer->text(Yii::t('app', '*** CANCEL TABLE - Table Checker ***'));
            } else {
                $printer->text(Yii::t('app', 'Table Checker'));
            }
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
            if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '='));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function printFooter($branchModel)
    {

        $allowShowCheckerFooter = true;
        $allowShowCheckerFooter = isset($this->settings['Show Checker Footer']) ? $this->settings['Show Checker Footer'] : true;
        if (!$allowShowCheckerFooter) {
            $allowShowCheckerFooter = false;
        }

        if ($allowShowCheckerFooter) {
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

            foreach (explode('>><<', $branchModel->printingCheckerFooter) as $lineFooter) {
                $printer->text($lineFooter);
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
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
    }

    private function printCheckerInfo($salesMenuModel, $branchModel = null)
    {
        $printer = $this->printer;
        $salesModel = $this->salesModel;
        $charLength = $this->stationModel->characterPerLine;
        $printTakeAwaySettings = array_key_exists(
            'Print Quick Service Table Text',
            $this->settings
        ) ? $this->settings['Print Quick Service Table Text'] : true;
        $showQueueNum = $salesModel->visitPurpose->flagShowQueue ? $salesModel->visitPurpose->flagShowQueue : 0;

        if ($branchModel->posModeID != 1 || ($printTakeAwaySettings && $salesModel->tableID == 0)) {
            if (array_key_exists('Queue Number', $this->settings)) {
                if ($this->settings['Queue Number'] == 1 && $showQueueNum == 1) {
                    if ($this->stationModel->printerTypeID != 15) {
                        $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
                    } else if ($this->stationModel->printerTypeID == 15) {
                        if ($charLength > 32) {
                            $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                            $printer->getPrintConnector()->write("\x1B" . "\x45");
                        }
                    }
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                    } else {
                        $printer->setJustification(Printer::JUSTIFY_CENTER);
                    }
                    $printer->text(Yii::t('app', 'Queue'));
                    $printer->text(' : ');
                    $printer->text($this->queueNum);
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                    if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                        $printer->selectPrintMode(Printer::MODE_FONT_B);
                    }
                    $printer->initialize();
                    $printer->text(str_pad('', $charLength, '='));
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }
        }
        if (($printTakeAwaySettings && $salesModel->tableID == 0) || $salesModel->tableID != 0) {

            $allowShowCheckerTable = true;
            $allowShowCheckerTable = isset($this->settings['Show Checker Table']) ? $this->settings['Show Checker Table'] : true;
            if (!$allowShowCheckerTable) {
                $allowShowCheckerTable = false;
            }

            if ($allowShowCheckerTable && $this->scenario == self::SCENARIO_MOVE_ITEM) {
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                } else {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }
                $printer->text($this->sourceTableModel ? $this->sourceTableModel->tableName : $this->sourceSalesNum);
                $printer->text(' MOVE TO ');
                $printer->text($this->salesModel->table ? $this->salesModel->table->tableName : $this->salesModel->salesNum);
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                $printer->initialize();
                $printer->text(str_pad('', $charLength, '='));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            } else if ($allowShowCheckerTable) {
                if ($this->stationModel->printerTypeID != 15) {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
                } else if ($this->stationModel->printerTypeID == 15) {
                    if ($charLength > 32) {
                        $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                }

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
                } else {
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                }

                $printer->text(Yii::t('app', 'Table'));
                $printer->text(' : ');
                $tableNameText = 'Quick Service';
                if ($salesModel->table) {
                    $tableNameText = $salesModel->table->tableName;
                } else {
                    if ($salesModel->tableQuickService) {
                        $tableNameText = $salesModel->tableQuickService->value;
                    }
                }
                $printer->text($tableNameText);

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                    $printer->selectPrintMode(Printer::MODE_FONT_B);
                }
                $printer->initialize();
                $printer->text(str_pad('', $charLength, '='));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $allowShowCheckerOrder = true;
        $allowShowCheckerOrder = isset($this->settings['Show Checker Order']) ? $this->settings['Show Checker Order'] : true;
        if (!$allowShowCheckerOrder) {
            $allowShowCheckerOrder = false;
        }

        if ($allowShowCheckerOrder) {
            $printer->text(str_pad(Yii::t('app', 'Order'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($salesModel->salesNum, $charLength - 11, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $allowShowCheckerDate = true;
        $allowShowCheckerDate = isset($this->settings['Show Checker Date']) ? $this->settings['Show Checker Date'] : true;
        if (!$allowShowCheckerDate) {
            $allowShowCheckerDate = false;
        }

        if ($allowShowCheckerDate) {
            $printer->text(str_pad(Yii::t('app', 'Date'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(date_format(
                date_create($salesMenuModel->createdDate),
                'd-m-Y H:i:s'
            ), $charLength - 11, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $allowShowCheckerVisitPurpose = true;
        $allowShowCheckerVisitPurpose = isset($this->settings['Show Checker Visit Purpose']) ? $this->settings['Show Checker Visit Purpose'] : true;
        if (!$allowShowCheckerVisitPurpose) {
            $allowShowCheckerVisitPurpose = false;
        }

        if ($allowShowCheckerVisitPurpose) {
            $visitPurposeName = $salesModel->visitPurpose->visitPurposeName ? $salesModel->visitPurpose->visitPurposeName : '';
            $printer->text(str_pad(Yii::t('app', 'Purpose'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($visitPurposeName, $charLength - 11, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $allowShowCheckerWaiter = isset($this->settings['Show Checker Waiter']) ? $this->settings['Show Checker Waiter'] : true;
        if (!$allowShowCheckerWaiter) {
            $allowShowCheckerWaiter = false;
        }

        if ($allowShowCheckerWaiter) {
            $printer->text(str_pad(Yii::t('app', 'Waiter'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(($salesModel->creator ? $salesModel->creator->fullName : Yii::t('app', 'SELF ORDER')),
                $charLength - 11,
                ' '
            ));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $allowShowCheckerSender = isset($this->settings['Show Checker Sender']) ? $this->settings['Show Checker Sender'] : true;
        if (!$allowShowCheckerSender) {
            $allowShowCheckerSender = false;
        }

        if ($allowShowCheckerSender) {
            $printer->text(str_pad(Yii::t('app', 'Sender'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad(($salesMenuModel->creator ? $salesMenuModel->creator->fullName : Yii::t('app', 'SELF ORDER')),
                $charLength - 11,
                ' '
            ));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        if ($this->scenario != self::SCENARIO_CANCEL_ORDER && $this->scenario != self::SCENARIO_MOVE_ITEM) {
            $onShowAdditionalInfo = true;
            $onShowAdditionalInfo = isset($this->settings['Show Printing Additional Info']) ? $this->settings['Show Printing Additional Info'] : true;
            if (!$onShowAdditionalInfo) $onShowAdditionalInfo = false;

            if ($onShowAdditionalInfo) {
                if ($salesModel->additionalInfo != '') {
                    $printer->text(str_pad(
                        Yii::t('app', 'Info'),
                        8,
                        ' ',
                        STR_PAD_RIGHT
                    ));
                    $printer->text(' : ');
                    $additionalInfo = str_split(preg_replace("/\r|\n/", "", $salesModel->additionalInfo), $charLength - 11);
                    $i = 0;
                    foreach ($additionalInfo as $value) {
                        if ($i == 0) {
                            $printer->text($value);
                        } else {
                            $printer->text(str_pad("", 11, ' '));
                            $printer->text(str_pad(ltrim($value), $charLength - 11, ' '));
                        }
                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                        $i++;
                    };
                }
            }

            $allowShowCheckerBatch = true;
            $allowShowCheckerBatch = isset($this->settings['Show Checker Batch']) ? $this->settings['Show Checker Batch'] : true;
            if (!$allowShowCheckerBatch) {
                $allowShowCheckerBatch = false;
            }
            if ($allowShowCheckerBatch) {
                $printer->text(str_pad(Yii::t('app', 'Batch'), 8, ' '));
                $printer->text(' : ');
                $printer->text(str_pad(
                    $salesMenuModel->batchID,
                    $charLength - 11,
                    ' '
                ));
                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
            }
        }

        $showCheckerCustomerInfo = isset($this->settings['Show Checker Customer Info']) ? $this->settings['Show Checker Customer Info'] : false;

        if ($showCheckerCustomerInfo) {
            $salesInfosFullName = SalesInfo::findBySalesNumKey($salesModel->salesNum, 'Full Name');
            $printer->text(str_pad(Yii::t('app', 'Customer'), 8, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($salesInfosFullName, $charLength - 11, ' '));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }

        $printer->text(str_pad('', $charLength, '='));
        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x0A");
        } else {
            $printer->feed(1);
        }
    }

    private function printCheckerDetail($salesMenusModel)
    {

        $allowShowCheckerDetail = true;
        $allowShowCheckerDetail = isset($this->settings['Show Checker Detail']) ? $this->settings['Show Checker Detail'] : true;

        if (!$allowShowCheckerDetail) {
            $allowShowCheckerDetail = false;
        }
        if ($allowShowCheckerDetail) {
            $printer = $this->printer;
            $charLength = $this->stationModel->characterPerLine;

            if ($this->stationModel->printerTypeID != 15) {
                if ($this->stationModel->printerTypeID == 3) {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
                } else {
                    $printer->setTextSize(1, 2);
                }
            } else if ($this->stationModel->printerTypeID == 15) {
                if ($charLength > 32) {
                    $printer->setTextSize(1, 1);
                }
            }

            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "0");
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }

            foreach ($salesMenusModel as $salesMenu) {
                $printer->text(str_pad(self::formatNumberValue($salesMenu->qty), self::setNumberPosition($salesMenu->qty, 4, 0), ' ', STR_PAD_LEFT));
                $printer->text(' ');
                $menuName = $salesMenu->customMenuName ? $salesMenu->customMenuName : AppHelper::fromChinese($salesMenu->menu->menuShortName);
                $menuName = $salesMenu->statusID == 46 ? $menuName . ' (Hold)' : $menuName;
                $printer->text($menuName);

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(1);
                }
                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $package) {
                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad(self::formatNumberValue($package->qty * $salesMenu->qty), self::setNumberPosition($package->qty * $salesMenu->qty, 4, 0), ' ', STR_PAD_LEFT));
                        $printer->text(' ');
                        // @notes: Menu Spacing
                        $menuShortName = AppHelper::fromChinese($package->menu->menuShortName);
                        $printer->text($menuShortName);

                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }

                        if ($package->notes) {
                            $notesPackageString = $package->notes;
                            if (strpos($notesPackageString, "\n") !== false) {
                                $notesPackageString = str_replace("\n", ", ", $notesPackageString);
                            }
                            if (strlen($notesPackageString) >= $charLength - 13) {
                                $printer->text(str_pad('', 10, ' '));
                                $printer->text('* ');
                                $printer->text(substr($notesPackageString, 0, $charLength - 13));
                                $subPackageString = substr($notesPackageString, $charLength - 13);
                                do {
                                    $printer->text(str_pad('', 13, ' '));
                                    $printer->text(substr($subPackageString, 0, $charLength - 13));
                                    if (strlen($subPackageString) >= ($charLength - 13)) {
                                        $subPackageString = substr($subPackageString, $charLength - 13);
                                    } else break;
                                } while (1);
                            } else {
                                $printer->text(str_pad('', 10, ' '));
                                $printer->text('* ');
                                $printer->text($notesPackageString);
                            }
                            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                                $printer->getPrintConnector()->write("\x0A");
                            } else {
                                $printer->feed(1);
                            }
                        }
                    }
                }
                if ($salesMenu->salesExtras) {
                    foreach ($salesMenu->salesExtras as $extra) {
                        $printer->text(str_pad('', 6, ' '));
                        $printer->text(str_pad(self::formatNumberValue($extra->qty * $salesMenu->qty), self::setNumberPosition($extra->qty * $salesMenu->qty, 4, 0), ' ', STR_PAD_LEFT));
                        $printer->text(' ');
                        // @notes: Menu Spacing
                        $menuExtraShortName = AppHelper::fromChinese($extra->menuExtra->menuExtraShortName);
                        $printer->text($menuExtraShortName);

                        if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                            $printer->getPrintConnector()->write("\x0A");
                        } else {
                            $printer->feed(1);
                        }
                    }
                }
                if ($salesMenu->notes) {
                    $notesString = $salesMenu->notes;
                    if (strpos($notesString, "\n") !== false) {
                        $notesString = str_replace("\n", ", ", $notesString);
                    }
                    if (strlen($notesString) >= $charLength - 7) {
                        $printer->text(str_pad('', 5, ' '));
                        $printer->text('* ');
                        $printer->text(substr($notesString, 0, $charLength - 7));
                        $subString = substr($notesString, $charLength - 7);
                        do {
                            $printer->text(str_pad('', 7, ' '));
                            $printer->text(substr($subString, 0, $charLength - 7));
                            if (strlen($subString) >= ($charLength - 7)) {
                                $subString = substr($subString, $charLength - 7);
                            } else break;
                        } while (1);
                    } else {
                        $printer->text(str_pad('', 5, ' '));
                        $printer->text('* ');
                        $printer->text($notesString);
                    }
                    if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                        $printer->getPrintConnector()->write("\x0A");
                    } else {
                        $printer->feed(1);
                    }
                }
            }

            if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                $printer->selectPrintMode(Printer::MODE_FONT_B);
            }
            $printer->initialize();
            $printer->text(str_pad('', $charLength, '-'));
            if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                $printer->getPrintConnector()->write("\x0A");
            } else {
                $printer->feed(1);
            }
        }
    }

    private function formatNumberValue($number)
    {
        return AppHelper::formatNumberValue($number, null, $this->salesDecimalSeparatorSetting, $this->reverseDecimalSeparator);
    }

    private function setNumberPosition($number, $numericLength, $decimalLength)
    {
        return (fmod($number, 1) !== 0.00 ? $decimalLength : $numericLength);
    }

    private function returnErrorMessage()
    {
        $this->printResult = ['status' => true, 'message' => null];
    }

    private function printLableTrialMode()
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;
        $trialMode = Setting::getSetting('Local Setting', 'Trial Mode');

        if (isset($trialMode)) {
            if ($trialMode->value1 == 1) {
                if ($this->stationModel->printerTypeID != 15) {
                    $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED);
                } else if ($this->stationModel->printerTypeID == 15) {
                    if ($charLength > 32) {
                        $printer->getPrintConnector()->write("\x1B" . "\x68" . "1");
                        $printer->getPrintConnector()->write("\x1B" . "\x45");
                    }
                }

                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));
                $printer->text(' TRIAL MODE ');
                $printer->text(str_pad('', ($charLength - 20) / 2, '*', STR_PAD_LEFT));

                if ($this->stationModel->printerTypeID == '4' || $this->stationModel->printerTypeID == 15) {
                    $printer->getPrintConnector()->write("\x0A");
                } else {
                    $printer->feed(2);
                }

                if ($this->stationModel->printerTypeID == PrinterTypeInterface::PRINTER_TYPE_EDOT) {
                    $printer->selectPrintMode(Printer::MODE_FONT_B);
                }
            };
        }
    }
}
