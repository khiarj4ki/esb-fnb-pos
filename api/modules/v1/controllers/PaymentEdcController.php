<?php
namespace app\modules\v1\controllers;

use app\models\forms\PaymentEdcSetting;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class PaymentEdcController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionSave() {
        if (!$this->request->post('paymentEdc')) {
            throw new HttpException(400);
        }

        $paymentEdcModel = new PaymentEdcSetting([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$paymentEdcModel->save()) {
                throw new Exception(json_encode($paymentEdcModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

    public function actionSaveEdc() {
        $paymentEdcModel = new PaymentEdcSetting([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$paymentEdcModel->saveEdc()) {
                throw new Exception(json_encode($paymentEdcModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }
}
