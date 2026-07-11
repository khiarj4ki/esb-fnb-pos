<?php
namespace app\modules\v1\controllers;

use app\models\Setting;
use app\models\Terminal;
use Yii;
use yii\db\Exception;

class TerminalCodeController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            ['index']);
        return $behaviors;
    }

    public function actionIndex()
    {
        return Terminal::find()->all();
    }


    public function actionCreateTerminal() {
        try {

            $model = new Terminal([
                'attributes' => $this->request->post()
            ]);
            $model->apiUrl = Setting::getApiUrl();
            
            if(!$model->create()){
                throw new Exception($model->responseData);
            }

            return $model->responseData;
        } catch (Exception $ex) {
            Yii::error($ex);
            return false;
        }
    }

}
