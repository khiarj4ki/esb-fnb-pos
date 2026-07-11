<?php

namespace app\models;

use app\models\Branch;
use app\models\forms\Logging;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\httpclient\Client;

/**
 * This is the model class for table "ms_voucher".
 *
 * @property string $voucherID
 * @property string $voucherSortID
 * @property int $voucherTypeID
 * @property int $voucherLength
 * @property string $voucherStartDate
 * @property string $voucherEndDate
 * @property int $createdBranchID
 * @property int $usedBranchID
 * @property string $usedDate
 * @property string $salesNum
 * @property string $minimumSalesAmount
 * @property string $voucherAmount
 * @property string $voucherPercentage
 * @property string $voucherSalesPrice
 * @property string $notes
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * @property string $flagVoucherTemplate
 * @property string $refBillNum
 */
class Voucher extends ActiveRecord {
    CONST ERR_NO_INTERNET_CONNECTION = "You seem to be offline. Please check your internet connection and try again.";

    public $currentOrder;
    public $voucherCodes;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_voucher';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['voucherID', 'voucherSortID', 'voucherTypeID', 'voucherLength', 'createdBranchID', 'minimumSalesAmount', 'voucherAmount', 
                'voucherPercentage', 'voucherSalesPrice', 'flagActive', 'createdBy', 'createdDate', 'flagVoucherTemplate'], 'required'],
            [['voucherTypeID', 'voucherLength', 'createdBranchID', 'usedBranchID', 'flagActive'], 'integer'],
            [['voucherStartDate', 'voucherEndDate', 'usedDate', 'createdDate', 'editedDate', 'syncDate', 'createdFrom', 'currentOrder', 
                'voucherCodes', 'salesModel'], 'safe'],
            [['minimumSalesAmount', 'voucherAmount', 'voucherPercentage', 'voucherSalesPrice'], 'number'],
            [['voucherID', 'salesNum'], 'string', 'max' => 20],
            [['voucherSortID'], 'string', 'max' => 10],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['notes'], 'default', 'value' => ''],
            [['voucherID'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'voucherID' => 'Voucher ID',
            'voucherSortID' => 'Voucher Sort ID',
            'voucherTypeID' => 'Voucher Type ID',
            'voucherLength' => 'Voucher Length',
            'voucherStartDate' => 'Voucher Start Date',
            'voucherEndDate' => 'Voucher End Date',
            'createdBranchID' => 'Created Branch ID',
            'usedBranchID' => 'Used Branch ID',
            'usedDate' => 'Used Date',
            'salesNum' => 'Sales Num',
            'minimumSalesAmount' => 'Minimum Sales Amount',
            'voucherAmount' => 'Voucher Amount',
            'voucherPercentage' => 'Voucher Percentage',
            'voucherSalesPrice' => 'Voucher Sales Price',
            'notes' => 'Notes',
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
        $fields['minimumSalesAmount'] = function ($model) {
            return (float) $model->minimumSalesAmount;
        };
        $fields['voucherAmount'] = function ($model) {
            return (float) $model->voucherAmount;
        };
        $fields['voucherPercentage'] = function ($model) {
            return (float) $model->voucherPercentage;
        };
        $fields['voucherSalesPrice'] = function ($model) {
            return (float) $model->voucherSalesPrice;
        };
        $fields['estimatedExpiredDate'] = function ($model) {
            return (string) date('Y-m-d', strtotime(' + ' . $model->voucherLength . ' days'));
        };
        return $fields;
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public static function findActive() {
        return Voucher::find()
                ->andWhere('DATE(NOW()) BETWEEN voucherStartDate and voucherEndDate')
                ->andWhere(['flagActive' => 1]);
    }

    public static function findNotActive() {
        return Voucher::find()
                ->andWhere(['IS', 'voucherStartDate', null])
                ->andWhere(['IS', 'voucherEndDate', null])
                ->andWhere(['flagActive' => 1])
                ->orderBy('voucherSortID');
    }

    public static function validateID($voucherID, $subtotal, $salesNum) {
        $settings = Setting::getPrintingSettings();
        $purchaseVoucher = 'offline';
        if (array_key_exists('Voucher Management', $settings)) {
            $purchaseVoucher = $settings['Voucher Management'];
        }

        if ($purchaseVoucher == 'offline') {
            $message = '';
            $model = Voucher::find()
                ->andWhere(['voucherID' => $voucherID])
                ->one();
    
            $today = date("Y-m-d");
            if (!$model) {
                $message = 'Voucher not found';
            } else if (is_null($model->voucherStartDate)) {
                $message = 'Voucher has not been activated';
            } else if (!is_null($model->usedDate)) {
                $message = 'Voucher has been used';
            } else if ($model->voucherStartDate > $today) {
                $message = 'Voucher can be used start from ' . date_format(date_create($model->voucherStartDate),
                        'd-m-Y');
            } else if (!is_null($model->voucherEndDate) && $model->voucherEndDate < $today) {
                $message = 'Voucher has been expired since ' . date_format(date_create($model->voucherEndDate),
                        'd-m-Y');
            } else if ($subtotal < $model->minimumSalesAmount) {
                $message = 'Voucher cannot be used. Minimum transaction is ' . number_format($model->minimumSalesAmount,
                        0, ',', '.');
            }
    
            if ($message != '') {
                return [
                    'status' => false,
                    'message' => $message,
                    'voucher' => null
                ];
            }
    
            return [
                'status' => true,
                'message' => 'Voucher is valid',
                'voucher' => $model
            ];
        } else if ($purchaseVoucher == 'online') {
            try {
                $apiUrl = Setting::getApiUrl();
                $apiKey = Setting::getApiKey();
                $branchID = Setting::getCurrentBranch();
                 // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $apiUrl . "/esb_api/voucher/validate-online-voucher-pos-payment";
                $headers = ['Authorization' => 'Bearer ' . $apiKey];
                $data =   [
                    "voucherID" => $voucherID,
                    "subtotal" => $subtotal,
                    "branchID" => $branchID,
                    "salesNum" => $salesNum,
                    "requestType" => "pos-validate"
                ];
                $options = ['timeOut' => 300];
                $response = $httpService->post($url, $headers, $data, $options);

                if (!$response->getIsOk()) {
                    return [
                        'status' => false,
                        'message' => 'Server Unreachable',
                        'voucher' => null
                    ];
                }

                $responseData = $response->getData();
                if ($response->statusCode == "200") {
                    $decodedResponse = json_decode($response->content, true);
                    if (isset($decodedResponse['status']) && $decodedResponse['status'] == '00') {
                        $voucher = $decodedResponse['voucher'];
                        if ($voucher['voucherAmount']) {
                            $voucher['voucherAmount'] = (float) $voucher['voucherAmount'];
                        }
                        if ($voucher['voucherSalesPrice']) {
                            $voucher['voucherSalesPrice'] = (float) $voucher['voucherSalesPrice'];
                        }
                        if ($voucher['voucherPercentage']) {
                            $voucher['voucherPercentage'] = (float) $voucher['voucherPercentage'];
                        }
                        if ($voucher['minimumSalesAmount']) {
                            $voucher['minimumSalesAmount'] = (float) $voucher['minimumSalesAmount'];
                        }
                        if ($voucher['maxVoucherAmount']) {
                            $voucher['maxVoucherAmount'] = (float) $voucher['maxVoucherAmount'];
                        }
                        return [
                            'status' => true,
                            'message' => 'Voucher is valid',
                            'voucher' => $voucher
                        ];
                    } else {
                        return [
                            'status' => false,
                            'message' => isset($decodedResponse['message']) ? $decodedResponse['message'] : 'Server Unreachable',
                            'voucher' => null
                        ];
                    }
                } else {
                    return [
                        'status' => false,
                        'message' => isset($responseData['message']) ? $responseData['message'] : 'Server Unreachable',
                        'voucher' => null
                    ];
                }
            } catch (\Exception $ex) {
                Yii::error($ex->getMessage());
                $errMsg = $ex->getMessage();
                if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                    $errMsg = self::ERR_NO_INTERNET_CONNECTION;
                }
                return [
                    'status' => false,
                    'message' => $errMsg,
                    'voucher' => null
                ];
            }
        }
    }

    public static function validateVoucherFreeItem($voucherID, $promotionMasterCode) {
        $VoucherManagementSetting = Setting::getSetting('POS', 'Voucher Management');
        $VoucherManagementSetting = $VoucherManagementSetting ? $VoucherManagementSetting->value1 : 'offline';
        $data = ['status' => false, 'voucher' => null, 'message' => null];

        try {
            if ($VoucherManagementSetting == 'offline') {
                throw new Exception("Cannot use this feature");
            }
            $apiUrl = Setting::getApiUrl();
            $apiKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . "/esb_api/voucher/validate-online-voucher-free-item-pos-payment";
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $data = [
                "voucherID" => $voucherID,
                "promotionMasterCode" => $promotionMasterCode,
                "branchID" => $branchID
            ];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $data, $options);
            
            $response = $result->getData();
            if ($result->getIsOk() && isset($response['status'])) {
                if ($response['status'] == '00') {
                    $voucher = $response['voucher'];
                    $voucher['voucherName'] = $response['voucher']['notes'];

                    $data['status'] = true;
                    $data['voucher'] = $voucher;
                } else {
                    $data['status'] = false;
                    $data['message'] = $response['message'] ? $response['message'] : 'Server is unreachable';
                }
            } else {
                throw new Exception($response['message'] ? $response['message'] : 'Server is unreachable');
            }
        } catch(\Exception $ex) {
            Yii::error($ex->getMessage());
            $data['status'] = false;
            $data['message'] = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $data['message'] = self::ERR_NO_INTERNET_CONNECTION;
            }
        }
        return $data;
    }

    public static function validateEsbVoucher($currentVoucherID, $voucherIDs, $salesPaymentTotal, $salesNum) {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);
        $client = new Client(['baseUrl' => $apiUrl]);
        try {

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . '/esb-order/esb-voucher/validate';
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Data-Branch' => $branchModel->branchCode
            ];
            $datas = [
                "voucherIDs" => $voucherIDs,
                "salesPaymentTotal" => $salesPaymentTotal,
                "salesNum" => $salesNum
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            $voucher = null;
            $message = null;
            $tempVoucherArray = [];
            if ($response->getIsOk()) {
                $response = $response->getData();
                if ($response['data']) {
                    $voucher = $response['data'];
                    foreach ($voucher as $voucherData) {
                        if (isset($voucherData['voucherID'])) {
                            $tempVoucherArray[$voucherData['voucherID']] = $voucherData;
                            if ($voucherData['voucherAmount']) {
                                $tempVoucherArray[$voucherData['voucherID']]['voucherAmount'] = (float) $voucherData['voucherAmount'];
                            }
                            if ($voucherData['minimumSalesAmount']) {
                                $tempVoucherArray[$voucherData['voucherID']]['minimumSalesAmount'] = (float) $voucherData['minimumSalesAmount'];
                            }
                        } else {
                            if ($voucher['voucherAmount']) {
                                $voucher['voucherAmount'] = (float) $voucher['voucherAmount'];
                            } 
                            if ($voucher['minimumSalesAmount']) {
                                $voucher['minimumSalesAmount'] = (float) $voucher['minimumSalesAmount'];
                            }
                        }
                    }
                    $message = $response['message'];
                }
            } else {
                $response = $response->getData();
                SELF::throwErrorValidateEsbVoucher(
                    $response,
                    $currentVoucherID,
                    $voucherIDs
                );
            }
            if ($tempVoucherArray) $voucher = array_values($tempVoucherArray);

            return [
                'status' => $message,
                'voucher' => $voucher
            ];
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return [
                'code' => $ex->getCode(),
                'status' => $ex->getMessage(),
                'voucher' => null
            ];
        }
    }

    private static function throwErrorValidateEsbVoucher(
        $response,
        $currentVoucherID,
        $voucherIDs
    ) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting'])
            ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting'])
            ? $settings['Sales Decimal Separator Setting'] : ',';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';

        $statusCode = 404;
        $responseMessage = isset($response['message']) ? $response['message'] : "FAILED_CONNECT_EXTERNAL";
        $responseData = isset($response['data']) ? $response['data'] : null;
        $messagesForDirectThrow = ['VOUCHER_NOT_FOUND', 'VOUCHER_HAS_BEEN_USED', 'VOUCHER_HAS_BEEN_EXPIRED'];
        $message = null;

        if (count($voucherIDs) == 1) {
            if (!in_array($responseMessage, $messagesForDirectThrow)) {
                $statusCode = 400;
                if (is_array($responseData)) {
                    $message = SELF::transformErrorDataToMessage(
                        $responseData[0],
                        $salesDecimalSetting,
                        $salesDecimalSeparatorSetting,
                        $reverseDecimalSeparator
                    );
                }
            }
            throw new Exception($message ? $message : $responseMessage, [], $statusCode);
        } else {
            if (is_array($responseData)) {
                //check currentVoucherID
                foreach ($responseData as $voucher) {
                    $message = $voucher['errorCode'];
                    if ($voucher['voucherID'] == $currentVoucherID) {
                        if (!in_array($message, $messagesForDirectThrow)) {
                            $statusCode = 400;
                            $message = SELF::transformErrorDataToMessage(
                                $voucher,
                                $salesDecimalSetting,
                                $salesDecimalSeparatorSetting,
                                $reverseDecimalSeparator
                            );
                        }
                        throw new Exception($message, [], $statusCode);
                    }
                }

                //check list vouchers
                $messageArr = [];
                foreach($responseData as $voucher) {
                    $message = SELF::transformErrorDataToMessage(
                        $voucher,
                        $salesDecimalSetting,
                        $salesDecimalSeparatorSetting,
                        $reverseDecimalSeparator,
                        false
                    );
                    array_push($messageArr, $message);
                }
                throw new Exception("ANOTHER_VOUCHER_INVALID" . implode("<br>", $messageArr), [], 400);
            }
        }
        throw new Exception($responseMessage, [], 500);
    }

    private static function transformErrorDataToMessage(
        $voucher,
        $salesDecimalSetting,
        $salesDecimalSeparatorSetting,
        $reverseDecimalSeparator,
        $validateCurrentVoucher = true
    ) {
        $message = $validateCurrentVoucher
            ? $voucher['errorCode']
            : $voucher['voucherID'] . " - " . strtolower(str_replace('_', ' ', $voucher['errorCode']));
        
            if ($voucher['errorData']) {
            if ($voucher['errorCode'] == "IS_REQUIRED") {
                $message = $voucher['errorData'];
            } elseif ($voucher['errorCode'] == "VOUCHER_HAS_BEEN_LOCKED") {
                $message = $voucher['errorCode'] . ' ' . substr($voucher['errorData']['lockTime'], 10, 6);
            } elseif ($voucher['errorCode'] == "MINIMUM_SALES_AMOUNT") {
                Yii::warning([
                    $voucher['errorData']['minimumSalesAmount'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator"
                ]);
                $minimumSalesAmount = number_format($voucher['errorData']['minimumSalesAmount'],
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator");
                $message = $voucher['errorCode'] . ' ' . $minimumSalesAmount;
            }
        }
        return $message;
    }

    public static function lockingVoucher($voucherID, $salesNum) {
        $voucherIDs[] = $voucherID;
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);

        try {
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . '/esb-order/esb-voucher/lock';
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Data-Branch' => $branchModel->branchCode
            ];
            $datas = [
                "voucherIDs" => $voucherIDs,
                "salesNum" => $salesNum,
                "lockTime" => 15
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
            
            $message = '';
            $responses = $response->getData();
            if ($response->getIsOk()) {
                $message = $responses['message'];
            } else {
                if (isset($responses['code'])) {
                    throw new Exception($responses['message']);
                } else {
                    throw new Exception("FAILED_CONNECT_EXTERNAL");
                }
            }

            return [
                'status' => true,
                'message' => $message
            ];
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return [
                'status' => false,
                'message' => $ex->getMessage()
            ];
        }
    }

    public static function unlockingVoucher($voucherID, $salesNum) {
        $voucherIDs[] = $voucherID;
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);

        try {

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . '/esb-order/esb-voucher/unlock';
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Data-Branch' => $branchModel->branchCode
            ];
            $datas = [
                "voucherIDs" => $voucherIDs,
                "salesNum" => $salesNum
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);
            
            $message = '';
            $responses = $response->getData();
            if ($response->getIsOk()) {
                $message = $responses['message'];
            } else {
                if (isset($responses['code'])) {
                    throw new Exception($responses['message']);
                } else {
                    throw new Exception("FAILED_CONNECT_EXTERNAL");
                }
            }

            return [
                'status' => true,
                'message' => $message
            ];
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return [
                'status' => false,
                'message' => $ex->getMessage()
            ];
        }
    }

    public static function claimEsbVoucher($vouchers, $salesNum, $salesPaymentTotal) {
        $tempError = null;
        try {
            $apiUrl = Setting::getApiUrl();
            $apiKey = Setting::getApiKey();
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne($branchID);
    
            $status = true;
            $voucherList = [];
            foreach ($vouchers as $voucher) {
                $voucherList[] = $voucher->voucherCode;
            }
    
            $bodyRequest = [
                "voucherIDs" => $voucherList,
                "salesNum" => $salesNum,
                "salesPaymentTotal" => $salesPaymentTotal
            ];
    
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . '/esb-order/esb-voucher/burn';
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Data-Branch' => $branchModel->branchCode
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $bodyRequest, $options);

            $responseContent = json_decode($response->getContent(), true);
            if (!$response->getIsOk()) {
                $status = false;
                $response = $response->getData();
                if (isset($response['data'])) {
                    foreach ($response['data'] as $log) {
                        if ($log['errorCode'] == 'VOUCHER_HAS_BEEN_EXPIRED') {
                            $tempError = (object) array(
                                'errorCode' => $log['errorCode'],
                                'voucherStartDate' => $log['errorData']['voucherStartDate'],
                                'voucherEndDate' => $log['errorData']['voucherEndDate'],
                            );
                        } else if ($log['errorCode'] == 'VOUCHER_HAS_BEEN_USED') {
                            $tempError = (object) array(
                                'errorCode' => $log['errorCode'],
                                'usedDate' => $log['errorData']['usedDate']
                            );
                        } else if ($log['errorCode'] == 'IS_REQUIRED') {
                            $tempError = $log['errorData'];
                        } else {
                            $tempError = (object) array(
                                'errorCode' => $log['errorCode']
                            );
                        }
                    }
                    throw new Exception("VOUCHER_INVALID");
                }
            }

            $dataLogging = [
                'body' => $bodyRequest,
                'response' => $responseContent
            ];

            if ($status) Logging::save($salesNum, Logging::BURN_EXTERNAL_VOUCHER, $dataLogging);
            
            return (object) array(
                'status' => $status,
                'error' => $tempError
            );
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return (object) array(
                'status' => false,
                'error' => $tempError
            );
        }
    }

    public static function activate($voucherID) {
        $message = '';
        $voucherModel = Voucher::findNotActive()
            ->andWhere(['voucherID' => $voucherID])
            ->one();
        if (!$voucherModel) {
            Yii::error($voucherID);
            $message = 'Voucher not found';
        }

        if ($message == '') {
            $voucherModel->voucherStartDate = new Expression('DATE(NOW())');
            $voucherModel->voucherEndDate = new Expression('DATE_ADD(DATE(NOW()), INTERVAL voucherLength DAY)');
            if (!$voucherModel->save()) {
                Yii::error($voucherModel->errors);
                $message = 'Failed to update voucher';
            }
        }

        if ($message != '') {
            return [
                'status' => false,
                'message' => $message
            ];
        }

        return [
            'status' => true,
            'message' => ''
        ];
    }

    public static function claim($voucherID, $salesModel) {
        $message = '';
        $voucherModel = Voucher::findActive()
            ->andWhere(['voucherID' => $voucherID])
            ->andWhere(['IS', 'usedDate', NULL])
            ->andWhere(['IS', 'usedBranchID', NULL])
            ->andWhere(['IS', 'salesNum', NULL])
            ->one();

        if ($voucherModel) {
            $voucherModel->usedBranchID = $salesModel->branchID;
            $voucherModel->usedDate = new Expression('NOW()');
            $voucherModel->salesNum = $salesModel->salesNum;
            if (!$voucherModel->save()) {
                $message = 'Failed to update voucher';
            }
        } else {
            $message = 'Voucher not found';
        }

        if ($message != '') {
            return [
                'status' => false,
                'message' => $message
            ];
        }

        return [
            'status' => true,
            'message' => ''
        ];
    }

    public static function claimOnline($salesNum, $subTotal, $internalOnlineVouchers, $internalOnlineVouchersFreeItem) {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();

        $requestBody = null;
        $response = null;
        try {
            #region (validate all voucher first)
            $vouchers = [];
            $invalidVouchers = [];
            if (!empty($internalOnlineVouchers)) {
                foreach ($internalOnlineVouchers as $voucher) {
                    $voucherValidation = Voucher::validateID($voucher, $subTotal, $salesNum);
                    if ($voucherValidation['status']) {
                        $vouchers[] = $voucher;
                    } else {
                        if ($voucherValidation['message'] == self::ERR_NO_INTERNET_CONNECTION) {
                            throw new Exception(self::ERR_NO_INTERNET_CONNECTION);
                        }
                        $invalidVouchers[] = "$voucher - " . $voucherValidation['message'];
                    }
                }
            }

            if (!empty($internalOnlineVouchersFreeItem)) {
                foreach ($internalOnlineVouchersFreeItem as $voucher) {
                    $voucherFreeItemValidation = Voucher::validateVoucherFreeItem(
                        $voucher->promotionVoucherCode, $voucher->promotionMasterCode
                    );
    
                    if ($voucherFreeItemValidation['status']) {
                        $vouchers[] = $voucher->promotionVoucherCode;
                    } else {
                        if ($voucherFreeItemValidation['message'] == self::ERR_NO_INTERNET_CONNECTION) {
                            throw new Exception(self::ERR_NO_INTERNET_CONNECTION);
                        }
                        $invalidVouchers[] = $voucher->promotionVoucherCode . " - " . $voucherFreeItemValidation['message'];
                    }
                }
            }
            if (!empty($invalidVouchers)) {
                throw new Exception('<div class="text-left">' . join("<br>", $invalidVouchers) . '</div>');
            }
            #endregion

            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $apiUrl . "/esb_api/voucher/claim-online-voucher-pos-payment";
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $data =   [
                "vouchers" => $vouchers,
                "subtotal" => $subTotal,
                "salesNum" => $salesNum,
                "branchID" => $branchID,
                "requestType" => "pos-claim"
            ];

            // @retry checking
            $maxRetries = 2;
            for ($i = 1; $i <= $maxRetries; $i++) {
                
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $data, $options);

                $response = $result->getData();

                if ($result->getIsOk()) {
                    if (isset($response['status']) && $response['status'] == false) {
                        throw new Exception($response['message']);
                    }
                    return $response;
                } else {

                    if ($i == $maxRetries) {
                        throw new Exception(isset($response['message']) ? $response['message'] : 'Server Unreachable');
                    }
                }
            }
            
            $dataLogging = [
                'body' => $requestBody,
                'response' => $response
            ];
            Logging::save($salesNum, Logging::CLAIM_ESB_ONLINE_VOUCHER, $dataLogging);
        } catch (\Exception $ex) {
            $dataLogging = [
                'body' => $requestBody,
                'response' => $response ? $response : $ex->getMessage()
            ];
            Logging::save($salesNum, Logging::CLAIM_ESB_ONLINE_VOUCHER, $dataLogging);

            $errMsg = $ex->getMessage();
            if (strpos($ex->getMessage(), 'fopen') !== false || strpos($ex->getMessage(), 'Curl error: #6') !== false) {
                $errMsg = self::ERR_NO_INTERNET_CONNECTION;
            }
            throw new Exception("ERR_ESBVOUCHERONLINE:" . $errMsg);
        }
    }

    public static function syncUpdate($voucherID, $syncDate) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            Voucher::updateAll([
                'syncDate' => $syncDate
                ], ['voucherID' => $voucherID]
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    public static function getVoucherCashbackUsedBySalesNum($salesNum) {
        $salesPaymentQuery = SalesPayment::find()
                ->select('voucherCode')
                ->where(['paymentMethodID' => 176])
                ->andWhere(['salesNum' => $salesNum]);
            
        $voucherCashbackUsed = Voucher::find()
            ->select('voucherID')
            ->where(['IN', 'voucherID', $salesPaymentQuery])
            ->andWhere(['createdBy' => 'Voucher Cashback'])
            ->andWhere(['flagVoucherTemplate' => 1])
            ->column();

        return $voucherCashbackUsed;
    }
    
    public static function getOnlineVoucherList() {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();
        $branchModel = Branch::findOne($branchID);

        //@refactor http_helper
        $httpService = new HttpHelperService();
        $url = $apiUrl . "/esb_api/voucher/get-available-voucher-online/?branchCode=" . $branchModel->branchCode;
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $options = ['timeOut' => 300];
        $response = $httpService->get($url, $headers, $options);

        if ($response->getIsOk() && $response->statusCode == "200") {
            $vouchers = [];
            $decodedResponse = json_decode($response->content, true);
            if (!empty($decodedResponse)) {
                foreach ($decodedResponse as $data) {
                    $voucherData = [];
                    foreach ($data as $key => $value) {
                        if ($key == 'minimumSalesAmount' || $key == 'voucherAmount' || $key == 'voucherPercentage' || $key == 'voucherSalesPrice') {
                            $voucherData[$key] = (float) $value;
                        } else {
                            $voucherData[$key] = $value;
                        }
                    }

                    $voucherLength = $data['voucherLength'];
                    $datenow = date('Y-m-d');
                    $expiredDateOnline = date('Y-m-d', strtotime($datenow. '+' . $voucherLength . ' days'));
                    $voucherData['estimatedExpiredDate'] = $expiredDateOnline;

                    $vouchers[] = $voucherData;
                }
            }
            return $vouchers;
        } else {
            return false;
        }
    }

    public function burnVouchers()
    {
        try {
            $salesNum = $this->currentOrder['salesNum'];
            $internalOnlineVouchers = [];
            $internalOnlineVouchersFreeItem = [];
            if ($this->voucherCodes) {
                foreach ($this->voucherCodes as $voucher) {
                    $internalOnlineVouchers[] = $voucher['voucherCode'];
                }
            }

            $summarySubtotal = $this->currentOrder['subtotal'];

            if (!empty($internalOnlineVouchers)) {
                $claimResult = self::claimOnline($salesNum, $summarySubtotal, $internalOnlineVouchers, $internalOnlineVouchersFreeItem);
                return $claimResult;
            }
        }
        catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

}
