<?php

namespace app\modules\v1\controllers;

use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\httpclient\Client;
use yii\web\HttpException;

class MigrateController extends BaseController {
    const API_VERSION = 'esb_apiv11';

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'index', 'get-branch', 'run'
        ]);
        return $behaviors;
    }

    public function actionIndex() {
        $apiUrl = Setting::getApiUrl();
        $apiKey = Setting::getApiKey();
        $branchID = Setting::getCurrentBranch();

        return $apiUrl == null && $apiKey == null && $branchID == null;
    }

    public function actionGetBranch() {
        $apiUrl = $this->request->post('apiUrl');
        $apiKey = $this->request->post('apiKey');

        if (!$apiUrl || !$apiKey) {
            throw new HttpException(400);
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $apiUrl . '/' . self::API_VERSION . '/main/get-branch';
        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        $options = ['timeOut' => 300];
        $datas =   [];
        $response = $httpService->post($url, $headers, $datas, $options);
        if ($response->getIsOk()) {
            return $response->getData();
        } else {
            switch ($response->getStatusCode()) {
                case '401':
                    throw new HttpException(500, 'Invalid API Key');
                case '404':
                    throw new HttpException(500, 'Invalid API URL');
                default:
                    throw new HttpException(500,
                    $response->getData() ? $response->getData()['message'] : 'Failed to fetch data');
            }
        }
    }

    public function actionRun() {
        $apiUrl = $this->request->post('apiUrl');
        $apiKey = $this->request->post('apiKey');
        $branchID = $this->request->post('branchID');

        try {
            if (!Setting::saveLocalSetting('Api Url', $apiUrl, true)) {
                throw new Exception('Failed to save api url');
            }
            if (!Setting::saveLocalSetting('Api Key', $apiKey, true)) {
                throw new Exception('Failed to save api key');
            }
            if (!Setting::saveLocalSetting('Branch ID', $branchID, false)) {
                throw new Exception('Failed to save branch id');
            }

            return true;
        } catch (Exception $ex) {
            Setting::deleteAll(['AND',
                ['key1' => 'Local Setting'],
                ['IN', 'key2', ['Api Url', 'Api Key', 'Branch ID']],
            ]);
            Yii::warning($ex);
            return false;
        }
    }

}
