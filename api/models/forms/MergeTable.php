<?php
namespace app\models\forms;

use app\models\Branch;
use app\models\PromotionDetail;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPayment;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\web\HttpException;

/**
 * @property int $tableID
 * @property array $salesMerge
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 */
class MergeTable extends Model {
    public $tableID;
    public $salesMerge;
    public $salesModel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID'], 'required'],
            [['tableID'], 'integer'],
            [['salesMerge'], 'safe'],
            [['tableID'], 'validateTable']
        ];
    }

    public function validateTable($attribute) {
        $this->salesModel = SalesHead::findOutstanding()
            ->joinWith('salesMergeTables')
            ->andWhere(['OR',
                [SalesHead::tableName() . '.tableID' => $this->tableID],
                [SalesMergeTable::tableName() . '.tableID' => $this->tableID]
            ])
            ->one();
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $tableIDs = [];
        $additionalPax = 0;
        $promotionID = $this->salesModel->promotionID;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            for ($i = 1; $i < count($this->salesMerge); $i++) {
                $salesMerge = $this->salesMerge[$i];

                $salesModel = SalesHead::findOutstanding()
                    ->andWhere(['tableID' => $salesMerge['tableID']])
                    ->one();
                if ($salesModel) {
                    if ($salesModel->promotionID > 0) {
                        $currentOrder = $this->findOutstandingOrder($salesModel->salesNum);
                        $sourceOrderApplyPromo = $this->applyPromoHead($currentOrder, 0, false);
                        if (!$this->updateCurrentOrder($sourceOrderApplyPromo)) {
                            throw new Exception("Failed to update sales head");
                        }
                    }
                    // Move menu to new table
                    SalesMenu::updateAll([
                        'salesNum' => $this->salesModel->salesNum,
                        'batchID' => SalesMenu::getNewBatchID($this->salesModel->salesNum)
                        ], ['salesNum' => $salesModel->salesNum]);

                    SalesMenuExtra::updateAll(['salesNum' => $this->salesModel->salesNum],
                        ['salesNum' => $salesModel->salesNum]);

                    SalesMenuCompletion::updateAll(['salesNum' => $this->salesModel->salesNum],
                        ['salesNum' => $salesModel->salesNum]);

                    // Close occupied table
                    $additionalPax += $salesModel->paxTotal;
                    $salesModel->salesDateOut = new Expression('NOW()');
                    $salesModel->additionalInfo = 'Merged';
                    $salesModel->statusID = 12;
                    $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE; 
                    if (!$salesModel->save()) {
                        
                        throw new Exception('Failed to close merged table');
                    }
                    
                    // Sync self order
                    $ezoSettings = Setting::getEZOSetting();
                    if ($ezoSettings['Activate EZO'] == 1) {
                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            $syncSelfOrderModel = new SyncSelfOrder();
                            $syncSelfOrderModel->refNum = $salesModel->salesNum;
                            $syncSelfOrderModel->type = 'salesNum';
                            $syncSelfOrderModel->addQueue();
                        }
                    }
                }

                $salesMergeModel = SalesMergeTable::find()
                    ->andWhere(['salesNum' => $this->salesModel->salesNum])
                    ->andWhere(['tableID' => $salesMerge['tableID']])
                    ->one();
                if (!$salesMergeModel) {
                    $salesMergeModel = new SalesMergeTable();
                    $salesMergeModel->salesNum = $this->salesModel->salesNum;
                    $salesMergeModel->tableID = $salesMerge['tableID'];
                    if (!$salesMergeModel->save()) {
                      
                        throw new Exception('Failed to save merged table');
                    }
                }
                $tableIDs[] = $salesMerge['tableID'];
            }
            $this->salesModel->paxTotal += $additionalPax;
            $this->salesModel->billingPrintCount = 0;
            if ($promotionID > 0) {
                $currentOrder = $this->findOutstandingOrder($this->salesModel->salesNum);
                $sourceOrderApplyPromo = $this->applyPromoHead($currentOrder, 0);
                if (!$this->updateCurrentOrder($sourceOrderApplyPromo)) {
                    throw new Exception("Failed to update sales head");
                } else {
                    $currentOrder = $this->findOutstandingOrder($this->salesModel->salesNum);
                    $sourceOrderApplyPromo = $this->applyPromoHead($currentOrder, $promotionID);
                    if (!$this->updateCurrentOrder($sourceOrderApplyPromo)) {
                        throw new Exception("Fauled to update sales head");
                    }
                }
            } else {
                if (!$this->salesModel->save()) {
                   
                    throw new Exception('Failed to update sales head');
                }
            }

            SalesMergeTable::deleteAll(['AND',
                ['NOT IN', 'tableID', $tableIDs],
                ['salesNum' => $this->salesModel->salesNum]
            ]);

            Logging::save($this->salesModel->salesNum, Logging::MERGE_TABLE,
                $this->getAttributes());

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesMerge', $ex->getMessage());
            return false;
        }
    }

    private function updateCurrentOrder($sourceOrderApplyPromo)
    {
        if ($sourceOrderApplyPromo !== null) {
            $updateModel = new UpdateOrder([
                'attributes' => $sourceOrderApplyPromo
            ]);
            if (!$updateModel->save()) {
                $errMsg = $updateModel->errMsg;
                if ($errMsg != '') {
                    $errCode = 400;
                } else {
                    $errMsg = json_encode($updateModel->getErrors());
                    $errCode = 500;
                }
                throw new Exception($errMsg, NULL, $errCode);
            }
        }

        return true;
    }

    private function applyPromoHead($currentOrder, $promotionID, $keepPromotionDetail = true) {
        $salesMenus = null;
        if ($currentOrder) {
            if (isset($currentOrder['salesMenu'])) {
                $salesMenus = $currentOrder['salesMenu'];
                for ($i=0; $i < count($salesMenus) ; $i++) { 
                    $salesMenus[$i] = $this->defineSalesMenu($salesMenus[$i], $keepPromotionDetail);
                }
                $currentOrder['salesMenu'] = $salesMenus;
            }
            $data = [
                'order' => $currentOrder,
                'promotionID' => $promotionID,
                'salesNum' => $currentOrder['salesNum'],
                'tableID' => $currentOrder['tableID']
            ];
            $applyPromoModel = new ApplyOrderPromo([
                'attributes' => $data
            ]);
            $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_HEAD;
            if (!$applyPromoModel->save()) {
                if (isset($applyPromoModel->errorMessage)) {
                    throw new HttpException(404, $applyPromoModel->errorMessage);
                }
                throw new Exception(json_encode($applyPromoModel->errors));
            }
            return $applyPromoModel['order'];
        }
        return null;
    }

    private function findOutstandingOrder($salesNum = null, $tableID = null)
    {
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);

        if (is_null($tableID)) {
            $salesModel = SalesHead::findOutstandingOrder()
                ->andWhere([
                    'OR',
                    [SalesHead::tableName() . '.salesNum' => $salesNum],
                    [SalesMergeTable::tableName() . '.salesNum' => $salesNum]
                ]);
        } else {
            $salesModel = SalesHead::findOutstandingOrder()
                ->andWhere([
                    'OR',
                    [SalesHead::tableName() . '.tableID' => $tableID],
                    [SalesMergeTable::tableName() . '.tableID' => $tableID]
                ]);
        }


        $salesModel = $salesModel->with('member')
            ->with('promotion')
            ->with('mainSalesMenus.menu')
            ->with('mainSalesMenus.status')
            ->with('mainSalesMenus.promotion')
            ->with('mainSalesMenus.childSalesMenus.menu')
            ->with('mainSalesMenus.childSalesMenus.status')
            ->with('mainSalesMenus.childSalesMenus.promotion')
            ->with('mainSalesMenus.salesExtras')
            ->one();

        if (!$salesModel) {
            throw new HttpException(404, Yii::t('app', 'Order not found'));
        }

        $taxInclusiveAfterDiscount = false;
        if ($salesModel->flagInclusive) {
            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                $taxInclusiveAfterDiscount = true;
            }
        }

        $salesModel->subtotal = $salesModel->subtotal;
        $salesModel->grandTotal = $salesModel->grandTotal;
        $interval = $salesModel->orderTimeOut ? SalesHead::getOrderTimeOut(
            date_create($salesModel->salesDateIn),
            date_create($salesModel->orderTimeOut)
        ) : null;
        $salesModel->orderTimeOut = $interval;

        if ($taxInclusiveAfterDiscount) {
            foreach ($salesModel->mainSalesMenus as $salesMenu) {
                $salesMenu->discountValue = $salesMenu->inclusiveDiscountValue;
                if ($salesMenu->childSalesMenus) {
                    foreach ($salesMenu->childSalesMenus as $perPackage) {
                        $perPackage->discountValue = $perPackage->inclusiveDiscountValue;
                    }
                }
                if ($salesMenu->salesExtras) {
                    foreach ($salesMenu->salesExtras as $perExtra) {
                        $perExtra->discountValue = $perExtra->inclusiveDiscountValue;
                    }
                }
            }
        }

        //checking for Package menu subs Promo
        $promoOnPackage = [];
        foreach ($salesModel->mainSalesMenus as $salesMenu) {
            if ($salesMenu->childSalesMenus) {
                $promotionModel = PromotionHead::find()
                    ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                    ->one();
                if ($promotionModel && $promotionModel->promotionTypeID == 7) {
                    $menuIDs = [];
                    foreach ($salesMenu->childSalesMenus as $perPackage) {
                        $menuIDs[] = $perPackage['menuID'];
                    }
                    $promotionDetailModel = PromotionDetail::find()
                        ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                        ->andWhere(['in', 'menuSubsID', $menuIDs])
                        ->one();
                    if ($promotionDetailModel) {
                        $promoOnPackage[] = [
                            'menuID' => $salesMenu['menuID'],
                            'value' => true
                        ];
                    }
                }
            }
        }

        $extraFields = [
            'memberName' => $salesModel->memberID != 0 ? $salesModel->member->memberName : '',
            'promotionName' => $salesModel->promotionID != 0 ? $salesModel->promotion->notes : '',
            'salesMenu' => $salesModel->mainSalesMenus,
            'visitPurposeName' => $salesModel->visitPurpose ? $salesModel->visitPurpose->visitPurposeName : '',
            'promoOnPackage' => $promoOnPackage
        ];

        return array_merge(
            $salesModel->toArray(),
            $extraFields
        );
    }

    private function defineSalesMenu($salesMenu, $keepPromotionDetail) {
        $packages = [];
        $extras = [];
        if (isset($salesMenu->childSalesMenus)) {
            for ($i=0; $i < count($salesMenu->childSalesMenus); $i++) { 
                $packages[$i] = $this->defineSalesMenu($salesMenu->childSalesMenus[$i], $keepPromotionDetail);
            }
        }

        if (isset($salesMenu->salesExtra)) {
            foreach ($salesMenu->salesExtras as $key => $extra) {
                $extras[$key] = $extra;
                $extras[$key]['promotionDetailID'] = isset($extra['promotionDetailID']) ? $extra['promotionDetailID'] : 0;
            }
        }

        $salesMenus = [];
        $salesMenus['ID'] = $salesMenu->ID;
        $salesMenus['localID'] = $salesMenu->localID;
        $salesMenus['salesNum'] = $salesMenu->salesNum;
        $salesMenus['menuRefID'] = $salesMenu->menuRefID;
        $salesMenus['menuGroupID'] = $salesMenu->menuGroupID;
        $salesMenus['menuID'] = $salesMenu->menuID;
        $salesMenus['menuCategoryID'] = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
        $salesMenus['menuCategoryDetailID'] = $salesMenu->menu->menuCategoryDetailID;
        $salesMenus['customMenuName'] = $salesMenu->customMenuName;
        $salesMenus['qty'] = $salesMenu->qty;
        $salesMenus['originalPrice'] = $salesMenu->originalPrice;
        $salesMenus['price'] = $salesMenu->price;
        $salesMenus['inclusivePrice'] = $salesMenu->inclusivePrice;
        $salesMenus['otherTax'] = $salesMenu->otherTax;
        $salesMenus['vat'] = $salesMenu->vat;
        $salesMenus['discount'] = $keepPromotionDetail ? $salesMenu->discount : 0;
        $salesMenus['discountTotal'] = 0;
        if ($keepPromotionDetail) {
            $salesMenus['total'] = $salesMenu->total;
        }
        $salesMenus['otherTaxOnVat'] = $salesMenu->otherTaxOnVat;
        $salesMenus['statusID'] = $salesMenu->statusID;
        $salesMenus['promotionDetailID'] = $keepPromotionDetail ? $salesMenu->promotionDetailID : 0;
        $salesMenus['menuPromotionID'] = $keepPromotionDetail ? $salesMenu->menuPromotionID : 0;
        $salesMenus['promotionVoucherCode'] = $keepPromotionDetail ? $salesMenu->promotionVoucherCode : '';
        $salesMenus['notes'] = $salesMenu->notes;
        $salesMenus['salesType'] = $salesMenu->salesType;
        $salesMenus['menuCode'] = $salesMenu->menu->menuCode;
        $salesMenus['packages'] = $packages;
        $salesMenus['extras'] = $extras;

        return $salesMenus;
    }

}
