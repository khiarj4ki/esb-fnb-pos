<?php
namespace app\modules\v1\controllers;

use app\models\Question;

class QuestionController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        return Question::findActiveAsArray();
    }

}