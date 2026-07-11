<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BranchMenu;
use app\models\EsoWebSocketQueue;
use app\models\Menu;
use app\models\SalesHead;
use app\models\Setting;
use app\models\forms\SelfOrderCampaign;
use app\models\forms\ApplyOrderPromo;
use app\models\forms\SyncSelfOrder;
use app\models\MenuExtra;
use app\models\SalesRewardHead;
use app\models\SalesRewardMenu;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesPlatformFee;
use app\services\http_helper\HttpHelperService;
use Exception;
use Yii;
use yii\base\Model;
use yii\httpclient\Client;
use yii\web\HttpException;

/**
 * @property string $transId
 * @property array $salesMenu
 * @property int $timestamp
 * 
 * PRIVATE
 * @property string $salesNum
 * @property SalesHead $salesModel
 * @property int $batchID
 * @property string $webSocketID
 */
class SaveOnlineOrder extends Model
{
    public $transId;
    public $salesMenu;
    public $platformFee;
    public $timestamp;
    public $salesNum;
    public $salesModel;
    public $batchID;
    public $serverTimeDifference;
    public $webSocketID;
    public $salesMenuCampaign;
    public $salesNumCampaign;
    public $promoID;
    public $salesModelArray;
    public $promotionDiscount;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['transId', 'timestamp'], 'required'],
            [['salesMenu', 'platformFee', 'serverTimeDifference', 'webSocketID', 'salesMenuCampaign', 'promoID', 'promotionDiscount'], 'safe'],
            [['transId'], 'validateSales'],
            [['timestamp'], 'validateTimestamp'],
            [['salesMenu'], 'validateSoldOut']
        ];
    }

    public function validateSales($attribute)
    {
        $this->salesNum = AppHelper::decryptTransId($this->transId);
        $this->salesModel = SalesHead::findOutstanding()
            ->with('salesRewardHead')
            ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
            ->one();
        if (!$this->salesModel) {
            $this->addError($attribute, Yii::t('app', 'Invalid transaction ID'));
        }
    }

    public function validateTimestamp($attribute)
    {
        if ((time() - $this->serverTimeDifference) - $this->timestamp > 25) {
            $this->addError($attribute, Yii::t('app', 'Time out'));
        }
    }

    public function validateSoldOut($attribute)
    {
        $menuIDs = [];
        $count = 0;
        foreach ($this->salesMenu as $salesMenu) {
            if (!$this->validateStock($salesMenu['menuID'], $salesMenu['qty'])) {
                $menuIDs[] = $salesMenu['menuID'];
                $count++;
            }

            if (isset($salesMenu['packages'])) {
                foreach ($salesMenu['packages'] as $package) {
                    if (!$this->validateStock($package['menuID'], $package['qty'] * $salesMenu['qty'])) {
                        $menuIDs[] = $package['menuID'];
                        $count++;
                    }
                }
            }

            if (isset($salesMenu['extras'])) {
              foreach ($salesMenu['extras'] as $extra) {
                  $menuExtraModel = MenuExtra::find()
                      ->with('menu')
                      ->where(['=', 'ms_menuextra.menuExtraID', $extra['menuExtraID']])
                      ->one();
                  if ($menuExtraModel->menu) {
                    if (!$this->validateStock($menuExtraModel->menu->menuID, $extra['qty'] * $salesMenu['qty'])) {
                        $menuIDs[] = $menuExtraModel->menu->menuID;
                        $count++;
                    }
                  }
              }
          }
        }
        if ($count > 0) {
            $this->addError($attribute, $menuIDs);
        }

        $menuIDs = array_column($this->salesMenu, 'menuID');
        if (($data = $this->validateMenuExists($menuIDs))) {
            if (!$data['valid']) {
              $this->addError('menu', $data['menuIDs']);
            }
        }
    }

    public function save()
    {
        set_time_limit(0);
        $startTime = time();
        $selfOrderApi = Setting::getEsoFsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();

        if (!$this->validate()) {
            $this->sendWsResultToEsoFs($selfOrderApi, $companyCode, $authKey);
            return false;
        }

        try {
            Yii::$app->db->createCommand('LOCK TABLES '.EsoWebSocketQueue::tableName().' WRITE')->execute();

            $findModel = EsoWebSocketQueue::find()
            ->where(['webSocketID' => $this->webSocketID])
            ->one();
            if (!$findModel) {
                // Create a new lock record
                $lock = new EsoWebSocketQueue();
                $lock->webSocketID = $this->webSocketID;
                $lock->save();
            } else { 
                throw new Exception("Process for web socket id ".$this->webSocketID.' still running');
            }
        } catch (\Exception $e) {
            Yii::error('Failed to acquire lock: ' . $e->getMessage());
            return false;
        } finally {
            Yii::$app->db->createCommand('UNLOCK TABLES')->execute();
        }



        $this->defineMissingFieldEsoFs($this->salesModel->flagInclusive);

        $arrErrorResponse = [];
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Lock Sales Head row by Sales Number
            $lockSalesHeadQuery = SalesHead::find()
                ->where(['salesNum' => $this->salesNum])
                ->createCommand()
                ->getRawSql();

            SalesHead::findBySql($lockSalesHeadQuery . ' FOR UPDATE')->one();

            $selfOrderCampaign = new SelfOrderCampaign();
            $selfOrderCampaignResult = $selfOrderCampaign->checkSelfOrderCampaign($this->salesModel, $this->salesMenu);
            $this->salesMenu = $selfOrderCampaignResult['salesMenu'];
            $this->salesMenuCampaign = isset($selfOrderCampaignResult['salesMenuCampaign']) ? $selfOrderCampaignResult['salesMenuCampaign'] : [];

            // @notes: Terminate / rollback process jika lewat dari batas toleransi process x detik.
            $timeoutTime = 20;
            if (time() - $startTime > $timeoutTime) {
                throw new Exception("Request Time out > $timeoutTime 's", 500);
            }

            // Insert Platform Fee Data
            if ($this->platformFee) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($this->salesModel->salesNum, $this->platformFee)) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }

            // Load All Sales Menu
            $this->salesMenu = $this->loadSalesMenus($this->salesModel, $this->salesMenu);

            $updateModel = new UpdateOrder();
            $updateModel->saveOnly = true;
            $updateModel->validateStock = false;
            $updateModel->tableID = $this->salesModel->tableID;
            $updateModel->memberCode = $this->salesModel->memberCode;
            $updateModel->salesNum = $this->salesModel->salesNum;
            $updateModel->salesMenu = $this->salesMenu;
            $updateModel->visitPurposeID = $this->salesModel->visitPurposeID;
            $updateModel->paxTotal = $this->salesModel->paxTotal;
            $updateModel->promotionID = $this->salesModel->promotionID;
            $updateModel->promotionVoucherCode = $this->salesModel->promotionVoucherCode;
            $updateModel->promotionDiscount = isset($selfOrderCampaign['promotionDiscount']) ? $selfOrderCampaign['promotionDiscount'] : $this->salesModel->promotionDiscount;
            $updateModel->orderTimeOut = $this->salesModel->orderTimeOut ? SalesHead::getOrderTimeOut(
                date_create($this->salesModel->salesDateIn),
                date_create($this->salesModel->orderTimeOut)
            )
            : null;
            $updateModel->orderFee = $this->salesModel->orderFee;
            $updateModel->externalMembershipTypeID = $this->salesModel->externalMembershipTypeID;
            $updateModel->flagExternalAPI = $this->salesModel->flagExternalAPI;
            $updateModel->flagExternalMemberID = $this->salesModel->flagExternalMemberID;
            $updateModel->flagExternalMemberPhone = $this->salesModel->flagExternalMemberPhone;
            $updateModel->flagExternalCardID = $this->salesModel->flagExternalCardID;
            $updateModel->externalMemberName = $this->salesModel->externalMemberName;
            $updateModel->rewardType = $this->salesModel->salesRewardHead ? $this->salesModel->salesRewardHead->rewardType : null;
            $updateModel->ezoFullService = true;
            $updateModel->additionalInfo = $this->salesModel->additionalInfo;
            $updateModel->webSocketID = $this->webSocketID;
            $updateModel->platformFee = $this->platformFee;

            if (!$updateModel->save()) {
                throw new Exception('Failed to save data', 500);
            } else {
                $selfOrderCampaign->incrementUsedQty($selfOrderCampaignResult['selectCampaign']);
                $salesModel = SalesHead::findOne($updateModel->salesNum);
                $salesHead = $this->defineSalesHeadEso($salesModel);
                
                $salesRewardHead = [];
                if ($updateModel->rewardType) {
                    $salesRewardHead = SalesRewardHead::find()->where(['salesNum' => $updateModel->salesNum])->asArray()->all();
                }

                $salesRewardMenu = [];
                $rewardVoucher = in_array('voucher', array_column($updateModel->newSalesMenuFs, 'rewardType'));
                $rewardBenefit = in_array('benefit', array_column($updateModel->newSalesMenuFs, 'rewardType'));
                if ($rewardVoucher || $rewardBenefit) {
                    $localIDs = array_column($updateModel->newSalesMenuFs, 'localID');
                    $salesRewardMenu = SalesRewardMenu::find()
                        ->where(['salesNum' => $updateModel->salesNum])
                        ->andWhere(['IN', 'localID', $localIDs])
                        ->asArray()
                        ->all();
                }

                $bodyRequest = [
                    'salesHead' => $salesHead,
                    'salesMenu' => $updateModel->newSalesMenuFs,
                    'selectCampaign' => $selfOrderCampaignResult['selectCampaign'],
                    'salesMenuCampaign' => $this->salesMenuCampaign,
                    'salesRewardHead' => $salesRewardHead,
                    'salesRewardMenu' => $salesRewardMenu,
                ];

                $sendStatus = false;
                $maxAttempts = 3;
                $attempt = 0;
                $startExecTime = null;
                $endExecTime = null;
                do {
                    $attempt++;
                    $bodyRequest['attempt'] = $attempt;
                    try {
                        $startExecTime = date('H:i:s.').preg_replace("/^.*\./i","", microtime(true));
  
                        // @refactor http_helper
                        $httpService = new HttpHelperService();
                        $url = $selfOrderApi . 'save-menu';
                        $headers = [
                            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                            'data-company' => AppHelper::getCompanyCode(),
                            'data-branch' => AppHelper::getBranchCode(),
                            'data-transId' => $this->transId,
                            'data-webSocketId' => $this->webSocketID
                        ];
                        $options = ['timeOut' => 300];
                        $result = $httpService->post($url, $headers, $bodyRequest, $options);

                        $endExecTime = date('H:i:s.').preg_replace("/^.*\./i","", microtime(true));
                        if ($result->getIsOk()) {
                            $this->batchID = $updateModel->batchID;
                            $transaction->commit();
                            $sendStatus = true;
                            break;
                        } else {
                            $response = $result->getData();
                            $arrErrorResponse[] = [
                                "attempt" => $attempt,
                                "message" => $response['message'],
                                "startTime" => $startExecTime,
                                "endTime" => $endExecTime,
                                "response" => $response,
                                "request" => $bodyRequest
                            ];
                        }
                    } catch (\Exception $ex) {
                        $endExecTime = date('H:i:s.').preg_replace("/^.*\./i","", microtime(true));
                        $arrErrorResponse[] = [
                            "attempt" => $attempt,
                            "message" => $ex->getMessage(),
                            "startTime" => $startExecTime,
                            "endTime" => $endExecTime,
                            "request" => $bodyRequest
                        ];
                    }
                    
                    sleep(3);
                } while ($attempt < $maxAttempts);

                if ($sendStatus) {
                    $this->saveLogSendApiMenuEsoFs($arrErrorResponse);
                    return true;
                } else {
                    throw new Exception(end($arrErrorResponse)['message'], 500);
                }
            }
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->saveLogSendApiMenuEsoFs($arrErrorResponse);
            $this->addError('salesMenu', $ex->getMessage() . ' ' . $ex->getLine() . ' ' . $ex->getFile());
            return false;
        }
    }

    public function saveCampaignPromo()
    {

        $startTime = time();

        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();

        $selfOrderApi = Setting::getEsoFsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();

        // @notes: Terminate / rollback process jika lewat dari batas toleransi process x detik.
        $timeoutTime = 20;
        if (time() - $startTime > $timeoutTime) {
            $transaction->rollBack();
            throw new Exception("Request Time out > $timeoutTime 's");
        }

        if ($this->promoID && $this->promotionDiscount) {
            try {
                $this->salesModel = SalesHead::findOutstanding()->where(['salesNum' => $this->salesNum])->asArray()->one();
                $this->salesModel['salesMenu'] = SalesMenu::findActive()
                    ->where(['salesNum' => $this->salesNum])
                    ->asArray()->all();
                $this->salesModel['promotionDiscount'] = $this->promotionDiscount;
                $this->salesModel['modePromotion'] = 0;
                $i = 0;
                $menuIDs = [];
                foreach ($this->salesModel['salesMenu'] as $salesMenu) {
                    $packageModel = SalesMenu::find()
                        ->where(['menuRefID' => $salesMenu['ID']])
                        ->andWhere(['<>', 'ID', $salesMenu['ID']])
                        ->andWhere(['<>', 'menuRefID', 0])
                        ->andWhere(['salesNum' => $salesMenu['salesNum']])
                        ->asArray()
                        ->all();

                    $extraModel = SalesMenuExtra::find()
                        ->where(['salesNum' => $salesMenu['salesNum']])
                        ->andWhere(['menuDetailID' => $salesMenu['ID']])
                        ->asArray()
                        ->all();
                    if (!$packageModel) {
                        $packageModel = [];
                    }
                    if (!$extraModel) {
                        $extraModel = [];
                    }
                    $this->salesModel['salesMenu'][$i]['discountTotal'] = isset($salesMenu['discountTotal']) ? $salesMenu['discountTotal'] : 0;
                    $this->salesModel['salesMenu'][$i]['sellPrice'] = $salesMenu['price'];
                    $this->salesModel['salesMenu'][$i]['packages'] = $packageModel;
                    $this->salesModel['salesMenu'][$i]['extras'] = $extraModel;
                    if (!in_array($salesMenu['menuID'], $menuIDs)) {
                        $menuIDs[] = $salesMenu['menuID'];
                    }
                    $i++;
                }

                $menuModel = Menu::find()
                    ->innerJoinWith('menuCategoryDetail')
                    ->where(['IN', 'menuID', $menuIDs])
                    ->all();

                $i = 0;
                foreach ($this->salesModel['salesMenu'] as $salesMenu) {
                    foreach ($menuModel as $menu) {
                        if ($salesMenu['menuID'] == $menu->menuID) {
                            $this->salesModel['salesMenu'][$i]['menuCategoryDetailID'] = $menu->menuCategoryDetailID;
                            $this->salesModel['salesMenu'][$i]['menuCategoryID'] = $menu->menuCategoryDetail->menuCategoryID;
                        }
                    }
                    $i++;
                }

                $applyPromoModel = new ApplyOrderPromo();
                $applyPromoModel->promotionID = $this->promoID;
                $applyPromoModel->order = $this->salesModel;
                $applyPromoModel->tableID = $this->salesModel['tableID'];
                $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_HEAD;

                if (!$applyPromoModel->save()) {
                    if (isset($applyPromoModel->errorMessage)) {
                        throw new HttpException(404, $applyPromoModel->errorMessage);
                    }
                    throw new Exception(json_encode($applyPromoModel->errors));
                }

                $this->salesModel = $applyPromoModel['order'];

                $updateModel = new UpdateOrder();
                $updateModel->saveOnly = true;
                $updateModel->validateStock = false;
                $updateModel->tableID = $this->salesModel['tableID'];
                $updateModel->salesNum = $this->salesModel['salesNum'];
                $updateModel->salesMenu = $this->salesModel['salesMenu'];
                $updateModel->visitPurposeID = $this->salesModel['visitPurposeID'];
                $updateModel->paxTotal = $this->salesModel['paxTotal'];
                $updateModel->promotionID = $this->salesModel['promotionID'];
                $updateModel->promotionDiscount = $this->salesModel['promotionDiscount'];
                $updateModel->orderTimeOut = $this->salesModel['orderTimeOut'] ? SalesHead::getOrderTimeOut(
                    date_create($this->salesModel['salesDateIn']),
                    date_create($this->salesModel['orderTimeOut'])
                )
                    : null;
                $updateModel->orderFee = $this->salesModel['orderFee'];

                if (!$updateModel->save()) {
                    throw new Exception('Failed to save data');
                }
                $this->batchID = $updateModel->batchID;

                $salesHead = SalesHead::findOutstanding()->where(['salesNum' => $this->salesNum])->asArray()->one();
                $salesMenu = $updateModel->salesMenu;

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $selfOrderApi . 'save-menu-discount';
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-company' => AppHelper::getCompanyCode(),
                    'data-branch' => AppHelper::getBranchCode(),
                    'data-transId' => $this->transId,
                    'data-webSocketId' => $this->webSocketID
                ];
                $bodyRequest = [
                'salesHead' => $salesHead,
                'salesMenu' => $salesMenu
                ];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $bodyRequest, $options);

                if (!$result->getIsOk()) {
                    throw new Exception($result->getData()['message']);
                }

                $transaction->commit();
                return true;
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('salesMenu', $ex->getMessage() . ' ' . $ex->getLine() . ' ' . $ex->getFile());
                return false;
            }
        } else {
            throw new Exception('Promo ID or Promotion Discount not found');
        }
    }

    public function saveCampaign()
    {

        $startTime = time();

        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();

        $selfOrderApi = Setting::getEsoFsApiUrl();
        $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
        $companyCode = $branch->companyCode;
        $authKey = Setting::getApiKey();

        // @notes: Terminate / rollback process jika lewat dari batas toleransi process x detik.
        $timeoutTime = 20;
        if (time() - $startTime > $timeoutTime) {
            $transaction->rollBack();
            throw new Exception("Request Time out > $timeoutTime 's");
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $selfOrderApi . 'save-menu-free-item';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
            'data-company' => AppHelper::getCompanyCode(),
            'data-branch' => AppHelper::getBranchCode(),
            'data-transId' => $this->transId,
            'data-webSocketId' => $this->webSocketID
        ];
        $bodyRequest = [
            'salesHead' => $this->salesModel,
            'salesMenu' => $this->salesMenu,
            'selectCampaign' => null,
            'salesMenuCampaign' => null
        ];
        $options = ['timeOut' => 300];
        $result = $httpService->post($url, $headers, $bodyRequest, $options);

        if ($result->getIsOk()) {
            try {
                $updateModel = new UpdateOrder();
                $updateModel->saveOnly = true;
                $updateModel->validateStock = false;
                $updateModel->tableID = $this->salesModel->tableID;
                $updateModel->salesNum = $this->salesModel->salesNum;
                $updateModel->salesMenu = $result->getData();
                $updateModel->visitPurposeID = $this->salesModel->visitPurposeID;
                $updateModel->paxTotal = $this->salesModel->paxTotal;
                $updateModel->promotionID = $this->salesModel->promotionID;
                $updateModel->promotionDiscount = $this->salesModel->promotionDiscount;
                $updateModel->orderTimeOut = $this->salesModel->orderTimeOut ? SalesHead::getOrderTimeOut(
                    date_create($this->salesModel->salesDateIn),
                    date_create($this->salesModel->orderTimeOut)
                )
                    : null;
                $updateModel->orderFee = $this->salesModel->orderFee;

                if (!$updateModel->save()) {
                    throw new Exception('Failed to save data');
                }
                $this->batchID = $updateModel->batchID;

                $ezoSettings = Setting::getEZOSetting();
                if ($ezoSettings['Activate EZO'] == 1) {
                    $apiUrl = Setting::getEsoFsApiUrl();
                    if ($apiUrl) {
                        $syncSelfOrderModel = new SyncSelfOrder();
                        $syncSelfOrderModel->refNum = $this->salesModel->salesNum;
                        $syncSelfOrderModel->type = 'salesNum';
                        $syncSelfOrderModel->addQueue();
                    }
                }

                $transaction->commit();
                return true;
            } catch (Exception $ex) {
                $transaction->rollBack();
                $this->addError('salesMenu', $ex->getMessage() . ' ' . $ex->getLine() . ' ' . $ex->getFile());
                return false;
            }
        } else {
            throw new Exception($result->getData()['message']);
        }
    }

    private function validateStock($menuID, $qty)
    {
        if ($this->salesModel) {
            $branchMenuModel = BranchMenu::find()
                ->andWhere(['branchID' => $this->salesModel->branchID])
                ->andWhere(['menuID' => $menuID])
                ->andWhere([
                    'OR',
                    [
                        'AND',
                        ['flagSoldOut' => 0],
                        ['>', 'qty', 0],
                        ['<', 'qty', $qty]
                    ],
                    ['flagSoldOut' => 1]
                ])
                ->one();
            if ($branchMenuModel) {
                return false;
            }
        }

        return true;
    }

    private function validateMenuExists($menuIDs)
    {
        $notExistsMenuIDs = [];
        $menuModel = Menu::find()
          ->where(['IN', 'menuID', $menuIDs])
          ->asArray()
          ->all();
        
        $existMenuIDs = array_column($menuModel, 'menuID');
        foreach ($menuIDs as $id) {
          if (!in_array($id, $existMenuIDs)) {
            $notExistsMenuIDs[] = $id;
          }
        }

        return [
          'valid' => empty($notExistsMenuIDs),
          'menuIDs' => $notExistsMenuIDs
        ];
    }

    private function sendWsResultToEsoFs($selfOrderApi, $companyCode, $authKey)
    {
        $error = $this->getErrors();
        $response = [];
        if (isset($error['transId'])) {
            $response = [
                'errorType' => 'INVALID_SALES_NUMBER'
            ];
        } elseif (isset($error['timestamp'])) {
            $response = [
                'errorType' => 'REQUEST_EXPIRED'
            ];
        } elseif (isset($error['salesMenu'])) {
            $response = [
                'errorType' => 'MENU_SOLDOUT',
                'errorData' => [
                    'menu' => $error['salesMenu'][0]
                ]
            ];
        } elseif (isset($error['menu'])) {
            $response = [
                'errorType' => 'MENU_NOT_FOUND',
                'errorData' => [
                    'menu' => $error['menu'][0]
                ]
            ];
        }

        // @refactor http_helper
        $httpService = new HttpHelperService();
        $url = $selfOrderApi . 'save-ws-failed-response';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
            'data-company' => AppHelper::getCompanyCode(),
            'data-branch' => AppHelper::getBranchCode(),
            'data-transId' => $this->transId,
            'data-webSocketId' => $this->webSocketID
        ];
        $bodyRequest = [
            'message' => json_encode($response)
        ];
        $options = ['timeOut' => 300];
        $httpService->post($url, $headers, $bodyRequest, $options);
    }

    private function defineSalesHeadEso($salesModel) {
        return [
            'salesNum' => $salesModel->salesNum,
            'billNum' => $salesModel->billNum,
            'salesDate' => $salesModel->salesDate,
            'salesDateIn' => $salesModel->salesDateIn,
            'orderTimeOut' => $salesModel->orderTimeOut,
            'salesDateOut' => $salesModel->salesDateOut,
            'branchID' => $salesModel->branchID,
            'memberID' => $salesModel->memberID,
            'memberCode' => $salesModel->memberCode,
            'tableID' => $salesModel->tableID,
            'visitPurposeID' => $salesModel->visitPurposeID,
            'paxTotal' => $salesModel->paxTotal,
            'subtotal' => $salesModel->subtotal,
            'discountTotal' => $salesModel->discountTotal,
            'menuDiscountTotal' => $salesModel->menuDiscountTotal,
            'promotionDiscount' => $salesModel->promotionDiscount,
            'otherTaxTotal' => $salesModel->otherTaxTotal,
            'deliveryCost' => $salesModel->deliveryCost,
            'vatTotal' => $salesModel->vatTotal,
            'otherVatTotal' => $salesModel->otherVatTotal,
            'orderFee' => $salesModel->orderFee,
            'grandTotal' => $salesModel->grandTotal,
            'voucherTotal' => $salesModel->voucherTotal,
            'roundingTotal' => $salesModel->roundingTotal,
            'paymentTotal' => $salesModel->paymentTotal,
            'billingPrintCount' => $salesModel->billingPrintCount,
            'paymentPrintCount' => $salesModel->paymentPrintCount,
            'additionalInfo' => $salesModel->additionalInfo,
            'promotionID' => $salesModel->promotionID,
            'statusID' => $salesModel->statusID,
            'createdBy' => $salesModel->createdBy,
            'editedBy' => $salesModel->editedBy,
            'editedDate' => $salesModel->editedDate,
            'syncDate' => $salesModel->syncDate
        ];
    }

    private function defineMissingFieldEsoFs($flagInclusive) {
        foreach ($this->salesMenu as &$salesMenu) {
            $salesMenu['originalPrice'] = $flagInclusive == 1 ? $salesMenu['originalInclusivePrice'] : $salesMenu['originalPrice'];
            $salesMenu['discount'] = 0;
            $salesMenu['total'] = 0;

            if (isset($salesMenu['packages']) && count($salesMenu['packages']) > 0) {
                foreach ($salesMenu['packages'] as &$package) {
                    $package['discount'] = 0;
                    $package['total'] = 0;
                }
            }

            if (isset($salesMenu['extras']) && count($salesMenu['extras']) > 0) {
                foreach ($salesMenu['extras'] as &$extra) {
                    $extra['discount'] = 0;
                }
            }
        }
    }

    private function saveLogSendApiMenuEsoFs($arrErrorResponse) {
        if (count($arrErrorResponse) > 0) {
            foreach ($arrErrorResponse as $error) {
                Logging::save($this->salesModel->salesNum, Logging::API_SAVE_MENU_ESO_FS, $error);
            }
        }
    }

    private function loadSalesMenus($salesModel, $salesMenus)
    {
        $findOutstanding = function ($salesNum = null, $tableID = null) {
            $model = new OutstandingOrder();
            $model->salesNum = $salesNum;
            $model->tableID = $tableID;
            $model->saveNewOrderEsoFs = false;
            return $model->get();
        };

        $salesModel = $findOutstanding($salesModel->salesNum);
        $newSalesMenus = [];
        foreach ($salesModel['salesMenu'] as $salesMenu) {
            $newSalesMenu = [];
            foreach ($salesMenu as $key => $value) {
                $newSalesMenu[$key] = $value;
            }
            $newSalesMenu['menuFlagTax'] = $salesMenu['flagTax'];

            $convertDataChild = function ($salesModel, $type) use ($salesMenu) {
                $salesArray = [];
                if ($salesModel) {
                    foreach ($salesModel as $salesChild) {
                        foreach ($salesChild as $key => $value) {
                            $newSalesChild[$key] = $value;
                        }
                        if ($type == 'package') {
                            $newSalesChild['menuFlagTax'] = $salesMenu['flagSeparateTaxCalculation'] === 0 ? $salesMenu['flagTax'] : $salesChild['flagTax'];
                        } else {
                            $newSalesChild['menuFlagTax'] = $salesMenu['flagTax'];
                        }
                        $salesArray[] = $newSalesChild;
                    }
                }
                return $salesArray;
            };

            $newSalesMenu['packages'] = $convertDataChild($salesMenu['packages'], 'package');
            $newSalesMenu['extras'] = $convertDataChild($salesMenu['extras'], 'extra');
            $newSalesMenus[] = $newSalesMenu;
        }

        if ($newSalesMenus) {
            foreach ($newSalesMenus as $newSalesMenu) {
                $salesMenus[] = $newSalesMenu;
            }
        }

        return $salesMenus;
    }
}
