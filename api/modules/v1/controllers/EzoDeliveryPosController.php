<?php
namespace app\modules\v1\controllers;

use app\models\forms\EzoDeliveryPos;
use Yii;
use yii\db\Expression;
use Exception;
use app\models\Setting;

class EzoDeliveryPosController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
                'get-data-action'
        ]);
        return $behaviors;
    }

    public function actionGetDataAction() {
        if (!$this->request->post('authEzoDeliveryPos')) {
            throw new Exception("Parameters is required");
        }
        $model = new EzoDeliveryPos([
            'attributes' => $this->request->post('authEzoDeliveryPos')
        ]);
        $bodyRequest = $this->request->post();
        if ($bodyRequest['authEzoDeliveryPos']['actionUrl'] === '/erp/ezo-delivery/pos-error-log') {
            $model->isErrorLog = true;
        }
        if ($bodyRequest['authEzoDeliveryPos']['actionUrl'] === '/erp/ezo-delivery/get-data-finish') {
            return $model->fetchSales();
        }
        if ($bodyRequest['authEzoDeliveryPos']['actionUrl'] === '/erp/ezo-delivery/push-data' && isset($bodyRequest['body'])) {
            $model->validateRetry($bodyRequest['body']);
            unset($bodyRequest['body']['isRetry']);
        }
        unset($bodyRequest['authEzoDeliveryPos']);
        if (isset($bodyRequest['body'])) {
            $bodyRequest = $bodyRequest['body'];
        }
        
        return $model->fetchEzoDeliveryPosData($bodyRequest);
    } 


    public function actionPrintLabel() {
        if (!$this->request->post('ezoOrderID')) {
            throw new Exception("Ez Order ID is required");
        }
        $model = new EzoDeliveryPos([
            'attributes' => $this->request->post()
        ]);

        return $model->printEzoDeliveryId();
    }

}
