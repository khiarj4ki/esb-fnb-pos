<?php
namespace app\modules\v1\controllers;

use app\models\forms\SyncFetch;
use app\models\forms\SyncPush;
use app\models\SalesHead;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class SyncController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            ['fetch-user','push-user', 'push', 'fetch']);
        return $behaviors;
    }

    public function actionIndex() {
        return SyncPush::getUnsyncCount();
    }

    public function actionFetch() {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3000);
        $fetchModel = new SyncFetch([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$fetchModel->doSync()) {
                throw new Exception(json_encode($fetchModel->errors));
            }
        } catch (Exception $ex) {
            $errors = json_decode($ex->getMessage());
            $error = json_decode($errors->syncType[0], true);
            if(isset($error['statusCode']) && $error['statusCode'] == 500){
                $resultData = json_encode([
                    'sync' => Yii::t('app', 'Failed to fetch data'),
                    'type' => $fetchModel->syncType,
                    'result' => 'An Internal Server Error'
                ]);
                throw new HttpException(500, $resultData);
            } else {
                $resultData = json_encode([
                    'sync' => Yii::t('app', 'Failed to fetch data'),
                    'type' => $fetchModel->syncType,
                    'result' => $ex->getMessage()
                ]);
                throw new HttpException(400, $resultData);
            }
        }
    }

    public function actionFetchUser() {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3000);
        $fetchModel = new SyncFetch([
            'attributes' => $this->request->post()
        ]);

        try {
            if (!$fetchModel->doSync()) {
                throw new Exception(json_encode($fetchModel->errors));
            }
        } catch (Exception $ex) {
            $resultData = json_encode([
                'sync' => Yii::t('app', 'Failed to fetch data'),
                'result' => $ex->getMessage()
            ]);
            throw new HttpException(400, $resultData);
        }
    }

    public function actionPush() {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3000);
        $pushModel = new SyncPush([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$pushModel->doSync()) {
                throw new Exception(json_encode($pushModel->errors));
            }
        } catch (Exception $ex) {
            $resultData = json_encode([
                'sync' => Yii::t('app', 'Failed to push data'),
                'result' => $ex->getMessage()
            ]);
            throw new HttpException(500, $resultData);
        }
    }

    public function actionPushUser() {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 3000);
        $pushModel = new SyncPush([
            'attributes' => $this->request->post()
        ]);
        try {
            if (!$pushModel->doSync()) {
                throw new Exception(json_encode($pushModel->errors));
            }
        } catch (Exception $ex) {
            $resultData = json_encode([
                'sync' => Yii::t('app', 'Failed to push data'),
                'result' => $ex->getMessage()
            ]);
            throw new HttpException(500, $resultData);
        }
    }
    
    public function actionCheckSalesNotSync() {
        return SalesHead::CheckSalesNotSync($this->request->post('shiftInTime'));
    }

}
