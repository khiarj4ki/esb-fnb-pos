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
 * @property string $branchCode
 * @property string $employeeCode
 * @property string $billNumber
 * @property string $discountValue
 * @property string $result
 * @property string $status
 */
class MapUpdateLimit extends Model
{
    const UPDATE_EMPLOYEE_LIMIT_API_URL = 'Update Employee Limit API Url';
    public $branchCode;
    public $employeeCode;
    public $billNumber;
    public $discountValue;
    public $result;
    public $status;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['employeeCode', 'billNumber', 'discountValue'], 'required'],
            [['employeeCode', 'billNumber', 'discountValue', 'result', 'status'], 'safe']
        ];
    }

    public function update()
    {
        if (!$this->validate()) {
            return false;
        }

        $dataLogging = null;

        try {
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
            $brandApiContentModel = BrandApiContent::findApiContent($brandModel->brandID, SELF::UPDATE_EMPLOYEE_LIMIT_API_URL);
            $client = new Client();
            $tokenApiUrl = Yii::$app->security->decryptByKey(base64_decode($brandPosSetting[SELF::UPDATE_EMPLOYEE_LIMIT_API_URL]), $companyAuthKey);
            $bodyRequest = [];
            foreach ($brandApiContentModel->all() as $tokenContent) {
                $bodyRequest[$tokenContent->keyAttribute] = Yii::$app->security->decryptByKey(base64_decode($tokenContent->valueAttribute), $companyAuthKey);
            }
            $bodyRequest['warehouse'] = $this->branchCode;
            $bodyRequest['invoiceNumber'] = $this->billNumber;
            $bodyRequest['cardNo'] = $this->employeeCode;
            $bodyRequest['discount'] = $this->discountValue;
            $bodyRequest['storetimestamp'] = time();
 
            // @refactor http_helper
            $httpService = new HttpHelperService();
            $url = $tokenApiUrl;
            $headers = [];
            $options = ['timeOut' => 300];
            $result = $httpService->post($url, $headers, $bodyRequest, $options);

            $dataLogging = [
                'body' => $bodyRequest,
                'response' => json_decode($result->getContent(), true)
            ];
            Logging::save('-', Logging::MAP_UPDATE_EMPLOYEE_LIMIT, $dataLogging);
            if ($result->getIsOk()) {
                $this->result = $result->getData()['status'];
                if ($this->result == 0) {
                    $this->status = 'Failed';
                }
                if ($this->result == 1) {
                    $this->status = 'Success';
                }
                return true;
            }
        } catch (Exception $ex) {
            Yii::error(json_encode($ex->getMessage()));
            throw $ex;
        }
    }
}
