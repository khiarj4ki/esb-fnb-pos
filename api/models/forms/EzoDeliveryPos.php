<?php

namespace app\models\forms;

use app\components\AppHelper;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\httpclient\Client;
use app\models\Setting;
use app\models\PosUser;
use app\models\Branch;
use app\models\EsoLogEvent;
use app\models\EsoProcessQueue;
use app\models\Station;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use yii\helpers\Url;
use app\models\SalesHead;
use app\models\ShiftLog;
use app\services\http_helper\HttpHelperService;

class EzoDeliveryPos extends Model
{

    public $username;
    public $actionUrl;
    public $ezoOrderID;
    public $stationID;
    public $printer;
    public $stationModel;
    public $ezoModel;
    public $isErrorLog;

    public function rules()
    {
        return [
            [['stationID', 'username', 'actionUrl', 'ezoOrderID', 'isErrorLog'], 'safe'],
            [['username', 'actionUrl'], 'string', 'max' => 50]
        ];
    }

    public function fetchEzoDeliveryPosData($bodyRequest)
    {
        $apiUrl = Setting::getApiUrl();
        $client = new Client();

        $authUsername = Yii::$app->params['restUsername'];
        $authPassword = Yii::$app->params['restPassword'];
        $response = $client->post($apiUrl . $this->actionUrl)
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$authUsername:$authPassword"),
                'data-auth-username' =>  $this->getPasswordSalt()['username'],
                'data-auth-password' =>  $this->getPasswordSalt()['password'],
                'data-auth-salt' =>  $this->getPasswordSalt()['salt']
            ])->addData($bodyRequest)->send();
            if ($response->getIsOk()) {
                $response = $response->getData();
                if ($this->isErrorLog) {
                    $response = array_merge([
                        'errorLog' => $response,
                        'salesOrder' => $this->fetchSales()
                    ]);
                }
                return $response;
            } else {
                throw new Exception("Failed to get data");
            }
    }

    public function fetchSales()
    {
        $shiftLog = ShiftLog::findActive();
        $date = $shiftLog ? date('Y-m-d', strtotime($shiftLog->shiftInTime)) : date('Y-m-d');
        $salesModel = SalesHead::findFinished()
            ->joinWith('table')
            ->joinWith('member')
            ->with('creator')
            ->with('editor')
            ->joinWith('status')
            ->joinWith('salesPayments.paymentMethod')
            ->andWhere(['salesDate' => $date])
            ->orderBy(['salesDateOut' => SORT_DESC]);

        $salesListFs = [];
        $salesListQs = [];
        foreach ($salesModel->all() as $sales) {
            $salesArr['salesNum'] = $sales->salesNum;
            $salesArr['billNum'] = $sales->billNum;
            $salesArr['salesDate'] = $sales->salesDate;
            $salesArr['memberName'] = $sales->flagExternalAPI == 1 ? 'External Member' : ($sales->member ? $sales->member->memberName : 'Non Member');
            $salesArr['tableName'] = $sales->table ? $sales->table->tableName : 'Quick Service';
            $salesArr['paxTotal'] = $sales->paxTotal;
            $salesArr['grandTotal'] = $sales->grandTotal;
            $salesArr['statusName'] = $sales->status->statusName;
            $salesArr['salesDateIn'] = date('d/m/Y H:i:s', strtotime(str_replace("-", "/", $sales->salesDateIn)));
            $salesArr['creator'] = SalesHead::getCreatorEditor($sales->createdBy, $sales->creator);
            $salesArr['roundingTotal'] = $sales->roundingTotal;
            $salesArr['editor'] = SalesHead::getCreatorEditor($sales->editedBy, $sales->editor);
            $salesArr['salesDateOut'] =  date('d/m/Y H:i:s', strtotime(str_replace("-", "/", $sales->salesDateOut)));

            $paymentMethods = '';
            $selfOrderIDs = '';
            foreach ($sales->salesPayments as $salesPayment) {
                $paymentMethods .= $salesPayment->paymentMethod->paymentMethodName . ', ';
            }
            if (strlen($paymentMethods) > 0) {
                $paymentMethods = substr($paymentMethods, 0, strlen($paymentMethods) - 2);
            }

            foreach ($sales->salesPayments as $salesPayment) {
                if ($salesPayment->selfOrderID) {
                    $selfOrderIDs .= $salesPayment->selfOrderID . ', ';
                }
            }
            if (strlen($selfOrderIDs) > 0) {
                $selfOrderIDs = substr($selfOrderIDs, 0, strlen($selfOrderIDs) - 2);
            }

            $salesArr['paymentMethods'] = $paymentMethods;
            $salesArr['selfOrderIDs'] = $selfOrderIDs;
            $salesArr['transactionMode'] = null;
            if (strlen($selfOrderIDs) > 0) {
                if ($sales->tableID > 0) {
                    $salesListFs[] = $salesArr;
                } else {
                    if ($sales->transactionModeID === 1) {
                        $salesArr['transactionMode'] = 'Dine In';
                    } else if($sales->transactionModeID === 2) {
                        $salesArr['transactionMode'] = 'Pick Up';
                    } else if($sales->transactionModeID === 3) {
                        $salesArr['transactionMode'] = 'Delivery';
                    } else if($sales->transactionModeID === 4) {
                        $salesArr['transactionMode'] = 'Custom';
                    } else if($sales->transactionModeID === 5) {
                        $salesArr['transactionMode'] = 'Grab';
                    }
                    $salesListQs[] = $salesArr;
                }
            }
        }
        return [
            'fS' => $salesListFs,
            'qS' => $salesListQs
        ];
    }

    public function printEzoDeliveryId()
    {
        try {
            $apiVersion = 'esb_api';
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl() . '/' . $apiVersion . '/ezo-delivery/print-label?id=' . $this->ezoOrderID;

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl;
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $requestBody = [];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $requestBody, $options);

            if ($response->getIsOk()) {
                $data = $response->getData();
                $this->ezoModel = $data;
                $this->doPrint();
                return true;
            } else {
                Yii::warning($response->getData());
                throw new Exception('Failed to fetch data');
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return false;
        }
    }

    public function doPrint()
    {
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findActive()
            ->andWhere(['branchID' => $branchID])
            ->one();
        $this->stationModel = Station::findActive()
            ->andWhere(['stationID' => $this->stationID])
            ->one();

        try {
            $connector = Station::getConnectorByModel(
                $this->stationModel,
                null
            );

            if($connector == null){
                throw new Exception("Failed to print. Connector not found", 400);
            }

            $this->printer = new Printer($connector);
            $printer = $this->printer;
            $charLength = $this->stationModel->characterPerLine;

            $printer->text(str_pad(Yii::t('app', $this->ezoModel['ID']), $charLength, ' '));
            $printer->feed(1);
            $printer->text(str_pad(Yii::t('app', $branchModel->branchName), $charLength, ' '));
            $printer->feed(2);

            $printer->text(str_pad(Yii::t('app', 'Name'), 7, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($this->ezoModel['fullName'], $charLength, ' '));
            $printer->feed(1);

            $printer->text(str_pad(Yii::t('app', 'Address'), 7, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($this->ezoModel['address'], $charLength, ' '));
            $printer->feed(2);

            $printer->text(str_pad(Yii::t('app', 'Phone'), 7, ' '));
            $printer->text(' : ');
            $printer->text(str_pad($this->ezoModel['phoneNumber'], $charLength - 7, ' '));
            $printer->feed(1);

            $this->generateBarcode($this->ezoModel['ID'], $printer);
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $printer->feed(1);
            $printer->cut();

            $printer->close();
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function generateBarcode($externalCode, $printer)
    {
        $sizeBarCode = 120;
        $filePath = Yii::$app->basePath . '/web/images/tempQR.png';
        $fileUrl = file_get_contents(Url::to('@web/phpbarcode/barcode-generator.php?codetype=Code128&size=' . $sizeBarCode . '&text=' . $this->ezoModel['ID'], true));
        file_put_contents($filePath, $fileUrl);
        $img = EscposImage::load($filePath);
        $printer->bitImage($img);
        unlink($filePath);
    }

    private function getPasswordSalt()
    {
        $posUser = PosUser::find()->where(['username' => $this->username])->one();
        return [
            'username' => $this->username,
            'password' => $posUser->password,
            'salt' => $posUser->salt
        ];
    }

    public function validateRetry($body)
    {
        $isRetry = isset($body['isRetry']) ? $body['isRetry'] : 0;
        if ($isRetry) {
            // @delete queue for retry
            EsoProcessQueue::deleteAll(['AND',
                ['orderID' => $body['ezoOrderID']],
                ['status' => EsoProcessQueue::PENDING]
            ]);

            // @success log event eso error
            EsoLogEvent::updateAll(
                ['isSuccess' => 1],
                ['refNum' => $body['ezoOrderID']]
            );
        }
    }
}
