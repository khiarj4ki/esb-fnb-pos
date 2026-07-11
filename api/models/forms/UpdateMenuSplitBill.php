<?php
namespace app\models\forms;

use app\models\Menu;
use app\models\SalesConditionalPromo;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMenuRecommendation;
use app\models\SalesMenuRelated;
use app\models\SalesMenuVat;
use app\models\SalesPlatformFee;
use app\models\SalesRewardMenu;
use app\models\SalesVisitor;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;

class UpdateMenuSplitBill extends Model {
    public $salesNumTarget;
    public $sourceSalesNum;
    public $salesMenuID;
    public $menuID;
    public $salesMenuModel;
    public $targetSalesMenuModel;
    public $menuModel;
    public $sourceMenuQty;
    public $sourceSalesHead;
    public $targetSalesHead;
    public $additionalInfo;
    public $qty;
    public $price;
    public $appliedPromoSalesMenu;


    public function rules() {
        return [
            [['salesNumTarget', 'salesMenuID', 'menuID', 'qty'], 'required'],
            [['salesNumTarget'], 'string', 'max' => 20],
            [['salesMenuID', 'menuID'], 'integer'],
            [['price'], 'safe'],
            [['salesMenuID'], 'validateSalesMenuID'],
            [['additionalInfo'], 'string', 'max' => 200],
            [['menuID'], 'validateMenuID'],
            [['salesMenuID'], 'validateSourceSalesMenuQty'],
            [['salesMenuID'], 'validateSourceSalesHead'],
            [['appliedPromoSalesMenu'], 'safe']
        ];
    }
    
    public function validateSalesMenuID($attribute) {
        $this->salesMenuModel = SalesMenu::find()
                ->where([
                    'ID' => $this->salesMenuID,
                    'menuID' => $this->menuID])
                ->one();
        if (!$this->salesMenuModel) {
            $this->addError($attribute, 'Invalid sales menu ID');
        }
    }
    
    public function validateMenuID($attribute) {
        $this->menuModel = Menu::find()
                ->where([
                    'menuID' => $this->menuID])
                ->one();
        if (!$this->menuModel) {
            $this->addError($attribute, 'Invalid menu');
        }
    }
    
    public function validateSourceSalesMenuQty($attribute) {
        $menuModel = $this->menuModel;
        $sourceSalesMenuModel = SalesMenu::find()
            ->joinWith('menu')
            ->where(['ID' => $this->salesMenuID])->one();
//        if ($menuModel->menuTypeID == 2) {
//            $this->sourceMenuQty = $sourceSalesMenuModel->qty;
//            if ($this->sourceMenuQty < 2) {
//                $this->addError($attribute, 'Sales menu source qty min 1');
//            }
//        }
    }
    
    public function validateSourceSalesHead($attribute) {
        $salesMenuModel = $this->salesMenuModel;
        $this->sourceSalesHead = SalesHead::find()->where(['salesNum' => $salesMenuModel->salesNum])->one();
        if (!$this->sourceSalesHead) {
            $this->addError($attribute, 'Invalid source sales head');
        }
    }
    
    public function save() {
        if (!$this->validate()) {
            return false;
        }
        
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $lockSalesMenuQuery = SalesMenu::find()
                ->where(['salesNum' => $this->salesMenuModel->salesNum])
                ->andWhere(['ID' => $this->salesMenuID])
                ->createCommand()
                ->getRawSql();

            SalesMenu::findBySql($lockSalesMenuQuery . ' FOR UPDATE')->one();

            if ($this->appliedPromoSalesMenu && count($this->appliedPromoSalesMenu) > 0) {
                foreach ($this->appliedPromoSalesMenu as $salesMenu) {
                    $salesMenuModel = SalesMenu::find()->where(['ID' => $salesMenu['ID']])->one();
                    if ($salesMenuModel) {
                        $salesMenuModel->promotionDetailID = 0;
                        $salesMenuModel->price = $salesMenu['originalPrice'];
                        if (!$salesMenuModel->save()) {
                            throw new Exception('Failed to save sales menu applied conditional promo');
                        }
                    }
                }

                SalesConditionalPromo::deleteAll(['salesNum' => $this->salesMenuModel->salesNum]);
            }

            $targetSalesHeadModel = SalesHead::find()->where(['salesNum' => $this->salesNumTarget])->one();
            $sourceSalesMenuModel = $this->salesMenuModel;            
            $sourceSalesHead = $this->sourceSalesHead;
            $this->sourceSalesNum = $sourceSalesHead->salesNum;
            $salesMenuModel = NULL;
            if ($sourceSalesHead->statusID == 8) {
                throw new Exception('This order have been paid');
            }
            // Insert sales menu child
            $salesMenuModel = new SalesMenu();
            $salesMenuModel->attributes = $sourceSalesMenuModel->attributes;
            $salesMenuModel->salesNum = $this->salesNumTarget;
            $salesMenuModel->qty = $this->qty;
            
            $limitUsageForBillAndMenuPromotion = Setting::getValue1('POS', 'Limit usage for bill and menu promotion');
            if ($limitUsageForBillAndMenuPromotion == 1 && $targetSalesHeadModel->promotionID > 0) {
                $salesMenuModel['promotionDetailID'] = 0;
                $salesMenuModel['promotionVoucherCode'] = '';
            }
            
            if ($this->salesMenuModel->promotionVoucherCode || $this->salesMenuModel->promotionVoucherCode !== '') {
                $salesMenuModel->promotionDetailID = 0;
                $salesMenuModel->promotionVoucherCode = '';
                $salesMenuModel->price = $this->price ? $this->price : 0;
            }
                        
            $salesMenuModel->calculateTotal();
            $this->targetSalesMenuModel = $salesMenuModel;
            if (!$salesMenuModel->save()) {
                
                throw new Exception('Failed to save sales menu');
            }
            
            $sourceSalesMenuModel->qty = $sourceSalesMenuModel->qty - $this->qty;
            if($sourceSalesMenuModel->qty <= 0){
                $sourceSalesMenuModel->delete();
                SalesMenuVat::deleteAll(['salesNum' => $this->salesMenuModel->salesNum, 'salesMenuID' => $this->salesMenuModel->ID]);
            } else {
                $sourceSalesMenuModel->calculateTotal();
                if (!$sourceSalesMenuModel->save()) {
                    
                    throw new Exception('Failed to update source sales menu');
                }
            }
            
            if ($salesMenuModel->menuRefID > 0) {
                $targetMenuPackage = SalesMenu::find()
                    ->where(['salesNum' => $this->salesNumTarget])
                    ->andWhere(['menuRefID' => $salesMenuModel->menuRefID])
                    ->andWhere(['=' ,'menuGroupID', 0])
                    ->one();
                $sourceMenuPackage = SalesMenu::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['menuRefID' => $salesMenuModel->menuRefID])
                    ->andWhere(['<>' ,'menuGroupID', 0])
                    ->all();
                $sourceMenuHeadPackage = SalesMenu::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['menuRefID' => $salesMenuModel->menuRefID])
                    ->andWhere(['=' ,'menuGroupID', 0])
                    ->one();
                foreach ($sourceMenuPackage as $dataPackage) {
                    $newSalesMenuPackageModel = new SalesMenu();
                    $newSalesMenuPackageModel->attributes = $dataPackage->attributes;
                    $newSalesMenuPackageModel->salesNum = $this->salesNumTarget;
                    $newSalesMenuPackageModel->menuRefID = $salesMenuModel->ID;
                    if (!$newSalesMenuPackageModel->save()) {
                        
                        throw new Exception('Failed to save sales menu');
                    }
                    $salesMenuPckCompletionModel = SalesMenuCompletion::find()
                        ->where(['salesNum' => $dataPackage->salesNum])
                        ->andWhere(['salesMenuID' => $dataPackage->ID])
                        ->all();
                    if ($salesMenuPckCompletionModel) {
                        foreach ($salesMenuPckCompletionModel as $salesMenuPckCompletion) {
                            $this->updateSalesMenuCompletion($salesMenuPckCompletion, $dataPackage, $newSalesMenuPackageModel, true);
                        }
                    }

                    if (!$sourceMenuHeadPackage) {
                        $dataPackage->delete();
                    }
                }
                
                $targetMenuPackage->menuRefID = $salesMenuModel->ID;
                
                if (!$targetMenuPackage->save()) {
                 
                    throw new Exception('Failed to save sales menu');
                }
                
                $salesMenuModel->calculateTotal();
                if (!$salesMenuModel->save()) {
                    
                    throw new Exception('Failed to save sales menu');
                }                
            }

            $sourceMenuExtra = SalesMenuExtra::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['menuDetailID' => $sourceSalesMenuModel->ID])
                    ->all();
            if ($sourceMenuExtra) {
                $sourceMenuHeadExtra = SalesMenu::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['ID' => $sourceSalesMenuModel->ID])
                    ->one();
                foreach ($sourceMenuExtra as $dataExtra) {
                    $newSalesMenuExtraModel = new SalesMenuExtra();
                    $newSalesMenuExtraModel->attributes = $dataExtra->attributes;
                    $newSalesMenuExtraModel->salesNum = $this->salesNumTarget;
                    $newSalesMenuExtraModel->menuDetailID = $salesMenuModel->ID;
                    if (!$newSalesMenuExtraModel->save()) {
                        
                        throw new Exception('Failed to save sales menu');
                    }
                    if (!$sourceMenuHeadExtra) {
                        $dataExtra->delete();
                    }
                }
                
                $salesMenuModel->calculateTotal();
                if (!$salesMenuModel->save()) {
                    
                    throw new Exception('Failed to save sales menu');
                }
            }

            //update sales menu related
            $sourceSalesMenuRelated = SalesMenuRelated::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['salesMenuID' => $sourceSalesMenuModel->ID])
                    ->all();
            if ($sourceSalesMenuRelated) {
                $sourceSalesMenuHead = SalesMenu::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['ID' => $sourceSalesMenuModel->ID])
                    ->one();
                foreach ($sourceSalesMenuRelated as $detail) {
                    $newSalesMenuRelatedModel = new SalesMenuRelated();
                    $newSalesMenuRelatedModel->salesNum = $this->salesNumTarget;
                    $newSalesMenuRelatedModel->salesMenuID = $salesMenuModel->ID;
                    $newSalesMenuRelatedModel->mainMenuID = $detail->mainMenuID;
                    $newSalesMenuRelatedModel->relatedMenuID = $detail->relatedMenuID;

                    if (!$newSalesMenuRelatedModel->save()) {
                        Yii::error($newSalesMenuRelatedModel->errors);
                    }
                    if (!$sourceSalesMenuHead) {
                        $detail->delete();
                    }
                }
            }

            //update sales menu recommedation
            $sourceSalesMenuRecommendation = SalesMenuRecommendation::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['salesMenuID' => $sourceSalesMenuModel->ID])
                    ->all();
            if ($sourceSalesMenuRecommendation) {
                $sourceSalesMenuHead = SalesMenu::find()
                    ->where(['salesNum' => $this->sourceSalesNum])
                    ->andWhere(['ID' => $sourceSalesMenuModel->ID])
                    ->one();
                foreach ($sourceSalesMenuRecommendation as $detail) {
                    $newSalesMenuRecommendationModel = new SalesMenuRecommendation();
                    $newSalesMenuRecommendationModel->salesNum = $this->salesNumTarget;
                    $newSalesMenuRecommendationModel->salesMenuID = $salesMenuModel->ID;

                    if (!$newSalesMenuRecommendationModel->save()) {
                        Yii::error($newSalesMenuRecommendationModel->errors);
                    }
                    if (!$sourceSalesMenuHead) {
                        $detail->delete();
                    }
                }
            }

            //delete sales reward menu (if any)
            if ($sourceSalesMenuModel->salesRewardMenu) {
                SalesRewardMenu::deleteAll(['ID' => $sourceSalesMenuModel->ID]);
            }
            
            // Update sales head main and child if menu package
            $salesMenuCompletionModel = SalesMenuCompletion::find()
                ->where(['salesNum' => $sourceSalesMenuModel->salesNum])
                ->andWhere(['salesMenuID' => $sourceSalesMenuModel->ID])
                ->all();
            if ($salesMenuCompletionModel) {
                foreach ($salesMenuCompletionModel as $salesMenuCompletion) {
                    $this->updateSalesMenuCompletion($salesMenuCompletion, $sourceSalesMenuModel, $salesMenuModel);
                }
                $this->updateStatusSalesMenu($salesMenuModel, $salesMenuCompletionModel);
            }

            if (!$sourceSalesHead->save()) {
                Yii::error($sourceSalesHead->errors);
                throw new Exception('Failed to re-calculate source sales head');
            }

            if ($targetSalesHeadModel) {
                $targetSalesHeadModel->paxTotal = 1;

                if (!$targetSalesHeadModel->save()) {
                    Yii::error($targetSalesHeadModel->errors);
                    throw new Exception('Failed to re-calculate sales head child');
                }
            }
            
            Logging::save($sourceSalesHead->salesNum, Logging::UPDATE_MENU_SPLIT,
                $this->getAttributes());
            
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesNum', $ex->getMessage());
            return false;
        }
    }

    private function updateSalesMenuCompletion($salesMenuCompletionModel, $sourceSalesMenuModel, $childSalesMenuModel, $package = false) {
        if ($salesMenuCompletionModel) {
            // compare qty
            $compareQty = $childSalesMenuModel->qty;
            $compareQtySource = $sourceSalesMenuModel->qty;
            if ($package) {
                $qtyHead = SalesMenu::find()
                    ->select('qty')
                    ->where(['localID' => $childSalesMenuModel->menuRefID])
                    ->andWhere(['menuGroupID' => 0])
                    ->scalar();
                $compareQty = $childSalesMenuModel->qty * $qtyHead;

                $qtyHeadSource = SalesMenu::find()
                    ->select('qty')
                    ->where(['localID' => $sourceSalesMenuModel->menuRefID])
                    ->andWhere(['menuGroupID' => 0])
                    ->scalar();
                $compareQtySource = $sourceSalesMenuModel->qty * $qtyHeadSource;
            }

            // update data only salesmenu completion
            if ( $compareQty >= $salesMenuCompletionModel->qty ) {
                $salesMenuCompletionModel->salesMenuID = $childSalesMenuModel->ID;
                $salesMenuCompletionModel->salesNum = $childSalesMenuModel->salesNum;
                if (!$salesMenuCompletionModel->save()) {
                    throw new Exception('Failed to update sales menu completion');
                }
            } else {

                if ($sourceSalesMenuModel->qty >= $salesMenuCompletionModel->qty) {
                    // update data salesmenu completion
                    $salesMenuCompletionModel->salesMenuID = $sourceSalesMenuModel->ID;
                    $salesMenuCompletionModel->salesNum = $sourceSalesMenuModel->salesNum;
                    $salesMenuCompletionModel->qty = $compareQtySource - $compareQty;
                    if (!$salesMenuCompletionModel->save()) {
                        throw new Exception('Failed to update sales menu completion');
                    }
                } else {

                    // update data salesmenu completion
                    $salesMenuCompletionModel->salesMenuID = $sourceSalesMenuModel->ID;
                    $salesMenuCompletionModel->salesNum = $sourceSalesMenuModel->salesNum;
                    $salesMenuCompletionModel->qty = $compareQtySource;
                    if (!$salesMenuCompletionModel->save()) {
                        throw new Exception('Failed to update sales menu completion');
                    }

                    // insert new row
                    $newSalesMenuCompletion = new SalesMenuCompletion();
                    $newSalesMenuCompletion->attributes = $salesMenuCompletionModel->attributes;
                    $newSalesMenuCompletion->salesNum = $childSalesMenuModel->salesNum;
                    $newSalesMenuCompletion->salesMenuID = $childSalesMenuModel->ID;
                    $newSalesMenuCompletion->typeID = $salesMenuCompletionModel->typeID;
                    $newSalesMenuCompletion->qty = $compareQty;
                    if (!$newSalesMenuCompletion->save()) {
                        throw new Exception('Failed to update sales menu completion');
                    }
                }
            }
        }
    }

    public function updateStatusSalesMenu($salesMenuModel, $salesMenuCompletionModel)
    {
        if ($salesMenuModel->statusID == 13) {
            $mode = Setting::find()
                ->where(['key1' => 'POS'])
                ->andWhere(['key2' => 'ODS Mode'])
                ->one()
                ->value1;

            $status = 34;
            $typeID = array_column($salesMenuCompletionModel, 'typeID');
            if ($mode) {
                if ($mode == 2) {
                    $status = 14;
                } else if (in_array(2, $typeID)) {
                    $status = 14;
                }
            }

            $salesMenuModel->statusID = $status;
            if (!$salesMenuModel->save()) {
                throw new Exception('Failed to save sales menu child');
            }
        }
    }
    
    public function setRenameBill(){
        $salesModel = SalesHead::find()
            ->where(['salesNum' => $this->salesNumTarget])
            ->one();

        if ($salesModel->statusID == 8) {
            throw new Exception('This order have been paid');
        }
        
        $salesModel->additionalInfo = $this->additionalInfo;
        $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
        if (!$salesModel->save()) {
            Yii::error($salesModel->errors);
            throw new Exception('Failed to update sales head');
        }
        return $salesModel->additionalInfo;
    }
    
}