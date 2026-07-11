<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\Setting;
use app\services\http_helper\HttpHelperService;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\httpclient\Client;

class RemoveMemberFs extends Model {

    public $salesNum;
    public $webSocketID;
    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum'], 'required'],
            [['webSocketID'], 'safe']
        ];
    }

    public function removeMember() {
        if (!$this->validate()) {
            return false;
        }
        $salesHeadModel = SalesHead::findOutstandingOrder()
            ->andWhere([SalesHead::tableName() . '.salesNum' => $this->salesNum])
            ->one();
    
        $salesMenuModel = SalesMenu::find()
            ->joinWith('salesHead')
            ->joinWith('promotion')
            ->where([SalesMenu::tableName() . '.salesNum' => $this->salesNum])
            ->andWhere([PromotionHead::tableName() . '.flagLoyalty' => 1])
            ->all();

        if ($salesHeadModel) {
            $orderTimeOut = $salesHeadModel->orderTimeOut ? SalesHead::getOrderTimeOut(
                date_create($salesHeadModel->salesDateIn),
                date_create($salesHeadModel->orderTimeOut)
            ) : null; 

            $salesUpdateModel = [
                'salesNum' => $salesHeadModel->salesNum,
                'billNum' => $salesHeadModel->billNum,
                'bookNum' => $salesHeadModel->bookNum,
                'queueNum' => $salesHeadModel->queueNum,
                'salesDate' => $salesHeadModel->salesDate,
                'salesDateIn' => $salesHeadModel->salesDateIn,
                'orderTimeOut' => $orderTimeOut,
                'salesDateOut' => $salesHeadModel->salesDateOut,
                'branchID' => $salesHeadModel->branchID,
                'memberID' => $salesHeadModel->memberID,
                'employeeCode' => $salesHeadModel->employeeCode,
                'employeeName' => $salesHeadModel->employeeName,
                'employeeType' => $salesHeadModel->employeeType,
                'flagRemoveMemberPromoFS' => true,
                'memberCode' => $salesHeadModel->memberCode,
                'tableID' => $salesHeadModel->tableID,
                'visitPurposeID' => $salesHeadModel->visitPurposeID,
                'visitorTypeID' => $salesHeadModel->visitorTypeID,
                'paxTotal' => $salesHeadModel->paxTotal,
                'subtotal' => $salesHeadModel->subtotal,
                'discountTotal' => $salesHeadModel->discountTotal,
                'menuDiscountTotal' => $salesHeadModel->menuDiscountTotal,
                'promotionDiscount' => $salesHeadModel->promotionDiscount,
                'voucherDiscountTotal' => $salesHeadModel->voucherDiscountTotal,
                'otherTaxTotal' => $salesHeadModel->otherTaxTotal,
                'vatTotal' => $salesHeadModel->vatTotal,
                'otherVatTotal' => $salesHeadModel->otherVatTotal,
                'deliveryCost' => $salesHeadModel->deliveryCost,
                'orderFee' => $salesHeadModel->orderFee,
                'grandTotal' => $salesHeadModel->grandTotal,
                'voucherTotal' => $salesHeadModel->voucherTotal,
                'roundingTotal' => $salesHeadModel->roundingTotal,
                'paymentTotal' => $salesHeadModel->paymentTotal,
                'billingPrintCount' => $salesHeadModel->billingPrintCount,
                'paymentPrintCount' => $salesHeadModel->paymentPrintCount,
                'additionalInfo' => $salesHeadModel->additionalInfo,
                'remarks' => $salesHeadModel->remarks,
                'promotionID' => 0,
                'promotionVoucherCode' => NULL,
                'flagInclusive' => $salesHeadModel->flagInclusive,
                'lockTable' => $salesHeadModel->lockTable,
                'transactionModeID' => $salesHeadModel->transactionModeID,
                'deliveryTime' => $salesHeadModel->deliveryTime,
                'externalMembershipTypeID' => $salesHeadModel->externalMembershipTypeID,
                'flagExternalAPI' => NULL,
                'flagExternalMemberID' => NULL,
                'flagExternalMemberPhone' => NULL,
                'flagExternalCardID' => NULL,
                'externalMemberName' => NULL,
                'externalTransID' => $salesHeadModel->externalTransID,
                'externalCancelTransID' => $salesHeadModel->externalCancelTransID,
                'terminalID' => $salesHeadModel->terminalID,
                'printEsoFsQr' => $salesHeadModel->printEsoFsQr,
                'statusID' => $salesHeadModel->statusID,
                'createdBy' => $salesHeadModel->createdBy,
                'editedBy' => $salesHeadModel->editedBy,
                'editedDate' => $salesHeadModel->editedDate,
                'syncDate' => $salesHeadModel->syncDate,
                'salesMenu' => $salesMenuModel ? $salesMenuModel : NULL
            ];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($salesUpdateModel) {
                $updateModel = new UpdateOrder([
                    'attributes' => $salesUpdateModel
                ]);

                if (isset($updateModel->salesMenu)) {
                    foreach($updateModel->salesMenu as $salesMenu) {
                        if (isset($salesMenu['promotionDetailID'])) $salesMenu['promotionDetailID'] = 0;
                        if (isset($salesMenu['promotionVoucherCode'])) $salesMenu['promotionVoucherCode'] = '';
                        if (isset($salesMenu['promotionDetailName'])) $salesMenu['promotionDetailName'] = '';
                        if (isset($salesMenu['discount'])) $salesMenu['discount'] = 0;
                    }
                }

                if (!$updateModel->save()) {
                    throw new Exception('Failed to remove member');
                }

                $selfOrderApi = Setting::getEsoFsApiUrl();
                $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
                $companyCode = $branch->companyCode;
                $authKey = Setting::getApiKey();

                // @refactor http_helper
                $httpService = new HttpHelperService();
                $url = $selfOrderApi . 'save-remove-member';
                $headers = [
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-company' => AppHelper::getCompanyCode(),
                    'data-branch' => AppHelper::getBranchCode(),
                    'data-webSocketId' => $this->webSocketID
                ];
                $datas = [
                    'salesNum' => $updateModel->salesNum,
                    'flagExternalAPI' => $updateModel->flagExternalAPI,
                    'flagExternalMemberID' => $updateModel->flagExternalMemberID,
                    'flagExternalMemberPhone' => $updateModel->flagExternalMemberPhone,
                    'flagExternalCardID' => $updateModel->flagExternalCardID,
                    'externalMemberName' => $updateModel->externalMemberName
                ];
                $options = ['timeOut' => 300];
                $result = $httpService->post($url, $headers, $datas, $options);

                if ($result->getIsOk()) {
                    Logging::save($salesHeadModel->salesNum, Logging::REMOVE_MEMBER_EZO, $this->getAttributes());
                    $transaction->commit();

                    $ezoSettings = Setting::getEZOSetting();
                    if ($ezoSettings['Activate EZO'] == 1) {
                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            $syncSelfOrderModel = new SyncSelfOrder();
                            $syncSelfOrderModel->refNum = $this->salesNum;
                            $syncSelfOrderModel->type = 'salesNum';
                            $syncSelfOrderModel->addQueue();
                        }
                    }

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
