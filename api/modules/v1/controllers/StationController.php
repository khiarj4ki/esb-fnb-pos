<?php
namespace app\modules\v1\controllers;

use app\models\forms\StationSetting;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class StationController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
        ]);
        return $behaviors;
    }

    public function actionSave() {
        if (!$this->request->post('station')) {
            throw new HttpException(400);
        }

        $stationModel = new StationSetting([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$stationModel->save()) {
                throw new Exception(json_encode($stationModel->errors));
            }
        } catch (Exception $ex) {
            throw new HttpException(500, Yii::t('app', 'Failed to save data'. $ex->getMessage()));
        }
    }

}
