<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\forms\PrintEndShiftCash;
use app\models\forms\PrintShiftOutCash;
use app\models\forms\ShiftCash;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class ShiftCashController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            []);
        return $behaviors;
    }

    private function validatePost() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
    }

    public function actionIn()
    {
        $this->validatePost();

        try {
            $shiftModel = new ShiftCash([
                'attributes' => $this->request->post()
            ]);
            $shiftModel->scenario = ShiftCash::SCENARIO_SHIFT_IN;

            return $shiftModel->save();
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionOut()
    {
        $this->validatePost();
        try {
            $shiftModel = new ShiftCash([
                'attributes' => $this->request->post()
            ]);
            $shiftModel->scenario = ShiftCash::SCENARIO_SHIFT_OUT;

            return $shiftModel->save();
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionValidateIn()
    {
        try {
            $shiftModel = new ShiftCash();
            $shiftModel->scenario = ShiftCash::SCENARIO_VALIDATE_IN;

            return $shiftModel->save();
        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }

    public function actionPrintEnd() {
        $model = new PrintEndShiftCash([
            'attributes' => $this->request->post()
        ]);
        $model->doPrint();
        
        if ($model->printResult) {
            return [
                "printDataError" => $model->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }

    public function actionPrintOut() {
        $model = new PrintShiftOutCash([
            'attributes' => $this->request->post()
        ]);
        $model->doPrint();
        
        if ($model->printResult) {
            return [
                "printDataError" => $model->printResult,
                "printData" => AndroidPrintConnector::getData()     
            ];
        }
    }
}
