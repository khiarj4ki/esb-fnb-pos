<?php

namespace app\models;

use app\components\AndroidPrintConnector;
use app\models\forms\Logging;
use app\models\forms\PrintChecker;
use Exception;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * This is the model class for table "ms_station".
 *
 * @property int $stationID
 * @property string $stationName
 * @property int $branchID
 * @property int $printerConnectionID
 * @property string $printerName
 * @property string $printerPort
 * @property int $characterPerLine
 * @property int $printingModeID
 * @property int $flagAutocut
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * 
 * @property PrinterConnection $printerConnection
 */
class Station extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_station';
    }

    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['createdDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['stationName', 'branchID', 'printerConnectionID', 'printingModeID', 'flagActive', 'createdBy', 'createdDate', 'flagAutocut', 'flagCashDrawer'], 'required'],
            [['branchID', 'printerConnectionID', 'printerTypeID', 'characterPerLine', 'printingModeID', 'flagActive', 'flagAutocut', 'flagCashDrawer'], 'integer'],
            [['stationID', 'createdDate', 'editedDate', 'syncDate'], 'safe'],
            [['stationName', 'printerPort'], 'string', 'max' => 50],
            [['printerName', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'stationID' => 'Station ID',
            'stationName' => 'Station Name',
            'branchID' => 'Branch ID',
            'printerConnectionID' => 'Printer Connection ID',
            'printerName' => 'Printer Name',
            'flagCashDrawer' => 'Flag Cash Drawer',
            'printerPort' => 'Printer Port',
            'characterPerLine' => 'Character Per Line',
            'printingModeID' => 'Printing Mode',
            'flagAutocut' => 'Active Auto Cut',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['printerConnectionName'] = function ($model) {
            return $model->printerConnection->printerConnectionName;
        };
        $fields['printerTypeName'] = function ($model) {
            return $model->printerType->printerTypeName;
        };
        
        $fields['printingModeName'] = function ($model) {
            return $model->printingMode->printingModeName;
        };

        return $fields;
    }

    public function getPrinterConnection() {
        return $this->hasOne(PrinterConnection::class,
                ['printerConnectionID' => 'printerConnectionID']);
    }

    public function getPrinterType() {
        return $this->hasOne(PrinterType::class,
                ['printerTypeID' => 'printerTypeID']);
    }
    
    public function getPrintingMode() {
        return $this->hasOne(PrintingMode::class, 
                ['printingModeID' => 'printingModeID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public static function findActive() {
        $branchID = Setting::getCurrentBranch();

        return Station::find()->with('printerConnection')
                ->andWhere([Station::tableName() . '.branchID' => $branchID])
                ->andWhere([Station::tableName() . '.flagActive' => 1])
                ->orderBy(Station::tableName() . '.stationName');
    }

    public static function getConnectorByModel($stationModel, $refNum = '', $retryAttempt = true, $scenario = null, $testPrint = false) {
        try {
            if($scenario && $scenario === PrintChecker::SCENARIO_SELF_ORDER) {
                if (!$testPrint) {
                    Logging::save($refNum, Logging::OPEN_PRINTER_SELFORDER, $stationModel);
                }
            } else {
                if (!$testPrint) {
                    Logging::save($refNum, Logging::OPEN_PRINTER, $stationModel);
                }
            }
            $connector = null;
            if ($stationModel->printerConnectionID == 1) {
                $connector = new NetworkPrintConnector($stationModel->printerName,
                    $stationModel->printerPort, $retryAttempt ? 10 : 1);
            } else if ($stationModel->printerConnectionID == 2) {
                $connector = new WindowsPrintConnector($stationModel->printerName);
            } else if ($stationModel->printerConnectionID == 3) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                    AndroidPrintConnector::BLUETOOTH, $stationModel->flagAutocut);
            } else if ($stationModel->printerConnectionID == 4) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                    AndroidPrintConnector::LAN, $stationModel->flagAutocut, $stationModel->printerPort);
            } else if ($stationModel->printerConnectionID == 5) {
                $connector = new CupsPrintConnector($stationModel->printerName);
            } else if ($stationModel->printerConnectionID == 7) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                AndroidPrintConnector::SUNMI_EXTERNAL, $stationModel->flagAutocut);
            } else if ($stationModel->printerConnectionID == 9) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                AndroidPrintConnector::USB, $stationModel->flagAutocut);
            } else if ($stationModel->printerConnectionID == 10) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                AndroidPrintConnector::WINTEC_EXTERNAL, $stationModel->flagAutocut);
            } else if ($stationModel->printerConnectionID == 11) {
                $flagOpenCashDrawer = self::getFlagOpenCashdrawer($refNum);
                $connector = new AndroidPrintConnector($stationModel->printerName,
                AndroidPrintConnector::SUNMI_T2S, $stationModel->flagAutocut, null, $flagOpenCashDrawer);
            } else if($stationModel->printerConnectionID == 12) {
                $connector = new AndroidPrintConnector($stationModel->printerName,
                AndroidPrintConnector::SNBC_BTP_S80, $stationModel->flagAutocut, null);
            }

            return $connector;
        } catch (Exception $ex) {
            if($scenario && $scenario === PrintChecker::SCENARIO_SELF_ORDER) {
                Logging::save($refNum, Logging::FAIL_OPEN_PRINTER_SELFORDER, $stationModel);
            } else {
                Logging::save($refNum, Logging::FAIL_OPEN_PRINTER, $stationModel);
            }
            Yii::warning($ex);
        }
    }

    // @Notes: Retry concept apparently no need. Keep for reference
//    public static function getConnectorByModel($stationModel, $retryAttempt = true) {
//        $maxTryAttempt = 1;
//        if ($retryAttempt) {
//            $maxTryAttempt = 3;
//        }
//
//        for ($tryAttempt = 1; $tryAttempt <= $maxTryAttempt; $tryAttempt++) {
//            try {
//                if ($stationModel->printerConnectionID == 1) {
//                    $connector = new NetworkPrintConnector($stationModel->printerName,
//                        $stationModel->printerPort, 2);
//                } else if ($stationModel->printerConnectionID == 2) {
//                    $connector = new WindowsPrintConnector($stationModel->printerName,
//                        2);
//                }
//
//                return $connector;
//            } catch (Exception $ex) {
//                Yii::warning($ex);
//                Yii::warning('Retry connection. Attempt: ' . $tryAttempt);
//            }
//        }
//    }

    public static function syncUpdate($stationID, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            Station::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['branchID' => $branchID], ['stationID' => $stationID]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

    private static function getFlagOpenCashdrawer($refNum){
        if(strlen($refNum) < 1){
            return 0;
        }
        if($refNum === "OpenDrawer"){
            return 1;
        }else{
            $query = (new Query())
                ->select("flagOpenCashDrawer")
                ->from(SalesPayment::tableName() . ' salesPayment')
                ->innerJoin(PaymentMethod::tableName() . ' payamentMethod', "payamentMethod.paymentMethodID = salesPayment.paymentMethodID")
                ->where(['salesPayment.salesNum' => $refNum, 'payamentMethod.flagOpenCashDrawer' => 1])
            ->one();
            return $query ? 1 : 0;
        }
    }

}
