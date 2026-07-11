<?php

namespace app\components;

use app\models\Setting;

class SimpleHash
{

    /**
     * 
     * @param string $string
     * @param int $key CompanyCode
     * @return string
     * @throws Exception
     */
    public static function encrypt($string, $key)
    {
        try {
            $key = self::stringToInt($key);
            $result = '';
            for ($i = 0, $k = strlen($string); $i < $k; $i++) {
                $char = substr($string, $i, 1);
                $keychar = substr($key, (($i + 1) % strlen($key)) - 1, 1);
                $char = chr(ord($char) + ord($keychar));
                $result .= $char;
            }
            return bin2hex($result);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * 
     * @param string $string
     * @param string $key CompanyCode
     * @return string
     * @throws Exception
     */
    public static function decrypt($string, $key)
    {
        try {
            $key = self::stringToInt($key);
            $result = '';
            if (ctype_xdigit($string) && strlen($string) % 2 == 0) {
                $string = hex2bin($string);
                for ($i = 0, $k = strlen($string); $i < $k; $i++) {
                    $char = substr($string, $i, 1);
                    $keychar = substr($key, (($i + 1) % strlen($key)) - 1, 1);
                    $char = chr(ord($char) - ord($keychar));
                    $result .= $char;
                }
            }
            if (!preg_match('~[^\x20-\x7E\t\r\n]~', $result) > 0) {
                return $result;
            }
            return '';
        } catch (\Exception $ex) {
            return '';
        }
        
    }

    /**
     * 
     * @param string $string
     * @param int $additionalKey
     * @return int
     */
    protected static function stringToInt($string, $additionalKey = null) {
        if ($additionalKey === null) {
            try {
                $companyCodeEncryption = Setting::getValue1('EZO', 'Company Code Encryption');
                $additionalKey = (int) $companyCodeEncryption;
            } catch (\Exception $ex) {
                $additionalKey = 0;
            }
        }
        $result = 0;
        $chars = str_split($string);
        foreach ($chars as $char) {
            $result += $additionalKey + ord($char);
        }
        return (int) $result;
    }
}
