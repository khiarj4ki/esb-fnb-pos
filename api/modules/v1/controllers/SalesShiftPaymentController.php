<?php

namespace app\modules\v1\controllers;

use app\models\SalesShiftPaymentHead;
use Yii;
use yii\web\HttpException;

class SalesShiftPaymentController extends BaseController
{

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            []
        );
        return $behaviors;
    }

    public function actionCheckSalesShiftPayment()
    {
        try {
            $model = new SalesShiftPaymentHead([
                'attributes' => $this->request->post()
            ]);
            if (!$res = $model->checkSalesShiftPayment()) {
                throw new HttpException(500, Yii::t("app", "Failed to fetch data sales"));
            }
            return $res;
            
        } catch (HttpException $ex) {
            throw new HttpException(500, Yii::t("app", "Failed to fetch data sales"));
        }
    }

    public function actionCreate()
    {
        try {
            $model = new SalesShiftPaymentHead([
                'attributes' => $this->request->post('data')
            ]);
            $model->detail = $this->request->post('data')['detail'];
            $model->cashFraction = $this->request->post('data')['cashFraction'];
            $model->cashFractionStatus = $this->request->post('data')['cashFractionStatus'];
            if (!$res = $model->saveModel()) {
                throw new HttpException(500, Yii::t("app", "Failed to save payment"));
            }
            return $res;
        } catch (HttpException $ex) {
            throw new HttpException(500, Yii::t("app", "Failed to save payment"));
        }
    }

    public function actionUpdateSubmittedBy()
    {
        try {
            $model = new SalesShiftPaymentHead([
                'attributes' => $this->request->post()
            ]);
            if (!$res = $model->updateSubmittedBy()) {
                throw new HttpException(500, Yii::t("app", "Failed to update data"));
            }
            return $res;
        } catch (HttpException $ex) {
            throw new HttpException(500, Yii::t("app", "Failed to update data"));
        }
    }
}
