<?php

namespace app\modules\v1\Terminal\TerminalQds\Exception;

use Exception;

class TerminalException implements TerminalExceptionInterface
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
                return 'Something went wrong';
            case self::SETTING_BRANCH_ID_NOTFOUND:
                return 'Setting Branch ID not found';
            case self::ERROR_INTERNAL_REQUEST_DTO:
                return 'Data transfer request did not match';
            case self::ERROR_REQUEST_VALIDATION:
                return 'Data request validation failed';
            case self::NO_VALID_TERMINAL:
                return 'Terminal data not found';
            case self::FAILED_SAVE_TERMINAL:
                return 'Failed to save data terminal';
            case self::FAILED_UPDATE_TERMINAL_SETTING:
                return 'Failed to update qds terminal setting';
            default:
                return 'Internal Error';
        }
    }
}