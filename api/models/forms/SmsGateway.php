<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\CustomerTransaction;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesPayment;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\httpclient\Client;

class SmsGateway extends Model
{
    public $order;
    public $salesNum;
    public $apiKey;
    public $apiUrl;
    public $branchID;

    public function rules()
    {
        return [
            [['order', 'salesNum'], 'safe']
        ];
    }

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->apiKey = Setting::getApiKey();
        $this->apiUrl = Setting::getApiUrl();
        $this->branchID = Setting::getCurrentBranch();
    }

    public function sendSms() {
        $salesNums = [];
        if ($this->order) {
            foreach ($this->order as $order) {
                if (!in_array($order['salesNum'], $salesNums)) {
                    $salesNums[] = $order['salesNum'];
                }
            }
        } else if ($this->salesNum) {
            $salesNums[] = $this->salesNum;
        }

        if (count($salesNums) > 0) {
            $countSalesMenu = SalesMenu::find()
                ->select(SalesMenu::tableName() . '.ID')
                ->innerJoinWith('salesHead')
                ->where(['<>', SalesMenu::tableName() . '.statusID', 19])
                ->andWhere(['IN', SalesMenu::tableName() . '.salesNum', $salesNums])
                ->andWhere([SalesMenu::tableName() . '.salesType' => 'EZO QS'])
                ->andWhere([SalesHead::tableName() . '.transactionModeID' => 2])
                ->count();

            $countDoneSalesMenu = SalesMenu::find()
                ->select(SalesMenu::tableName() . '.ID')
                ->innerJoinWith('salesHead')
                ->where([SalesMenu::tableName() . '.statusID' => 14])
                ->andWhere(['IN', SalesMenu::tableName() . '.salesNum', $salesNums])
                ->andWhere([SalesMenu::tableName() . '.salesType' => 'EZO QS'])
                ->andWhere([SalesHead::tableName() . '.transactionModeID' => 2])
                ->count();
            
            if ($countSalesMenu === $countDoneSalesMenu) {
                $branchModel = Branch::find()
                    ->select([
                        'branchName',
                        'brandID'
                    ])
                    ->where(['branchID' => $this->branchID])
                    ->one();
                
                $branchName = $branchModel ? $branchModel->branchName : 'ESB Branch';
                if (strlen($branchName) > 30) {
                    $branchName = substr($branchName, 0, 30);
                 }                 

                $model = SalesPayment::find()
                    ->select([
                        SalesPayment::tableName() . '.selfOrderID',
                        'b.phoneNumber',
                        'c.queueNum',
                        'c.transactionModeID',
                        'd.salesType'
                    ])
                    ->innerJoin(CustomerTransaction::tableName() . ' b', 
                        SalesPayment::tableName() . '.salesNum = b.salesNum')
                    ->innerJoin(SalesHead::tableName() . ' c', 
                        SalesPayment::tableName() . '.salesNum = c.salesNum')
                    ->innerJoin(SalesMenu::tableName() . ' d', 
                        SalesPayment::tableName() . '.salesNum = d.salesNum')
                    ->where(['IN', SalesPayment::tableName() . '.salesNum', $salesNums])
                    ->andWhere(['d.salesType' => 'EZO QS'])
                    ->andWhere(['c.transactionModeID' => 2])
                    ->andWhere(['IS NOT', SalesPayment::tableName() . '.selfOrderID', null])
                    ->groupBy([
                        SalesPayment::tableName() . '.salesNum',
                        SalesPayment::tableName() . '.selfOrderID'
                    ])->asArray()->all();
                
                if ($model) {
                   foreach ($model as $data) {
                       try {
                        // @refactor http_helper
                        $httpService = new HttpHelperService();
                        $url = $this->apiUrl . '/esb_api/main/send-sms';
                        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                        $datas =   [
                            'uniqueNumber' => date('Ymd').sprintf("%05d", $data['queueNum']),
                            'branchName' => $branchName,
                            'selfOrderID' => $data['selfOrderID'],
                            'queueNum' => $data['queueNum'],
                            'phoneNumberForTest' => $data['phoneNumber'],
                            'brandID' => $branchModel->brandID
                        ];
                        $options = ['timeOut' => 300];
                        $response = $httpService->post($url, $headers, $datas, $options);
                        return $response->getData();
                       } catch (Exception $ex) {
                           Yii::error($ex);
                           return false;
                       }
                   }
                }
            }
        }
    }
}
