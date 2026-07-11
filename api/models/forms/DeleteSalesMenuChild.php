<?php
namespace app\models\forms;

use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesMenuExtra;
use app\models\SalesMenuRecommendation;
use app\models\SalesMenuRelated;
use app\models\SalesMenuVat;
use app\models\SalesVisitor;
use app\models\Setting;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\db\Exception as DbException;

class DeleteSalesMenuChild extends Model {
    public $salesMenuID;
    public $salesNum;
    public $menuID;
    public $salesNumHead;
    public $sourceSalesNum;
    public $salesHeadModel;
    public $salesHeadModelChild;
    public $salesMenuModelChild;
    public $salesMenuModelHead;
    public $targetSalesMenuModel;
    public $salesMenuExtra;
    public $salesMenuRelated;
    public $salesMenuRecommendation;
    public $qty;


    public function rules() {
        return [
            [['salesMenuID', 'qty'], 'required'],
            [['salesNum', 'salesNumHead'], 'string', 'max' => 20],
            [['salesMenuID'], 'validateSalesMenuID'],
        ];
    }
    
    public function validateSalesMenuID($attribute) {
        $salesMenuModelChild = SalesMenu::find()
            ->innerJoinWith("menu")
            ->where(['ID' => $this->salesMenuID])->one();
        $this->targetSalesMenuModel = $salesMenuModelChild;
        if ($salesMenuModelChild) {
            $this->salesMenuModelChild = $salesMenuModelChild;
            $this->salesNum = $salesMenuModelChild->salesNum;
            $this->menuID = $salesMenuModelChild->menuID;
            $this->salesHeadModelChild = SalesHead::find()
                ->andWhere(['salesNum' => $this->salesNum])
                ->one();
            $exploded = explode("-", $this->salesNum);
            $this->salesNumHead = $exploded[0];
            $this->sourceSalesNum = $this->salesNumHead;
            $this->salesHeadModel = SalesHead::find()
                ->andWhere(['salesNum' => $this->salesNumHead])
                ->one();
            $this->salesMenuModelHead = SalesMenu::find()
                ->innerJoinWith("menu")
                ->where([
                    'salesNum' => $this->salesNumHead, 
                    'tr_salesmenu.menuID' => $salesMenuModelChild->menuID])
                ->one();
            $this->salesMenuExtra = SalesMenuExtra::find()
                ->where(['salesNum' => $salesMenuModelChild->salesNum, 'menuDetailID' => $salesMenuModelChild->ID])
                ->all();

            $this->salesMenuRelated = SalesMenuRelated::find()
                ->where(['salesNum' => $salesMenuModelChild->salesNum, 'salesMenuID' => $salesMenuModelChild->ID])
                ->all();

            $this->salesMenuRecommendation = SalesMenuRecommendation::find()
                ->where(['salesNum' => $salesMenuModelChild->salesNum, 'salesMenuID' => $salesMenuModelChild->ID])
                ->all();
        } else {
            $this->addError($attribute, 'Invalid sales menu ID');
        }
    }
    
    public function save() {
        if (!$this->validate()) {
            return false;
        }
        
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $salesHeadModel = $this->salesHeadModel;
            $salesHeadModelChild = $this->salesHeadModelChild;
            $salesMenuModel = $this->salesMenuModelChild;
            $salesMenuModelHead = null;
            $salesMenuExtraModel = $this->salesMenuExtra;
            $salesMenuChildModel = SalesMenu::find()
                ->where(['salesNum' => $this->salesNum, 'menuID' => $this->menuID, 'menuRefID' => $salesMenuModel->menuRefID])
                ->one();
            if ($salesHeadModel->statusID == 8) {
                throw new Exception('This order have been paid');
            }
            $salesMenuModelHead = new SalesMenu();
            $salesMenuModelHead->attributes = $salesMenuChildModel->attributes;
            $salesMenuModelHead->salesNum = $this->salesNumHead;
            if (is_float($this->qty)) {
                $salesMenuModelHead->qty = $this->qty;
            } else {
                $salesMenuModelHead->qty = 1;
            }
            
            $limitUsageForBillAndMenuPromotion = Setting::getValue1('POS', 'Limit usage for bill and menu promotion');
            if ($limitUsageForBillAndMenuPromotion == 1 && $salesHeadModel->promotionID > 0) {
                $salesMenuModelHead['promotionDetailID'] = 0;
                $salesMenuModelHead['promotionVoucherCode'] = '';
            }

            if ($salesMenuChildModel->promotion && ($salesMenuChildModel->promotion->promotionTypeID == 18 || $salesMenuChildModel->promotion->promotionTypeID == 19)) {
                $salesMenuModelHead->promotionDetailID = 0;
            }

            $salesMenuModelHead->calculateTotal();
            if (!$salesMenuModelHead->save()) {
                throw new Exception('Failed to save sales menu');
            }

            $salesMenusChildModel = SalesMenu::find()
                ->where(['salesNum' => $this->salesNum])
                ->all();

            if ($salesMenusChildModel) {
                foreach ($salesMenusChildModel as $salesMenu) {
                    if ($salesMenu->promotion && ($salesMenu->promotion->promotionTypeID == 18 || $salesMenu->promotion->promotionTypeID == 19)) {
                        $salesMenu->promotionDetailID = 0;
                        $salesMenu->calculateTotal();
                        if (!$salesMenu->save()) {
                            throw new Exception('Failed to save sales menu');
                        }
                    }
                }
            }
            
            if (is_float($this->qty)) {
                $salesMenuModel->qty = $salesMenuModel->qty - $this->qty;
            } else {
                $salesMenuModel->qty = $salesMenuModel->qty - 1;
            }
            $salesMenuModel->calculateTotal();
            if (!$salesMenuModel->save()) {
                throw new Exception('Failed to save sales menu');
            }

            $menuPackages = [];
            if ($salesMenuModel->menuRefID > 0) {
                $targetMenuPackage = SalesMenu::find()
                    ->where(['salesNum' => $this->salesNumHead])
                    ->andWhere(['menuRefID' => $salesMenuChildModel->menuRefID])
                    ->andWhere(['=' ,'menuGroupID', 0])
                    ->one();
                $sourceMenuPackage = SalesMenu::find()
                    ->where(['salesNum' => $salesMenuModel->salesNum])
                    ->andWhere(['menuRefID' => $salesMenuModel->menuRefID])
                    ->andWhere(['<>' ,'menuGroupID', 0])
                    ->all();

                foreach ($sourceMenuPackage as $dataPackage) {
                    $newSalesMenuPackageModel = new SalesMenu();
                    $newSalesMenuPackageModel->attributes = $dataPackage->attributes;
                    $newSalesMenuPackageModel->salesNum = $this->salesNumHead;
                    $newSalesMenuPackageModel->menuRefID = $targetMenuPackage->ID;
                    if (!$newSalesMenuPackageModel->save()) {
                        throw new Exception('Failed to save new sales menu');
                    }
                    if (!$dataPackage->save()) {
                        throw new Exception('Failed to save new source sales menu');
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
                    $menuPackages[] = $newSalesMenuPackageModel->toArray();
                }
                
                $targetMenuPackage->menuRefID = $targetMenuPackage->ID;
                if (!$targetMenuPackage->save()) {
                    throw new Exception('Failed to save sales menu');
                }
                
            }
            $menuExtras = [];
            if ($salesMenuExtraModel) {
                foreach ($salesMenuExtraModel as $key => $dataExtra) {
                    $dataExtra->salesNum = $salesMenuModelHead->salesNum;
                    $dataExtra->menuDetailID = $salesMenuModelHead->ID;
                    $dataExtra->calculateTotal();
                    if (!$dataExtra->save()) {
                        throw new Exception('Failed to save sales extra');
                    }

                    if ($salesMenuModel->qty > 0) {
                        $newSalesMenuExtra = new SalesMenuExtra();
                        $newSalesMenuExtra->attributes = $dataExtra->attributes;
                        $newSalesMenuExtra->salesNum = $salesMenuModel->salesNum;
                        $newSalesMenuExtra->menuDetailID = $salesMenuModel->ID;

                        if (!$newSalesMenuExtra->save()) {
                            throw new Exception('Failed to save sales extra');
                        }
                    }
                    $menuExtras[] = $dataExtra->toArray();
                }
            }

            $salesMenuCompletionModel = SalesMenuCompletion::find()
                ->where(['salesNum' => $salesMenuModel->salesNum])
                ->andWhere(['salesMenuID' => $salesMenuModel->ID])
                ->all();
            if ($salesMenuCompletionModel) {
                foreach ($salesMenuCompletionModel as $salesMenuCompletion) {
                    $this->updateSalesMenuCompletion($salesMenuCompletion,  $salesMenuModelHead, $salesMenuModel);
                }
            }

            if (!$salesHeadModel->save()) {
                throw new Exception('Failed to re-calculate source sales head');
            }

            if ($salesHeadModelChild) {
                if (!$salesHeadModelChild->save()) {
                    throw new Exception('Failed to re-calculate sales head child');
                }
            }

            if ($this->salesMenuRelated) {
                $sourceSalesMenuHead = SalesMenu::find()
                    ->where(['salesNum' => $this->salesMenuModelHead->salesNum])
                    ->andWhere(['ID' => $this->salesMenuModelHead->ID])
                    ->one();

                foreach ($this->salesMenuRelated as $detail) {
                    $newSalesMenuRelatedModel = new SalesMenuRelated();
                    $newSalesMenuRelatedModel->salesNum = $salesMenuModelHead->salesNum;
                    $newSalesMenuRelatedModel->salesMenuID = $salesMenuModelHead->ID;
                    $newSalesMenuRelatedModel->mainMenuID = $detail->mainMenuID;
                    $newSalesMenuRelatedModel->relatedMenuID = $detail->relatedMenuID;

                    if (!$newSalesMenuRelatedModel->save()) {
                        throw new DbException($newSalesMenuRelatedModel->errors);
                    }
                    if (!$sourceSalesMenuHead) {
                        $detail->delete();
                    }
                }
            }

            if ($this->salesMenuRecommendation) {
                $sourceSalesMenuHead = SalesMenu::find()
                    ->where(['salesNum' => $this->salesMenuModelHead->salesNum])
                    ->andWhere(['ID' => $this->salesMenuModelHead->ID])
                    ->one();

                foreach ($this->salesMenuRecommendation as $detail) {
                    $newSalesMenuRecommendationModel = new SalesMenuRecommendation();
                    $newSalesMenuRecommendationModel->salesNum = $salesMenuModelHead->salesNum;
                    $newSalesMenuRecommendationModel->salesMenuID = $salesMenuModelHead->ID;

                    if (!$newSalesMenuRecommendationModel->save()) {
                        throw new DbException($newSalesMenuRecommendationModel->errors);

                    }
                    if (!$sourceSalesMenuHead) {
                        $detail->delete();
                    }
                }
            }
            
            $oldSalesMenuChild = SalesMenu::find()
                ->where(['qty' => 0, 'salesNum' => $this->salesNum])
                ->all();

            if ($oldSalesMenuChild) {
                foreach ($oldSalesMenuChild as $row) {
                    if ($row->delete()) {
                        SalesMenu::deleteAll("salesNum = '$row->salesNum' AND menuRefID > 0 AND menuRefID = '$row->menuRefID'");
                        SalesMenuExtra::deleteAll(['salesNum' => $row->salesNum, 'menuDetailID' => $row->localID]);
                        SalesMenuRecommendation::deleteAll(['salesNum' => $row->salesNum, 'salesMenuID' => $row->localID]);
                        SalesMenuVat::deleteAll(['salesNum' => $row->salesNum, 'salesMenuID' => $row->localID]);
                    }
                }
            }

            Logging::save($this->salesNum, Logging::DELETE_MENU_SPLIT,
            $this->getAttributes(), $menuPackages, $menuExtras);
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
}