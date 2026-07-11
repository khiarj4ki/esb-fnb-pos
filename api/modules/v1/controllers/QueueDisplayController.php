<?php

namespace app\modules\v1\controllers;

use app\models\forms\OrderCompletion;
use app\models\forms\TerminalQds;
use app\models\MapStationPosCustomerDisplay;
use Yii;
use yii\web\HttpException;

class QueueDisplayController extends BaseController {

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
                [
                    'get-order', 'complete-order', 'get-checker-order' , 'get-queue-order', 'get-finished-queue-order',
                    'get-ready-queue-order', 'get-customer-display-image', 'save-terminal'
        ]);
        return $behaviors;
    }

    public function actionGetOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $viewMode = $this->request->post("viewMode");
        return OrderCompletion::getOutstandingOrder($viewMode, $stationID);
    }
    
    public function actionGetCheckerOrder() {
        $this->validatePost();
        return OrderCompletion::getOutstandingCheckerOrder();
    }
    
    public function actionGetQueueOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $visitPurposeID = $this->request->post("visitPurposeID");
        return OrderCompletion::getOutstandingQueueOrder($stationID, $visitPurposeID);
    }
    
    public function actionGetReadyQueueOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $visitPurposeID = $this->request->post("visitPurposeID");
        return OrderCompletion::getReadyQueueOrder($stationID, $visitPurposeID);
    }

    public function actionGetAllQueueOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $visitPurposeID = $this->request->post("visitPurposeID");
        return OrderCompletion::getAllDataQueue($stationID, $visitPurposeID);
    }
    
    public function actionGetFinishedQueueOrder() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $visitPurposeID = $this->request->post("visitPurposeID");
        return OrderCompletion::getFinishedQueueOrder($stationID, $visitPurposeID);
    }

    public function actionCompleteOrder() {
        $this->validatePost();

        $orderCompletionModel = new OrderCompletion([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$orderCompletionModel->save()) {
                throw new HttpException(500, json_encode($orderCompletionModel->errors));
            }
        } catch (\Exception $ex) {
            $this->returnSaveError($ex);
        }
    }

    public function actionGetCustomerDisplayImage() {
        $this->validatePost();
        $stationID = $this->request->post("stationID");
        $applicationID = $this->request->post("applicationID");
        return MapStationPosCustomerDisplay::findCustomerDisplayByStation($stationID, $applicationID);
    }

    public function actionSaveTerminal(){
        TerminalQds::saveTerminalQds();
    }

    private function validatePost() {
        if (!$this->request->isPost) {
            throw new HttpException(400);
        }
    }

    private function returnSaveError($ex) {
        throw new HttpException(500, Yii::t('app', 'Failed to save data' . $ex->getMessage()));
    }

}
