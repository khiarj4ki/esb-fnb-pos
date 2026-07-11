<?php

namespace app\models\forms;

use app\models\Brand;
use app\models\BrandApiContent;
use app\models\BrandSetting;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * @property string $employeeCode
 * @property string $result
 * @property string $status
 */
class MapValidate extends Model
{
    const GET_EMPLOYEE_LIMIT_API_URL = 'Get Employee Limit API Url';
    public $employeeCode;
    public $result;
    public $status;
    public $dataLogging;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['employeeCode'], 'required'],
            [['employeeCode', 'result', 'status'], 'safe']
        ];
    }

    public function init()
    {
        if (!$this->validate()) {
            return false;
        }

        try {
            $result = $this->connectToThirdParty();
            if ($result->getIsOk()) {
                $this->result = $result->getData()['status'];
                if ($this->result == 0) {
                    $this->status = 'Tidak Valid';
                }
                if ($this->result == 1) {
                    $this->status = 'Valid';
                }
                if ($this->result == 2) {
                    $this->status = 'Invalid Token';
                }
                return true;
            } else {
                $this->status = 'Failed to get api';
                return false;
            }
        } catch (Exception $ex) {
            Yii::error(json_encode($ex->getMessage()));
            throw $ex;
        }
    }

    public function getMapEmployee()
    {
        if (!$this->validate()) {
            return false;
        }

        try {

            $result = $this->connectToThirdParty();
            Logging::save('-', Logging::MAP_EMPLOYEE, $this->dataLogging);
            if ($result->getIsOk()) {
                return $result->getData();
            } else {
                throw new Exception("Fail to fetch MAP Employee");
            }
        } catch (Exception $ex) {
            Yii::error(json_encode($ex->getMessage()));
            throw $ex;
        }
    }

    public function connectToThirdParty()
    {
        $branchID = Setting::getCurrentBranch();
        $companyAuthKey = Setting::getApiKey();
        $brandModel = Brand::find()
            ->joinWith('branch')
            ->andWhere(['branchID' => $branchID])
            ->one();
        if (!$brandModel) {
            throw new Exception("Brand Not Found", true);
        }
        $brandPosSetting = BrandSetting::getBrandPosSetting();
        $brandApiContentModel = BrandApiContent::findApiContent($brandModel->brandID, SELF::GET_EMPLOYEE_LIMIT_API_URL);
        $tokenApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::GET_EMPLOYEE_LIMIT_API_URL]), $companyAuthKey);
        $bodyRequest = [];
        foreach ($brandApiContentModel->all() as $tokenContent) {
            $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
        }
        $bodyRequest['isFromMobile'] = true;
        $bodyRequest['cardNo'] = $this->employeeCode;
        $bodyRequest['discountUse'] = 0;

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $tokenApiUrl;
        $headers = [];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $bodyRequest, $options);

        $this->dataLogging = [
            'body' => $bodyRequest,
            'response' => json_decode($result->getContent(), true)
        ];

        return $result;
    }
}
