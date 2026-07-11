<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\SalesHead;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\httpclient\Client;

class SaveMemberFs extends Model {

    public $salesNum;
    public $flagExternalAPI;
    public $flagExternalMemberID;
    public $flagExternalMemberPhone;
    public $flagExternalCardID;
    public $externalMemberName;
    public $externalMembershipTypeID;
    public $webSocketID;
    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'flagExternalAPI', 'flagExternalMemberID', 'flagExternalMemberPhone', 'flagExternalCardID'], 'required'],
            [['externalMemberName', 'externalMembershipTypeID', 'webSocketID'], 'safe']
        ];
    }

    public function saveMember() {
        if (!$this->validate()) {
            return false;
        }
        $salesHeadModel = SalesHead::findOutstandingOrder()
            ->andWhere([SalesHead::tableName() . '.salesNum' => $this->salesNum])
            ->one();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($salesHeadModel) {
                $salesHeadModel->flagExternalAPI = $this->flagExternalAPI;
                $salesHeadModel->flagExternalMemberID = $this->flagExternalMemberID;
                $salesHeadModel->flagExternalMemberPhone = $this->flagExternalMemberPhone ? substr($this->flagExternalMemberPhone, 0, 20) : $this->flagExternalMemberPhone;
                $salesHeadModel->flagExternalCardID = $this->flagExternalCardID;
                $salesHeadModel->externalMemberName = $this->externalMemberName;
                $salesHeadModel->externalMembershipTypeID = $this->externalMembershipTypeID;
            
                if (!$salesHeadModel->save()) {
                    throw new Exception('Failed to save member');
                }

                $selfOrderApi = Setting::getEsoFsApiUrl();
                $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
                $companyCode = $branch->companyCode;
                $authKey = Setting::getApiKey();

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $selfOrderApi . 'save-member';
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-company' => AppHelper::getCompanyCode(),
                    'data-branch' => AppHelper::getBranchCode(),
                    'data-webSocketId' => $this->webSocketID
                ];
                $datas = [
                    'salesNum' => $salesHeadModel->salesNum,
                    'flagExternalAPI' => $salesHeadModel->flagExternalAPI,
                    'flagExternalMemberID' => $salesHeadModel->flagExternalMemberID,
                    'flagExternalMemberPhone' => $salesHeadModel->flagExternalMemberPhone,
                    'flagExternalCardID' => $salesHeadModel->flagExternalCardID,
                    'externalMemberName' => $salesHeadModel->externalMemberName
                ];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $datas, $options);

                if ($result->getIsOk()) {
                    Logging::save($salesHeadModel->salesNum, Logging::ADD_MEMBER_EZO, $this->getAttributes());
                    $transaction->commit();
                    return $this->salesNum;
                } else {
                    throw new Exception("Failed to save online.");
                }
            } else {
                throw new Exception("Sales Head Not Found");
            }
        } catch (\Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }

    }

}