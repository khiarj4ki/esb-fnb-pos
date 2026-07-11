<?php
namespace app\modules\v1\controllers;

use app\models\forms\OnlineFund;

class OnlineFundController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            []);
        return $behaviors;
    }

    public function actionGetPaymentHistory()
    {
        $model = new OnlineFund([
            'attributes' => $this->request->post()
        ]);

        return $model->getPaymentHistory();
    }

    public function actionGetPaymentMethod()
    {
        $model = new OnlineFund();

        return $model->getPaymentMethodDropdownList();
    }

    public function actionSaveLog()
    {
        $model = new OnlineFund([
            'attributes' => $this->request->post()
        ]);

        return $model->saveLog();
    }
}