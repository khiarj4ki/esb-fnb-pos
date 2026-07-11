<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\components\ExtPrinter;
use app\models\Branch;
use app\models\BrandSetting;
use app\models\SalesHead;
use app\models\SalesMergeTable;
use app\models\Setting;
use app\models\Station;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Yii;
use yii\base\Model;
use yii\httpclient\Exception;
use app\models\forms\SyncSelfOrder;
use app\models\SalesLink;

/**
 * @property int $tableID
 * @property int $stationID
 * 
 * PRIVATE
 * @property Printer $printer
 * @property Station $stationModel
 * @property SalesHead $salesModel
 */
class PrintQRTransaction extends Model
{
    public $tableID;
    public $salesNum;
    public $stationID;
    public $printer;
    public $stationModel;
    public $salesModel;
    public $settings;
    public $printResult;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tableID', 'salesNum', 'stationID'], 'required'],
            [['tableID', 'stationID'], 'integer'],
            [['tableID'], 'validateTable']
        ];
    }

    public function validateTable($attribute)
    {
        $this->salesModel = SalesHead::findOutstandingOrder()
            ->with('table.tableSection')
            ->andWhere([
                'OR',
                [SalesHead::tableName() . '.tableID' => $this->tableID],
                [SalesMergeTable::tableName() . '.tableID' => $this->tableID]
            ])
            ->andWhere([SalesHead::tableName() . '.salesNum' => $this->salesNum])
            ->one();
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID');
        }
    }

    public function doPrint()
    {
        if (!$this->validate()) {
            return false;
        }
        $this->settings = Setting::getEZOSetting();
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

        $filename = Yii::$app->basePath . '/web/assets_b/images/' . md5(uniqid(
            rand(),
            true
        )) . '.png';
        try {
            $newSalesModel =  SalesHead::findOne($this->salesModel->salesNum);
            if ($newSalesModel) {
                $newSalesModel->printEsoFsQr = 1;
                $newSalesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
                if (!$newSalesModel->save()) {
                    return false;
                } else {
                    if ($this->settings['Activate EZO'] == 1) {
                        $syncSalesNums = [];
                        $syncSalesNums[] = $this->salesModel->salesNum;

                        $mainLinks = SalesLink::find()
                            ->select(['salesNum'])
                            ->andWhere(['linkSalesNum' => $this->salesModel->salesNum])
                            ->column();

                        $childLinks = SalesLink::find()
                            ->select(['linkSalesNum'])
                            ->andWhere(['salesNum' => $this->salesModel->salesNum])
                            ->column();

                        if (!empty($mainLinks)) {
                            foreach ($mainLinks as $mainLinkSalesNum) {
                                $syncSalesNums[] = $mainLinkSalesNum;
                            }
                        }

                        if (!empty($childLinks)) {
                            foreach ($childLinks as $childLinkSalesNum) {
                                $syncSalesNums[] = $childLinkSalesNum;
                            }
                        }

                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            SalesHead::updateAll([
                                'printEsoFsQr' => $newSalesModel->printEsoFsQr,
                                ], ['IN', 'salesNum', $syncSalesNums]
                            );

                            foreach ($syncSalesNums as $salesNum) {
                                $salesModel = SalesHead::find()->where(['salesNum' => $salesNum])->one();
                                if($salesModel){
                                    $result = AppHelper::sendSales($salesModel, $salesNum);
                                    if (!$result->getIsOk()) {
                                       
                                        throw new Exception($result->getData()['message']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }

        try {
            require_once(Yii::$app->basePath . '/web/phpqrcode/qrlib.php');
            $qrText = AppHelper::encryptSalesNum($this->salesModel->salesNum);
            $charLength = $this->stationModel->characterPerLine;
            $qrSize = 6;

            if ($charLength > 37) {
                $qrSize = 8;
            }
            if ($charLength >= 33 && $charLength < 38) {
                $qrSize = 7;
            }

            \QRcode::png(BrandSetting::getBrandSetting('EZO', 'Frontend Url') . '/' . $branchModel->companyCode . '/' . $branchModel->branchCode .'/' . $qrText . '/order',
                $filename, 'L', $qrSize, 0);
            $connector = Station::getConnectorByModel($this->stationModel,
                    $this->salesModel->salesNum);
            
            $isErrorConnector = false;
            if ($connector !== null) {
                $this->printer = new Printer($connector);
                $printer = $this->printer;
                if ($this->stationModel->printerTypeID == 15) {
                    $printer = new ExtPrinter($connector);
                }
    
                $this->printHeader($branchModel);
    
                $printer->text(str_pad(Yii::t('app', 'Table'), 7, ' '));
                $printer->text(' : ');
                $printer->text($this->salesModel->table->tableName);
                $printer->text(str_pad(
                    '',
                    $charLength - (20 + strlen($this->salesModel->table->tableName) + strlen($this->salesModel->paxTotal)),
                    ' '
                ));
                $printer->text(str_pad(Yii::t('app', 'Pax'), 7, ' '));
                $printer->text(' : ');
                $printer->text($this->salesModel->paxTotal);
                if ($this->stationModel->printerTypeID != 15) {
                    $printer->feed(2);
                } else {
                    $printer->feed(1);
                }
    
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $img = EscposImage::load($filename);
                if ($this->stationModel->printerTypeID == 15) {
                    $printer->bitImageMpop($img);
                } else {
                    $printer->bitImage($img);
                }
                unlink($filename);
                $printer->feed(1);
                $printer->text('SCAN ME TO ORDER');
                $printer->feed(1);
                if (isset($this->settings['QR Footer Text'])) {
                    $printer->text($this->settings['QR Footer Text']);
                }
                if ($this->stationModel->printerTypeID != 15) {
                    $printer->feed(2);
                } else {
                    $printer->feed(1);
                }
                
                $printer->setJustification();
                if ($this->stationModel->printerTypeID == 15) {
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
            unlink($filename);
        }
    }

    private function printHeader($branchModel)
    {
        $printer = $this->printer;
        $charLength = $this->stationModel->characterPerLine;

        if ($this->stationModel->printerTypeID == 15) {
            $printer->getPrintConnector()->write("\x1B" . "\x1D" . "\x61" . "1");
        } else {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
        }
        foreach (explode('>><<', $branchModel->printingHeader) as $lineHeader) {
            $printer->text($lineHeader);
            $printer->feed(1);
        }
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text(str_pad('', $charLength, '-'));
        $printer->feed(1);
    }
}
