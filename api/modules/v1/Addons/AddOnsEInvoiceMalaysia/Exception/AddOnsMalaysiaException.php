<?php

namespace app\modules\v1\AddOns\AddOnsEInvoiceMalaysia\Exception;

use Exception;

class AddOnsMalaysiaException implements AddOnsMalaysiaExceptionInterface
{
    /**
     * @param int $code
     * @return mixed
     * @throws Exception
     */
    public static function error(int $code)
    {
        throw new Exception(self::getErrorMessage($code), $code);

    }

    /**
     * @param $errorCode
     * @return string
     */
    public static function getErrorMessage($errorCode): string
    {
        switch ($errorCode) {
            case self::INTERNAL_ERROR:
                return 'something went wrong';
            case self::SALES_HEAD_NOT_FOUND:
                return 'sales head not found';
            case self::SALES_PAYMENT_NOT_FOUND:
                return 'Sales Payment not found';
            case self::SALES_MENU_NOT_FOUND;
                return 'Sales Menu not found';
            case self::SETTING_BRANCH_ID_NOTFOUND:
                return 'Setting Branch ID not found';
            case self::DECLINE_HTTP_SUBMIT_DOCUMENT:
                return 'Failed to generate E-Invoice';
            case self::DECLINE_SETTING_ADD_ONS_NOT_FOUND:
                return 'Setting Add-Ons not found/inactive';
            case self::ERROR_INTERNAL_REQUEST_DTO:
                return 'Data transfer request did not match';
            case self::ERROR_REQUEST_VALIDATION:
                return 'Data request validation failed';
            default:
                return 'Internal Error';
        }
    }
}