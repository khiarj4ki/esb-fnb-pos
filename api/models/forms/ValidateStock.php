<?php

namespace app\models\forms;

use app\models\BranchMenu;
use app\models\BranchMenuTransaction;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\Menu;
use app\models\ProductDetailMenu;
use yii\base\Model;
use Yii;


/**
 * @property string $salesNum
 * @property string $menuID
 * @property string $qty
 * 
 * PRIVATE
 * @property SalesMenu $salesModel
 */
class ValidateStock extends Model {
    public $salesNum;
    public $menuID;
    public $qty;
    public $salesModel;
    public $transactionModeID;
    public $validateStock = true;
    public $isCancelOrder  = false;
    public $salesMenuID  = null;
    public $category = 'Add';

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'menuID', 'qty'], 'required'],
            [['isCancelOrder','salesMenuID','category'], 'safe'],
            [['salesNum'], 'validateSalesHead']
        ];
    }

    public function validateSalesHead($attribute) {
        $this->salesModel = SalesHead::find()
            ->where(['salesNum' => $this->salesNum])
            ->one();
    }

    public function validateStock() {
        if (!$this->validate()) {
            return false;
        }

        $productDetailMenuModel = ProductDetailMenu::find()
            ->where(['menuID' => $this->menuID])
            ->one();

        if ($productDetailMenuModel) {

            $menuIDs = ProductDetailMenu::find()
                ->select('menuID')
                ->where(['productID' => $productDetailMenuModel->productID])
                ->column();
 
            if ($this->validateStock && !$this->isCancelOrder) {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $menuIDs])
                ->andWhere(['>', 'qty', 0])
                ->andWhere(['<>', 'flagSoldOut', true])
                ->all();
            } else {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $menuIDs])
                ->all();
            }

            if ($branchMenuModel) {

                // @notes: change qty as mines if cancel Order for RTS
                $qtyCurr = $this->qty * $productDetailMenuModel->convertionQty;
                $qty = $this->isCancelOrder ? -abs($qtyCurr) : abs($qtyCurr);
                
                // @notes: check cancel Order for RTS
                $branchMenuTransactionModel = BranchMenuTransaction::find()
                ->where(['menuID' => $productDetailMenuModel->menuID])
                ->andWhere(['salesNum' => $this->salesNum])
                ->andWhere(['branchID' => $this->salesModel->branchID])
                ->andWhere(['salesMenuID' => $this->salesMenuID])
                ->one();

                $isNewRecordForBranchMenuTransaction = $this->category == 'Cancel Table' ? true : !$branchMenuTransactionModel && ($this->salesMenuID != 1 || !$this->salesMenuID);
                if ($this->transactionModeID === null || $this->transactionModeID === 0) {
                    foreach ($branchMenuModel as $bMenu) {
                        $branchMenu = BranchMenu::find()
                            ->where(['menuID' => $bMenu['menuID']])
                            ->one();
                        $stockQty = $branchMenu->qty;
                        $currQty = $stockQty - $qty;

                        if ($stockQty != 0 && !$this->validateStock) {
                            $branchMenu->qty = $currQty <= 0 ? 0 : $currQty;
                        } else {
                            $branchMenu->qty = $currQty;
                        }

                        $branchMenu->flagSoldOut = ($stockQty - $qty) <= 0 ? 1 : 0;

                        if ($isNewRecordForBranchMenuTransaction ||  in_array($this->category, ['Move','Add'])) {
                            if (!$branchMenu->save()) {
                                $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                                if ($menuModel) {
                                    $stockQty = intval($stockQty / $productDetailMenuModel->convertionQty);
                                    return $menuModel->menuID . '-'. $menuModel->menuShortName . "-($stockQty)";
                                }
                            }
                        }
                    }
                }

                // @notes: schema for RTS BranchMenuTransaction
                if ($this->isCancelOrder) {
                    if ($isNewRecordForBranchMenuTransaction) {
                        $branchMenuTransactionModel = new BranchMenuTransaction();
                        $branchMenuTransactionModel->transactionDate = date('Y-m-d H:i:s');
                        $branchMenuTransactionModel->branchID = $this->salesModel->branchID;
                        $branchMenuTransactionModel->salesNum = $this->salesNum;
                        $branchMenuTransactionModel->menuID = $productDetailMenuModel->menuID;
                        $branchMenuTransactionModel->qty = $qty;
                        $branchMenuTransactionModel->salesMenuID = $this->salesMenuID;
                        $branchMenuTransactionModel->category = 'Cancel';
                        $branchMenuTransactionModel->save();
                    }
                } else {
                    $realQty = $this->category == 'Move' ? -abs($qty) : $qty;
                    $branchMenuTransactionModel = new BranchMenuTransaction();
                    $branchMenuTransactionModel->transactionDate = date('Y-m-d H:i:s');
                    $branchMenuTransactionModel->branchID = $this->salesModel->branchID;
                    $branchMenuTransactionModel->salesNum = $this->salesNum;
                    $branchMenuTransactionModel->menuID = $productDetailMenuModel->menuID;
                    $branchMenuTransactionModel->qty = $realQty;
                    $branchMenuTransactionModel->salesMenuID = $this->salesMenuID;
                    $branchMenuTransactionModel->category = $this->category;
                    $branchMenuTransactionModel->save();
                }

            } else {
                //check if sold out
                $branchMenuModel = BranchMenu::find()
                    ->andWhere(['menuID' => $this->menuID])
                    ->andWhere(['=', 'flagSoldOut', true])
                    ->one();
                if ($branchMenuModel) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuID . '-'. $menuModel->menuShortName . "-(Sold Out)";
                    }
                }
            }
        } else {

            // @notes: non RTS not validate stock
            if($this->isCancelOrder) {
                return null;
            }

            if ($this->validateStock) {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $this->menuID])
                ->andWhere(['>', 'qty', 0])
                ->andWhere(['<>', 'flagSoldOut', true])
                ->one();
            } else {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $this->menuID])
                ->one();
            }

            if ($branchMenuModel) {
                $stockQty = $branchMenuModel->qty;
                $currQty = $stockQty - $this->qty;

                if ($stockQty != 0 && !$this->validateStock) {
                    $branchMenuModel->qty = $currQty <= 0 ? 0 : $currQty;
                } else {
                    $branchMenuModel->qty = $currQty;
                }

                $branchMenuModel->flagSoldOut = ($stockQty - $this->qty) <= 0 ? 1 : $branchMenuModel->flagSoldOut;
                if (!$branchMenuModel->save()) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuID . '-'. $menuModel->menuShortName . "-($stockQty)";
                    }
                }
            } else {
                //check if sold out
                $branchMenuModel = BranchMenu::find()
                    ->andWhere(['menuID' => $this->menuID])
                    ->andWhere(['=', 'flagSoldOut', true])
                    ->one();
                if ($branchMenuModel) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuID . '-'. $menuModel->menuShortName . "-(Sold Out)";
                    }
                }
            }
        }

        return null;
    }

    public function validateStockOnBranchMenu() {
        if ($this->validateStock) {
            $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $this->menuID])
                ->andWhere(['>', 'qty', 0])
                ->andWhere(['<>', 'flagSoldOut', true])
                ->one();
        } else {
            $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $this->menuID])
                ->one();
        }

        if ($branchMenuModel) {
            $stockQty = $branchMenuModel->qty;
            $currQty = $stockQty - $this->qty;

            if ($currQty < 0) {
                $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                if ($menuModel) {
                    return $menuModel->menuID . '-'. $menuModel->menuShortName . "-($stockQty)";
                }
            }
        } else {
            //check if sold out
            $branchMenuModel = BranchMenu::find()
                ->andWhere(['menuID' => $this->menuID])
                ->andWhere(['=', 'flagSoldOut', true])
                ->one();
            if ($branchMenuModel) {
                $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
                if ($menuModel) {
                    return $menuModel->menuID . '-'. $menuModel->menuShortName . "-(Sold Out)";
                }
            }
        }
    }
}
