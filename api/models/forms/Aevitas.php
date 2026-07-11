<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Setting;
use Yii;
use yii\base\Model;

/**
 * @property boolean $printAllBills
 * @property boolean $printPaymentMethod
 * @property boolean $printMenu
 */
class Aevitas extends Model {
    public static function generateAevitas($mainModels) {
        $textContent = '';

        $ipaddress = '';
        $result = false;
        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } elseif(getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } elseif(getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } elseif(getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }

        $ipaddress = $ipaddress == '::1' ? '' : $ipaddress;
        $ipaddress = str_replace(".","",$ipaddress);

        $redeemDir = Setting::getValue1('Local Setting', 'redeemDir');
        $voucherDir = Setting::getValue1('Local Setting', 'voucherDir');

        foreach ($mainModels['salesMenu'] as $detail) {
            $menuQty = intval($detail['qty']);
            $menuPrice = intval($detail['price']);
            $menuCode = $detail->menu['menuCode'];
            $textContent .= "M, $menuQty, $menuPrice, $menuCode\n";

            foreach ($detail->childSalesMenus as $childSalesMenu) {
                $menuQty = intval($childSalesMenu['qty']);
                $menuPrice = intval($childSalesMenu['price']);
                $menuCode = $childSalesMenu->menu['menuCode'];
                $textContent .= "M, $menuQty, $menuPrice, $menuCode\n";
            }
        }

        $salesNum = $mainModels['salesNum'];
        $tempSubtotal = intval($mainModels['subtotal']);
        $tempOtherTaxTotal = intval($mainModels['otherTaxTotal']);
        $tempVatTotal = intval($mainModels['vatTotal']);
        $tempPaymentTotal = intval($mainModels['grandTotal'] - $mainModels['roundingTotal']);

        $textContent .= "T, 1, $tempPaymentTotal, 1\n";
        $textContent .= "$tempSubtotal, $tempOtherTaxTotal, $tempVatTotal, 1\n";
        $textContent .= "$salesNum, 0, 0, 0\n";

        $voucherFileName = $voucherDir.$ipaddress.'_voucher.txt';
        if ($voucherFileName != '') {
            if (file_exists($voucherFileName)) {
                unlink($voucherFileName);
            }

            AppHelper::writeToTextFile($voucherFileName, $textContent);
        }

        $redeemFileName = $redeemDir.$ipaddress.'_redeem.txt';
        if ($redeemFileName != '') {
            if (file_exists($redeemFileName)) {
                unlink($redeemFileName);
            }

            AppHelper::writeToTextFile($redeemFileName, $textContent);
        }
    }

}
