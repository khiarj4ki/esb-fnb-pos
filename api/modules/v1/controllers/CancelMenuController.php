<?php
namespace app\modules\v1\controllers;

use app\models\forms\CancelMenuModel;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class CancelMenuController extends BaseController {

    public function actionStoreLog() {

        if (!$this->request->post()) {
            throw new HttpException(400);
        }

        $cancelMenuModel = new CancelMenuModel([
            'attributes' => $this->request->post()
        ]);
                
        try {

            if (!$cancelMenuModel->saveModel()) {
                throw new Exception(json_encode($cancelMenuModel->errors));
            }

            return $cancelMenuModel->salesNum;
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save Cancel Menu.'));
        }
    }

}
