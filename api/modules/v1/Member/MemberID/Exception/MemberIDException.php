<?php

namespace app\modules\v1\Member\MemberID\Exception;

use Exception;

class MemberIDException implements MemberIDExceptionInterface
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
            case self::DTO_INVALID:
                return 'DTO Invalid';
            case self::SETTING_STATIC_TOKEN_NOT_FOUND;
                return 'SETTING: static token not found';
            default:
                return 'Internal Error';
        }
    }
}