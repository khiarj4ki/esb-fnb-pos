<?php
namespace app\modules\v1\controllers;

use app\models\QuestionAnswer;
use app\models\Questionnaire;
use Exception;
use Yii;
use yii\web\HttpException;

class QuestionnaireController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        return Questionnaire::findActiveAsArray();
    }

    public function actionSaveAnswer() {
        $questionAnswerModel = new QuestionAnswer([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$questionAnswerModel->save()) {
                throw new Exception(json_encode($questionAnswerModel->errors));
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            throw new HttpException(500, Yii::t('app', 'Failed to save data'));
        }
    }

}