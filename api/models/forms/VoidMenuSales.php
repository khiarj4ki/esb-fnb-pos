<?php

namespace app\models\forms;

use app\models\MapBranchVisitPurpose;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property string $salesMenuID
 * @property string $voidQty
 * 
 * PRIVATE
 * @property SalesMenu $salesMenuModel
 */
class VoidMenuSales extends Model {
    public $salesMenuID;
    public $voidQty;
    public $salesMenuModel;
    public $salesModel;
    public $inclusivePrice;
    public $discountValue;
    public $serviceCharge;
    public $taxTotal;
    public $subTotal;
    public $grandTotal;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesMenuID', 'voidQty'], 'required'],
            [['salesMenuID'], 'validateSalesMenu']
        ];
    }

    public function validateSalesMenu($attribute) {
        $this->salesMenuModel = SalesMenu::find()
            ->where(['ID' => $this->salesMenuID])
            ->one();
        if (!$this->salesMenuModel) {
            $this->addError($attribute, 'Invalid sales menu ID');
        }

        $this->salesModel = SalesHead::find()
            ->where(['salesNum' => $this->salesMenuModel->salesNum])
            ->one();
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $salesMenuModel = $this->salesMenuModel;
            $qtyFinal = $salesMenuModel->qty - $this->voidQty;
            $statusID = $salesMenuModel->statusID;
            $batchID = $salesMenuModel->batchID;
            $cancelNotes = '';
            if ($qtyFinal < 1) {
                $qtyFinal = $salesMenuModel->qty;
                $statusID = 19;
                $cancelNotes = 'Void Sales Menu';
                $menuInclusivePrice = 0;
                $menuDiscountValue = 0; 
                $menuPriceTotal = 0;
                $menuTaxTotal = 0;
                $menuVatTotal = 0;
                $menuGrandTotal = 0;
            } else {
                $batchID = SalesMenu::getNewBatchID($salesMenuModel->salesNum);
                $newSalesMenuModel = new SalesMenu([
                    'attributes' => $salesMenuModel->attributes
                ]);
                $newSalesMenuModel->qty = $this->voidQty;
                $newSalesMenuModel->batchID = $batchID;
                $newSalesMenuModel->statusID = 19;
                $newSalesMenuModel->cancelNotes = 'Void Sales Menu';
                $newSalesMenuModel->promotionDetailID = 0;
                $newSalesMenuModel->editedBy = Yii::$app->user->identity->username;
                $newSalesMenuModel->editedDate = date('Y-m-d H:i:s');
                $newSalesMenuModel->calculateTotal();
                
                $menuInclusivePrice = $this->salesMenuModel->inclusivePrice * $this->voidQty;
                $menuDiscountValue = $this->salesMenuModel->discountValue * $this->voidQty;
                $menuPriceTotal = $this->salesMenuModel->price * $this->voidQty;
                $menuTaxTotal = $menuPriceTotal * $this->salesMenuModel->otherTax / 100;
                $menuVatTotal = ($menuPriceTotal + $menuTaxTotal) * $this->salesMenuModel->vat / 100;
                $menuGrandTotal = $menuPriceTotal + $menuTaxTotal + $menuVatTotal;

                if (!$newSalesMenuModel->save()) {
                    throw new Exception(json_encode($newSalesMenuModel->getErrors()));
                }
            }

            $menuPackages = [];
            $allSalesMenuPackageModel = SalesMenu::find()
                ->where([
                    'salesNum' => $salesMenuModel->salesNum,
                    'menuRefID' => $salesMenuModel->menuRefID
                ])
                ->andWhere("menuGroupID > 0")
                ->all();

            $packageInclusivePrice = 0;
            $packageDiscountValue = 0;
            $packagePriceTotal = 0;
            $packageTaxTotal = 0;
            $packageVatTotal = 0;
            $packageGrandTotal = 0;
            if ($allSalesMenuPackageModel) {
                if (isset($newSalesMenuModel)) {
                    $newSalesMenuModel->menuRefID = $newSalesMenuModel->ID;
                    if (!$newSalesMenuModel->save()) {
                        throw new Exception('Failed to update main menu package');
                    }
                }
                foreach ($allSalesMenuPackageModel as $item) {
                    $salesMenuPackageModel = SalesMenu::find()
                        ->where([
                            'localID' => $item->ID
                        ])
                        ->one();

                    if ($salesMenuPackageModel) {
                        if ($qtyFinal > 0 && in_array($statusID, [13, 34, 14])) {
                            $newSalesMenuPackageModel = new SalesMenu([
                                'attributes' => $salesMenuPackageModel->attributes
                            ]);
                            $newSalesMenuPackageModel->batchID = $batchID;
                            $newSalesMenuPackageModel->menuRefID = $newSalesMenuModel->menuRefID;
                            $newSalesMenuPackageModel->statusID = 19;
                            $newSalesMenuPackageModel->cancelNotes = 'Void Sales Menu';
                            $newSalesMenuPackageModel->editedBy = Yii::$app->user->identity->username;
                            $newSalesMenuPackageModel->editedDate = date('Y-m-d H:i:s');
                            $newSalesMenuPackageModel->calculateTotal();
                            $newSalesMenuPackageModel->total = $salesMenuPackageModel->total;

                            $packageInclusivePrice += $newSalesMenuPackageModel->inclusivePrice * $this->voidQty;
                            $packageDiscountValue += $newSalesMenuPackageModel->discountValue * $this->voidQty;
                            $packagePriceTotal += $newSalesMenuPackageModel->price * $newSalesMenuPackageModel->qty * $this->voidQty;
                            $packageTaxTotal += $newSalesMenuPackageModel->otherTaxValue * $this->voidQty;
                            $packageVatTotal += $newSalesMenuPackageModel->vatValue * $this->voidQty;
                            $packageGrandTotal = $packagePriceTotal + $packageTaxTotal + $packageVatTotal;

                            if (!$newSalesMenuPackageModel->save()) {
                                throw new Exception(json_encode($newSalesMenuPackageModel->getErrors()));
                            }
                        } else {
                            SalesMenu::updateAll([
                                'cancelNotes' => $cancelNotes,
                                'statusID' => $statusID
                            ], ['=', 'ID', $item->ID]);
                        }
                    }
                    $menuPackages[] = $item->toArray();
                }
            }

            $menuExtras = [];
            $allSalesMenuExtraModel = SalesMenuExtra::find()
                ->where([
                    'salesNum' => $salesMenuModel->salesNum,
                    'menuDetailID' => $salesMenuModel->ID
                ])
                ->all();

            $extraInclusivePrice = 0;
            $extraDiscountValue = 0;
            $extraPriceTotal = 0;
            $extraTaxTotal = 0;
            $extraVatTotal = 0;
            $extraGrandTotal = 0;
            if ($allSalesMenuExtraModel) {
                foreach ($allSalesMenuExtraModel as $menuExtra) {
                    $salesMenuExtraModel = SalesMenuExtra::find()
                        ->andWhere([
                            'ID' => $menuExtra->localID
                        ])
                        ->one();

                    if ($salesMenuExtraModel) {
                        if ($qtyFinal > 0 && in_array($statusID, [13, 34, 14])) {
                            $newSalesMenuExtraModel = new SalesMenuExtra([
                                'attributes' => $salesMenuExtraModel->attributes
                            ]);
                            $newSalesMenuExtraModel->menuDetailID = $newSalesMenuModel->ID;
                            $newSalesMenuExtraModel->statusID = 19;
                            $newSalesMenuModel->calculateTotal();
                            
                            $extraInclusivePrice += $newSalesMenuExtraModel->inclusivePrice * $this->voidQty;
                            $extraDiscountValue += $newSalesMenuExtraModel->discountValue * $this->voidQty;
                            $extraPriceTotal += $newSalesMenuExtraModel->price * $newSalesMenuExtraModel->qty * $this->voidQty;
                            $extraTaxTotal += $newSalesMenuExtraModel->otherTaxValue * $this->voidQty;
                            $extraVatTotal += $newSalesMenuExtraModel->vatValue * $this->voidQty;
                            $extraGrandTotal += $extraPriceTotal + $extraTaxTotal + $extraVatTotal;

                            if (!$newSalesMenuExtraModel->save()) {
                                throw new Exception(json_encode($newSalesMenuExtraModel->getErrors()));
                            }
                        } else {
                            SalesMenuExtra::updateAll([
                                'statusID' => $statusID
                            ], ['=', 'ID', $menuExtra->ID]);
                        }
                    }
                    $menuExtras[] = $menuExtra->toArray();
                }
            }
            
            SalesMenu::updateAll([
                'qty' => $qtyFinal,
                'cancelNotes' => $cancelNotes,
                'statusID' => $statusID
                ], ['=', 'ID', $this->salesMenuID]);
            
            $updatedSalesMenuModel = SalesMenu::find()
                            ->andWhere(['ID' => $this->salesMenuID])
                            ->one();
            
            $updatedSalesMenuModel->calculateTotal();
            if (!$updatedSalesMenuModel->save()) {
                throw new Exception(json_encode($updatedSalesMenuModel->getErrors()));
            }
            
            if (isset($this->salesModel) && $this->salesModel->promotionID > 0) {
                if ($this->salesModel->promotion->promotionTypeID == 1 && !$this->salesModel->promotion->promotionCategories) {
                    $freeItemSalesMenuModel = SalesMenu::findActive()
                        ->where(['salesNum' => $salesMenuModel->salesNum])
                        ->andWhere('originalPrice <> price')
                        ->andWhere('statusID <> 19')
                        ->all();

                    if (!$freeItemSalesMenuModel) {
                        $findSalesMenuPromo = SalesMenu::find()
                            ->where(['salesNum' => $salesMenuModel->salesNum])
                            ->andWhere(['promotionDetailID' => $this->salesModel->promotionID])
                            ->all();

                        if ($findSalesMenuPromo) {
                            $salesMenuIDs = [];
                            foreach ($findSalesMenuPromo as $salesMenu) {
                                $salesMenuIDs[] = $salesMenu->ID;
                            }

                            SalesMenu::updateAll([
                                'discount' => 0,
                                'discountValue' => 0,
                                'inclusiveDiscountValue' => 0,
                                'promotionDetailID' => 0
                            ], ['IN', 'ID', $salesMenuIDs]);

                            SalesMenuExtra::updateAll([
                                'discount' => 0,
                                'discountValue' => 0,
                                'inclusiveDiscountValue' => 0
                            ], ['IN', 'menuDetailID', $salesMenuIDs]);

                            SalesHead::updateAll([
                                'promotionDiscount' => $this->salesModel->promotion->discount
                            ], ['=', 'salesNum', $salesMenuModel->salesNum]);
                        }
                    }
                }
            }

            $salesMenuCount = SalesMenu::find()
                ->where(['salesNum' => $salesMenuModel->salesNum])
                ->andWhere(['in', 'statusID', [13, 14, 34]])
                ->count();
            
            if ($salesMenuCount > 0) {
                $salesHeadModel = SalesHead::findOne(['salesNum' => $salesMenuModel->salesNum]);
                if (!$salesHeadModel->save()) {
                    throw new Exception('Failed to update sales');
                }
            } else {
                $voidModel = new VoidSales();
                $voidModel->salesNum = $salesMenuModel->salesNum;
                $voidModel->voidNotes = 'Void All Sales Menu';
                if (!$voidModel->save()) {
                    throw new Exception('Failed to void sales');
                }
            }

            $this->inclusivePrice = $menuInclusivePrice + $packageInclusivePrice + $extraInclusivePrice;
            $this->discountValue = $menuDiscountValue + $packageDiscountValue + $extraDiscountValue;
            $this->serviceCharge = $menuVatTotal + $packageVatTotal + $extraVatTotal;
            $this->taxTotal = $menuTaxTotal + $packageTaxTotal + $extraTaxTotal;
            $this->subTotal = $menuPriceTotal + $packagePriceTotal + $extraPriceTotal;
            $this->grandTotal = $menuGrandTotal + $packageGrandTotal + $extraGrandTotal;

            Logging::save($this->salesModel->salesNum, Logging::VOID_MENU_SALES,
                $this->getAttributes(), $menuPackages, $menuExtras);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('voidNotes', $ex->getMessage());
            return false;
        }
    }

}
