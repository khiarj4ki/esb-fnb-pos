<?php

namespace app\models\forms;

use app\models\BranchMenuTransaction;
use app\models\MapBranchVisitPurpose;
use app\models\MenuTemplateDetail;
use app\models\MenuTemplateHead;
use app\models\SalesConditionalPromo;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMenuRecommendation;
use app\models\SalesMenuRelated;
use app\models\SalesMenuVat;
use app\models\SalesMergeTable;
use app\models\SalesPlatformFee;
use app\models\SalesRewardMenu;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $sourceTableID
 * @property string $sourceSalesNum
 * @property array $salesMove
 * @property int $batchID
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 * @property SalesHead $sourceSalesModel
 */
class MoveItem extends Model
{
    public $tableID;
    public $salesNum;
    public $sourceTableID;
    public $sourceSalesNum;
    public $salesMove;
    public $batchID;
    public $salesModel;
    public $sourceSalesModel;
    public $appliedPromoSalesMenu;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tableID', 'sourceTableID', 'salesMove'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                return $model->tableID == 0;
            }],
            [['tableID', 'sourceTableID'], 'integer'],
            [['sourceSalesNum'], 'required', 'when' => function ($model) {
                return $model->sourceTableID == 0;
            }],
            [['salesNum'], 'string', 'max' => 20],
            [['tableID'], 'validateTable'],
            [['sourceTableID'], 'validateSourceTable'],
            [['appliedPromoSalesMenu'], 'safe']
        ];
    }

    public function validateTable($attribute)
    {
        $this->salesModel = SalesHead::findOutstanding()
            ->joinWith('salesMergeTables')
            ->andWhere([SalesHead::tableName() . '.salesNum' => $this->salesNum])
            ->one();
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }
    }

    public function validateSourceTable($attribute)
    {
        if ($this->sourceTableID != 0) {
            $this->sourceSalesModel = SalesHead::findOutstanding()
                ->joinWith('salesMergeTables')
                ->andWhere([
                    'OR',
                    [SalesHead::tableName() . '.tableID' => $this->sourceTableID],
                    [SalesMergeTable::tableName() . '.tableID' => $this->sourceTableID]
                ])
                ->one();
        } else {
            $this->sourceSalesModel = SalesHead::findOutstanding()
                ->joinWith('salesMergeTables')
                ->andWhere([SalesHead::tableName() . '.salesNum' => $this->sourceSalesNum])
                ->one();
        }
        if (!$this->sourceSalesModel) {
            $this->addError($attribute, 'Invalid table ID');
        }
    }

    public function save()
    {
        if (!$this->validate()) {
            return false;
        }

        $menuIDs = [];
        $isVoucherMemberID = false;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->salesMove as $salesMove) {
                $salesMenuModel = SalesMenu::findActive()
                    ->andWhere(['ID' => $salesMove['ID']])
                    ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                    ->one();
                if (!$salesMenuModel) {
                    throw new Exception('Menu not found');
                }
                if ($salesMenuModel->qty < $salesMove['qty']) {
                    throw new Exception('Menu qty is less than the moved qty');
                }

                if ($salesMenuModel->qty == $salesMove['qty']) {

                    if ($salesMenuModel->promotionVoucherCode != '' || $salesMenuModel->promotionVoucherCode != null) {
                        $isVoucherMemberID = true;
                    }

                    if (($this->sourceSalesModel->promotionVoucherCode != '' || $this->sourceSalesModel->promotionVoucherCode != null) &&
                        ($this->sourceSalesModel->promotionID == $salesMenuModel->promotionDetailID)
                    ) {
                        $isVoucherMemberID = true;
                    }

                    if ($isVoucherMemberID) {
                        $salesMenuModel->promotionDetailID = 0;
                        $salesMenuModel->promotionVoucherCode = '';
                        $salesMenuModel->price = $salesMenuModel->originalPrice;
                    }
                    
                    $salesMenuModel->salesNum = $this->salesModel->salesNum;
                    if (!$salesMenuModel->save()) {
                    
                        throw new Exception('Failed to update menu');
                    }
                    $menuIDs[] = $salesMenuModel->ID;

                    SalesMenuVat::updateSalesNum($this->sourceSalesModel->salesNum, $this->salesModel->salesNum, $salesMenuModel->ID);

                    if ($salesMove['packages']) {
                        foreach ($salesMove['packages'] as $package) {
                            $salesPackageModel = SalesMenu::findActive()
                                ->andWhere(['ID' => $package['ID']])
                                ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                                ->one();
                            $salesPackageModel->salesNum = $this->salesModel->salesNum;
                            if (!$salesPackageModel->save()) {
                                
                                throw new Exception('Failed to update package');
                            }
                            $menuIDs[] = $salesPackageModel->ID;

                            SalesMenuVat::updateSalesNum($this->sourceSalesModel->salesNum, $this->salesModel->salesNum, $salesPackageModel->ID);
                        }
                    }

                    if ($salesMove['extras']) {
                        foreach ($salesMove['extras'] as $extra) {
                            $salesExtraModel = SalesMenuExtra::findActive()
                                ->andWhere(['ID' => $extra['ID']])
                                ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                                ->one();
                            $salesExtraModel->salesNum = $this->salesModel->salesNum;
                            if (!$salesExtraModel->save()) {
                               
                                throw new Exception('Failed to update extra');
                            }

                            SalesMenuVat::updateSalesNum($this->sourceSalesModel->salesNum, $this->salesModel->salesNum, $extra['ID']);
                        }
                    }

                    //@notes: Validate Menu RTS Move Item for target salesNum (Add)
                    $this->validateStockMenuReadyToSale($salesMenuModel, $salesMove,  $this->salesModel,$salesMenuModel->qty, 'Add');
           
                    //update salesNum SalesMenuCompletion
                    $salesMenuCompletionModel = SalesMenuCompletion::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($salesMenuCompletionModel) {
                        foreach ($salesMenuCompletionModel as $model) {
                            $model->salesNum = $this->salesModel->salesNum;
                            if (!$model->save()) {
                               
                                throw new Exception('Failed to update sales menu completion');
                            }
                        }
                    }

                    //update salesNum SalesMenuRelated
                    $SalesMenuRelatedModel = SalesMenuRelated::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($SalesMenuRelatedModel) {
                        foreach ($SalesMenuRelatedModel as $model) {
                            $model->salesNum = $this->salesModel->salesNum;
                            if (!$model->save()) {
                              
                                throw new Exception('Failed to update sales menu related');
                            }
                        }
                    }

                    //update salesNum SalesMenuRecommendation
                    $salesMenuRecommendationModel = SalesMenuRecommendation::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($salesMenuRecommendationModel) {
                        foreach ($salesMenuRecommendationModel as $model) {
                            $model->salesNum = $this->salesModel->salesNum;
                            if (!$model->save()) {
                            
                                throw new Exception('Failed to update sales menu recommedation');
                            }
                        }
                    }

                } else {

                    $salesMenuModel->qty = $salesMenuModel->qty - $salesMove['qty'];
                    $salesMenuModel->calculateTotal();
                    if (!$salesMenuModel->save()) {
                     
                        throw new Exception('Failed to update menu');
                    }

                    $newSalesMenuModel = new SalesMenu([
                        'attributes' => $salesMove
                    ]);

                    if ($newSalesMenuModel->promotionVoucherCode != '' || $newSalesMenuModel->promotionVoucherCode != null) {
                        $isVoucherMemberID = true;
                    }

                    if (($this->sourceSalesModel->promotionVoucherCode != '' || $this->sourceSalesModel->promotionVoucherCode != null) &&
                        ($this->sourceSalesModel->promotionID == $newSalesMenuModel->promotionDetailID)
                    ) {
                        $isVoucherMemberID = true;
                    }

                    if ($isVoucherMemberID) {
                        $newSalesMenuModel->promotionDetailID = 0;
                        $newSalesMenuModel->promotionVoucherCode = '';
                    }
                    
                    $newSalesMenuModel->salesNum = $this->salesModel->salesNum;
                    if (SalesHead::getInclusiveFlag(
                        $this->salesModel->branchID,
                        $this->salesModel->visitPurposeID
                    ) == MenuTemplateHead::INCLUSIVE_YES) {
                        $mapBranchModel = MapBranchVisitPurpose::find()
                            ->andWhere(['branchID' => $this->salesModel->branchID, 'visitPurposeID' => $this->salesModel->visitPurposeID])
                            ->one();
                        $menuTemplateDetailModel = MenuTemplateDetail::find()
                            ->with('activeSpecialPriceMenu')
                            ->andWhere(['menuTemplateID' => $mapBranchModel->menuTemplateID, 'menuID' => $newSalesMenuModel->menuID])
                            ->one();
                        if (!$menuTemplateDetailModel) {
                            throw new Exception('Cannot move menu, because menu is not registered on destination sales mode');
                        }
                    }

                    $newSalesMenuModel->calculateTotal();
                    if (!$newSalesMenuModel->save()) {
                    
                        throw new Exception('Failed to save menu');
                    }
                    $menuIDs[] = $newSalesMenuModel->ID;

                    if ($salesMove['packages']) {
                        $newSalesMenuModel->menuRefID = $newSalesMenuModel->ID;
                        if (!$newSalesMenuModel->save()) {
                           
                            throw new Exception('Failed to save menu');
                        }
                        foreach ($salesMove['packages'] as $package) {
                            $salesPackageModel = new SalesMenu([
                                'attributes' => $package
                            ]);
                            $salesPackageModel->salesNum = $this->salesModel->salesNum;
                            $salesPackageModel->menuRefID = $newSalesMenuModel->ID;
                            if (!$salesPackageModel->save()) {
                              
                                throw new Exception('Failed to save package');
                            }
                            $menuIDs[] = $salesPackageModel->ID;
                        }
                    }

                    if ($salesMove['extras']) {
                        foreach ($salesMove['extras'] as $extra) {
                            $salesExtraModel = new SalesMenuExtra([
                                'attributes' => $extra
                            ]);
                            $salesExtraModel->salesNum = $this->salesModel->salesNum;
                            $salesExtraModel->menuDetailID = $newSalesMenuModel->ID;
                            if (!$salesExtraModel->save()) {
                               
                                throw new Exception('Failed to save extra');
                            }
                        }
                    }

                    //@notes: Validate Menu RTS Move Item for target salesNum (Add)
                    $this->validateStockMenuReadyToSale($newSalesMenuModel,$salesMove, $this->salesModel,$newSalesMenuModel->qty, 'Add');
              
                    //@notes: Validate Menu RTS Move Item for source salesNum (Move)
                    $this->validateStockMenuReadyToSale($salesMenuModel, $salesMove,$this->salesModel,$salesMenuModel->qty, 'Move');

                    //update qty salesnum lama dan insert dengan salesNum baru dan qty baru
                    $salesMenuCompletionModel = SalesMenuCompletion::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($salesMenuCompletionModel) {
                        foreach ($salesMenuCompletionModel as $model) {
                            $model->qty = $salesMenuModel->qty;
                            if (!$model->save()) {
                                
                                throw new Exception('Failed to update sales menu completion');
                            }

                            $newSalesCompletionModel = new SalesMenuCompletion();
                            $newSalesCompletionModel->localID = $newSalesMenuModel->localID;
                            $newSalesCompletionModel->salesNum = $newSalesMenuModel->salesNum;
                            $newSalesCompletionModel->salesMenuID = $newSalesMenuModel->ID;
                            $newSalesCompletionModel->qty = $newSalesMenuModel->qty;
                            $newSalesCompletionModel->completedDate = date('Y-m-d H:i:s');
                            $newSalesCompletionModel->typeID = $model->typeID;
                            $newSalesCompletionModel->startDate = $newSalesMenuModel->createdDate;
                            if (!$newSalesCompletionModel->save()) {
                           
                                throw new Exception('Failed to save sales completion');
                            }
                        }
                    }

                    // update sales menu related
                    $salesMenuRelatedModel = SalesMenuRelated::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($salesMenuRelatedModel) {
                        foreach ($salesMenuRelatedModel as $model) {
                            if (!$model->save()) {
                                
                                throw new Exception('Failed to update sales menu related');
                            }

                            $newSalesMenuRelatedModel = new SalesMenuRelated();
                            $newSalesMenuRelatedModel->mainMenuID = $model->mainMenuID;
                            $newSalesMenuRelatedModel->salesNum = $newSalesMenuModel->salesNum;
                            $newSalesMenuRelatedModel->salesMenuID = $newSalesMenuModel->ID;
                            $newSalesMenuRelatedModel->relatedMenuID = $newSalesMenuModel->menuID;
                           
                            if (!$newSalesMenuRelatedModel->save()) {
                             
                                throw new Exception('Failed to save sales menu related');
                            }
                        }
                    }

                    // update sales menu recommendation
                    $salesMenuRecommendationModel = SalesMenuRecommendation::find()
                        ->andWhere(['salesMenuID' => $salesMove['ID']])
                        ->andWhere(['salesNum' => $this->sourceSalesModel->salesNum])
                        ->all();

                    if ($salesMenuRecommendationModel) {
                        foreach ($salesMenuRecommendationModel as $model) {
                            if (!$model->save()) {
                                
                                throw new Exception('Failed to update sales menu recommendation');
                            }

                            $newSalesMenuRecommendationModel = new SalesMenuRecommendation();
                            $newSalesMenuRecommendationModel->salesNum = $newSalesMenuModel->salesNum;
                            $newSalesMenuRecommendationModel->salesMenuID = $newSalesMenuModel->ID;
                           
                            if (!$newSalesMenuRecommendationModel->save()) {
                            
                                throw new Exception('Failed to save sales menu recommendation');
                            }
                        }
                    }
                }

                //delete sales reward menu (if any)
                if (!empty($salesMove['rewardType'])) {
                    SalesRewardMenu::deleteAll(['ID' => $salesMove['ID']]);
                }
            }

            if ($this->appliedPromoSalesMenu && count($this->appliedPromoSalesMenu) > 0) {
                foreach ($this->appliedPromoSalesMenu as $salesMenu) {
                    $salesMenuModel = SalesMenu::find()->where(['ID' => $salesMenu['ID']])->one();
                    if ($salesMenuModel) {
                        $salesMenuModel->promotionDetailID = 0;
                        if (!$salesMenuModel->save()) {
                            throw new Exception('Failed to save sales menu applied conditional promo');
                        }
                    }
                }
                SalesConditionalPromo::deleteAll(['salesNum' => $this->sourceSalesModel->salesNum]);
            }

            $this->batchID = SalesMenu::getNewBatchID($this->salesModel->salesNum);
            SalesMenu::updateAll(
                ['batchID' => $this->batchID],
                ['IN', 'ID', $menuIDs]
            );

            // Insert Platform Fee Data - Start
            $salesPlatformFees = SalesPlatformFee::find()
                ->where(['salesNum' => $this->sourceSalesModel->salesNum])
                ->asArray()
                ->all();

            if ($salesPlatformFees) {
                $salesPlatformFeeModel = new SalesPlatformFee();
                if (!$salesPlatformFeeModel->saveModel($this->salesModel->salesNum, $salesPlatformFees)) {
                    throw new Exception(json_encode($salesPlatformFeeModel->errMsg), 500);
                }
            }
            // Insert Platform Fee Data - End

            $this->sourceSalesModel->billingPrintCount = 0;
            if (!$this->sourceSalesModel->save()) {
                
                throw new Exception('Failed to update source total sales');
            }

            $this->salesModel->billingPrintCount = 0;
            if (!$this->salesModel->save()) {
                
                throw new Exception('Failed to update destination total sales');
            }

            Logging::save(
                $this->sourceSalesModel->salesNum,
                Logging::MOVE_ITEM,
                $this->getAttributes()
            );

            Logging::save(
                $this->salesModel->salesNum,
                Logging::MOVE_ITEM_DESTINATION,
                $this->getAttributes()
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesMove', $ex->getMessage());
            return false;
        }
    }

    public static function applyMenuPromo($salesModel)
    {
        $newSalesModel = $salesModel;
        $promoOrder = $salesModel;
        $promotionID = $salesModel['promotionID'];
        $salesNum = $salesModel['salesNum'];
        $salesType = 'POS';
        $salesUpdate = false;
        $tableID = $salesModel['tableID'];
        $attributesPromo = [
            'order' => $promoOrder,
            'promotionID' => $promotionID,
            'salesNum' => $salesNum,
            'salesType' => $salesType,
            'salesUpdate' => $salesUpdate,
            'tableID' => $tableID
        ];

        $applyPromoModel = new ApplyOrderPromo([
            'attributes' => $attributesPromo
        ]);
        $applyPromoModel->mode = ApplyOrderPromo::SCENARIO_APPLY_FROM_HEAD;
        if ($applyPromoModel->save()) {
            $newSalesModel = $applyPromoModel['order'];
        }

        return $newSalesModel;
    }

    protected function validateStockMenuReadyToSale($salesMenu, $salesMove, $salesHead, $qty, $category){

        $validateStockModel = new ValidateStock();
        $validateStockModel->salesNum = $salesHead->salesNum;
        $validateStockModel->menuID = $salesMenu->menuID;
        $validateStockModel->qty = $qty;
        $validateStockModel->transactionModeID = $salesHead->transactionModeID;
        $validateStockModel->isCancelOrder = in_array($salesMenu->statusID, [ 12, 19 ]);
        $validateStockModel->salesMenuID = $salesMenu->ID;
        $validateStockModel->category = $category;
        $validateStockModel->validateStock();

        if ($salesMove['packages']) {
            foreach ($salesMove['packages'] as $menuPackage) {
           
                $validateStockModel = new ValidateStock();
                $validateStockModel->salesNum =  $salesMenu->salesNum;
                $validateStockModel->menuID = $menuPackage['menuID'];
                $validateStockModel->qty = $menuPackage['qty'];
                $validateStockModel->transactionModeID = $salesHead->transactionModeID;;
                $validateStockModel->isCancelOrder = in_array($menuPackage['statusID'], [ 12, 19 ]);
                $validateStockModel->salesMenuID = $menuPackage['ID'];
                $validateStockModel->category = $category;

                $validateStockModel->validateStock();
            }
        }

    }
}
