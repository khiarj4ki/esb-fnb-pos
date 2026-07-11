<?php

namespace app\modules\external\controllers;

use app\models\forms\SelfOrderTakeAway;
use Yii;
use yii\web\BadRequestHttpException;

class OrderController extends BaseController {

    public function actionCreate() {
        $model = new SelfOrderTakeAway([
            'attributes' => $this->request->post()
        ]);
        $model->externalApi = 1;
        if (!$result = $model->save()) {
            throw new BadRequestHttpException($model->errMsg);
        }
        return $result;
    }
    
}