<?php

namespace app\modules\v1\controllers;

use app\models\forms\Employee;
use app\models\forms\EmployeeExternal;
use app\models\forms\MapValidate;
use app\models\MsEmployee;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

class EmployeeController extends BaseController {

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
                [ 
        ]);
        return $behaviors;
    }

    public function actionValidate() {
        $model = new Employee([
            'scenario' => Employee::SCENARIO_VALIDATE,
            'attributes' => $this->request->post()
        ]);
        
        if (!$result = $model->validateEmployeeCheck()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }

    public function actionGetBalance() {
        $model = new Employee([
            'scenario' => Employee::SCENARIO_GET_BALANCE,
            'attributes' => $this->request->post()
        ]);
        
        if (!$result = $model->getBalance()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }


    public function actionUseBalance() {
        $model = new Employee([
            'scenario' => Employee::SCENARIO_USE_BALANCE,
            'attributes' => $this->request->post()
        ]);
        
        if (!$result = $model->useBalance()) {
            Yii::error($model->errors);
            throw new ServerErrorHttpException();
        }
        return $result;
    }

    public function actionMapEmployee() {
        $model = new MapValidate([
            'attributes' => $this->request->post()
        ]);

        return $model->getMapEmployee();
    }

    public function actionEmployeeOnline() {
        if (!$employeeCode = $this->request->post('employeeCode')) {
            throw new BadRequestHttpException("Employee code is required");
        }
        return MsEmployee::findEmployeeActive($employeeCode);
    }
}