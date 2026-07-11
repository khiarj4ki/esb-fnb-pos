<?php
namespace app\models\forms;

use app\models\SalesHead;
use app\models\SalesHeadVat;
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
use yii\db\Expression;

class DeleteSalesChild extends Model {
    public $salesNum;
    public $salesNumHead;
    public $salesHeadModel;
    public $salesHeadModelChild;
    public $salesMenuModelChild;
    public $targetSalesMenuModel;


    public function rules() {
        return [
            [['salesNum'], 'required'],
            [['salesNum', 'salesNumHead'], 'string', 'max' => 20],
            [['salesNum'], 'validateSalesNum'],
            [['salesNum'], 'validateSalesNumHead'],
        ];
    }
    
    public function validateSalesNum($attribute) {
        $this->salesHeadModelChild = SalesHead::find()
            ->andWhere(['salesNum' => $this->salesNum])
            ->one();
        if (!$this->salesHeadModelChild) {
            $this->addError($attribute, 'Invalid sales number');
        }
    }
    
    public function validateSalesNumHead($attribute) {
        $exploded = explode("-", $this->salesNum);
        $this->salesNumHead = $exploded[0];
        $this->salesHeadModel = SalesHead::find()
            ->andWhere(['salesNum' => $this->salesNumHead])
            ->one();
        if (!$this->salesHeadModel) {
            $this->addError($attribute, 'Invalid sales number head');
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
            $salesMenuModel = SalesMenu::find()
                ->innerJoinWith("menu")
                ->where(['salesNum' => $this->salesNum])
                ->andWhere(['=', 'menuGroupID', 0])
                ->all();
            $this->targetSalesMenuModel = $salesMenuModel;
            $menuPackages = [];
            $menuExtras = [];
            if ($salesHeadModel->statusID == 8) {
                throw new Exception('This order have been paid');
            }
            if ($salesMenuModel) {
                
                foreach ($salesMenuModel as $data) {
                    $tempRefID = $data->menuRefID;
                    $salesMenuModelHead = null;
                    $checkSalesMenu = SalesMenu::find()
                        ->where(['salesNum' => $this->salesNumHead, 'ID' => $data->ID])
                        ->one();
                    $salesMenuChildModel = SalesMenu::find()
                        ->where(['salesNum' => $this->salesNum, 'ID' => $data->ID])
                        ->one();
                    $salesMenuModelHead = new SalesMenu();
                    $salesMenuModelHead->attributes = $salesMenuChildModel->attributes;
                    $salesMenuModelHead->salesNum = $this->salesNumHead;

                    $limitUsageForBillAndMenuPromotion = Setting::getValue1('POS', 'Limit usage for bill and menu promotion');
                    if ($limitUsageForBillAndMenuPromotion == 1 && $salesHeadModel->promotionID > 0) {
                        $salesMenuModelHead['promotionDetailID'] = 0;
                        $salesMenuModelHead['promotionVoucherCode'] = '';
                    }
                    
                    if (!$salesMenuModelHead->save()) {
                        throw new Exception('Failed to save sales menu');
                    }

                    $salesMenuExtraModel = SalesMenuExtra::find()
                        ->where(['salesNum' => $this->salesNum, 'menuDetailID' => $data->ID])
                        ->all();
                    if ($salesMenuExtraModel) {
                        foreach ($salesMenuExtraModel as $key => $dataExtra) {
                            $dataExtra->salesNum = $salesMenuModelHead->salesNum;
                            $dataExtra->menuDetailID = $salesMenuModelHead->ID;
    
                            if (!$dataExtra->save()) {
                                throw new Exception('Failed to save sales extra');
                            }

                            $dataExtra->salesNum = $this->salesNum;
                            $dataExtra->menuDetailID = $data->ID;
                            $menuExtras[] = $dataExtra->toArray();
                        }
                    }

                    $data->qty = $data->qty - $data->qty;
                    if (!$data->save()) {
                        throw new Exception('Failed to save sales menu');
                    }
                    if ($salesMenuModelHead->menuRefID > 0) {
                        $targetMenuPackage = SalesMenu::find()
                            ->where(['salesNum' => $this->salesNumHead])
                            ->andWhere(['menuRefID' => $data->ID])
                            ->andWhere(['=' ,'menuGroupID', 0])
                            ->one();
                        $sourceMenuPackage = SalesMenu::find()
                            ->where(['salesNum' => $salesMenuChildModel->salesNum])
                            ->andWhere(['menuRefID' => $data->ID])
                            ->andWhere(['<>' ,'menuGroupID', 0])
                            ->all();
                        
                        foreach ($sourceMenuPackage as $dataPackage) {
                            $packageID = $dataPackage->ID;
                            $dataPackage->salesNum = $this->salesNumHead;
                            $dataPackage->menuRefID = $targetMenuPackage->ID;
                            if (!$dataPackage->save()) {
                                throw new Exception('Failed to save sales menu');
                            }

                            $salesMenuCompletionPckModel = SalesMenuCompletion::find()
                                ->where(['salesNum' => $this->salesNum])
                                ->andWhere(['salesMenuID' => $packageID])
                                ->one();
                            
                            if ($salesMenuCompletionPckModel) {
                                $salesMenuCompletionPckModel->salesNum = $salesMenuModelHead->salesNum;
                                $salesMenuCompletionPckModel->salesMenuID = $dataPackage->ID;
                                
                                if (!$salesMenuCompletionPckModel->save()) {
                                    throw new Exception('Failed to save sales menu completion');
                                }
                            }

                            $dataPackage->salesNum = $this->salesNum;
                            $dataPackage->menuRefID = $data->ID;
                            $menuPackages[] = $dataPackage->toArray();
                        }
                        
                        $targetMenuPackage->menuRefID = $targetMenuPackage->ID;
                        if (!$targetMenuPackage->save()) {
                            throw new Exception('Failed to save sales menu');
                        }
                        
                    }

                    $salesMenuCompletionModel = SalesMenuCompletion::find()
                        ->where(['salesNum' => $this->salesNum])
                        ->andWhere(['salesMenuID' => $data->ID])
                        ->one();
                    
                    if ($salesMenuCompletionModel) {
                        $salesMenuCompletionModel->salesNum = $salesMenuModelHead->salesNum;
                        $salesMenuCompletionModel->salesMenuID = $salesMenuModelHead->ID;
                        
                        if (!$salesMenuCompletionModel->save()) {
                            throw new Exception('Failed to save sales menu completion');
                        }
                    }

                    $salesMenuRelatedModel = SalesMenuRelated::find()
                        ->where(['salesNum' => $this->salesNum, 'salesMenuID' => $data->ID])
                        ->all();
                    if ($salesMenuRelatedModel) {
                        foreach ($salesMenuRelatedModel as $key => $dataRelated) {
                            $dataRelated->salesNum = $salesMenuModelHead->salesNum;
                            $dataRelated->salesMenuID = $salesMenuModelHead->ID;
                            $dataRelated->mainMenuID = $dataRelated->mainMenuID;
                            $dataRelated->relatedMenuID = $salesMenuModelHead->menuID;
    
                            if (!$dataRelated->save()) {
                                throw new Exception('Failed to save sales menu related');
                            }
                        }
                    }

                    $salesMenuRecommendationModel = SalesMenuRecommendation::find()
                        ->where(['salesNum' => $this->salesNum, 'salesMenuID' => $data->ID])
                        ->all();

                    if ($salesMenuRecommendationModel) {
                        foreach ($salesMenuRecommendationModel as $key => $dataRecommendation) {
                            $dataRecommendation->salesNum = $salesMenuModelHead->salesNum;
                            $dataRecommendation->salesMenuID = $salesMenuModelHead->ID;
    
                            if (!$dataRecommendation->save()) {
                                throw new Exception('Failed to save sales menu recommedation');
                            }
                        }
                    }
                }
            }
            SalesMenu::deleteAll(['qty' => 0, 'salesNum' => $this->salesNum]);
            //SalesVisitor::deleteAll(['paxTotal' => 0, 'salesNum' => $this->salesNum]);
            
            //$salesHeadModel->paxTotal = $salesHeadModel->paxTotal + $salesHeadModelChild->paxTotal;
            if (!$salesHeadModel->save()) {
                throw new Exception('Failed to save sales head');
            }
            
            $salesHeadModelChild->paxTotal = $salesHeadModelChild->paxTotal - $salesHeadModelChild->paxTotal;
            if (!$salesHeadModelChild->save()) {
                throw new Exception('Failed to save sales head');
            }

            SalesMenuVat::deleteAll(['salesNum' => $this->salesNum]);
            SalesHeadVat::deleteAll(['salesNum' => $this->salesNum]);

            SalesHead::updateAll([
                'salesDateOut' => new Expression('NOW()'),
                'statusID' => 12,
                'syncDate' => null
                ], ['=', 'salesNum', $this->salesNum]);
            
            Logging::save($this->salesNum, Logging::DELETE_SALES_CHILD,
            $this->getAttributes(), $menuPackages, $menuExtras);
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesNum', $ex->getMessage());
            return false;
        }
    }
}