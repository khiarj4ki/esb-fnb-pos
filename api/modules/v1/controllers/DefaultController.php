<?php
namespace app\modules\v1\controllers;

use Yii;

class DefaultController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'index', 'encrypt', 'decrypt', 'print'
        ];
        return $behaviors;
    }

    public function actionIndex() {
        // @TODO: For testing purpose only
        $enc = \app\components\AppHelper::encryptSalesNum('SSDR160439773722');
        return $enc;
    }
    
    public function actionEncrypt() {
        $value1 = 'ESBADMIN';
        $value2 = '14ecdbacbc51885a09c4d0592e685898';
        $value3 = 'http://localhost:4205';
        $value4 = 'http://localhost:85/ezo-dev/api';
        $result = base64_encode(Yii::$app->security->encryptByKey($value4, Yii::$app->params['key']));
        // $result = bin2hex(Yii::$app->security->encryptByKey("esbfnb.2018", "nU9BV98Jw2sh6ug"));
        return $result;
    }
    
    public function actionDecrypt() {
        $username = \app\models\Setting::getSelfOrderSetting('Basic Rest Username');
        $password = \app\models\Setting::getSelfOrderSetting('EZO FS API Url');

        // $password = Yii::$app->security->decryptByKey(hex2bin("252701284d2fc36f0ba34cbd21971c6a38313661333831643664663365636137626361663231613561353164366435636438633565626466363730383031336536616131653632363538663063663164421a3725b70b2ab816453e3da97d005ebb96f23e911bc308441065dafc6ec952"), "nU9BV98Jw2sh6ug");
        return $password;
    }

    public function actionPrint() {
        // @TODO: For testing purpose only
//        $connector = new NetworkPrintConnector('192.168.123.100', 9100);
//        $printer = new Printer($connector);
//        $printer->text('TEXT SIZE NORMAL');
//        $printer->feed(1);
//        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
//        $printer->text('EMPHASIZED DOUBLE HEIGHT');
//        $printer->feed(1);
//        $printer->initialize();
//        $printer->setTextSize(1, 1);
//        $printer->text('TEXT SIZE NORMAL');
//        $printer->feed(1);
//        $printer->setTextSize(1, 2);
//        $printer->text('DOUBLE HEIGHT');
//        $printer->feed(1);
//        $printer->setTextSize(1, 2);
//        $printer->setEmphasis(true);
//        $printer->text('EMPHASIZED DOUBLE HEIGHT');
//        $printer->feed(1);
//        $printer->cut();
//        $printer->close();
    }

}
