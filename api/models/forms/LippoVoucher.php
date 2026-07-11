<?php

namespace app\models\forms;

use yii\base\Model;
use app\models\BrandApiContent;
use app\models\Setting;
use app\models\BrandSetting;
use app\models\Brand;
use Yii;
use Exception;
use yii\httpclient\Client;

/**
 * @property SalesHead $salesModel
 */
class LippoVoucher extends Model
{
    const LIPPO_PARKING_VOUCHER_URL = 'Lippo Parking Voucher URL';
    const LIPPO_PARKING_VOUCHER_TOKEN = 'Lippo Parking Voucher Token';

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['salesModel',], 'required'],
        ];
    }

    private static function getToken($brandID, $brandPosSetting, $companyAuthKey, $key)
    {
        try {
            $grantType = 'password';
            $username = Setting::getValue1('POS', 'Lippo Parking Username');
            $password = Yii::$app->security->decryptByKey(base64_decode(Setting::getValue1('POS', 'Lippo Parking Password')), $companyAuthKey);
            $settingModel = Setting::getSetting('Local Setting', $key);
            $brandApiContentModel = BrandApiContent::findApiContent($brandID, SELF::LIPPO_PARKING_VOUCHER_TOKEN);

            $bodyRequest = [];
            foreach ($brandApiContentModel->all() as $tokenContent) {
                $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
            }
            $bodyRequest['grant_type'] = $grantType;
            $bodyRequest['username'] = $username ? $username : '';
            $bodyRequest['password'] = $username ? $password : '';

            $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
            $tokenApiUrl = $brandPosSetting[SELF::LIPPO_PARKING_VOUCHER_TOKEN];
            $result = $client->post($tokenApiUrl)
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->setFormat(Client::FORMAT_URLENCODED)
                ->setOptions([
                    CURLOPT_FRESH_CONNECT => TRUE,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 5
                ])
                ->addData(
                    $bodyRequest
                )->send();
            $response = $result->getData();
            if ($result->getIsOk()) {
                $token =  $response['access_token'];
                $settingModel->value1 = $token;
                if ($settingModel->save()) {
                    return $token;
                } else {
                    throw $settingModel->getErrors();
                }
            }
        } catch (Exception $ex) {
            $exMessage = $ex->getMessage();
            throw new Exception($exMessage);
        }
    }

    public static function saveVoucher($salesModel)
    {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if (!$brandModel) {
            throw new Exception("Brand Not Found", 1);
        }
        $brandPosSetting = BrandSetting::getBrandPosSetting();

        $authToken = Setting::getTokenQrLippoParking();
        $maxAttempts = 2;
        $numOfAttempts = $maxAttempts + 1;
        $attempts = 0;
        $dataLogging = null;
        $transactionModeID = $salesModel['transactionModeID'];
        $salesNum = $salesModel['salesNum'];
        $billNum = $salesModel['billNum'];
        $subtotal = intval($salesModel['subtotal']);
        do {
            try {
                if ($authToken === null || $authToken === '') {
                    $authToken = SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, 'Lippo Parking Voucher Token');
                }
                $transactionApiUrl = $brandPosSetting[SELF::LIPPO_PARKING_VOUCHER_URL];
                $params = "?transactionNumber=$billNum&amount=$subtotal";
                $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
                $result = $client->get($transactionApiUrl . $params)
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $authToken,
                    ])
                    ->setFormat(Client::FORMAT_JSON)
                    ->setOptions([
                        CURLOPT_FRESH_CONNECT => TRUE,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_TIMEOUT => 5
                    ])
                    ->send();

                $dataLogging['response'] = json_decode($result->getContent(), true);
                $response = $result->getData();
                if ($result->getIsOk()) {
                    if ($response['Code'] === 0) {
                        return $response['voucherLinkData'];
                    }
                } else {
                    $errMsg = isset($response['Message']) ? $response['Message'] : 'Server Unreachable';
                    throw new Exception($errMsg);
                }
            } catch (Exception $ex) {
                $eventSubject =  in_array($transactionModeID, [1, 2, 13]) || $salesModel['printEsoFsQr'] === 1 ? Logging::FAIL_GENERATE_PARKING_VOUCHER_SELFORDER : (($transactionModeID == null && $salesModel['additionalInfo'] == 'QRIS') ? Logging::FAIL_GENERATE_PARKING_VOUCHER_KIOSK : Logging::FAIL_GENERATE_PARKING_VOUCHER);

                if ($ex->getMessage() === 'Authorization has been denied for this request.' && $attempts < $maxAttempts - 1) {
                    $dataLogging['billNum'] = $billNum;
                    $dataLogging['subtotal'] = $subtotal;
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save($salesNum, $eventSubject, $dataLogging);
                    $authToken = SELF::getToken($brandModel->brandID, $brandPosSetting, $companyAuthKey, 'Lippo Parking Voucher Token');
                    $attempts++;
                    sleep(1);
                    continue;
                } else if (strpos($ex->getMessage(), 'Curl error: #28') !== false) {
                    $errorMessage = 'Failed to generate parking voucher, Unable to Connect to Voucher Provider';
                    $dataLogging['billNum'] = $billNum;
                    $dataLogging['subtotal'] = $subtotal;
                    $dataLogging['response'] = $errorMessage;
                    Logging::save($salesNum, $eventSubject, $dataLogging);
                } else if ((strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false || strpos($ex->getMessage(), 'php_network_getaddresses') !== false)) {
                    $errorMessage = "Failed to generate parking voucher, No internet connection";
                    $dataLogging['billNum'] = $billNum;
                    $dataLogging['subtotal'] = $subtotal;
                    $dataLogging['response'] = $errorMessage;
                    Logging::save($salesNum, $eventSubject, $dataLogging);
                    $dataLogging['status'] = [
                        'status' => false,
                        'message' => $errorMessage
                    ];
                    return $dataLogging['status'];
                } else {
                    $dataLogging['billNum'] = $billNum;
                    $dataLogging['subtotal'] = $subtotal;
                    $dataLogging['response'] = $ex->getMessage();
                    Logging::save($salesNum, $eventSubject, $dataLogging);
                }
            }
            break;
        } while ($attempts < $numOfAttempts);
    }
}
