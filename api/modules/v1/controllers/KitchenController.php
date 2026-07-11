<?php

namespace app\modules\v1\controllers;

use app\models\KitchenOrder;
use yii\db\Exception;
use Yii;

class KitchenController extends BaseController
{
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            []);
        return $behaviors;
    }

    public function actionScan()
    {
        try {
            $kitcherOrderModel = new KitchenOrder([
                'attributes' => $this->request->post()
            ]);
            
            if (!$kitcherOrderModel->completeAllOrder()) {
                throw new Exception(json_encode($kitcherOrderModel->errors));
            }

            return [
                'status' => "ok",
                'message' => "Success Complete Order",
                'code' => 200
            ];
        } catch (\Exception $ex) {
            \Yii::error("Scan:" . $ex->getMessage());
            \Yii::$app->response->statusCode = $ex->getCode() >= 400 && $ex->getCode() < 500 ? $ex->getCode() : 500;
            return [
                'status' => "fail",
                'message' => $ex->getMessage(),
                'code' => $ex->getCode()
            ];
        }
    }
}
