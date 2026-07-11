<?php
namespace app\modules\v1\controllers;

use app\components\HOTP;
use app\models\Branch;
use app\models\forms\Logging;
use Exception;
use Yii;
use yii\web\HttpException;

class OtpController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionDecrypt() {
        
        if (!$this->request->post('otpType')) {
            throw new HttpException(400, Yii::t('app', 'Invalid OTP'));
        }

        if($this->request->post('otpType') && $this->request->post('otpType') == 'START_DAY') {
            if (!$this->request->post('otp')) {
                throw new HttpException(400, Yii::t('app', 'Invalid OTP'));
            }
        } else {
            if (!$this->request->post('identity') || !$this->request->post('otp') || !$this->request->post('otpType')) {
                throw new HttpException(400, Yii::t('app', 'Invalid OTP'));
            }
        }

        try{
            $identity = $this->request->post('identity');
            $otpType = $this->request->post('otpType');
            $otp = $this->request->post('otp');
            $promotionID = $this->request->post('promotionID');

            // @notes: startday enhance
            $user = $this->request->post('user');

            // @notes: SALESNUM//OTPTYPE [VOID_SALES, CANCEL_TABLE, PROMOTION]
            if ($otpType == 'PROMOTION') {
                $key = strtoupper($identity . '//' . $otpType . '//' . $promotionID);
            } elseif ($otpType == 'START_DAY') {
                
                $key = strtoupper($identity);
                $dataLogging = [
                    'otp' => $otp,
                    'startDayTime' => date('Y-m-d H:i:s'),
                    'user' => $user
                ];

            } else {
                $key = strtoupper($identity . '//' . $otpType);
            }

            $expiredInSecond = 60 * 60;
            $defaultTimezone = date_default_timezone_get();
            date_default_timezone_set('Asia/Jakarta');
            $time = strtotime("now");
            $hotp = HOTP::generateByTime($key, $expiredInSecond, $time);
            date_default_timezone_set($defaultTimezone);

            if($otpType == 'START_DAY') {
                if($hotp->toHOTP(6) !== $otp){
                    throw new HttpException(400, Yii::t('app', 'Invalid Code, please try again'));
                }

            Logging::save($otp, Logging::START_DAY_OTP, $dataLogging);
            
            } else {
                if($hotp->toHOTP(6) !== $otp){
                    throw new HttpException(400, Yii::t('app', 'Wrong OTP, please try again'));
                }
            }

        }
        catch (Exception $ex) {
            throw new HttpException(400, $ex->getMessage());
        }
    }

    public function actionGenerateCode() {
        try{

            $companyCode = $this->request->post('companyCode');
            $branchCode = $this->request->post('branchCode');
            $keyValue = $companyCode."-".strtotime("now");
            $key = strtoupper($keyValue);

            return ['referenceNumber' => $key];

        }
        catch (Exception $ex) {
            throw new HttpException(400, $ex->getMessage());
        }
    }
}
