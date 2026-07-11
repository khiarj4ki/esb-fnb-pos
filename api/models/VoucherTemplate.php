<?php
namespace app\models;

use app\models\forms\Logging;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;
use yii\httpclient\Client;

/**
 * This is the model class for table "lk_status".
 *
 * @property int $statusID
 * @property string $statusName
 */
class VoucherTemplate extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_vouchertemplate';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['voucherTemplateID', 'voucherTemplateName', 'voucherLength', 'voucherTypeID', 'minSalesPrice',
                'minSalesUsagePrice', 'maxVoucherAmount', 'voucherAmount', 'voucherPercentage', 'flagActive', 'isOnlinePurchaseVoucher'], 'required'],
            [['startDate', 'endDate', 'additionalInfo'], 'safe'],
            [['voucherLength', 'voucherTypeID', 'voucherUseTypeID'], 'integer'],
            [['flagActive'], 'boolean'],
            [['voucherTemplateName'], 'string', 'max' => 50],
            [['additionalInfo'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'voucherTemplateID' => Yii::t('app', 'Voucher Template ID'),
            'voucherTemplateName' => Yii::t('app', 'Voucher Template Name'),
            'voucherLength' => Yii::t('app', 'Voucher Length (in Days)'),
            'startDate' => Yii::t('app', 'Start Date'),
            'endDate' => Yii::t('app', 'End Date'),
            'voucherTypeID' => Yii::t('app', 'Voucher Type'),
            'voucherUseTypeID' => Yii::t('app', 'Voucher Use Type'),
            'minSalesPrice' => Yii::t('app', 'Minimum Sales Price'),
            'minSalesUsagePrice' => Yii::t('app', 'Minimum Sales Usage Price'),
            'maxVoucherAmount' => Yii::t('app', 'Max Voucher Amount'),
            'voucherAmount' => Yii::t('app', 'Voucher Amount'),
            'voucherPercentage' => Yii::t('app', 'Voucher Percentage'),
            'additionalInfo' => Yii::t('app', 'Additional Info'),
            'flagActive' => Yii::t('app', 'Flag Active')
        ];
    }

    public static function generateModel($salesNum, $voucherOnlineCashback) {
        $dataResponse = ['status' => true, 'message' => null];


        try {
            /**
             * 1 = CASH
             * 2 = CARD
             * 6 = MEMBER DEPOSIT
             * 
             * 4 = VOUCHER
             * 5 = OTHER VOUCHER
             */
            $paymentMethodForSpent = [1,2,6];
            $paymentVoucher = [4,5];

            $sales = SalesHead::findOne(['salesNum' => $salesNum]);
            if (empty($sales))
                throw new Exception('Sales not found');

            $branch = Branch::findOne(['branchID' => $sales->branchID]);
            if (empty($branch))
                throw new Exception('Branch not found');

            $isESOSales = (new Query())
            ->from('tr_salespayment')
            ->where([
                'AND',
                ['salesNum' => $salesNum],
                'selfOrderID IS NOT NULL',
            ])->exists();

            if ($isESOSales){
                Yii::error('Cannot process ESO Sales');
                return $dataResponse;
            }

            $paymentSales = (new Query())
                ->select([
                    'b.paymentMethodTypeID',
                    'a.fullPaymentAmount'
                ])
                ->from(SalesPayment::tableName() . ' a')
                ->innerJoin(PaymentMethod::tableName() . ' b', 'a.paymentMethodID = b.paymentMethodID')
                ->where([
                    'AND',
                    ['a.salesNum' => $salesNum],
                    ['IN', 'b.paymentMethodTypeID', $paymentMethodForSpent]
                ])->all();

            $totalSpent = 0;
            $subTotal = floatval($sales->subtotal);
            if (!empty($paymentSales) || count($paymentSales) > 0) {
                foreach ($paymentSales as $paymentSale) {
                    if ($paymentSale['paymentMethodTypeID'] == 1) {
                        $charge = floatval($sales->paymentTotal) - (floatval($sales->grandTotal) - floatval($sales->roundingTotal));
                        if ($charge > 0)
                            $totalSpent += (floatval($paymentSale['fullPaymentAmount']) - $charge);
                        else
                            $totalSpent += floatval($paymentSale['fullPaymentAmount']);
                    } else {
                        $totalSpent += floatval($paymentSale['fullPaymentAmount']);
                    }
                }
            }

            $vouchers = (new Query())
                ->select([
                    'voucherType' => new Expression('
                        CASE
                            WHEN b.paymentMethodTypeID = 4 THEN
                                \'VOUCHER\'
                            WHEN b.paymentMethodTypeID = 5 THEN
                                \'OTHERVOUCHER\'
                            ELSE
                                NULL
                        END
                    '),
                    'voucherSourceID' => new Expression('IFNULL(b.voucherSourceID, 0)'),
                    'flagIncludeTotalSpent' => new Expression('IFNULL(b.flagIncludeTotalSpent, 0)'),
                    'voucherCode' => 'a.voucherCode',
                    'amount' => 'a.paymentAmount',
                    'flagVoucherTemplate' => new Expression('IFNULL(c.flagVoucherTemplate, 0)'),
                ])
                ->from(SalesPayment::tableName() . ' a')
                ->leftJoin(PaymentMethod::tableName() . ' b', 'a.paymentMethodID = b.paymentMethodID')
                ->leftJoin(Voucher::tableName() . ' c', 'c.voucherID = a.voucherCode')
                ->where([
                    'AND',
                    ['a.salesNum' => $salesNum],
                    ['IN', 'b.paymentMethodTypeID', $paymentVoucher],
                    ['<>', 'IFNULL(c.flagVoucherTemplate, 0)', 1]
                ])->all();

            if (!empty($vouchers) && count($vouchers) > 0) {
                array_multisort(array_column($vouchers, 'voucherSourceID'), SORT_ASC,  $vouchers);
                foreach ($vouchers as $voucher) {
                    if ($voucher['voucherType'] == 'VOUCHER') {
                        $model = Voucher::findOne(['voucherID' => $voucher['voucherCode']]);
                        if (!empty($model) && $model->flagVoucherTemplate == 0) {
                            $totalSpent += floatval($voucher['amount']);
                        } else {
                            $voucherNonMap = isset($voucher['voucherSourceID']) && ($voucher['voucherSourceID'] != 2);
                            $voucherMap = isset($voucher['voucherSourceID']) && ($voucher['voucherSourceID'] == 2 && $voucher['flagIncludeTotalSpent'] == 1);
                            if ($voucherNonMap || $voucherMap) {
                                $totalSpent += floatval($voucher['amount']);
                            }
                        }
                    } else {
                        $totalSpent += floatval($voucher['amount']);
                    }
                }
            }

            if (isset($voucherOnlineCashback)) {
                foreach ($voucherOnlineCashback as $voucherOnline) {
                    if ($voucherOnline['createdFrom'] == 'Cashback') {
                        if ($voucherOnline['voucherType'] == 'Percentage') {
                            $totalSpent -= $voucherOnline['voucherPercentageValue'];
                        } else {
                            $totalSpent -= $voucherOnline['voucherSalesPriceOriginal'];
                        }
                    }
                }
            }

            $newSubtotal = ( $totalSpent + $sales['roundingTotal'] ) - $sales['vatTotal'] - $sales['otherVatTotal'] - $sales['otherTaxTotal'] + $sales['discountTotal'] + $sales['menuDiscountTotal'];
            $todayTime = date("Y-m-d H:i:s");
            $isValidVoucherTemplate = (new Query())
                ->from(VoucherTemplate::tableName() . ' a')
                ->innerJoin(VoucherTemplateDetail::tableName() . ' b', 'a.voucherTemplateID = b.voucherTemplateID')
                ->where([
                    'AND',
                    ['a.flagActive' => 1],
                    ['OR',
                        ['AND',
                            ['=', 'a.voucherTypeID', 1],
                            ['<=', 'b.minSalesPrice', $newSubtotal]],
                        ['AND',
                            ['=', 'a.voucherTypeID', 2],
                            ['<=', 'b.minSalesPrice', $totalSpent]]
                    ],
                    ['<=', 'a.startDate', $todayTime],
                    ['>=', 'a.endDate', $todayTime],
                ])
                ->orderBy("b.minSalesPrice DESC")
                ->exists();

            if (!$isValidVoucherTemplate){
                Yii::error('Invalid sales for voucher template');
                return $dataResponse;
            }
            
            // @refactor http_helper
            $apiKey = Setting::getApiKey();
            $apiUrl = Setting::getApiUrl();
            $httpService = new HttpHelperService();
            $url = $apiUrl . '/esb_api/voucher/claim-voucher-template';
            $headers = ['Authorization' => 'Bearer ' . $apiKey];
            $datas =   [
                'refNum' => $sales->salesNum,
                'transType' => 'SALESNUM',
                'branchID' => intval($sales->branchID),
                'totalSpent' => floatval($totalSpent),
                'subtotal' => floatval($newSubtotal),
                'visitPurposeID' => intval($sales->visitPurposeID),
                'vouchers' => $vouchers
            ];
            $options = ['timeOut' => 300];
            $response = $httpService->post($url, $headers, $datas, $options);

            $responseBody = $response->getData();

            if (!$response->getIsOk()) {
                $errorMessage = 'invalid response';
                $dataResponse = [
                    'status' => false,
                    'message' => $errorMessage
                ];

                self::setLoggingGenerateVoucher($salesNum, $datas, $responseBody);
                throw new Exception($errorMessage);
            }

            if (!isset($responseBody['status']) ||  $responseBody['status'] != '00') {
                $errorMessage = 'Transaction amount has not been fulfilled';
                self::setLoggingGenerateVoucher($salesNum, $datas, $errorMessage);
                throw new Exception($errorMessage);
            }
            
            if (isset($responseBody) && (isset($responseBody['status']) && $responseBody['status'] == '00')) {
                $dataResponse = [
                    'voucherCode' => $responseBody['voucherID'],
                    'additionalInfo' => $responseBody['additionalInfo'],
                    'voucherLength' => $responseBody['voucherLength'],
                    'voucherAmount' => $responseBody['voucherAmount'],
                    'voucherPercentage' => $responseBody['voucherPercentage'],
                    'minimumSalesPrice' => $responseBody['minimumSalesPrice'],
                    'expiredDate' => $responseBody['expiredDate'],
                ];

                self::setLoggingGenerateVoucher($salesNum, $datas, $dataResponse);
            }
        } catch (\Exception $ex) {
            Yii::error($ex, 'error on generateModel');
            $exceptionMsg = $ex->getMessage();
            if (strpos($exceptionMsg, 'php_network_getaddresses') !== false) {
                $dataResponse = [
                    'status' => false,
                    'message' => 'No Internet Connection'
                ];
                
                $exceptionMsg = 'No internet Connection';
                self::setLoggingGenerateVoucher($salesNum, $datas, $exceptionMsg);
            } else {
                return $dataResponse;
            }
        }

        return $dataResponse;
    }
    
    public static function checkVoucherTemplate($salesNum, $subtotal, $grandTotal){
        $todayTime = date("Y-m-d H:i:s");

        $checkSalesIsESBOrder = (new Query())
            ->select('salesNum')
            ->from('tr_salespayment')
            ->where("salesNum = '$salesNum'")
            ->andWhere("selfOrderID IS NOT NULL")
            ->column();

        if (!empty($checkSalesIsESBOrder) && count($checkSalesIsESBOrder) > 0) {
            return false;
        }

        $voucherCashbackUsed = Voucher::getVoucherCashbackUsedBySalesNum($salesNum);
        if(!empty($voucherCashbackUsed)) {
            $voucherAmounts = Voucher::find()
                ->select('voucherAmount')
                ->where(['IN', 'voucherID', $voucherCashbackUsed])
                ->column();

            if(!empty($voucherAmounts)) {
                $totalVoucherAmount = 0;
                foreach ($voucherAmounts as $value) {
                    $totalVoucherAmount += $value;
                }

                $subtotal -= $totalVoucherAmount;
                $grandTotal -= $totalVoucherAmount;
            }
        }

        $voucherTemplateModel = (new Query())
            ->select([
                'ms_vouchertemplate.voucherTemplateID',
                'ms_vouchertemplate.voucherTypeID',
                'ms_vouchertemplate.voucherUseTypeID',
                'ms_vouchertemplate.voucherLength',
                'ms_vouchertemplate.additionalInfo',
                'ms_vouchertemplate.flagActive',
                'ms_vouchertemplatedetail.minSalesPrice',
                'ms_vouchertemplatedetail.minSalesUsagePrice',
                'ms_vouchertemplatedetail.voucherAmount',
                'ms_vouchertemplatedetail.voucherPercentage',
                'ms_vouchertemplatedetail.maxVoucherAmount',
            ])
            ->from("ms_vouchertemplate")
            ->join('INNER JOIN', "ms_vouchertemplatedetail",
                'ms_vouchertemplate.voucherTemplateID = ms_vouchertemplatedetail.voucherTemplateID')
            ->where('ms_vouchertemplate.flagActive = 1')
            //->andWhere(['IN', 'map_branchvouchertemplate.branchID', $branchID])
            ->andWhere(['OR',
                ['AND',
                    ['=', 'ms_vouchertemplate.voucherTypeID', 1],
                    ['<=', 'ms_vouchertemplatedetail.minSalesPrice', $subtotal]],
                ['AND',
                    ['=', 'ms_vouchertemplate.voucherTypeID', 2],
                    ['<=', 'ms_vouchertemplatedetail.minSalesPrice', $grandTotal]]
            ])
            ->andWhere(['AND',
                ['<=', 'startDate', $todayTime],
                ['>=', 'endDate', $todayTime]
            ])
            ->orderBy("ms_vouchertemplatedetail.minSalesPrice DESC")
            ->one();
        
        if($voucherTemplateModel){
            return true;
        }
        
        return false;
    }

    private static function setLoggingGenerateVoucher($salesNum, $requestBody, $responseBody) {
        if ($responseBody && isset($responseBody['voucherCode'])) {
            $responseBody = [
                'additionalInfo' => $responseBody['additionalInfo'],
                'voucherLength' => $responseBody['voucherLength'],
                'voucherAmount' => $responseBody['voucherAmount'],
                'voucherPercentage' => $responseBody['voucherPercentage'],
                'minimumSalesPrice' => $responseBody['minimumSalesPrice'],
                'expiredDate' => $responseBody['expiredDate'],
            ];
        }

        $dataLogging = [
            'requestBody' => $requestBody,
            'responseBody' => $responseBody
        ];

        Logging::save($salesNum, Logging::GENERATE_VOUCHER_CASHBACK, $dataLogging);
    }
}
