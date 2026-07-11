<?php
namespace app\modules\v1\controllers;

use app\components\AppHelper;
use app\models\Device;
use Yii;
use yii\db\Exception;
use yii\db\Expression;
use yii\web\HttpException;

class DeviceController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
                'index', 'get-device'
        ]);
        return $behaviors;
    }
    
    public function actionGetDevice() {
        $_IP_SERVER = $_SERVER['SERVER_ADDR'];
        $_IP_ADDRESS = $_SERVER['REMOTE_ADDR'];
        $_RESULT = AppHelper::getMacAddress($_IP_SERVER, $_IP_ADDRESS);
        
        $deviceModel = Device::findOne(['macAddress' => $_RESULT]);
        return $deviceModel;
    }

    public function actionSysdate(){
        return date('Y-m-d H:i:s');
    }
}
