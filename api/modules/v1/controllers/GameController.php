<?php
namespace app\modules\v1\controllers;

use app\models\Branch;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\httpclient\Client;
use yii\web\HttpException;

class GameController extends BaseController {
    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = array_merge($behaviors['authenticator']['except'],
            [
            'check-branch-participate'
        ]);
        return $behaviors;
    }

    public function actionCheckBranchParticipate() {
        try {
            $postData = Yii::$app->request->post();
            if (!isset($postData['branchCode']) || !$postData['branchCode']) {
                throw new Exception('Branch code cannot be empty', 400);
            }
            
            $branch = Branch::findOne(['branchCode' => $postData['branchCode']]);
            if ($branch) {
                $selfOrderApi = Setting::getEsoQsApiUrl();
                $companyCode = $branch->companyCode;
                $authKey = Setting::getApiKey();

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $selfOrderApi . 'check-branch-participate';
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-company' => $companyCode,
                    'data-branch' => $postData['branchCode'],
                ];
                $data = ['clientBranchID' => $branch->branchID];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $data, $options);

                if ($result->getIsOk()) {
                    return $result->getData();
                } else {
                    throw new Exception($result->getData(), $result->getStatusCode());
                }
            } else {
               throw new Exception('Invalid branch code', 400);
            }
        } catch (Exception $ex) {
            Yii::error($ex);
            throw $ex;
        }
    }

}
