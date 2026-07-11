<?php

namespace app\commands;

use app\components\AppHelper;
use app\models\DeviceTransaction;
use app\models\Setting;
use yii\console\Controller;

class InsertMacAddressLogController extends Controller {
    public function actionIndex($_IP_SERVER, $_IP_ADDRESS) {
        $settings = Setting::getPrintingSettings();
        $logMacAddressSetting = isset($settings['Log MAC Address']) ? $settings['Log MAC Address'] : 0;

        if ($logMacAddressSetting == 1) {
            if($_IP_ADDRESS && $_IP_SERVER) {
                $_RESULT = AppHelper::getMacAddress($_IP_SERVER, $_IP_ADDRESS);

                $deviceModel = new DeviceTransaction();
                $deviceModel->saveTodayMac($_RESULT);
            }
        }
    }

}
