<?php

namespace app\modules\v1\controllers;

use app\models\WebSocket;
use Yii;
use yii\db\Exception;
use yii\web\HttpException;

class WebSocketController extends BaseController
{
    
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge(
            $behaviors['authenticator']['except'],
            [
                'get-web-socket', 'update-web-socket', 'save-log'
            ]
        );
        return $behaviors;
    }

    public function actionGetWebSocket()
    {
        $model = new WebSocket();
        return $model->getWebSocket();
    }


    public function actionUpdateWebSocket()
    {
        $this->validatePost();
        
        try {
            
            $timestamp = WebSocket::updateWebSocket($this->request->post('timestamp'));
            Yii::warning($timestamp);
            if(empty($timestamp) && $timestamp !== 0 && $timestamp !== '0'){
                throw new HttpException(
                    500,
                    Yii::t('app', 'Failed to update Websocket')
                );
            }

            return [
                'timestamp' => $timestamp
            ];

        } catch (Exception $ex) {
            throw new HttpException(500, $ex->getMessage());
        }
    }


    private function validatePost() {
        if (!$this->request->post()) {
            throw new HttpException(400);
        }
    }

    public function actionSaveLog() {
        $this->validatePost();
        return WebSocket::saveErrorLog($this->request->post('message'));
    }

}
