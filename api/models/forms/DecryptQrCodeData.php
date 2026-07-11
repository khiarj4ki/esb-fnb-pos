<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\SalesPayment;
use app\models\Setting;
use app\models\TempOrder;
use Yii;
use yii\base\Model;
use yii\httpclient\Exception;

/**
 * @property string $orderId * 
 * 
 */
class DecryptQrCodeData extends Model {
    public $data;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['data',], 'required'],
        ];
    }

    public function checkOrder()
    {
        if (strpos($this->data, '<<EZO_TA_MANUAL_INPUT>>') === 0) {
            $orderID = str_replace('<<EZO_TA_MANUAL_INPUT>>', '', $this->data);

            if(SelfOrderTakeAway::loadCheckOrder($orderID) == null){
                return "Order Number cannot be found, please check your input.";
            }

            $salesPayment = SalesPayment::findOne([
                'selfOrderID' => $orderID
            ]);
            if ($salesPayment) {
                return "The order number has already been registered in the POS.";
            }
        }

       
        if (strpos($this->data, '<<TEMP_ORDER>>') === 0) {
            $orderID = str_replace('<<TEMP_ORDER>>', '', $this->data);
            $tempOrder = TempOrder::findOne(['orderID' => $orderID]);
            if ($tempOrder) {
                $plainData = $tempOrder->orderData;
            } else {
                return false;
            }
        } else {
            $plainData = $this->data;
        }

         // @note : do this if data in encrypted
        $authKey = Setting::getApiKey();
        $data = Yii::$app->security->decryptByKey(base64_decode($plainData),
            $authKey);
        if (strpos($data, '<<EZO_TA>>') === 0) {
            $orderID = str_replace('<<EZO_TA>>', '', $data);
            if(SelfOrderTakeAway::loadCheckOrder($orderID) == null){
                return 'Source data for the order number cannot be found. Please try again.';
            }

            $salesPayment = SalesPayment::findOne([
                'selfOrderID' => $orderID
            ]);
            if ($salesPayment) {
                return "The order number you're entering has already been registered in the POS.";
            }
        }

        return null;
    }

    public function decrypt() {
        if (!$this->validate()) {
            return false;
        }
        try {
            if (strpos($this->data, '<<TEMP_ORDER>>') === 0) {
                $orderID = str_replace('<<TEMP_ORDER>>', '', $this->data);
                $tempOrder = TempOrder::findOne(['orderID' => $orderID]);
                if ($tempOrder) {
                    $plainData = $tempOrder->orderData;
                } else {
                    return false;
                }
            } else {
                $plainData = $this->data;
            }

            if (strpos($this->data, '<<EZO_TA_MANUAL_INPUT>>') === 0) {
                $orderID = str_replace('<<EZO_TA_MANUAL_INPUT>>', '', $this->data);

                $result = SelfOrderTakeAway::loadOrder($orderID);
                if ($result) {
                    $result['orderID'] = $orderID;
                    return $result;
                } else {
                    return false;
                }
            }
            
            if (strpos($this->data, '<<ESO_QR>>') === 0) {
                $orderID = str_replace('<<ESO_QR>>', '', $this->data);
                $result = SelfOrderTakeAway::loadOrder($orderID);
                if ($result) {
                    return $result;
                } else {
                    return false;
                }
            }

            $authKey = Setting::getApiKey();
            $data = Yii::$app->security->decryptByKey(base64_decode($plainData),
                $authKey);

            if (strpos($data, '<<QOQI>>') === 0) {
                $orderID = str_replace('<<QOQI>>', '', $data);
                $result = SelfOrderTakeAway::loadOrder($orderID, 'qoqi');
                if ($result) {
                    $result['orderID'] = $orderID;
                    return $result;
                } else {
                    return false;
                }
            }
            if (strpos($data, '<<EZO_TA>>') === 0) {
                $orderID = str_replace('<<EZO_TA>>', '', $data);
                $result = SelfOrderTakeAway::loadOrder($orderID);
                if ($result) {
                    $result['orderID'] = $orderID;
                    return $result;
                } else {
                    return false;
                }
            }

            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $data);
            rewind($stream);

            while ($row = fgetcsv($stream)) {
                $csvArray[] = $row;
            }
            fclose($stream);

            $result = [];
            $prevSalesMenuIndex = 0;
            $salesMenuIndex = 0;
            
            foreach ($csvArray as $line) {
                $command = $line[0];
                if ($command == 'b') {
                    $branchCode = Branch::findOne([
                            'branchID' => Setting::getCurrentBranch()
                        ])->branchCode;
                    if ($branchCode !== $line[1]) {
                        return false;
                    }
                } else if ($command == 'v') {
                    $result['visitPurposeID'] = $line[1];
                } else if ($command == 'e') {
                    $result['email'] = $line[1];
                } else if ($command == 'n') {
                    $result['fullName'] = $line[1];
                } else if ($command == 's') {
                    $result['salesNum'] = $line[1];
                } else if ($command == 'spg') {
                    $result['salesPaymentGatewayNum'] = $line[1];
                } else if ($command == 'm') {
                    $result['paymentMethodID'] = $line[1];
                } else if ($command == 'o') {
                    $prevSalesMenuIndex = $salesMenuIndex;
                    if (count($line) > 6) {
                        $salesMenuArray = [
                            'menuID' => (int) $line[1],
                            'qty' => (int) $line[2],
                            'notes' => $line[3],
                            'price' => (float) $line[4],
                            'salesType' => isset($line[5]) ? $line[5] : 'POS',
                            'packages' => [],
                            'extras' => [],
                            'otherTax' => (float) $line[6],
                            'vat' => (float) $line[7],
                            'otherTaxOnVat' => (float) $line[8],
                            'total' => (float) $line[9]
                        ];
                    } else {
                        $salesMenuArray = [
                            'menuID' => (int) $line[1],
                            'qty' => (int) $line[2],
                            'notes' => $line[3],
                            'price' => (float) $line[4],
                            'salesType' => isset($line[5]) ? $line[5] : 'POS',
                            'packages' => [],
                            'extras' => [],
                        ];
                    }

                    $result['salesMenu'][$salesMenuIndex] = $salesMenuArray;
                    $salesMenuIndex += 1;
                } else if ($command == 'p') {
                    if (count($line) == 7) {
                        $packageArray = [
                            'menuID' => (int) $line[1],
                            'menuGroupID' => (int) $line[2],
                            'qty' => (int) $line[3],
                            'notes' => $line[4],
                            'price' => (float) $line[5],
                            'salesType' => isset($line[6]) ? $line[6] : 'POS'
                        ];
                    } else if (count($line) > 6) {
                        $packageArray = [
                            'menuID' => (int) $line[1],
                            'menuGroupID' => (int) $line[2],
                            'qty' => (int) $line[3],
                            'price' => (float) $line[4],
                            'salesType' => isset($line[5]) ? $line[5] : 'POS',
                            'otherTax' => (float) $line[6],
                            'vat' => (float) $line[7],
                            'otherTaxOnVat' => (float) $line[8],
                            'total' => (float) $line[9]
                        ];
                    } else {
                        $packageArray = [
                            'menuID' => (int) $line[1],
                            'menuGroupID' => (int) $line[2],
                            'qty' => (int) $line[3],
                            'price' => (float) $line[4],
                            'salesType' => isset($line[5]) ? $line[5] : 'POS'
                        ];
                    }

                    $result['salesMenu'][$prevSalesMenuIndex]["packages"][] = $packageArray;
                } else if ($command == 'x') {
                    if (count($line) > 4) {
                        $extraArray = [
                            'menuExtraID' => (int) $line[1],
                            'qty' => (int) $line[2],
                            'price' => (float) $line[3],
                            'otherTax' => (float) $line[4],
                            'vat' => (float) $line[5],
                            'otherTaxOnVat' => (float) $line[6],
                            'total' => (float) $line[7]
                        ];
                    } else {
                        $extraArray = [
                            'menuExtraID' => (int) $line[1],
                            'qty' => (int) $line[2],
                            'price' => (float) $line[3]
                        ];
                    }

                    $result['salesMenu'][$prevSalesMenuIndex]["extras"][] = $extraArray;
                } else if ($command == 'pg') {
                    $result['orderID'] = $line[1];
                    $result['subtotal'] = $line[2];
                    $result['additionalTax'] = $line[3];
                    $result['pb1'] = $line[4];
                    $result['grandTotal'] = $line[5];
                    $result['roundingTotal'] = $line[6];
                    $result['paymentMethod'] = $line[7];
                    $result['paymentTotal'] = $line[8];
                    if (isset($line[9])) {
                        $result['visitPurposeID'] = $line[9];
                    }
                } else if ($command == 'm') {
                    if (count($line) > 4) {
                        $memberArray = [
                            'flagExternalAPI' => (int) $line[1],
                            'flagExternalMemberID' => $line[2],
                            'flagExternalPhoneNo' => $line[3],
                            'flagExternalCardID' => $line[4],
                        ];
                    }
                    $result['dataMember'] = $memberArray;
                } else if ($command == 'of') {
                    if (isset($line[1])) {
                        $result['orderFee'] = $line[1];
                    }
                } else if ($command == 'dc') {
                    if (isset($line[1])) {
                        $result['deliveryCost'] = $line[1];
                    }
                } else if ($command == 'dm') {
                    if (isset($line[1])) {
                        $result['phoneNumber'] = $line[1];
                    }
                    if (isset($line[2])) {
                        $result['email'] = $line[2];
                    }
                } else if ($command == 'tm') {
                    if (isset($line[1])) {
                        $result['transactionModeID'] = $line[1];
                    }
                } else if ($command == 'rm') {
                    if (count($line) > 0) {
                        if ($result['salesMenu'][$prevSalesMenuIndex]['menuID'] == (int) $line[1]) {
                            $result['salesMenu'][$prevSalesMenuIndex]['mainMenuID'] = (int) $line[2];
                        }
                    }
                } else if ($command == 'edc') {
                    $result['orderID'] = $orderID;
                    $result['paymentMethodId'] = $line[2];
                    $result['paymentMethod'] = $line[3];
                    $result['cardNumber'] = $line[4];
                    $result['bankName'] = $line[5];
                    $result['accountName'] = $line[6];
                    $result['traceNumber'] = $line[7];
                    $result['verificationCode'] = $line[8];
                    $result['edcTerminalID'] = $line[9];
                    $result['cardNumberValidationTypeID'] = $line[10];
                    $result['edcPort'] = $line[11];
                    $result['edcWssUrl'] = $line[12];
                    $result['fixedAmount'] = $line[13];
                    $result['flagEdcActive'] = $line[14];
                    $result['flagMandatoryCardNumber'] = $line[15];
                    $result['flagMandatoryVerificationCode'] = $line[16];
                    $result['paymentMethodCode'] = $line[17];
                    $result['paymentTotal'] = $line[18];
                    $result['posExternalPaymentID'] = $line[19];
                    $result['kioskStationID'] = $line[20];
                    $result['coaNo'] = $line[21];
                    $result['externalApi'] = true;
                    $result['flagEdcPayment'] = true;
                }
            }

            return $result;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

}
