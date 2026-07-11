<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\httpclient\Client;

class GoFoodNotification extends Model
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

    public function markFoodReady(){
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
            $checkSalesNum = (new Query)
            ->select([
                'salesHead.salesNum',
                'salesHead.additionalInfo'
            ])
            ->from(['salesHead' => SalesHead::tableName()])
            ->innerJoin([
                'serviceType' => SalesInfo::tableName()],
                "serviceType.salesNum = salesHead.salesNum AND serviceType.key = 'GoFood Type' AND serviceType.value = 'GoFood Pickup'")
            ->where(['IN', 'salesHead.salesNum', $salesNums])
            ->andWhere(['salesHead.transactionModeID' => 10])
            ->one();

            if($checkSalesNum){
                $countSalesMenu = SalesMenu::find()
                    ->select(SalesMenu::tableName() . '.ID')
                    ->innerJoinWith('salesHead')
                    ->where(['<>', SalesMenu::tableName() . '.statusID', 19])
                    ->andWhere(['IN', SalesMenu::tableName() . '.salesNum', $salesNums])
                    ->andWhere([SalesHead::tableName() . '.transactionModeID' => 10])
                    ->andWhere([SalesMenu::tableName() . '.salesType' => 'GOFOOD'])
                ->count();
    
                $countDoneSalesMenu = SalesMenu::find()
                    ->select(SalesMenu::tableName() . '.ID')
                    ->innerJoinWith('salesHead')
                    ->where([SalesMenu::tableName() . '.statusID' => 14])
                    ->andWhere(['IN', SalesMenu::tableName() . '.salesNum', $salesNums])
                    ->andWhere([SalesHead::tableName() . '.transactionModeID' => 10])
                    ->andWhere([SalesMenu::tableName() . '.salesType' => 'GOFOOD'])
                ->count();
                
                if ($countSalesMenu === $countDoneSalesMenu) {
                    $branchModel = Branch::find()
                        ->select([
                            'branchID',
                            'branchName',
                            'brandID'
                        ])
                        ->where(['branchID' => $this->branchID])
                    ->one();

                    try {
                        $additionalInfoArray = explode("|", $checkSalesNum['additionalInfo']);
                        $goFoodOrderID = sizeof($additionalInfoArray) > 0 ? trim($additionalInfoArray[0]) : 0;
                
                        // @refactor http_helper
                        $httpService = new HttpHelperService();
                        $url = $this->apiUrl . '/gofood/main/mark-food-ready';
                        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
                        $requestBody = [
                            'salesNum' => $checkSalesNum['salesNum'],
                            'goFoodOrderID' => $goFoodOrderID,
                            'brandID' => $branchModel->brandID,
                            'branchID' => $branchModel->branchID
                        ];
                        $options = ['timeOut' => 300];
                        $response = $httpService->post($url, $headers, $requestBody, $options);

                        return $response->getData();
                    } catch (Exception $ex) {
                        Yii::error($ex);
                        return false;
                    }
                }
            }else{
                return false; //not GOFOOD, not GOFOOD Pickup
            }
        }
    }
}