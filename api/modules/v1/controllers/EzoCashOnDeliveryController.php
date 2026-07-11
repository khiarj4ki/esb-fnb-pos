<?php
namespace app\modules\v1\controllers;
use app\models\forms\EzoCashOnDelivery;
use Yii;
use yii\web\ServerErrorHttpException;
use yii\web\BadRequestHttpException;

class EzoCashOnDeliveryController extends BaseController {

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionCreateCashOnDeliveryPayment() {
        $model = new EzoCashOnDelivery([
            'attributes' => $this->request->post()
        ]);

        if (!$model->validate()) {
            return [
                'errors' => $model->errors
            ];
        }
        if (!$result = $model->save()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException($model->errMsg);
        }
        return $result;
    }

    public function actionPosView() {
        if (!$orderID = $this->request->post('orderID')) {
            throw new BadRequestHttpException();
        }
        $model = new EzoCashOnDelivery();
        return $model->loadOrderId($orderID);
    }

}
