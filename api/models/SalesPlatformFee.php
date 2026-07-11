<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Exception;
use Yii;

/**
 * This is the model class for table "tr_platform_fee".
 *
 * @property string $orderID
 * @property string $salesNum
 * @property int $platformFeeTypeID
 * @property string $feeNameID
 * @property string $feeNameEN
 * @property string $percentage
 * @property string $amount
 */
class SalesPlatformFee extends ActiveRecord
{
    public $errMsg;

    public static function tableName()
    {
        return 'tr_platform_fee';
    }

    public function rules()
    {
        return [
            [['platformFeeTypeID'], 'integer'],
            [['percentage', 'amount', 'minAmount', 'maxAmount'], 'number'],
            [['percentage', 'amount', 'minAmount', 'maxAmount'], 'number'],
            [['orderID', 'salesNum'], 'string', 'max' => 20],
            [['feeNameID', 'feeNameEN'], 'string', 'max' => 200],
            [['orderID', 'salesNum', 'platformFeeTypeID'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'orderID' => 'Order ID',
            'salesNum' => 'Sales Number',
            'platformFeeTypeID' => 'Platform Fee Type ID',
            'feeNameID' => 'Fee Name ID',
            'feeNameEN' => 'Fee Name EN',
            'percentage' => 'Percentage',
            'amount' => 'Amount',
            'minAmount' => 'Min Amount',
            'maxAmount' => 'Max Amount',
        ];
    }

    public static function getPlatformFeeForCalculateTotal($platformFee, &$salesHead)
    {
        if (isset($salesHead['platformFee'])) {
            $i = 0;
            foreach ($salesHead['platformFee'] as $row) {
                if (isset($row['platformFeeTypeID'])) {
                    if ($row['platformFeeTypeID'] == 1 && $row['percentage'] > 0) {
                        $singlePlatformFee = $salesHead['subtotal'] * $row['percentage'] / 100;

                        // Membulatkan ke 100 terdekat
                        $singlePlatformFee = ceil($singlePlatformFee / 100) * 100;
                        if ($singlePlatformFee < $row['minAmount']) {
                            $singlePlatformFee = $row['minAmount'];
                        }
                        if ($row['maxAmount'] > 0 && $singlePlatformFee > $row['maxAmount']) {
                            $singlePlatformFee = $row['maxAmount'];
                        }
                        $platformFee += $singlePlatformFee;
                        $salesHead['platformFee'][$i]['amount'] = $singlePlatformFee;
                    } else if ($row['platformFeeTypeID'] == 1 && $row['percentage'] == 0) {
                        $platformFee += $row['amount'];
                    }
                }
                $i++;
            }
        }

        return $platformFee;
    }

    public static function getPlatformFeeTotal($salesNum, $platformFeeList, $subtotal, $selfOrderPaymentMethodID)
    {
        $platformFee = 0;
        if ($platformFeeList) {
            foreach ($platformFeeList as $row) {
                if ($row['platformFeeTypeID'] == 1 && $row['percentage'] > 0) {
                    $singlePlatformFee = $subtotal * $row['percentage'] / 100;

                    if ($selfOrderPaymentMethodID == 'delivery') {
                        // Jika payment COD tidak ada recalculate
                        $singlePlatformFee = $row['amount'];
                    } else {
                        // Membulatkan ke 100 terdekat
                        $singlePlatformFee = ceil($singlePlatformFee / 100) * 100;
                    }

                    if ($singlePlatformFee < $row['minAmount']) {
                        $singlePlatformFee = $row['minAmount'];
                    }

                    if ($row['maxAmount'] > 0 && $singlePlatformFee > $row['maxAmount']) {
                        $singlePlatformFee = $row['maxAmount'];
                    }
                    $platformFee += $singlePlatformFee;

                    // Update amount
                    SalesHead::updateSalesPlatformFee($salesNum, $row['percentage'], $singlePlatformFee);
                } else if ($row['platformFeeTypeID'] == 1 && $row['percentage'] == 0) {
                    $platformFee += $row['amount'];
                }
            }
        }

        return $platformFee;
    }

    public function saveModel($salesNum, $platformFees)
    {
        try {
            if (isset($platformFees) && $platformFees) {
                foreach ($platformFees as $row) {
                    $type = 0;
                    if (isset($row['type'])) {
                        $type = $row['type'];
                    } elseif (isset($row['platformFeeTypeID'])) {
                        $type = $row['platformFeeTypeID'];
                    }

                    $feeNameID = "";
                    if (isset($row['feeNameID'])) {
                        $feeNameID = $row['feeNameID'];
                    } elseif (isset($row['labelId'])) {
                        $feeNameID = $row['labelId'];
                    }

                    $feeNameEN = "";
                    if (isset($row['feeNameEN'])) {
                        $feeNameEN = $row['feeNameEN'];
                    } elseif (isset($row['labelEn'])) {
                        $feeNameEN = $row['labelEn'];
                    }

                    $maxAmount = 0;
                    if (isset($row['maxAmount'])) {
                        $maxAmount = $row['maxAmount'];
                    }

                    $checkExist = SalesPlatformFee::find()->where(['salesNum' => $salesNum])->one();
                    if (!$checkExist) {
                        $platformFee = new SalesPlatformFee();
                        $platformFee->salesNum = $salesNum;
                        $platformFee->platformFeeTypeID = $type;
                        $platformFee->feeNameID = $feeNameID;
                        $platformFee->feeNameEN = $feeNameEN;
                        $platformFee->percentage = $row['percentage'];
                        $platformFee->amount = $row['amount'];
                        $platformFee->maxAmount = $maxAmount;
        
                        if (!$platformFee->save()) {
                            throw new Exception(json_encode($platformFee->getErrors()), 500);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $this->errMsg = $ex->getMessage();
            return false;
        }
        
        return true;
    }
}