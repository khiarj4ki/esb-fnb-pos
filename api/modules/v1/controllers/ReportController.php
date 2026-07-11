<?php
namespace app\modules\v1\controllers;

use app\components\AndroidPrintConnector;
use app\models\forms\PrintReporting;

class ReportController extends BaseController {
    
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'print'
            
        ]);
        return $behaviors;
    }

    public function actionPrint() {
        $printingModel = new PrintReporting([
            'attributes' => $this->request->post()
        ]);
        
        $printingModel->runPrint();

        return AndroidPrintConnector::getData();
    }
}
