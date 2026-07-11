<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BrandSetting;
use app\models\MapBranchVisitPurpose;
use app\models\MapMenuTemplatePackage;
use app\models\MenuExtra;
use app\models\MenuPackage;
use app\models\MenuTemplateDetail;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesRewardHead;
use app\models\SalesRewardMenu;
use app\models\Setting;
use app\models\SpecialPriceMenu;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $promotionID
 * $property string $errorMessage
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 * @property PromotionHead $promotionModel
 * @property array $promotionCategoryIDs
 */
class ApplyBillPromo extends Model {
    public $tableID;
    public $salesNum;
    public $promotionID;
    public $errorMessage;
    public $salesModel;
    public $promotionModel;
    public $promotionCategoryIDs;
    public $promotionCategoryDetailIDs;
    public $promotionCategoryMenuIDs;
    public $promotionDiscount;
    public $promotionPaymentMethodID;
    public $newPromotionPaymentMethodID;
    public $menuDiscountTotal;
    public $authUserName;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'promotionID'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID', 'promotionID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            [['tableID'], 'validateTable'],
            [['promotionID'], 'validatePromotion'],
            [['promotionDiscount', 'promotionPaymentMethodID', 'newPromotionPaymentMethodID', 'authUserName'], 'safe']
        ];
    }

    public function validateTable($attribute) {
        if ($this->tableID != 0) {
            $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        } else {
            $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        }
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID or sales number');
        }
    }

    public function validatePromotion($attribute) {
        // @Notes: this->promotionID = 0: remove promo
        if ($this->promotionID != 0) {
            $this->promotionModel = PromotionHead::findActiveForBill($this->salesModel->memberID, $this->salesModel->employeeCode, 0, $this->salesModel->flagExternalMemberID)
                ->andWhere([PromotionHead::tableName() . '.promotionID' => $this->promotionID])
                ->one();
            if (!$this->promotionModel) {
                $this->addError($attribute, 'Invalid promotion ID');
            }

            $grandTotal = SalesHead::getTotal($this->salesModel->salesNum,
                    'subtotal');
            if ($grandTotal < $this->promotionModel->minSalesPrice) {
                $this->errorMessage = Yii::t('app', 'Subtotal does not reach ') . number_format($this->promotionModel->minSalesPrice,
                        0, ',', '.');
                $this->addError($attribute, 'Invalid subtotal');
            }
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        $errMsg = "";
        $this->menuDiscountTotal = $this->salesModel->menuDiscountTotal;
        try {
            $linkedSalesModel = SalesHead::findLinkSalesHeads($this->salesModel->salesNum);
            $headBeforeUpdate = SalesHead::findPromotionSalesHead($this->salesModel->salesNum);
            if ($this->promotionID != 0) {
                if ($this->salesModel->promotionID != $this->promotionID) {
                    $this->removePromo($this->salesModel);
                    foreach ($linkedSalesModel as $salesModel) {
                        $this->removePromo($salesModel);
                    }

                    $headChangePromotion = SalesHead::findPromotionSalesHead($this->salesNum);
                    $eventDescription = self::getEventDescription($headBeforeUpdate, $headChangePromotion);
                    Logging::save($this->salesModel->salesNum, Logging::REMOVE_BILL_PROMO, $eventDescription);                
                }
                
                $this->promotionCategoryIDs = [];
                $this->promotionCategoryDetailIDs = [];
                $this->promotionCategoryMenuIDs = [];
                foreach ($this->promotionModel->promotionCategories as $promotionCategory) {
                    $this->promotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                    $this->promotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                    $this->promotionCategoryMenuIDs[] = $promotionCategory->menuID;
                }

                $this->applyPromo($this->salesModel, $errMsg);
                foreach ($linkedSalesModel as $salesModel) {
                    $this->applyPromo($salesModel, $errMsg);
                }
                
                $headAfterUpdate = SalesHead::findPromotionSalesHead($this->salesModel->salesNum);
                $eventDescription = self::getEventDescription($headBeforeUpdate, $headAfterUpdate);
                Logging::save($this->salesModel->salesNum, Logging::ADD_BILL_PROMO, $eventDescription);
                
                if ($this->authUserName && $this->authUserName != null) {
                    Logging::save($this->salesModel->salesNum, Logging::APPLY_PROMOTION_WITH_PIN, $this->getAttributes());
                }
                
            } else {
                $this->removePromo($this->salesModel);
                foreach ($linkedSalesModel as $salesModel) {
                    $this->removePromo($salesModel);
                }

                $headChangePromotion = SalesHead::findPromotionSalesHead($this->salesNum);
                $eventDescription = self::getEventDescription($headBeforeUpdate, $headChangePromotion);
                Logging::save($this->salesModel->salesNum, Logging::REMOVE_BILL_PROMO, $eventDescription);
            }

            if ($errMsg != "") {
                throw new Exception($errMsg);
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('promotionID', $ex->getMessage());
            return false;
        }
    }

    private function applyPromo($salesModel, &$errMsg) {
        $updatePromoPaymentMethod = false;
        if ($this->promotionPaymentMethodID != $this->newPromotionPaymentMethodID) {
            $updatePromoPaymentMethod = true;
            $this->revertPromo($salesModel);
        }

        $salesMenu['discount'] = 0;
        $salesMenu['promotionDetailID'] = 0;
        // @Notes: 3 = Discount(Rp) always apply to bill
        $applyToBill = $this->promotionModel->paymentMethodTypeID == 3 || $this->promotionModel->promotionTypeID == 10 || $this->promotionModel->paymentMethodTypeID == 12 || count($this->promotionCategoryIDs) == 0 || 
            count($this->promotionCategoryDetailIDs) == 0 || count($this->promotionCategoryMenuIDs) == 0;

        $applyToBill = $this->promotionModel->promotionTypeID == 9 ? false : $applyToBill;

        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($salesModel->visitPurposeID);
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();

        //open bill discount % include special price
        if($this->promotionModel->promotionTypeID == 11){
            $issetSpecialPrice = false;
        } else {
            $salesMenuSpecialPrice = SalesMenu::findActive()
                ->where(['salesNum' => $salesModel->salesNum])
                ->andWhere('originalPrice <> price')
                ->andWhere('statusID <> 19')
                ->one();
            $issetSpecialPrice = $salesMenuSpecialPrice ? true : false;
        }
        
        $isSubs = false;
        if($issetSpecialPrice != true){
            $salesMenuSubs = SalesMenu::findActive()
                ->where(['salesNum' => $salesModel->salesNum])
                ->andWhere(['!=','menuPromotionID', 0])
                ->one();
            $issetSpecialPrice = $salesMenuSubs ? true : false;
            $isSubs = $salesMenuSubs ? true : false;
        }
        $applyToBill = 
        $issetSpecialPrice 
        && $this->promotionModel->promotionTypeID !== 3 
        && $this->promotionModel->promotionTypeID !== 10
        && $this->promotionModel->promotionTypeID !== 12  
        && $this->promotionModel->promotionTypeID !== 14 
        && $this->promotionModel->promotionTypeID !== 15 
        && $this->promotionModel->promotionTypeID !== 16 ? false : $applyToBill;

        if ($updatePromoPaymentMethod) {
            $tempSalesMenuModel = SalesMenu::findActive()
                ->joinWith('menu.menuCategoryDetail')
                ->joinWith('promotion')
                ->andWhere(['salesNum' => $salesModel->salesNum])
                ->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = tr_salesmenu.ID'
                ])
                ->andWhere(['menuPromotionID' => 0])
                ->andWhere(['OR',
                    ['promotionDetailID' => 0],
                    ['promotionDetailID' => $salesModel->promotionID],
                ])
                ->all();

            $salesMenuIDs = [];
            foreach ($tempSalesMenuModel as $tempSalesMenu) {                
                if ($tempSalesMenu->promotion) {
                    $promotionPaymentMethodID = (int) $this->promotionModel->paymentMethodID;
                    $promotionDetailPaymentMethodID = (int) $tempSalesMenu->promotion->paymentMethodID;
                    if ($promotionPaymentMethodID != $promotionDetailPaymentMethodID) {
                        $salesMenuIDs[] = $tempSalesMenu->ID;
                    }
                }
            }

            if ($salesMenuIDs) {
                SalesMenu::updateAll(['promotionDetailID' => 0, 'discount' => 0, 'discountValue' => 0], ['IN', 'ID', $salesMenuIDs]);
            }

            $tempNonApplyPromoSalesMenuModel = SalesMenu::findActive()
                ->joinWith('menu.menuCategoryDetail')
                ->joinWith('promotion')
                ->andWhere(['salesNum' => $salesModel->salesNum])
                ->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = tr_salesmenu.ID'
                ])
                ->andWhere(['<>', 'promotionDetailID', 0])
                ->andWhere(['<>', 'promotionDetailID', $salesModel->promotionID])
                ->all();

            $salesMenuIDs = [];
            if ($tempNonApplyPromoSalesMenuModel) {
                foreach ($tempNonApplyPromoSalesMenuModel as $tempsalesMenu) {
                    if ($tempsalesMenu->promotion) {
                        $promotionPaymentMethodID = (int) $this->promotionModel->paymentMethodID;
                        $promotionDetailPaymentMethodID = (int) $tempsalesMenu->promotion->paymentMethodID;
                        if ($promotionPaymentMethodID != $promotionDetailPaymentMethodID) {
                            $salesMenuIDs[] = $tempsalesMenu->ID;
                        }
                    }
                }
            }

            if ($salesMenuIDs) {
                SalesMenu::updateAll(['promotionDetailID' => 0, 'discount' => 0, 'discountValue' => 0], ['IN', 'ID', $salesMenuIDs]);
            }
        }

        $salesMenusModel = SalesMenu::findActive()
            ->joinWith('menu.menuCategoryDetail')
            ->andWhere(['salesNum' => $salesModel->salesNum])
            ->andWhere(['OR',
                ['menuRefID' => 0],
                'menuRefID = tr_salesmenu.ID'
            ])
            ->andWhere(['menuPromotionID' => 0])
            ->andWhere(['OR',
                ['promotionDetailID' => 0],
                ['promotionDetailID' => $salesModel->promotionID],
            ])
            ->all();

        $newSalesMenus = [];
        foreach ($salesMenusModel as $salesMenu) {
            foreach ($salesMenu as $key => $value) {
                $newSalesMenu[$key] = $value;
            }
            $newSalesMenu['menuCategoryID'] = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
            $newSalesMenu['menuCategoryDetailID'] = $salesMenu->menu->menuCategoryDetail->ID;            
            $newSalesMenu['flagLuxuryItem'] = $salesMenu->menu && $salesMenu->menu->flagLuxuryItem ? $salesMenu->menu->flagLuxuryItem : 0;
            $newSalesMenu['packages'] = $salesMenu->childSalesMenus;
            $newSalesMenu['extras'] = $salesMenu->salesExtras;
            
            SalesHead::applyPromotion($newSalesMenu, $applyToBill,
                $newSalesMenu['menuCategoryID'], $newSalesMenu['menuCategoryDetailID'], 
                $newSalesMenu['menuID'], $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, 
                $this->promotionCategoryMenuIDs, $this->promotionModel, $this->salesModel->visitPurposeID);


            $newSalesMenus[] = $newSalesMenu;
        }

        $nonApplyPromoSalesMenuModel = SalesMenu::findActive()
            ->joinWith('menu.menuCategoryDetail')
            ->andWhere(['salesNum' => $salesModel->salesNum])
            ->andWhere(['OR',
                ['menuRefID' => 0],
                'menuRefID = tr_salesmenu.ID'
            ])
            //->andWhere(['menuPromotionID' => 0])
            ->andWhere(['<>', 'promotionDetailID', 0])
            ->andWhere(['<>', 'promotionDetailID', $salesModel->promotionID])
            ->all();

        if ($nonApplyPromoSalesMenuModel) {
            foreach ($nonApplyPromoSalesMenuModel as $salesMenu) {
                $flagIsSubs = false;
                foreach ($salesMenu as $key => $value) {
                    $newSalesMenu[$key] = $value;
                }
                $newSalesMenu['menuCategoryID'] = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
                $newSalesMenu['menuCategoryDetailID'] = $salesMenu->menu->menuCategoryDetail->ID;
                $newSalesMenu['flagLuxuryItem'] = $salesMenu->menu && $salesMenu->menu->flagLuxuryItem ? $salesMenu->menu->flagLuxuryItem : 0;
                $newSalesMenu['packages'] = $salesMenu->childSalesMenus;
                $newSalesMenu['extras'] = $salesMenu->salesExtras;
                
                if ($newSalesMenu['menuPromotionID'] != 0) {
                    $flagIsSubs = true;
                } 
                if ($newSalesMenu['packages']) {
                    foreach ($newSalesMenu['packages'] as $package) {
                        if ($package['menuPromotionID'] != 0) {
                            $flagIsSubs = true;
                            break;
                        } 
                    }
                }
                
                if ($salesMenu['promotionDetailID'] == $this->promotionModel->promotionID && $flagIsSubs == false) {
                    SalesHead::applyPromotion($newSalesMenu, $applyToBill,
                        $newSalesMenu['menuCategoryID'], $newSalesMenu['menuCategoryDetailID'], 
                        $newSalesMenu['menuID'], $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, 
                        $this->promotionCategoryMenuIDs, $this->promotionModel, $this->salesModel->visitPurposeID);
                }

                $newSalesMenus[] = $newSalesMenu;
            }
        }

        $salesModel->promotionDiscount = 0;
        $salesModel->discountTotal = 0;
        if($this->promotionModel->promotionTypeID == 11 || $this->promotionModel->promotionTypeID == 12 || $this->promotionModel->promotionTypeID == 14 || $this->promotionModel->promotionTypeID == 15 || $this->promotionModel->promotionTypeID == 16){
            $newSalesModel = [
                'salesNum' => $salesModel->salesNum,
                'promotionID' => $this->promotionModel->promotionID,
                'promotionDiscount' => $this->promotionDiscount,
                'salesMenu' => $newSalesMenus,
                'visitPurposeID' => $salesModel->visitPurposeID
            ];
        } else {
            $newSalesModel = [
                'salesNum' => $salesModel->salesNum,
                'promotionID' => $this->promotionModel->promotionID,
                'promotionDiscount' => $this->promotionModel->promotionTypeID == 9 ? 0 : $this->promotionModel->discount,
                'salesMenu' => $newSalesMenus,
                'visitPurposeID' => $salesModel->visitPurposeID
            ];
        }
        
        $newErrMsg = "";
        $this->updatePromo($newSalesModel, $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, 
            $this->promotionCategoryMenuIDs, $applyToBill, $newErrMsg);
        $errMsg = $newErrMsg;
    }

    private function removePromo($salesModel) {
        $salesMenusModel = SalesMenu::findActive()
            ->with('menu')
            ->andWhere(['salesNum' => $salesModel->salesNum])
            ->andWhere(['menuPromotionID' => 0])
            ->andWhere(['OR',
                ['menuRefID' => 0],
                'menuRefID = ID'
            ])
            ->andWhere(['OR',
                ['promotionDetailID' => 0],
                ['promotionDetailID' => $salesModel->promotionID],
            ])
            ->all();
        
        $newSalesMenus = [];
        foreach ($salesMenusModel as $salesMenu) {
            foreach ($salesMenu as $key => $value) {
                $newSalesMenu[$key] = $value;
            }
            $newSalesMenu['menuFlagTax'] = $salesMenu->menu ? $salesMenu->menu->flagTax : 0;
            $newSalesMenu['flagLuxuryItem'] = $salesMenu->menu && $salesMenu->menu->flagLuxuryItem ? $salesMenu->menu->flagLuxuryItem : 0;
            $newSalesMenu['packages'] = $salesMenu->childSalesMenus;
            $newSalesMenu['extras'] = $salesMenu->salesExtras;
            $this->salesModel->removePromotion($newSalesMenu, $this->salesModel->visitPurposeID);
            $newSalesMenus[] = $newSalesMenu;
        }

        $nonApplyPromoSalesMenuModel = SalesMenu::findActive()
            ->andWhere(['salesNum' => $salesModel->salesNum])
            ->andWhere(['OR',
                ['menuRefID' => 0],
                'menuRefID = ID'
            ])
            //->andWhere(['menuPromotionID' => 0])
            ->andWhere(['<>', 'promotionDetailID', 0])
            ->andWhere(['<>', 'promotionDetailID', $salesModel->promotionID])
            ->all();

        if ($nonApplyPromoSalesMenuModel) {
            foreach ($nonApplyPromoSalesMenuModel as $salesMenu) {
                foreach ($salesMenu as $key => $value) {
                    $newSalesMenu[$key] = $value;
                }
                $newSalesMenu['menuFlagTax'] = $salesMenu->menu ? $salesMenu->menu->flagTax : 0;
                $newSalesMenu['flagLuxuryItem'] = $salesMenu->menu && $salesMenu->menu->flagLuxuryItem ? $salesMenu->menu->flagLuxuryItem : 0;
                $newSalesMenu['packages'] = $salesMenu->childSalesMenus;
                $newSalesMenu['extras'] = $salesMenu->salesExtras;
                $newSalesMenus[] = $newSalesMenu;
            }
        }

        $salesModel->discountTotal = 0;
        $salesModel->menuDiscountTotal = 0;
        $salesModel->promotionDiscount = 0;
        $salesModel->promotionID = 0;
        $newSalesModel = [
            'salesNum' => $salesModel->salesNum,
            'promotionID' => $salesModel->promotionID,
            'promotionDiscount' => 0,
            'salesMenu' => $newSalesMenus,
            'visitPurposeID' => $salesModel->visitPurposeID
        ];

        $this->updatePromo($newSalesModel, $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, 
            $this->promotionCategoryMenuIDs);
    }

    private function updatePromo($newSalesModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $applyToBill = false, &$newErrMsg = "") {
        $salesModel = SalesHead::find()
            ->where(['salesNum' => $newSalesModel['salesNum']])
            ->one();

        $updatePromotionHead = false;
        if ($salesModel->promotionID != $newSalesModel['promotionID']) {
            $updatePromotionHead = true;
        }
        
        $salesModel->promotionID = $newSalesModel['promotionID'];
        $salesModel->promotionDiscount = $newSalesModel['promotionDiscount'];
        
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($salesModel->visitPurposeID);
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();
        $promotionArrModel = PromotionHead::findActiveArrayValue();
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $this->salesModel->visitPurposeID])->one();
        $menuTemplateID = 0;
        $otherTaxOnVat = 0;
        $taxCalculation = [];
        $vatSubject = 0;
        if ($mapBranchModel) {
            $menuTemplateID = $mapBranchModel->menuTemplateID;
            $otherTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatValue = $mapBranchModel->taxValue;
            $vatSubject = $mapBranchModel->vatSubject;
            $taxCalculation['otherTax'] = $otherTaxValue;
            $taxCalculation['vat'] = $vatValue;
            $taxCalculation['otherTaxOnVat'] = $otherTaxOnVat;
            $taxCalculation['salesDecimalSetting'] = $salesDecimalSetting;
            $taxCalculation['settingDecimalMode'] = $settingDecimalMode;
        }

        $taxCalculationType = Branch::getPosTaxCalculationType($salesModel->branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($salesModel->branchID);
        $promotionModel = null;
        $sumMenuDiscount = 0;
        $sumMenuSubtotal = 0;
        $tempMenuGrandTotal = 0;
        $tempGrandTotal = 0;
        $applyBillDiscountToPackageContent = 0;
        $applyBillDiscountToExtra = 0;
        $promotionHeadTypeID = 0;
        $promotionHeadModel = PromotionHead::findOne($salesModel->promotionID);
        $promotionPaymentMethodIDs = [];
        if ($promotionHeadModel) {
            $promotionHeadTypeID = $promotionHeadModel->promotionTypeID;
            if ($promotionHeadTypeID == 10) {
                $applyBillDiscountToPackageContent = $promotionHeadModel->flagPackageContent;
                $applyBillDiscountToExtra = $promotionHeadModel->flagMenuExtra;
            } else {
                if ($applyToBill) {
                    if (in_array($promotionHeadTypeID, [1, 5, 10])) {
                        $applyBillDiscountToPackageContent = $promotionHeadModel->flagPackageContent;
                        $applyBillDiscountToExtra = $promotionHeadModel->flagMenuExtra;
                    } else {
                        $applyBillDiscountToPackageContent = 1;
                        $applyBillDiscountToExtra = 1;
                    }
                } else {                
                    $applyBillDiscountToPackageContent = $promotionHeadModel->flagPackageContent;
                    $applyBillDiscountToExtra = $promotionHeadModel->flagMenuExtra;
                }
            }

            if ($promotionHeadModel->paymentMethodID) {
                $promotionPaymentMethodIDs[] = $promotionHeadModel->paymentMethodID;
            }
        }

        if ($inclusiveMenuTemplateID) {
            $otherTaxCalculationType = $taxCalculationType;
        }

        if ($inclusiveMenuTemplateID) {
            if ($otherTaxCalculationType == 1 && $taxCalculationType == 1) {
                $calculationMode = SalesHead::INCLUSIVE_BEFORE_DISCOUNT;
            } else {
                $calculationMode = SalesHead::INCLUSIVE_AFTER_DISCOUNT;
            }
        } else {
            if ($taxCalculationType == 1) {
                $calculationMode = SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT;
            } else {
                $calculationMode = SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT;
            }
        }

        $taxInclusiveAfterDiscount = false;
        if ($inclusiveMenuTemplateID) {
            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                $taxInclusiveAfterDiscount = true;
            }
        }

        $otherTaxTotal = 0;
        $vatTotal = 0;
        $otherVatTotal = 0;
        $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID);

        // @Notes: Check if membership type member.id is currently active
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $externalMember = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $isMemberID = ($externalMember == 1 && $membershipType == 'memberid' && $salesModel->flagExternalMemberID) ? TRUE : FALSE;
        $isStamps = ($externalMember == 1 && $membershipType == 'stamps' && $salesModel->flagExternalMemberID) ? TRUE : FALSE;
        $allMenuSubtotal = 0;
        $platformFeeIncludeOtherTax = 0;
        $totalPlatformFee = 0;
        $sumSubtotalPlatformFee = 0;
        $allMenuDiscountTotal = 0;

        $platformFeeList = SalesHead::getSalesPlatformFee($salesModel->salesNum);
        if ($platformFeeList) {
            foreach ($platformFeeList as $row) {
                if (isset($row['platformFeeTypeID']) && $row['platformFeeTypeID'] == 2) {
                    $platformFeeIncludeOtherTax += $row['amount'];
                }
            }
        }

        foreach ($newSalesModel['salesMenu'] as $salesMenu) {
            $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] == 2));
            $detailPromotionTypeID = 0;
            $tempMenuID = 0;
            $subsID = isset($salesMenu['subsID']) ? $salesMenu['subsID'] : 0 ;
            if ($subsID != 0) {
                $tempMenuID = $subsID;
            }
            else{
                $menuPromotionID = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] : 0;
                $tempMenuID = $salesMenu['menuID'];
                if($menuPromotionID != 0 && ($salesMenu['statusID'] != 1 || $salesMenu['statusID'] != 12)){
                    $tempMenuID = $menuPromotionID;
                }
            }
            if (isset($salesMenu['promotionDetailID'])) {
                if (in_array($salesMenu['statusID'], [13, 34, 14])) {
                    $promotionModel = PromotionHead::find()
                        ->where(['promotionID' => $salesMenu['promotionDetailID']])
                        ->one();
                } else {
                    $promotionModel = PromotionHead::findActiveForMenu($tempMenuID,
                        $salesModel->memberID, $salesModel->employeeCode, null, $salesModel->flagExternalMemberID)
                    ->andWhere([PromotionHead::tableName() . '.promotionID' => $salesMenu['promotionDetailID']])
                    ->one();
                }

                if ($promotionModel) {
                    if ($salesModel->memberID == 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '') && (!$isMemberID && !$isStamps)) {
                        if (in_array($promotionModel->promotionMemberTypeID, [1, 2, 3])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID == 0 && ($salesModel->employeeCode != null && $salesModel->employeeCode != '')) {
                        if (in_array($promotionModel->promotionMemberTypeID, [3])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID != 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '')) {
                        if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID == 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '') && ($isMemberID || $isStamps)) {
                        if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    }

                    if ($promotionModel && $promotionModel->paymentMethodID) {
                        $promotionPaymentMethodIDs[] = $promotionModel->paymentMethodID;
                    }
                }
                
                if ($promotionModel) {
                    $detailPromotionTypeID = $promotionModel->promotionTypeID;
                } else {
                    $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                    if (isset($salesMenu['flagLuxuryItem'])) {
                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenu['otherVat']) : $salesMenu['vat'];
                    }
                    
                    if ($salesMenu['price'] == 0) {
                        $specialMenuPrice = null;
                        if (array_key_exists($salesMenu['menuID'],
                                $specialPriceArrModel)) {
                            $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                        }

                        if ($specialMenuPrice) {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $specialMenuPrice;
                                $salesMenu['price'] = UpdateOrder::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat,
                                    $salesDecimalSetting, $settingDecimalMode, $specialMenuPrice);
                            } else {
                                $salesMenu['price'] = $specialMenuPrice;
                            }
                        } else {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $menuTemplateDetailModel[$salesMenu['menuID']]->price;
                            }
                            $salesMenu['price'] = $salesMenu['originalPrice'];
                        }

                    }
                }
            }

            $salesMenuModel = SalesMenu::find()
                        ->with('menu')
                        ->andWhere(['ID' => $salesMenu['ID']])
                        ->one();

            $salesMenuModel->load(['SalesMenu' => $salesMenu]);
            $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
            
            $salesMenuFlagTax = $salesMenuModel->menu->flagTax;
            $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenuFlagTax) && $salesMenuFlagTax == 2));

            if ($detailPromotionTypeID == 9) {
                if ($promotionModel->discount > $salesMenuModel->price) {
                    $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                } else {
                    $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                }
            }

            $applyDiscountBill = false;
            if ($promotionHeadModel) {
                $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
            }

            $sumMenuDiscount += $salesMenuModel->discountValue;

            if ($applyDiscountBill) {
                if ($salesModel->promotionID != $salesMenuModel->promotionDetailID) {
                    if ($calculationMode !== SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT) {
                        $sumMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue;
                    } else {
                        $sumMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price;
                    }
                } else {
                    $sumMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price;
                }
            }

            $allMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price;
            $allMenuDiscountTotal += $salesMenuModel->discountValue;

            if ($inclusiveMenuTemplateID) {
                $specialMenuPrice = null;
                if (array_key_exists($salesMenu['menuID'],
                        $specialPriceArrModel)) {
                    $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                }

                if ($salesMenuModel->price == 0 && $salesMenuModel->promotionDetailID > 0) {
                    $inclusivePrice = 0;
                } else {
                    if ($specialMenuPrice) {
                        $inclusivePrice = $specialMenuPrice;
                    } else {
                        $inclusivePrice = $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                    }
                }

                $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                if ($salesTypeEzo) {
                    $inclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $salesMenu['price'];
                }

                //$inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                if (strlen($salesMenuModel->customMenuName) > 0) {
                    $inclusivePrice = $salesMenuModel->inclusivePrice;
                }

                if ($salesMenuModel->promotionDetailID > 0) {
                    if ($promotionModel) {
                        if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                            $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                            $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                        } else {
                            $detailPromotionTypeID = $promotionModel->promotionTypeID;
                            $detailPromotionDiscount = $promotionModel->discount;
                        }
                    }
                    if ($detailPromotionTypeID == 9) {
                        $menuDiscountVal = 0;
                    } else {
                        $menuDiscountVal = $detailPromotionDiscount;
                    }
                } else {
                    $menuDiscountVal = 0;
                }

                if($detailPromotionTypeID == 7) {
                    $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                }

                $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;

                $newDiscountVal = $menuDiscountVal;
                $salesMenuModel->discount = $newDiscountVal;
                $salesMenuModel->discountValue = $menuGrandTotal / 100 * $salesMenuModel->discount;
                $salesMenuModel->inclusiveDiscountValue = $salesMenuModel->discountValue;
                if ($detailPromotionTypeID == 9) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $netPrice = UpdateOrder::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $salesMenuModel->vat,
                        $salesDecimalSetting, $settingDecimalMode, $inclusivePrice);
                        if ($inclusivePrice > 0) {
                            $tempPromotionDiscount = $netPrice / $inclusivePrice * $promotionModel->discount;
                        } else {
                            $tempPromotionDiscount = 0;
                        }
                        
                        if ($tempPromotionDiscount > $netPrice) {
                            $salesMenuModel->discountValue = $netPrice * $salesMenuModel->qty;
                            $salesMenuModel->inclusiveDiscountValue = $inclusivePrice * $salesMenuModel->qty;                            
                            $discountValue = $salesMenuModel->inclusiveDiscountValue;
                        } else {
                            if ($inclusivePrice > 0) {
                                $percentageDiscountValue = $promotionModel->discount / $inclusivePrice * 100;
                                $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                $salesMenuModel->discountValue = $tempDiscountValue * $salesMenuModel->qty;
                                $discountValue = $salesMenuModel->qty * $promotionModel->discount;
                                $salesMenuModel->inclusiveDiscountValue = $discountValue;
                            } else {
                                $salesMenuModel->discountValue = 0;
                                $discountValue = 0;
                                $salesMenuModel->inclusiveDiscountValue = $discountValue;
                            }                            
                        }
                    } else {
                        if ($promotionModel->discount > $inclusivePrice) {
                            $salesMenuModel->inclusiveDiscountValue = $inclusivePrice * $salesMenuModel->qty;
                        } else {
                            $salesMenuModel->inclusiveDiscountValue = $promotionModel->discount * $salesMenuModel->qty;                       
                        }
                    }
                } else if ($detailPromotionTypeID == 1) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $salesMenuModel->discountValue = $menuSubtotal * $promotionModel->discount / 100;
                        $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $promotionModel->discount / 100;
                        $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                    }                              
                }

                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                        if ($applyDiscountBill) {
                            $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue;
                        }
                    } else {
                        $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue;
                    }
                } else {
                    if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                        if ($applyDiscountBill) {
                            $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice;
                            $tempGrandTotal = $tempMenuGrandTotal;
                        }
                    } else {
                        if ($applyDiscountBill) {
                            $tempGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice;
                        }
                    }
                }

                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $salesMenuModel->otherTaxValue = (float) ($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue) / 100 * $salesMenuModel->otherTax;                            
                    if ($salesMenuModel->otherTaxOnVat == 0) {                          
                        $salesMenuModel->vatValue = (float) ($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue) / 100 * $salesMenuModel->vat;
                        $salesMenuModel->otherVatValue = (float) ($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue) / 100 * $salesMenuModel->otherVat;                                
                    } else {                            
                        $salesMenuModel->vatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;
                        $salesMenuModel->otherVatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->otherVat;                                
                    }

                    if ($promotionHeadTypeID == 10) {
                        if ($applyDiscountBill) {
                            $otherTaxTotal += $salesMenuModel->otherTaxValue;
                            $vatTotal += $salesMenuModel->vatValue;
                            $otherVatTotal += $salesMenuModel->otherVatValue;
                        }
                    } else {
                        $otherTaxTotal += $salesMenuModel->otherTaxValue;
                        $vatTotal += $salesMenuModel->vatValue;
                        $otherVatTotal += $salesMenuModel->otherVatValue;
                    }
                }
            }

            if (isset($salesMenu['packages'])) {
                foreach ($salesMenu['packages'] as $package) {
                    $salesMenuPackageModel = SalesMenu::find()
                        ->with('menu')
                        ->andWhere(['ID' => $package['ID']])
                        ->one();

                    $salesMenuPackageFlagTax = isset($salesMenuModel->menu->flagSeparateTaxCalculation) && $salesMenuModel->menu->flagSeparateTaxCalculation === 0 ? $salesMenuModel->menu->flagTax : $salesMenuPackageModel->menu->flagTax;
                    $isApplyPckOtherVat = ($vatSubject === 1 && (isset($salesMenuPackageFlagTax) && $salesMenuPackageFlagTax == 2));

                    $tempMenuID = 0;
                    $subsID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0 ;
                    if ($subsID != 0) {
                        $tempMenuID = $subsID;
                    }
                    else{
                        $menuPromotionID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0;
                        $tempMenuID = $salesMenuPackageModel['menuID'];
                        if($menuPromotionID != 0 && ($salesMenuPackageModel['statusID'] != 1 || $salesMenuPackageModel['statusID'] != 12)){
                            $tempMenuID = $menuPromotionID;
                        }
                    }

                    // @Notes: untuk inclusive
                    $menuPackageModel = MenuPackage::find()
                        ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                            $query->andOnCondition([
                                MapMenuTemplatePackage::tableName() . '.menuTemplateID' => $menuTemplateID
                            ]);
                        }])
                        ->where([
                            'ms_menupackage.menuID' => $tempMenuID,
                            'ms_menupackage.menuGroupID' => $salesMenuPackageModel->menuGroupID
                        ])
                    ->one();
                    
                    $applyPackagePrice = $menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price;
                    $salesTypeEzo = $this->checkSalesTypeEzo($package['salesType']);
                    if ($salesTypeEzo) {
                        $applyPackagePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $package['price'];
                    }
                    $salesMenuPackageModel->load(['SalesMenu' => $package]);
                    // @Notes: Remove promo
                    $currentPromotionID = $salesMenuModel->promotionDetailID;
                    if ($currentPromotionID != $salesMenuPackageModel->promotionDetailID) {
                        $this->removeMenuPromo($salesMenuPackageModel,
                                    $currentPromotionID);
                    }

                    $discountBill = 0;
                    if ($promotionModel) {
                        if ($promotionModel->flagPackageContent == 1) {
                            // @Notes: Apply promo
                            if ($salesMenuModel->promotionDetailID != 0) {
                                $this->applyMenuPromo($salesMenuPackageModel,
                                    $salesMenuModel->promotionDetailID);
                            }

                            if ($salesMenuPackageModel->promotionDetailID != 0) {
                                if ($detailPromotionTypeID == 4) {
                                    $salesMenuPackageModel->discount = 0;
                                    $salesMenuPackageModel->price = 0;
                                }
                                
                                if ($detailPromotionTypeID == 9) {
                                    $salesMenuPackageModel->discount = 0;
                                    $salesMenuPackageModel->promotionDetailID = $salesMenuModel->promotionDetailID;
                                    if ($inclusiveMenuTemplateID) {
                                        if ($promotionModel->discount > $applyPackagePrice) {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $applyPackagePrice;
                                            $salesMenuPackageModel->inclusiveDiscountValue = $salesMenuPackageModel->discountValue;
                                        } else {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                            $salesMenuPackageModel->inclusiveDiscountValue = $salesMenuPackageModel->discountValue;
                                        }
                                    } else {
                                        if ($promotionModel->discount > $salesMenuPackageModel->price) {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price;
                                        } else {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                        }
                                    }
                                } else {
                                    if ($inclusiveMenuTemplateID) {
                                        $menuPackageSubtotal = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                        $menuPackageTotal = $applyPackagePrice * $salesMenuPackageModel->qty;
                                        $newPackageDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuPackageSubtotal,
                                                $menuPackageTotal, $promotionModel->discount);
                                        $salesMenuPackageModel->discount = $newPackageDiscountVal;
                                        if ($detailPromotionTypeID == 1) {
                                            $salesMenuPackageModel->discountValue = (float) $menuPackageTotal * $promotionModel->discount / 100;
                                        } else {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                        }
                                    } else {
                                        $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                    }
                                }
                            } else {
                                $salesMenuPackageModel->discountValue = 0;
                            }                            
                        } else {
                            $salesMenuPackageModel->discountValue = 0;
                        }
                    } else {
                        $salesMenuPackageModel->promotionDetailID = 0;
                        $salesMenuPackageModel->discountValue = 0;
                    }

                    $sumMenuDiscount += $salesMenuModel->qty * $salesMenuPackageModel->discountValue;

                    if ($applyDiscountBill) {
                        if ($applyBillDiscountToPackageContent) {
                            if ($promotionHeadTypeID == 10) {
                                $applyDiscount = false;
                                if ($promotionHeadModel) {
                                    $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                }

                                if ($applyDiscount) {
                                    if ($calculationMode == SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT) {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                                    } else {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue);
                                    }
                                }
                            } else {
                                if ($salesModel->promotionID == $salesMenuModel->promotionDetailID) {
                                    $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                                } else {
                                    if ($calculationMode == SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT) {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                                    } else {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue);
                                    }
                                }
                            }
                        }
                    }

                    $allMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                    $allMenuDiscountTotal += $salesMenuModel->qty * $salesMenuPackageModel->discountValue;

                    if ($inclusiveMenuTemplateID) {
                        // @Notes: untuk inclusive
                        $menuPackageModel = MenuPackage::find()
                            ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                                $query->andOnCondition([
                                    MapMenuTemplatePackage::tableName() . '.menuTemplateID' => $menuTemplateID
                                ]);
                            }])
                            ->where([
                                'ms_menupackage.menuID' => $tempMenuID,
                                'ms_menupackage.menuGroupID' => $salesMenuPackageModel->menuGroupID
                            ])
                        ->one();

                        $applyPackagePrice = $menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price;
                        $currentPromotionID = $salesMenuModel->promotionDetailID;
                        $salesMenuPackageModel->load(['SalesMenu' => $package]);
                        if ($promotionModel) {
                            if ($promotionModel->flagPackageContent == 1) {
                                if ($salesMenuPackageModel->promotionDetailID != 0) {
                                    if ($detailPromotionTypeID == 4) {
                                        $salesMenuPackageModel->discount = 0;
                                        $salesMenuPackageModel->price = 0;
                                        $salesMenuPackageModel->inclusivePrice = 0;
                                    }
                                    
                                    if ($detailPromotionTypeID == 9) {
                                        $salesMenuPackageModel->discount = 0;
                                        $salesMenuPackageModel->promotionDetailID = $salesMenuModel->promotionDetailID;
                                        if ($inclusiveMenuTemplateID) {
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                if ($applyPackagePrice > 0) {
                                                    $tempPromotionDiscount = $salesMenuPackageModel->price / $applyPackagePrice * $promotionModel->discount;
                                                } else {
                                                    $tempPromotionDiscount = 0;
                                                }
        
                                                if ($tempPromotionDiscount > $salesMenuPackageModel->price) {
                                                    $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price;
                                                    $salesMenuPackageModel->inclusiveDiscountValue = (float) $salesMenuPackageModel->qty * $applyPackagePrice;                                            
                                                    $discountValue = (float) $salesMenuPackageModel->inclusiveDiscountValue;
                                                } else {
                                                    if ($applyPackagePrice > 0) {
                                                        $percentageDiscountValue = $promotionModel->discount / $applyPackagePrice * 100;
                                                        $tempDiscountValue = $salesMenuPackageModel->price * $percentageDiscountValue / 100;
                                                        $salesMenuPackageModel->discountValue = (float) $package['qty'] * $tempDiscountValue;
                                                        $discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                                        $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;
                                                    } else {
                                                        $salesMenuPackageModel->discountValue = 0;
                                                        $discountValue = 0;
                                                        $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        if ($inclusiveMenuTemplateID) {
                                            $menuPackageSubtotal = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                            $menuPackageTotal = $applyPackagePrice * $salesMenuPackageModel->qty;
    
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                $salesMenuPackageModel->discount = $promotionModel->discount;
                                                if ($detailPromotionTypeID == 1) {
                                                    $salesMenuPackageModel->discountValue = (float) $menuPackageSubtotal * $promotionModel->discount / 100;
                                                    $salesMenuPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                    $discountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                } else {
                                                    $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                                    $discountValue = $salesMenuPackageModel->discountValue;
                                                }
                                            } else {
                                                if ($detailPromotionTypeID == 1) {
                                                    $salesMenuPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                }
                                            }                                         
                                        }
                                    }
                                } else {
                                    $salesMenuPackageModel->discountValue = 0;
                                    $salesMenuPackageModel->inclusiveDiscountValue = 0;
                                }                                
                            } else {
                                $salesMenuPackageModel->discountValue = 0;
                                $salesMenuPackageModel->inclusiveDiscountValue = 0;
                            }
                        } else {
                            $salesMenuPackageModel->discountValue = 0;
                            $salesMenuPackageModel->inclusiveDiscountValue = 0;
                        }

                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $applyDiscount = false;
                                        if ($promotionHeadModel) {
                                            $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        }
        
                                        if ($applyDiscount) {
                                            $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue);
                                        }
                                    }
                                }
                            } else if ($promotionHeadTypeID == 11) {
                                if ($applyDiscountBill) {
                                    $applyDiscount = false;
                                    if ($promotionHeadModel) {
                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                    }
    
                                    if ($applyDiscount) {
                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue);
                                    }
                                }
                            } else {
                                if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue);
                                }
                            }
                        } else {
                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $applyDiscount = false;
                                        if ($promotionHeadModel) {
                                            $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        }
        
                                        if ($applyDiscount) {
                                            $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
                                            $tempGrandTotal = $tempMenuGrandTotal;
                                        }
                                    }
                                }
                            } else if ($promotionHeadTypeID == 11) {
                                if ($applyDiscountBill) {
                                    $applyDiscount = false;
                                    if ($promotionHeadModel) {
                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                    }
    
                                    if ($applyDiscount) {
                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
                                        $tempGrandTotal = $tempMenuGrandTotal;
                                    }
                                }
                            } else {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $applyDiscount = false;
                                        if ($promotionHeadModel) {
                                            $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        }
        
                                        if ($applyDiscount) {
                                            $tempGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
                                        }
                                    }
                                }
                            }
                        }

                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuPackageModel->otherTaxValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue) / 100 * $salesMenuPackageModel->otherTax;
                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                $salesMenuPackageModel->vatValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue) / 100 * $salesMenuPackageModel->vat;
                                $salesMenuPackageModel->otherVatValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue) / 100 * $salesMenuPackageModel->otherVat;
                            } else {
                                $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                                $salesMenuPackageModel->otherVatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->otherVat;
                            }

                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $applyDiscount = false;
                                        if ($promotionHeadModel) {
                                            $applyDiscount = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $package, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        }
        
                                        if ($applyDiscount) {
                                            $otherTaxTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherTaxValue;
                                            $vatTotal += $salesMenuModel->qty * $salesMenuPackageModel->vatValue;
                                            $otherVatTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherVatValue;
                                        }
                                    }
                                }
                            } else {
                                $otherTaxTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherTaxValue;
                                $vatTotal += $salesMenuModel->qty * $salesMenuPackageModel->vatValue;
                                $otherVatTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherVatValue;
                            }
                        }
                    }
                }
            }

            if ($salesMenu['extras']) {
                foreach ($salesMenu['extras'] as $extra) {
                    $salesMenuExtraModel = SalesMenuExtra::find()
                        ->andWhere([
                            'ID' => $extra['localID'],
                            'salesNum' => $extra['salesNum']
                        ])
                        ->one();

                    $menuExtraModel = MenuExtra::find()
                            ->where(['menuExtraID' => $salesMenuExtraModel->menuExtraID])
                            ->one();                                    
                            
                    $discountBill = 0;
                    if ($salesMenuModel->promotionDetailID != 0) {
                        if ($promotionModel->flagMenuExtra == 1) {
                            $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                            $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $menuExtraModel->price;
                            $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                            $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;                                   
                            if ($detailPromotionTypeID == 9) {
                                if ($inclusiveMenuTemplateID) {
                                    if ($promotionModel->discount > $menuExtraModel->price) {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $menuExtraModel->price;
                                        $salesMenuExtraModel->inclusiveDiscountValue = $salesMenuExtraModel->discountValue;
                                    } else {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                        $salesMenuExtraModel->inclusiveDiscountValue = $salesMenuExtraModel->discountValue;
                                    }
                                } else {
                                    if ($promotionModel->discount > $salesMenuExtraModel->price) {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price;
                                    } else {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                    }
                                }
                            } else {
                                if ($inclusiveMenuTemplateID) {
                                    $menuExtraSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                    $menuExtraTotal = $menuExtraModel->price * $salesMenuExtraModel->qty;
                                    $newExtraDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuExtraSubtotal,
                                            $menuExtraTotal, $promotionModel->discount);
                                    $salesMenuExtraModel->discount = $newExtraDiscountVal;
                                    if ($detailPromotionTypeID == 1) {
                                        $salesMenuExtraModel->discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                    } else {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                    }
                                } else {
                                    $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                }
                            }
                        } else {
                            $salesMenuExtraModel->discountValue = 0;
                        }
                    } else {
                        $salesMenuExtraModel->discount = 0;
                        $extra['discount'] = 0;
                        $salesMenuExtraModel->discountValue = 0;
                    }

                    $sumMenuDiscount += $salesMenuModel->qty * $salesMenuExtraModel->discountValue;

                    if ($applyDiscountBill) {
                        if ($applyBillDiscountToExtra) {
                            if ($promotionHeadTypeID == 10) {
                                if ($calculationMode == SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT) {
                                    $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                                } else  {
                                    $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue);
                                }
                            } else {
                                if ($salesModel->promotionID == $salesMenuModel->promotionDetailID) {
                                    $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                                } else {
                                    if ($calculationMode == SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT) {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                                    } else {
                                        $sumMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue);
                                    }
                                }
                            }
                        }
                    }

                    $allMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                    $allMenuDiscountTotal += $salesMenuModel->qty * $salesMenuExtraModel->discountValue;

                    if ($inclusiveMenuTemplateID) {
                        $menuExtraModel = MenuExtra::find()
                            ->where(['menuExtraID' => $salesMenuExtraModel->menuExtraID])
                            ->one();

                        $currentPromotionID = $salesMenuModel->promotionDetailID;
                        if ($salesMenuModel->promotionDetailID != 0) {
                            if ($promotionModel->flagMenuExtra == 1) {
                                $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $menuExtraModel->price;
                                $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;        
                                $salesMenuExtraModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->inclusivePrice;                           
                                if ($detailPromotionTypeID == 9) {
                                    if ($inclusiveMenuTemplateID) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            if ($menuExtraModel->price > 0) {
                                                $tempPromotionDiscount = $salesMenuExtraModel->price / $menuExtraModel->price * $promotionModel->discount;
                                            } else {
                                                $tempPromotionDiscount = 0;
                                            }
                                            
                                            if ($tempPromotionDiscount > $salesMenuExtraModel->price) {
                                                $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $salesMenuExtraModel->price;
                                                $salesMenuExtraModel->inclusiveDiscountValue = (float) $salesMenuExtraModel->qty * $menuExtraModel->price;                                             
                                                $discountValue = (float) $salesMenuExtraModel->inclusiveDiscountValue;
                                            } else {
                                                if ($menuExtraModel->price > 0) {
                                                    $percentageDiscountValue = $promotionModel->discount / $menuExtraModel->price * 100;
                                                    $tempDiscountValue = $salesMenuExtraModel->price * $percentageDiscountValue / 100;
    
                                                    $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $tempDiscountValue;
                                                    $discountValue = (float) $extra['qty'] * $promotionModel->discount;
                                                    $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                                } else {
                                                    $salesMenuExtraModel->discountValue = 0;
                                                    $discountValue = 0;
                                                    $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                                }                                            
                                            }
                                        }
                                    }
                                } else {
                                    if ($inclusiveMenuTemplateID) {
                                        $menuExtraSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                        $menuExtraTotal = $menuExtraModel->price * $salesMenuExtraModel->qty;
                                        $salesMenuExtraModel->inclusiveDiscountValue = ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice) * $salesMenuExtraModel->discount / 100;

                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $salesMenuExtraModel->discount = $promotionModel->discount;
                                            if ($detailPromotionTypeID == 1) {
                                                $salesMenuExtraModel->discountValue = (float) $menuExtraSubtotal * $promotionModel->discount / 100;
                                                $salesMenuExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                $discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                            } else {
                                                $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                                $discountValue = $salesMenuExtraModel->discountValue;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $salesMenuExtraModel->discountValue = 0;
                                $salesMenuExtraModel->inclusiveDiscountValue = 0;
                            }
                        } else {
                            $salesMenuExtraModel->discount = 0;
                            $extra['discount'] = 0;
                            $salesMenuExtraModel->discountValue = 0;
                            $salesMenuExtraModel->inclusiveDiscountValue = 0;
                        }

                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {                                        
                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice - $salesMenuExtraModel->inclusiveDiscountValue);                                       
                                    }
                                }
                            } else if ($promotionHeadTypeID == 11) {
                                if ($applyDiscountBill) {
                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice - $salesMenuExtraModel->inclusiveDiscountValue);                                       
                                }
                            } else {
                                if ($applyDiscountBill && $applyBillDiscountToExtra) {
                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice - $salesMenuExtraModel->inclusiveDiscountValue);
                                }
                            }
                        } else {
                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {                     
                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice);
                                        $tempGrandTotal = $tempMenuGrandTotal;                                     
                                    }
                                }
                            } else if ($promotionHeadTypeID == 11) {
                                if ($applyDiscountBill) {
                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice);
                                    $tempGrandTotal = $tempMenuGrandTotal; 
                                }
                            } else {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {                     
                                        $tempGrandTotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice);                                       
                                    }
                                }
                            }
                        }

                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuExtraModel->otherTaxValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue) / 100 * $salesMenuExtraModel->otherTax;
                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                $salesMenuExtraModel->vatValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue) / 100 * $salesMenuExtraModel->vat;
                                $salesMenuExtraModel->otherVatValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue) / 100 * $salesMenuExtraModel->otherVat;
                            } else {
                                $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                                $salesMenuExtraModel->otherVatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->otherVat;
                            }

                            if ($promotionHeadTypeID == 10) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {                                        
                                        $otherTaxTotal += $salesMenuModel->qty * $salesMenuExtraModel->otherTaxValue;
                                        $vatTotal += $salesMenuModel->qty * $salesMenuExtraModel->vatValue;
                                        $otherVatTotal += $salesMenuModel->qty * $salesMenuExtraModel->otherVatValue;
                                    }
                                }
                            } else {
                                $otherTaxTotal += $salesMenuModel->qty * $salesMenuExtraModel->otherTaxValue;
                                $vatTotal += $salesMenuModel->qty * $salesMenuExtraModel->vatValue;
                                $otherVatTotal += $salesMenuModel->qty * $salesMenuExtraModel->otherVatValue;
                            }
                        }
                    }
                }
            }
        }

        if ($promotionPaymentMethodIDs) {
            $promotionPaymentMethodIDs = array_unique($promotionPaymentMethodIDs);
            if (count($promotionPaymentMethodIDs) > 1) {
                $errPaymentMethodMessage = 'Error: Multi payment method for promotions';
                $newErrMsg .= $errPaymentMethodMessage;
            }
        }

        if ($taxInclusiveAfterDiscount) {
            $sumMenuSubtotal = $tempMenuGrandTotal;
        } else if ($inclusiveMenuTemplateID) {
            if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                $sumMenuSubtotal = $tempMenuGrandTotal;
            }
        }

        $promotionModel = null;
        $allBillDiscount = 0;
        $allInclusiveBillDiscount = 0;
        foreach ($newSalesModel['salesMenu'] as $salesMenu) {
            $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] == 2));
            $detailPromotionTypeID = 0;
            $tempMenuID = 0;
            $subsID = isset($salesMenu['subsID']) ? $salesMenu['subsID'] : 0 ;
            if ($subsID != 0) {
                $tempMenuID = $subsID;
            }
            else{
                $menuPromotionID = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] : 0;
                $tempMenuID = $salesMenu['menuID'];
                if($menuPromotionID != 0 && ($salesMenu['statusID'] != 1 || $salesMenu['statusID'] != 12)){
                    $tempMenuID = $menuPromotionID;
                }
            }
            if (isset($salesMenu['promotionDetailID'])) {
                if (in_array($salesMenu['statusID'], [13, 34, 14])) {
                    $promotionModel = PromotionHead::find()
                        ->where(['promotionID' => $salesMenu['promotionDetailID']])
                        ->one();
                } else {
                    $promotionModel = PromotionHead::findActiveForMenu($tempMenuID,
                        $salesModel->memberID, $salesModel->employeeCode, null, $salesModel->flagExternalMemberID)
                    ->andWhere([PromotionHead::tableName() . '.promotionID' => $salesMenu['promotionDetailID']])
                    ->one();
                }

                if ($promotionModel) {
                    if ($salesModel->memberID == 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '') && (!$isMemberID && !$isStamps)) {
                        if (in_array($promotionModel->promotionMemberTypeID, [1, 2, 3])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID == 0 && ($salesModel->employeeCode != null && $salesModel->employeeCode != '')) {
                        if (in_array($promotionModel->promotionMemberTypeID, [3])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID != 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '')) {
                        if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    } else if ($salesModel->memberID == 0 && ($salesModel->employeeCode == null || $salesModel->employeeCode == '') && ($isMemberID || $isStamps)) {
                        if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                            $salesMenu['promotionDetailID'] = 0;
                            $salesMenu['promotionDetailName'] = '';
                            $salesMenu['promotionVoucherCode'] = '';
                            $salesMenu['discount'] = 0;
                            $promotionModel = null;
                        }
                    }
                }
                
                
                if ($promotionModel) {
                    $detailPromotionTypeID = $promotionModel->promotionTypeID;
                } else {
                    $appliedVat = $isApplyOtherVat ? $salesMenu['otherVat'] : $salesMenu['vat'];
                    if (isset($salesMenu['flagLuxuryItem'])) {
                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenu['otherVat']) : $salesMenu['vat'];
                    }
                    
                    if ($salesMenu['price'] == 0) {
                        $specialMenuPrice = null;
                        if (array_key_exists($salesMenu['menuID'],
                                $specialPriceArrModel)) {
                            $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                        }

                        if ($specialMenuPrice) {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $specialMenuPrice;
                                $salesMenu['price'] = UpdateOrder::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat,
                                    $salesDecimalSetting, $settingDecimalMode, $specialMenuPrice);
                            } else {
                                $salesMenu['price'] = $specialMenuPrice;
                            }
                        } else {
                            if ($inclusiveMenuTemplateID) {
                                $salesMenu['inclusivePrice'] = $menuTemplateDetailModel[$salesMenu['menuID']]->price;
                            }
                            $salesMenu['price'] = $salesMenu['originalPrice'];
                        }
                    }
                }
            }

            $salesMenuUpdated = false;
            $salesMenuModel = SalesMenu::find()
                        ->with('menu')
                        ->andWhere(['ID' => $salesMenu['ID']])
                        ->one();

            $salesMenuFlagTax = $salesMenuModel->menu ? $salesMenuModel->menu->flagTax : 0;
            $flagSeparateTaxCalculation = $salesMenuModel->menu ? $salesMenuModel->menu->flagSeparateTaxCalculation : 0;
            $isApplyOtherVat = ($vatSubject === 1 && ($salesMenuFlagTax && $salesMenuFlagTax == 2));
            if ($salesMenuModel->promotionDetailID != $salesMenu['promotionDetailID']) {
                $salesMenuUpdated = true;
            }

            if ($updatePromotionHead) {
                $salesMenuUpdated = true;
            }

            $salesMenuModel->load(['SalesMenu' => $salesMenu]);
            $inclusivePrice = 0;
            $discountValue = 0;
            $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
            if (isset($salesMenu['flagLuxuryItem'])) {
                $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
            }
            
            if ($inclusiveMenuTemplateID) {
                $inclusivePrice = $salesMenuModel->inclusivePrice;
                if ($salesMenuModel->promotionDetailID > 0) {
                    if ($promotionModel) {
                        if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                            $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                            $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                        } else {
                            $detailPromotionTypeID = $promotionModel->promotionTypeID;
                            $detailPromotionDiscount = $promotionModel->discount;
                        }
                    }
                    if ($detailPromotionTypeID == 9) {
                        $menuDiscountVal = 0;
                    } else {
                        $menuDiscountVal = $detailPromotionDiscount;
                    }
                } else {
                    $menuDiscountVal = 0;
                }

                if($detailPromotionTypeID == 7) {
                    $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                }

                $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;
                $newDiscountVal = $menuDiscountVal;
                $salesMenuModel->discount = $newDiscountVal;
                $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                $discountValue = $salesMenuModel->discountValue;
                if ($detailPromotionTypeID == 9) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $netPrice = UpdateOrder::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                        $salesDecimalSetting, $settingDecimalMode, $inclusivePrice);
                        if ($inclusivePrice > 0) {
                            $tempPromotionDiscount = $netPrice / $inclusivePrice * $promotionModel->discount;
                        } else {
                            $tempPromotionDiscount = 0;
                        }
                        
                        if ($tempPromotionDiscount > $netPrice) {
                            $salesMenuModel->discountValue = $netPrice * $salesMenuModel->qty;
                            $salesMenuModel->inclusiveDiscountValue = $inclusivePrice * $salesMenuModel->qty;                            
                            $discountValue = $salesMenuModel->inclusiveDiscountValue;
                        } else {
                            if ($inclusivePrice > 0) {
                                $percentageDiscountValue = $promotionModel->discount / $inclusivePrice * 100;
                                $tempDiscountValue = $netPrice * $percentageDiscountValue / 100;
                                $salesMenuModel->discountValue = $tempDiscountValue * $salesMenuModel->qty;
                                $discountValue = $salesMenuModel->qty * $promotionModel->discount;
                                $salesMenuModel->inclusiveDiscountValue = $discountValue;
                            } else {
                                $salesMenuModel->discountValue = 0;
                                $discountValue = 0;
                                $salesMenuModel->inclusiveDiscountValue = $discountValue;
                            }                            
                        }
                    } else {
                        if ($promotionModel->discount > $inclusivePrice) {
                            $salesMenuModel->discountValue = $inclusivePrice * $salesMenuModel->qty;
                        } else {
                            $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                        }
                    }
                } else if ($detailPromotionTypeID == 1) {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $netPrice = UpdateOrder::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                            $salesDecimalSetting, $settingDecimalMode, $inclusivePrice);
                        $salesMenuModel->discountValue = $netPrice * $salesMenuModel->qty * $promotionModel->discount / 100;
                        $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                        $salesMenuModel->inclusiveDiscountValue = $discountValue;

                        $subtotalBeforeDiscount = $salesMenuModel->price * $salesMenuModel->qty;
                        if ($salesMenuModel->otherTaxOnVat == 0) {                            
                            $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                        } else {
                            $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                        }

                        $salesMenuModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterMenuDiscount;
                    } else {
                        $salesMenuModel->discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                    }
                } else {
                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                        $salesMenuModel->inclusiveDiscountValue = 0;
                    }
                }
            } else {
                $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                if ($detailPromotionTypeID == 9) {
                    if ($promotionModel->discount > $salesMenuModel->price) {
                        $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                    } else {
                        $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                    }
                }
            }

            $sumMenuDiscount += $salesMenuModel->discountValue;

            $discountBill = 0;
            $inclusiveDiscountBill = 0;
            $otherTaxDiscountBill = 0;
            if ($applyToBill) {
                $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                    $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Main', $calculationMode, $taxCalculation,
                    0, 0, 0, SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT, $allMenuDiscountTotal);
                    
                if ($otherTaxCalculationType == 2) {
                    $otherTaxDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                        $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Main', $calculationMode, $taxCalculation, 
                        0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                }

                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                    $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
            }

            $applyDiscountBill = false;
            if ($promotionHeadModel) {
                $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($salesModel->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
            }

            if ($inclusiveMenuTemplateID) {
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $currentMenuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                    $totalAfterBillDisc = 0 > $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue ? 0 : $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue;
                    if ($salesMenuModel->otherTaxOnVat == 0) {
                        $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                        $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                        
                        $otherTaxValue = $subtotalAfterDiscount * $salesMenuModel->otherTax / 100;
                        $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                        
                        $vatValue = $isApplyOtherVat ? 0 : $subtotalAfterDiscount * $salesMenuModel->vat / 100;
                        $salesMenuModel->vatValue = (float) $vatValue;

                        $otherVatValue = !$isApplyOtherVat ? 0 : $subtotalAfterDiscount * $salesMenuModel->otherVat / 100;
                        if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                            $dppValue = CalculateTotal::getDppValue(
                                $salesMenu['flagLuxuryItem'],
                                $salesMenuModel->otherTaxOnVat,
                                $subtotalAfterDiscount,
                                $salesMenuModel->otherTaxValue
                            );
                            $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                $dppValue,
                                $salesMenu['otherVat']
                            );
                        }
                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                    } else {
                        $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                        $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);;

                        $otherTaxValue = $subtotalAfterDiscount * $salesMenuModel->otherTax / 100;
                        $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                        
                        $taxValue = ($subtotalAfterDiscount + $salesMenuModel->otherTaxValue) * $salesMenuModel->vat / 100;
                        $salesMenuModel->vatValue = (float) $taxValue;

                        $otherVatValue = ($subtotalAfterDiscount + $salesMenuModel->otherTaxValue) * $salesMenuModel->otherVat / 100;
                        if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                            $dppValue = CalculateTotal::getDppValue(
                                $salesMenu['flagLuxuryItem'],
                                $salesMenuModel->otherTaxOnVat,
                                $subtotalAfterDiscount,
                                $salesMenuModel->otherTaxValue
                            );
                            $otherVatValue = CalculateTotal::getOtherVatValue(
                                $dppValue,
                                $salesMenu['otherVat']
                            );
                        }
                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                    }

                    if ($salesMenuModel->discountValue > 0) {
                        $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                    } else {
                        $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                    }
                } else {
                    $menuDiscountTotal = $this->menuDiscountTotal > 0 ? $this->menuDiscountTotal : 0;
                    $totalAfterBillDisc = ($discountBill > 0 || $salesMenuModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $salesModel->promotionDiscount, $menuGrandTotal, $salesMenuModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill) : $menuGrandTotal;
                    $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;

                    if ($salesMenuModel->otherTaxOnVat == 0) {
                        $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $salesMenuModel->otherVat + $salesMenuModel->otherTax));
                        $subtotalBeforeDiscount = $menuGrandTotal * (100 / (100 + $salesMenuModel->vat + $salesMenuModel->otherTax));

                        $otherTaxValue = $menuGrandTotal * (100 / (100 + $appliedVat + $salesMenuModel->otherTax)) * ($salesMenuModel->otherTax / 100);
                        $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                        
                        $vatValue = $subtotalBeforeDiscount * $salesMenuModel->vat / 100;
                        $salesMenuModel->vatValue = (float) $vatValue;

                        $otherVatValue =  $subtotalAfterDiscount * $salesMenuModel->otherVat / 100;
                        if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                            $dppValue = CalculateTotal::getDppValue(
                                $salesMenu['flagLuxuryItem'],
                                $salesMenuModel->otherTaxOnVat,
                                $subtotalAfterDiscount,
                                $salesMenuModel->otherTaxValue
                            );
                            $otherVatValue =  CalculateTotal::getOtherVatValue(
                                $dppValue,
                                $salesMenu['otherVat']
                            );
                        }
                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                    } else {
                        $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $salesMenuModel->otherVat) * 100 / ( 100 + $salesMenuModel->otherTax));
                        $subtotalBeforeDiscount = $menuGrandTotal *  (100 / (100 + $salesMenuModel->vat) * 100 / ( 100 + $salesMenuModel->otherTax));
                        
                        $otherTaxValue = $menuGrandTotal * (100 / (100 + $appliedVat)) * ($salesMenuModel->otherTax / (100 + $salesMenuModel->otherTax));
                        $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                        
                        $vatValue = ($subtotalBeforeDiscount + $salesMenuModel->otherTaxValue) * $salesMenuModel->vat / 100;
                        $salesMenuModel->vatValue = (float) $vatValue;

                        $otherVatValue = ($subtotalAfterDiscount + $salesMenuModel->otherTaxValue) * $salesMenuModel->otherVat / 100;
                        if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                            $dppValue = CalculateTotal::getDppValue(
                                $salesMenu['flagLuxuryItem'],
                                $salesMenuModel->otherTaxOnVat,
                                $subtotalAfterDiscount,
                                $salesMenuModel->otherTaxValue
                            );
                            $otherVatValue = CalculateTotal::getOtherVatValue(
                                $dppValue,
                                $salesMenu['otherVat']
                            );
                        }
                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                    }
                }
            } else {
                $menuSubtotalBeforeDiscount = ($salesMenuModel->price * $salesMenuModel->qty);
                $menuSubtotalAfterDiscount = ($salesMenuModel->price * $salesMenuModel->qty) - $salesMenuModel->discountValue - $discountBill;
                $menuSubtotalAfterDiscountOtherTax = ($salesMenuModel->price * $salesMenuModel->qty) - $salesMenuModel->discountValue - $otherTaxDiscountBill;

                $menuPlatformFee = 0;
                if ($platformFeeIncludeOtherTax > 0 && $menuSubtotalBeforeDiscount > 0 && $allMenuSubtotal > 0) {
                    $menuPlatformFee = round($menuSubtotalBeforeDiscount / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                    $totalPlatformFee += $menuPlatformFee;
                    $sumSubtotalPlatformFee += $menuSubtotalBeforeDiscount;

                    if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                        $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                        $menuPlatformFee = $menuPlatformFee + $diffPlatformFee;
                    }
                }

                $salesMenuModel->otherTaxValue = (float) ($otherTaxCalculationType == 2 ? $menuSubtotalAfterDiscountOtherTax : $menuSubtotalBeforeDiscount) / 100 * $salesMenuModel->otherTax;
                if ($menuPlatformFee > 0) {
                    $salesMenuModel->platformFee = $menuPlatformFee;
                    $salesMenuModel->otherTaxValue = $salesMenuModel->otherTaxValue + $menuPlatformFee;
                }

                if ($salesMenuModel->otherTaxOnVat == 0) {
                    $salesMenuModel->vatValue = (float) ($taxCalculationType == 2 ? $menuSubtotalAfterDiscount : $menuSubtotalBeforeDiscount) / 100 * $salesMenuModel->vat;
                    $salesMenuModel->otherVatValue = (float) $menuSubtotalAfterDiscount / 100 * $salesMenuModel->otherVat;
                    if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                        $dppValue = CalculateTotal::getDppValue(
                            $salesMenu['flagLuxuryItem'],
                            $salesMenuModel->otherTaxOnVat,
                            $menuSubtotalAfterDiscount,
                            $salesMenuModel->otherTaxValue
                        );
                        $salesMenuModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                            $dppValue,
                            $salesMenu['otherVat']
                        );
                    }
                } else {
                    $salesMenuModel->vatValue = (float) (($taxCalculationType == 2 ? $menuSubtotalAfterDiscount : $menuSubtotalBeforeDiscount) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;
                    $salesMenuModel->otherVatValue = (float) ($menuSubtotalAfterDiscount + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->otherVat;
                    if ($isApplyOtherVat && isset($salesMenu['flagLuxuryItem'])) {
                        $dppValue = CalculateTotal::getDppValue(
                            $salesMenu['flagLuxuryItem'],
                            $salesMenuModel->otherTaxOnVat,
                            $menuSubtotalAfterDiscount,
                            $salesMenuModel->otherTaxValue
                        );
                        $salesMenuModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                            $dppValue,
                            $salesMenu['otherVat']
                        );
                    }
                }
            }

            $allBillDiscount += $discountBill;
            $allInclusiveBillDiscount += $inclusiveDiscountBill;

            if ($inclusiveMenuTemplateID) {
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $salesMenuModel->total = $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue - $discountBill;
                } else {
                    $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                }

                if ($discountBill > 0) {
                    $salesMenuModel->total = $salesMenuModel->price * $salesMenuModel->qty - $salesMenuModel->discountValue + $salesMenuModel->otherTaxValue + $salesMenuModel->vatValue + $salesMenuModel->otherVatValue;
                }
            } else {
                $salesMenuModel->calculateTotal(0, 0, $discountBill, $salesModel->promotionID, $otherTaxDiscountBill);
            }

            if ($salesMenuUpdated) {
                if (!$salesMenuModel->save()) {
                    throw new Exception('Failed to update menu');
                } else {
                    SalesRewardMenu::adjustSalesRewardMenu(
                        $salesModel->externalMembershipTypeID,
                        $salesMenuModel,
                        isset($salesMenu['rewardType']) ? $salesMenu['rewardType'] : null
                    );
                }
            }
            $currentIDs[] = $salesMenuModel->ID;

            if (isset($salesMenu['packages'])) {
                foreach ($salesMenu['packages'] as $package) {
                    $salesMenuPackageModel = SalesMenu::find()
                        ->with('menu')
                        ->andWhere(['ID' => $package['ID']])
                        ->one();
                    
                    $salesPckMenuFlagTax = ($salesMenuPackageModel && $salesMenuPackageModel->menu) ? $salesMenuPackageModel->menu->flagTax : 0;
                    $appliedPckFlagTax = $flagSeparateTaxCalculation === 0 ? $salesMenuFlagTax : $salesPckMenuFlagTax;
                    $isApplyPckOtherVat = ($vatSubject === 1 && ($appliedPckFlagTax && $appliedPckFlagTax == 2));
                    $appliedPckVat = $isApplyPckOtherVat ? $salesMenuPackageModel->otherVat : $salesMenuPackageModel->vat;
                    if (isset($package['flagLuxuryItem'])) {
                        $appliedPckVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $salesMenuPackageModel->otherVat) : $salesMenuPackageModel->vat;
                    }

                    $tempMenuID = 0;
                    $subsID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0 ;
                    if ($subsID != 0) {
                        $tempMenuID = $subsID;
                    }
                    else{
                        $menuPromotionID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0;
                        $tempMenuID = $salesMenuPackageModel['menuID'];
                        if($menuPromotionID != 0 && ($salesMenuPackageModel['statusID'] != 1 || $salesMenuPackageModel['statusID'] != 12)){
                            $tempMenuID = $menuPromotionID;
                        }
                    }

                    // @Notes: untuk inclusive
                    $menuPackageModel = MenuPackage::find()
                        ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                            $query->andOnCondition([
                                MapMenuTemplatePackage::tableName() . '.menuTemplateID' => $menuTemplateID
                            ]);
                        }])
                        ->where([
                            'ms_menupackage.menuID' => $tempMenuID,
                            'ms_menupackage.menuGroupID' => $salesMenuPackageModel->menuGroupID
                        ])
                    ->one();

                    $applyPackagePrice = $menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price;
                    $salesTypeEzo = $this->checkSalesTypeEzo($package['salesType']);
                    if ($salesTypeEzo) {
                        $applyPackagePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $package['price'];
                    }
                    $salesMenuPackageModel->load(['SalesMenu' => $package]);
                    $currentPromotionID = $salesMenuModel->promotionDetailID;
                    // @Notes: Remove promo
                    if ($currentPromotionID != $salesMenuPackageModel->promotionDetailID) {
                        $this->removeMenuPromo($salesMenuPackageModel,
                            $currentPromotionID);
                    }

                    $packageInclusivePrice = $applyPackagePrice;
                    $discountBill = 0;
                    $inclusiveDiscountBill = 0;
                    $discountValue = 0;
                    $otherTaxDiscountBillPackage = 0;
                    if ($promotionModel) {
                        if ($promotionModel->flagPackageContent == 1) {
                            // @Notes: Apply promo
                            if ($salesMenuModel->promotionDetailID != 0) {
                                $this->applyMenuPromo($salesMenuPackageModel,
                                    $salesMenuModel->promotionDetailID);
                            }

                            if ($salesMenuPackageModel->promotionDetailID != 0) {
                                if ($detailPromotionTypeID == 4) {
                                    $salesMenuPackageModel->discount = 0;
                                    $salesMenuPackageModel->price = 0;
                                    $packageInclusivePrice = 0;
                                } else {
                                    $salesMenuPackageModel->discount = $promotionModel->discount;
                                }
                                
                                if ($detailPromotionTypeID == 9) {
                                    $salesMenuPackageModel->discount = 0;
                                    $salesMenuPackageModel->promotionDetailID = $salesMenuModel->promotionDetailID;
                                    if ($inclusiveMenuTemplateID) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            if ($applyPackagePrice > 0) {
                                                $tempPromotionDiscount = $salesMenuPackageModel->price / $applyPackagePrice * $promotionModel->discount;
                                            } else {
                                                $tempPromotionDiscount = 0;
                                            }
    
                                            if ($tempPromotionDiscount > $salesMenuPackageModel->price) {
                                                $salesMenuPackageModel->discountValue = (float) $package['qty'] * $salesMenuPackageModel->price;
                                                $salesMenuPackageModel->inclusiveDiscountValue = (float) $salesMenuPackageModel->qty * $applyPackagePrice;                                            
                                                $discountValue = (float) $salesMenuPackageModel->inclusiveDiscountValue;
                                            } else {
                                                if ($applyPackagePrice > 0) {
                                                    $percentageDiscountValue = $promotionModel->discount / $applyPackagePrice * 100;
                                                    $tempDiscountValue = $package['price'] * $percentageDiscountValue / 100;
                                                    $salesMenuPackageModel->discountValue = (float) $package['qty'] * $tempDiscountValue;
                                                    $discountValue = (float) $package['qty'] * $promotionModel->discount;
                                                    $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;
                                                } else {
                                                    $salesMenuPackageModel->discountValue = 0;
                                                    $discountValue = 0;
                                                    $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;
                                                }
                                            }
                                        } else {
                                            if ($promotionModel->discount > $applyPackagePrice) {
                                                $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $applyPackagePrice;
                                            } else {
                                                $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                            }
                                        }
                                    } else {
                                        if ($promotionModel->discount > $salesMenuPackageModel->price) {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price;
                                        } else {
                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                        }
                                    }
                                } else {
                                    if ($inclusiveMenuTemplateID) {
                                        $menuPackageSubtotal = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                        $menuPackageTotal = $applyPackagePrice * $salesMenuPackageModel->qty;
    
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            if ($detailPromotionTypeID == 1) {
                                                $salesMenuPackageModel->discountValue = (float) $menuPackageSubtotal * $promotionModel->discount / 100;
                                                $discountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;

                                                $subtotalBeforeDiscount = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                                if ($salesMenuPackageModel->otherTaxOnVat == 0) {       

                                                    $subtotalAfterMenuDiscount = ($menuPackageTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                                } else {
                                                    $subtotalAfterMenuDiscount = ($menuPackageTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                                }

                                                $salesMenuPackageModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterMenuDiscount;
                                            } else {
                                                $salesMenuPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                                $discountValue = $salesMenuPackageModel->discountValue;
                                                $salesMenuPackageModel->inclusiveDiscountValue = $discountValue;
                                            }
                                        } else {
                                            $newPackageDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuPackageSubtotal,
                                                $menuPackageTotal, $promotionModel->discount);
                                            $salesMenuPackageModel->discount = $newPackageDiscountVal;
                                            if ($detailPromotionTypeID == 1) {
                                                $salesMenuPackageModel->discountValue = (float) $menuPackageTotal * $promotionModel->discount / 100;
                                            } else {
                                                $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                            }
                                        }
                                    } else {
                                        $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                    }
                                }
                            } else {
                                $salesMenuPackageModel->promotionDetailID = 0;
                                $salesMenuPackageModel->discount = 0;
                                $salesMenuPackageModel->discountValue = 0;
                                $salesMenuPackageModel->inclusiveDiscountValue = 0;
                            }                            
                        } else {
                            $salesMenuPackageModel->promotionDetailID = 0;
                            $salesMenuPackageModel->discount = 0;
                            $salesMenuPackageModel->discountValue = 0;
                            $salesMenuPackageModel->inclusiveDiscountValue = 0;
                        }
                    } else {
                        $salesMenuPackageModel->promotionDetailID = 0;
                        $salesMenuPackageModel->discount = 0;
                        $salesMenuPackageModel->discountValue = 0;
                        $salesMenuPackageModel->inclusiveDiscountValue = 0;
                    }

                    $sumMenuDiscount += $salesMenuModel->qty * $salesMenuPackageModel->discountValue;

                    if ($salesMenuPackageModel->otherTax >= 0 || $salesMenuPackageModel->vat >= 0 || $salesMenuPackageModel->otherVat >= 0) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($newSalesModel['promotionID'] != $salesMenuModel->promotionDetailID) {
                                if ($applyToBill) {
                                    if ($promotionHeadModel->promotionTypeID == 10) {
                                        if ($applyDiscountBill) {
                                            if ($applyBillDiscountToPackageContent) {
                                                $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                    $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode) * $salesMenuModel->qty;
                                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                        $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                            }
                                        }
                                    } else {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode) * $salesMenuModel->qty;
                                            $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                            $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                }
                            }
                        } else {
                            if ($applyToBill) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode) * $salesMenuModel->qty;
                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                        }
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                }
                            }
                        }
                    }

                    $menuSubtotalBeforeDiscount = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                    $menuSubtotalAfterDiscount = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue - $discountBill;
                    $menuSubtotalAfterDiscountOtherTax = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue - $otherTaxDiscountBillPackage;

                    $menuPackagePlatformFee = 0;
                    if ($platformFeeIncludeOtherTax > 0 && $package['price'] > 0 && $allMenuSubtotal > 0) {
                        $menuPackagePlatformFee = round($menuSubtotalBeforeDiscount / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                        $totalPlatformFee += $menuPackagePlatformFee;
                        $sumSubtotalPlatformFee += $menuSubtotalBeforeDiscount;

                        if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                            $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                            $menuPackagePlatformFee = $menuPackagePlatformFee + $diffPlatformFee;
                        }
                    }

                    $salesMenuPackageModel->otherTaxValue = (float) ($otherTaxCalculationType == 2 ? $menuSubtotalAfterDiscountOtherTax : $menuSubtotalBeforeDiscount) / 100 * $salesMenuPackageModel->otherTax;
                    if ($menuPackagePlatformFee > 0) {
                        $salesMenuPackageModel->platformFee = $menuPackagePlatformFee;
                        $salesMenuPackageModel->otherTaxValue = $salesMenuPackageModel->otherTaxValue + $menuPackagePlatformFee;
                    }

                    if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                        $salesMenuPackageModel->vatValue = (float) ($taxCalculationType == 2 ? $menuSubtotalAfterDiscount : $menuSubtotalBeforeDiscount) / 100 * $salesMenuPackageModel->vat;
                        $salesMenuPackageModel->otherVatValue = (float) $menuSubtotalAfterDiscount / 100 * $salesMenuPackageModel->otherVat;
                    } else {
                        $salesMenuPackageModel->vatValue = (float) (($taxCalculationType == 2 ? $menuSubtotalAfterDiscount : $menuSubtotalBeforeDiscount) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                        $salesMenuPackageModel->otherVatValue = (float) ($menuSubtotalAfterDiscount + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->otherVat;
                    }
                    $salesMenuPackageModel->total = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesMenuPackageModel->discountValue) + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;

                    if ($inclusiveMenuTemplateID) {
                        $packageGrandTotal = $packageInclusivePrice * $salesMenuPackageModel->qty;
                        $totalAfterBillDiscount = 0 > $packageGrandTotal - $discountBill - $salesMenuPackageModel->inclusiveDiscountValue ? 0 : $packageGrandTotal - $discountBill - $salesMenuPackageModel->inclusiveDiscountValue;
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $currentMenuSubtotal = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                $subtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                
                                $newDiscountBill = $discountBill / $salesMenuModel->qty;
                                $totalAfterBillDiscount = 0 > $packageGrandTotal - $newDiscountBill - $salesMenuPackageModel->inclusiveDiscountValue ? 0 : $packageGrandTotal - $newDiscountBill - $salesMenuPackageModel->inclusiveDiscountValue;
                                $newSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);

                                $otherTaxValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherTax / 100;
                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->vat / 100;
                                $salesMenuPackageModel->vatValue = (float) $vatValue;

                                $otherVatValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherVat / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $salesMenuPackageModel->otherTaxOnVat,
                                        $newSubtotalAfterDiscount,
                                        $salesMenuPackageModel->otherTaxValue
                                    );
                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                            } else {
                                $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                $subtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                
                                $newDiscountBill = $discountBill / $salesMenuModel->qty;
                                $totalAfterBillDiscount = 0 > $packageGrandTotal - $newDiscountBill - $salesMenuPackageModel->inclusiveDiscountValue ? 0 : $packageGrandTotal - $newDiscountBill - $salesMenuPackageModel->inclusiveDiscountValue;
                                $newSubtotalAfterDiscount = $totalAfterBillDiscount * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);

                                $otherTaxValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherTax / 100;
                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = ($newSubtotalAfterDiscount + $salesMenuPackageModel->otherTaxValue) * $salesMenuPackageModel->vat / 100;
                                $salesMenuPackageModel->vatValue = (float) $vatValue;

                                $otherVatValue = ($newSubtotalAfterDiscount + $salesMenuPackageModel->otherTaxValue) * $salesMenuPackageModel->otherVat / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $salesMenuPackageModel->otherTaxOnVat,
                                        $newSubtotalAfterDiscount,
                                        $salesMenuPackageModel->otherTaxValue
                                    );
                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                            }

                            if ($salesMenuPackageModel->discountValue > 0) {
                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                            } else {
                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                            }
                        } else {
                            $menuDiscountTotal = $this->menuDiscountTotal > 0 ? $this->menuDiscountTotal : 0;
                            $totalAfterBillDisc = ($discountBill > 0 || $salesMenuPackageModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $salesModel->promotionDiscount, $packageGrandTotal, $salesMenuPackageModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill) : $packageGrandTotal;
                            $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;

                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax));
                                $subtotalBeforeDiscount = $packageGrandTotal * (100 / (100 + $salesMenuPackageModel->vat + $salesMenuPackageModel->otherTax));

                                $otherTaxValue = $packageGrandTotal * (100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax)) * ($salesMenuPackageModel->otherTax / 100);
                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = $subtotalBeforeDiscount * $salesMenuPackageModel->vat / 100;
                                $salesMenuPackageModel->vatValue = (float) $vatValue;

                                $otherVatValue = $subtotalAfterDiscount * $salesMenuPackageModel->otherVat / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $salesMenuPackageModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuPackageModel->otherTaxValue
                                    );
                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                            } else {
                                $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $appliedPckVat) * 100 / ( 100 + $salesMenuPackageModel->otherTax));
                                $subtotalBeforeDiscount = $packageGrandTotal *  (100 / (100 + $salesMenuPackageModel->vat) * 100 / ( 100 + $salesMenuPackageModel->otherTax));

                                $otherTaxValue = $packageGrandTotal * (100 / (100 + $appliedPckVat)) * ($salesMenuPackageModel->otherTax / (100 + $salesMenuPackageModel->otherTax));
                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = ($subtotalBeforeDiscount + $salesMenuPackageModel->otherTaxValue) * $salesMenuPackageModel->vat / 100;
                                $salesMenuPackageModel->vatValue = (float) $vatValue;

                                $otherVatValue = ($subtotalAfterDiscount + $salesMenuPackageModel->otherTaxValue) * $salesMenuPackageModel->otherVat / 100;
                                if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $package['flagLuxuryItem'],
                                        $salesMenuPackageModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuPackageModel->otherTaxValue
                                    );
                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $package['otherVat']
                                    );
                                }
                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                            }
                        }
                    }

                    $allBillDiscount += $discountBill;
                    $allInclusiveBillDiscount += $inclusiveDiscountBill;

                    if ($inclusiveMenuTemplateID) {

                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuPackageModel->total = $salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue - ($discountBill > 0 ?  $discountBill / $salesMenuModel->qty : 0);
                        }  else {
                            $salesMenuPackageModel->total = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;
                        }

                        if ($discountBill > 0) {
                            $salesMenuPackageModel->total = $salesMenuPackageModel->price * $salesMenuPackageModel->qty - $salesMenuPackageModel->discountValue + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;
                        }

                        if (0 > $salesMenuPackageModel->total) {
                            $salesMenuPackageModel->total = 0;
                        }
                    } else {
                        $discountBill = $discountBill / $salesMenuModel->qty;
                        $salesMenuPackageModel->calculateTotal(0, 0, $discountBill, $salesModel->promotionID, $otherTaxDiscountBillPackage);
                    }

                    if ($salesMenuUpdated) {
                        if (!$salesMenuPackageModel->save()) {
                            throw new Exception('Failed to update menu');
                        }
                    }
                    $currentIDs[] = $package['ID'];
                }
            }

            if ($salesMenu['extras']) {
                foreach ($salesMenu['extras'] as $extra) {
                    $salesMenuExtraModel = SalesMenuExtra::find()
                        ->andWhere([
                            'ID' => $extra['localID'],
                            'salesNum' => $extra['salesNum']
                        ])
                        ->one();

                    $menuExtraModel = MenuExtra::find()
                            ->where(['menuExtraID' => $salesMenuExtraModel->menuExtraID])
                            ->one();                                    
                        
                    $inclusiveDiscountBill = 0;
                    $discountBill = 0;
                    $discountValue = 0;
                    $otherTaxDiscountBillExtra = 0;
                    $appliedExtVat = $isApplyOtherVat ? $salesMenuExtraModel->otherVat : $salesMenuExtraModel->vat;
                    if (isset($extra['flagLuxuryItem'])) {
                        $appliedExtVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($extra['flagLuxuryItem'], $salesMenuExtraModel->otherVat) : $salesMenuExtraModel->vat;
                    }
                    $extraInclusivePrice = $menuExtraModel->price;
                    if ($salesMenuModel->promotionDetailID != 0) {
                        if ($promotionModel->flagMenuExtra == 1) {
                            $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                            $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $menuExtraModel->price;
                            $extraInclusivePrice = $detailPromotionTypeID == 4 ? 0 : $extraInclusivePrice;
                            $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                            $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;                                   
                            if ($detailPromotionTypeID == 9) {
                                if ($inclusiveMenuTemplateID) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($menuExtraModel->price > 0) {
                                            $tempPromotionDiscount = $salesMenuExtraModel->price / $menuExtraModel->price * $promotionModel->discount;
                                        } else {
                                            $tempPromotionDiscount = 0;
                                        }
                                        
                                        if ($tempPromotionDiscount > $salesMenuExtraModel->price) {
                                            $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $salesMenuExtraModel->price;
                                            $salesMenuExtraModel->inclusiveDiscountValue = (float) $salesMenuExtraModel->qty * $menuExtraModel->price;                                             
                                            $discountValue = (float) $salesMenuExtraModel->inclusiveDiscountValue;
                                        } else {
                                            if ($menuExtraModel->price > 0) {
                                                $percentageDiscountValue = $promotionModel->discount / $menuExtraModel->price * 100;
                                                $tempDiscountValue = $salesMenuExtraModel->price * $percentageDiscountValue / 100;
                                                $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $tempDiscountValue;
                                                $discountValue = (float) $extra['qty'] * $promotionModel->discount;
                                                $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                            } else {
                                                $salesMenuExtraModel->discountValue = 0;
                                                $discountValue = 0;
                                                $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                            }                                            
                                        }
                                    } else {
                                        if ($promotionModel->discount > $menuExtraModel->price) {
                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $menuExtraModel->price;
                                        } else {
                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                        }
                                    }
                                } else {
                                    if ($promotionModel->discount > $salesMenuExtraModel->price) {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price;
                                    } else {
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                    }
                                }
                            } else {
                                if ($inclusiveMenuTemplateID) {
                                    $menuExtraSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                    $menuExtraTotal = $menuExtraModel->price * $salesMenuExtraModel->qty;

                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($detailPromotionTypeID == 1) {
                                            $salesMenuExtraModel->discountValue = (float) $menuExtraSubtotal * $promotionModel->discount / 100;
                                            $discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                            $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;

                                            $subtotalBeforeDiscount = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {                            
                                                $subtotalAfterMenuDiscount = ($menuExtraTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedExtVat + $salesMenuExtraModel->otherTax);
                                            } else {
                                                $subtotalAfterMenuDiscount = ($menuExtraTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedExtVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                            }

                                            $salesMenuExtraModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterMenuDiscount;
                                        } else {
                                            $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                            $discountValue = $salesMenuExtraModel->discountValue;
                                            $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                        }
                                    } else {
                                        $newExtraDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuExtraSubtotal,
                                            $menuExtraTotal, $promotionModel->discount);
                                        $salesMenuExtraModel->discount = $newExtraDiscountVal;
                                        if ($detailPromotionTypeID == 1) {
                                            $salesMenuExtraModel->discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                        } else {
                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                        }
                                        $salesMenuExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                    }
                                } else {
                                    $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                }
                            }
                        } else {
                            $salesMenuExtraModel->discountValue = 0;
                            $salesMenuExtraModel->discount = 0;
                            $extra['discount'] = 0;
                            $salesMenuExtraModel->inclusiveDiscountValue = 0;
                        }
                    } else {
                        $salesMenuExtraModel->discount = 0;
                        $extra['discount'] = 0;
                        $salesMenuExtraModel->discountValue = 0;
                        $salesMenuExtraModel->inclusiveDiscountValue = 0;
                    }

                    $sumMenuDiscount += $salesMenuModel->qty * $salesMenuExtraModel->discountValue;

                    if ($salesMenuExtraModel->otherTax >= 0 || $salesMenuExtraModel->vat >= 0 || $salesMenuExtraModel->otherVat >= 0) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            if ($newSalesModel['promotionID'] != $salesMenuModel->promotionDetailID) {
                                if ($applyToBill) {
                                    if ($promotionHeadModel->promotionTypeID == 10) {
                                        if ($applyDiscountBill) {
                                            if ($applyBillDiscountToExtra) {
                                                $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                    $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode) * $salesMenuModel->qty;
                                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                            }
                                        }
                                    } else {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode) * $salesMenuModel->qty;
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                }
                            }
                        } else {
                            if ($applyToBill) {
                                if ($applyDiscountBill) {
                                    if ($applyBillDiscountToExtra) {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode) * $salesMenuModel->qty;
                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillExtra = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                                $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                        }
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($newSalesModel,
                                            $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $sumMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                }
                            }
                        }
                    }

                    $allBillDiscount += $discountBill;
                    $discountBill = $discountBill / $salesMenuModel->qty;

                    if ($inclusiveMenuTemplateID) {
                        $extraGrandTotal = $extraInclusivePrice * $salesMenuExtraModel->qty;
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $currentMenuSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                            $totalAfterBillDisc = 0 > $extraGrandTotal - $discountBill - $salesMenuExtraModel->inclusiveDiscountValue ? 0 : $extraGrandTotal - $discountBill- $salesMenuExtraModel->inclusiveDiscountValue;
                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedExtVat + $salesMenuExtraModel->otherTax);
                                $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedExtVat + $salesMenuExtraModel->otherTax);
                                
                                $otherTaxValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherTax / 100;
                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = $subtotalAfterDiscount * $salesMenuExtraModel->vat / 100;
                                $salesMenuExtraModel->vatValue = (float) $vatValue;

                                $otherVatValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherVat / 100;
                                if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $salesMenuExtraModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuExtraModel->otherTaxValue
                                    );
                                    $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                            } else {
                                $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedExtVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedExtVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                
                                $otherTaxValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherTax / 100;
                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->vat / 100;
                                $salesMenuExtraModel->vatValue = (float) $vatValue;

                                $otherVatValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->otherVat / 100;
                                if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $salesMenuExtraModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuExtraModel->otherTaxValue
                                    );
                                    $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                            }

                            if ($salesMenuExtraModel->discountValue > 0) {
                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                            } else {
                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                            }
                        } else {
                            $menuDiscountTotal = $this->menuDiscountTotal > 0 ? $this->menuDiscountTotal : 0;
                            $totalAfterBillDisc = ($discountBill > 0 || $salesMenuExtraModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $salesModel->promotionDiscount, $extraGrandTotal, $salesMenuExtraModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill) : $extraGrandTotal;
                            $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                            
                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedExtVat + $salesMenuExtraModel->otherTax));
                                $subtotalBeforeDiscount = $extraGrandTotal * (100 / (100 + $salesMenuExtraModel->vat + $salesMenuExtraModel->otherTax));

                                $otherTaxValue = $extraGrandTotal * (100 / (100 + $appliedExtVat + $salesMenuExtraModel->otherTax)) * ($salesMenuExtraModel->otherTax / 100);
                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = $subtotalBeforeDiscount * $salesMenuExtraModel->vat / 100;
                                $salesMenuExtraModel->vatValue = (float) $vatValue;

                                $otherVatValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherVat / 100;
                                if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $salesMenuExtraModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuExtraModel->otherTaxValue
                                    );
                                    $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                            } else {
                                $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $appliedExtVat) * 100 / ( 100 + $salesMenuExtraModel->otherTax));
                                $subtotalBeforeDiscount = $extraGrandTotal *  (100 / (100 + $salesMenuExtraModel->vat) * 100 / ( 100 + $salesMenuExtraModel->otherTax));

                                $otherTaxValue = $extraGrandTotal * (100 / (100 + $appliedExtVat)) * ($salesMenuExtraModel->otherTax / (100 + $salesMenuExtraModel->otherTax));
                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                
                                $vatValue = ($subtotalBeforeDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->vat / 100;
                                $salesMenuExtraModel->vatValue = (float) $vatValue;

                                $otherVatValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->otherVat / 100;
                                if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $extra['flagLuxuryItem'],
                                        $salesMenuExtraModel->otherTaxOnVat,
                                        $subtotalAfterDiscount,
                                        $salesMenuExtraModel->otherTaxValue
                                    );
                                    $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $extra['otherVat']
                                    );
                                }
                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                            }
                        }
                    } else {

                        $menuSubtotalBeforeDiscount = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                        $menuSubtotalBeforeAfterDiscount = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - $salesMenuExtraModel->discountValue - $discountBill;
                        $menuSubtotalBeforeAfterDiscountOtherTax = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - $salesMenuExtraModel->discountValue - $otherTaxDiscountBillExtra;
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $menuSubtotalBeforeAfterDiscountOtherTax = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - $salesMenuExtraModel->discountValue - $discountBill;
                        }

                        $menuExtraPlatformFee = 0;
                        if ($platformFeeIncludeOtherTax > 0 && $menuSubtotalBeforeDiscount > 0 && $allMenuSubtotal > 0) {
                            $menuExtraPlatformFee = round($menuSubtotalBeforeDiscount / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                            $totalPlatformFee += $menuExtraPlatformFee;
                            $sumSubtotalPlatformFee += $menuSubtotalBeforeDiscount;
    
                            if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                $menuExtraPlatformFee = $menuExtraPlatformFee + $diffPlatformFee;
                            }
                        }

                        $salesMenuExtraModel->otherTaxValue = (float) ($otherTaxCalculationType == 2 ? $menuSubtotalBeforeAfterDiscountOtherTax : $menuSubtotalBeforeDiscount) / 100 * $salesMenuExtraModel->otherTax;
                        if ($salesMenuExtraModel->otherTaxValue < 0) {
                            $salesMenuExtraModel->otherTaxValue = 0;
                        }

                        if ($menuExtraPlatformFee > 0) {
                            $salesMenuExtraModel->otherTaxValue = $salesMenuExtraModel->otherTaxValue + $menuExtraPlatformFee;
                        }

                        if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                            $salesMenuExtraModel->vatValue = (float) ($taxCalculationType == 2 ? $menuSubtotalBeforeAfterDiscount : $menuSubtotalBeforeDiscount) / 100 * $salesMenuExtraModel->vat;
                            $salesMenuExtraModel->otherVatValue = (float) $menuSubtotalBeforeAfterDiscountOtherTax / 100 * $salesMenuExtraModel->otherVat;
                            if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $extra['flagLuxuryItem'],
                                    $salesMenuExtraModel->otherTaxOnVat,
                                    $menuSubtotalBeforeAfterDiscountOtherTax,
                                    $salesMenuExtraModel->otherTaxValue
                                );
                                $salesMenuExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $salesMenu['otherVat']
                                );
                            }
                        } else {
                            $salesMenuExtraModel->vatValue = (float) (($taxCalculationType == 2 ? $menuSubtotalBeforeAfterDiscount : $menuSubtotalBeforeDiscount) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                            $salesMenuExtraModel->otherVatValue = (float) ($menuSubtotalBeforeAfterDiscountOtherTax + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->otherVat;
                            if ($isApplyOtherVat && isset($extra['flagLuxuryItem'])) {
                                $dppValue = CalculateTotal::getDppValue(
                                    $extra['flagLuxuryItem'],
                                    $salesMenuExtraModel->otherTaxOnVat,
                                    $menuSubtotalBeforeAfterDiscountOtherTax,
                                    $salesMenuExtraModel->otherTaxValue
                                );
                                $salesMenuExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                    $dppValue,
                                    $salesMenu['otherVat']
                                );
                            }
                        }
                    }

                    if ($salesMenuExtraModel->otherVatValue < 0) {
                        $salesMenuExtraModel->otherVatValue = 0;
                    }

                    $inclusiveDiscountBill = $inclusiveDiscountBill * $salesMenuModel->qty;
                    $allInclusiveBillDiscount += $inclusiveDiscountBill;

                    $salesMenuExtraModel->total = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesMenuExtraModel->discountValue) + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue + $salesMenuExtraModel->otherVatValue;

                    if ($salesMenuExtraModel->otherVatValue < 0) {
                        $salesMenuExtraModel->otherVatValue = 0;
                    }

                    if ($inclusiveMenuTemplateID) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuExtraModel->total = ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice) - $salesMenuExtraModel->inclusiveDiscountValue - $discountBill;
                        }

                        if ($discountBill > 0) {
                            $salesMenuExtraModel->total = $salesMenuExtraModel->price * $salesMenuExtraModel->qty - $salesMenuExtraModel->discountValue + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue + $salesMenuExtraModel->otherVatValue;
                        }
                    }
                    if (!$salesMenuExtraModel->save()) {
                        throw new Exception('Failed to save extra');
                    }
                    $currentIDs[] = $extra['ID'];
                }
            }
        }

        if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 1 || $promotionHeadTypeID == 5) {
            $salesModel->tempMenuSubtotal = $sumMenuSubtotal;
        } else if($promotionHeadTypeID == 11){
            $salesModel->tempMenuSubtotal = $sumMenuSubtotal;
            $promotionDiscount = $salesModel->promotionDiscount > 100 ? 100 : $salesModel->promotionDiscount;
            $salesModel->promotionDiscount = $promotionDiscount;
        }

        if ($salesModel->promotionID == 0) {
            $salesModel->promotionVoucherCode = '';
        }

        if ($platformFeeList) {
            $salesModel->platformFee = $platformFeeList;
        }

        $salesModel->inclusiveDiscountTotal = $allInclusiveBillDiscount;
        $salesModel->flagAutoRemovePromotion = false;
        if (!$salesModel->save()) {
            throw new Exception('Failed to update sales head');
        } else {
            SalesRewardHead::adjustSalesRewardHead(
                $salesModel->externalMembershipTypeID,
                null,
                $salesModel->salesNum,
                null
            );
        }
    }

    private function applyMenuPromo(&$newSalesMenuModel, $newPromotionID = null) {
        $promotionID = $newPromotionID ? $newPromotionID : $newSalesMenuModel->promotionDetailID;
        $promotionModel = PromotionHead::findActiveForMenu($newSalesMenuModel->menuID,
                $this->salesModel->memberID, $this->salesModel->employeeCode, null, $this->salesModel->flagExternalMemberID)
            ->andWhere([PromotionHead::tableName() . '.promotionID' => $promotionID])
            ->one();
        if (!$promotionModel) {
            return;
        }

        // @Notes: 1 = Discount(%), 4 = Free Item
        if ($promotionModel->promotionTypeID == 1) {
            $newSalesMenuModel->discount = $promotionModel->discount;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        } else if (($promotionModel->promotionTypeID == 4 || $promotionModel->promotionTypeID == 18 || $promotionModel->promotionTypeID == 19)) {
            $newSalesMenuModel->total = 0;
            $newSalesMenuModel->price = 0;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        } else if ($promotionModel->promotionTypeID == 9) {
            $newSalesMenuModel->discount = 0;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        }
    }

    private function removeMenuPromo(&$newSalesMenuModel, $currentPromotionID) {
        $newSalesMenuModel->discount = 0;
        $newSalesMenuModel->promotionDetailID = 0;
        $newSalesMenuModel->promotionVoucherCode = '';

        // @Notes: Get current promo
        $currentPromotionModel = PromotionHead::find()
            ->andWhere(['promotionID' => $currentPromotionID])
            ->one();
        if (!$currentPromotionModel) {
            return false;
        }

        // @Notes: 4 = Free Item, 18 = Conditional Promo
        if (($currentPromotionModel->promotionTypeID == 4 || $currentPromotionModel->promotionTypeID == 18 || $currentPromotionModel->promotionTypeID == 19)) {
            $newSalesMenuModel->price = $newSalesMenuModel->originalPrice;
        }
    }

    private function revertPromo($salesModel) {
        $tempSalesMenuModel = SalesMenu::findActive()
                ->joinWith('menu.menuCategoryDetail')
                ->joinWith('promotion.paymentMethod')
                ->where('COALESCE (ms_promotionhead.paymentMethodID,0) <> '.$this->newPromotionPaymentMethodID)
                ->andWhere(['salesNum' => $salesModel->salesNum])
                ->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = tr_salesmenu.ID'
                ])
                ->all();
        if (!$tempSalesMenuModel) {
            return true;
        }

        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($salesModel->visitPurposeID);
        $taxCalculationType = Branch::getPosTaxCalculationType($salesModel->branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($salesModel->branchID);
        $promotionArrModel = PromotionHead::findActiveArrayValue();
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $this->salesModel->visitPurposeID])->one();
        $menuTemplateID = ($mapBranchModel) ? $mapBranchModel->menuTemplateID : 0;
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

        $newSalesMenus = [];
        foreach ($tempSalesMenuModel as $salesMenu) {
            foreach ($salesMenu as $key => $value) {
                $newSalesMenu[$key] = $value;
            }
            $newSalesMenu['menuCategoryID'] = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
            $newSalesMenu['menuCategoryDetailID'] = $salesMenu->menu->menuCategoryDetail->ID;            
            $newSalesMenu['packages'] = $salesMenu->childSalesMenus;
            $newSalesMenu['extras'] = $salesMenu->salesExtras;
            $newSalesMenu['promotionDetailID'] = 0;
            $newSalesMenu['discount'] = 0;
            $newSalesMenu['discountValue'] = 0;
            $newSalesMenu['inclusiveDiscountValue'] = 0;
            $newSalesMenu['menuPromotionID'] = 0;

            $newSalesMenus[] = $newSalesMenu;
        }

        foreach ($newSalesMenus as $salesMenu) {
            $salesMenuUpdated = false;
            $salesMenuModel = SalesMenu::find()
                        ->andWhere(['ID' => $salesMenu['ID']])
                        ->one();
            if ($salesMenuModel->promotionDetailID != $salesMenu['promotionDetailID']) {
                $salesMenuUpdated = true;
            }
            // @Notes: Get current promo
            $currentPromotionModel = PromotionHead::find()
                ->andWhere(['promotionID' => $salesMenuModel->promotionDetailID])
                ->one();

            // @Notes: 4 = Revert Free Item, 18 = Revert Conditional Promotion
            if ($currentPromotionModel && ($currentPromotionModel->promotionTypeID == 4 || $currentPromotionModel->promotionTypeID == 18 || $currentPromotionModel->promotionTypeID == 19)) {
                $salesMenu['price'] = $salesMenu['originalPrice'];
                if ($inclusiveMenuTemplateID) {
                    $salesMenu['inclusivePrice'] = $menuTemplateDetailModel[$salesMenu['menuID']]->price;
                }

            }
            $salesMenuModel->load(['SalesMenu' => $salesMenu]);
            $inclusivePrice = 0;
            $discountValue = 0;
            if ($inclusiveMenuTemplateID) {
                $inclusivePrice = $salesMenuModel->inclusivePrice;
                $menuDiscountVal = 0;
                $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;

                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $newDiscountVal = $menuDiscountVal;
                } else {
                    $newDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuSubtotal,
                        $menuGrandTotal, $menuDiscountVal);
                }

                $salesMenuModel->discount = $newDiscountVal;
                $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                $discountValue = $salesMenuModel->discountValue;                
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $salesMenuModel->inclusiveDiscountValue = 0;
                }
            } else {
                $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
            }

            $discountBill = 0;
            $inclusiveDiscountBill = 0;
            if ($inclusiveMenuTemplateID) {
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $salesMenuModel->otherTaxValue = (float) ($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue - $inclusiveDiscountBill) / 100 * $salesMenuModel->otherTax;                            
                    if ($salesMenuModel->otherTaxOnVat == 0) {                          
                        $salesMenuModel->vatValue = (float) ($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue -  $inclusiveDiscountBill) / 100 * $salesMenuModel->vat;                                
                    } else {                            
                        $salesMenuModel->vatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue - $inclusiveDiscountBill) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;                                
                    }
                } else {
                    $salesMenuModel->otherTaxValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->otherTax;
                    if ($salesMenuModel->otherTaxOnVat == 0) {
                        $salesMenuModel->vatValue = (float) ($salesMenuModel->qty * $salesMenuModel->price) / 100 * $salesMenuModel->vat;
                    } else {
                        $salesMenuModel->vatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;
                    }
                }
            } else {
                $salesMenuModel->otherTaxValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->otherTax;
                if ($salesMenuModel->otherTaxOnVat == 0) {
                    $salesMenuModel->vatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price) - ($taxCalculationType == 2 ? $salesMenuModel->discountValue + $discountBill : 0)) / 100 * $salesMenuModel->vat;
                } else {
                    $salesMenuModel->vatValue = (float) (($salesMenuModel->qty * $salesMenuModel->price) - ($taxCalculationType == 2 ? $salesMenuModel->discountValue + $discountBill : 0) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;
                }
            }

            if ($inclusiveMenuTemplateID) {
                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                    $salesMenuModel->total = $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue - $discountBill;
                } else {
                    $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                }
            } else {
                $salesMenuModel->calculateTotal(0, 0, $discountBill, $salesModel->promotionID);
            }

            if ($salesMenuUpdated) {
                if (!$salesMenuModel->save()) {
                    throw new Exception('Failed to update menu');
                } else {
                    SalesRewardMenu::adjustSalesRewardMenu( 
                        $salesModel->externalMembershipTypeID,
                        $salesMenuModel,
                        isset($salesMenu['rewardType']) ? $salesMenu['rewardType'] : null
                    );
                }
            }

            if (isset($salesMenu['packages'])) {
                foreach ($salesMenu['packages'] as $package) {
                    $salesMenuPackageModel = SalesMenu::find()
                        ->andWhere(['ID' => $package['ID']])
                        ->one();

                    $tempMenuID = 0;
                    $subsID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0 ;
                    if ($subsID != 0) {
                        $tempMenuID = $subsID;
                    }
                    else{
                        $menuPromotionID = isset($salesMenuPackageModel['menuPromotionID']) ? $salesMenuPackageModel['menuPromotionID'] : 0;
                        $tempMenuID = $salesMenuPackageModel['menuID'];
                        if($menuPromotionID != 0 && ($salesMenuPackageModel['statusID'] != 1 || $salesMenuPackageModel['statusID'] != 12)){
                            $tempMenuID = $menuPromotionID;
                        }
                    }

                    // @Notes: untuk inclusive
                    $menuPackageModel = MenuPackage::find()
                        ->joinWith(['mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                            $query->andOnCondition([
                                MapMenuTemplatePackage::tableName() . '.menuTemplateID' => $menuTemplateID
                            ]);
                        }])
                        ->where([
                            'ms_menupackage.menuID' => $tempMenuID,
                            'ms_menupackage.menuGroupID' => $salesMenuPackageModel->menuGroupID
                        ])
                    ->one();

                    $applyPackagePrice = $menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price;
                    $salesMenuPackageModel->load(['SalesMenu' => $package]);
                    $currentPromotionID = $salesMenuModel->promotionDetailID;
                    // @Notes: Remove promo
                    if ($currentPromotionID != $salesMenuPackageModel->promotionDetailID) {
                        $this->removeMenuPromo($salesMenuPackageModel,
                            $currentPromotionID);
                    }

                    $discountBill = 0;
                    $inclusiveDiscountBill = 0;
                    $discountValue = 0;
                    $salesMenuPackageModel->promotionDetailID = 0;
                    $salesMenuPackageModel->discount = 0;
                    $salesMenuPackageModel->discountValue = 0;
                    $salesMenuPackageModel->inclusiveDiscountValue = 0;

                    $salesMenuPackageModel->otherTaxValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($otherTaxCalculationType == 2 ? $salesMenuPackageModel->discountValue : 0)) / 100 * $salesMenuPackageModel->otherTax;
                    if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                        $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 ? $salesMenuPackageModel->discountValue : 0)) / 100 * $salesMenuPackageModel->vat;
                    } else {
                        $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 ? $salesMenuPackageModel->discountValue : 0) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                    }
                    $salesMenuPackageModel->total = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesMenuPackageModel->discountValue) + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue;

                    if ($inclusiveMenuTemplateID) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuPackageModel->otherTaxValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue - $inclusiveDiscountBill) / 100 * $salesMenuPackageModel->otherTax;
                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                $salesMenuPackageModel->vatValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue - $inclusiveDiscountBill) / 100 * $salesMenuPackageModel->vat;
                            } else {
                                $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue - $inclusiveDiscountBill) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                            }
                        } else {
                            $salesMenuPackageModel->otherTaxValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) / 100 * $salesMenuPackageModel->otherTax;
                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                $salesMenuPackageModel->vatValue = (float) ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) / 100 * $salesMenuPackageModel->vat;
                            } else {
                                $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                            }
                        }
                    }

                    if ($inclusiveMenuTemplateID) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuPackageModel->total = $salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue - ($discountBill > 0 ?  $discountBill / $salesMenuModel->qty : 0);
                        } else {
                            $salesMenuPackageModel->calculateTotal(1, $applyPackagePrice);
                        }
                    } else {
                        $discountBill = $discountBill / $salesMenuModel->qty;
                        $salesMenuPackageModel->calculateTotal(0, 0, $discountBill, $salesModel->promotionID);
                    }

                    if ($salesMenuUpdated) {
                        if (!$salesMenuPackageModel->save()) {
                            throw new Exception('Failed to update menu');
                        }
                    }
                }
            }

            if ($salesMenu['extras']) {
                foreach ($salesMenu['extras'] as $extra) {
                    $salesMenuExtraModel = SalesMenuExtra::find()
                        ->andWhere([
                            'ID' => $extra['localID'],
                            'salesNum' => $extra['salesNum']
                        ])
                        ->one();                                   
                        
                    $inclusiveDiscountBill = 0;
                    $discountBill = 0;
                    $discountValue = 0;
                    $salesMenuExtraModel->discount = 0;
                    $extra['discount'] = 0;
                    $salesMenuExtraModel->discountValue = 0;
                    $salesMenuExtraModel->inclusiveDiscountValue = 0;

                    if ($inclusiveMenuTemplateID) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuExtraModel->otherTaxValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue - $inclusiveDiscountBill) / 100 * $salesMenuExtraModel->otherTax;
                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                $salesMenuExtraModel->vatValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue - $inclusiveDiscountBill) / 100 * $salesMenuExtraModel->vat;
                            } else {
                                $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue - $inclusiveDiscountBill) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                            }
                        } else {
                            $salesMenuExtraModel->otherTaxValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) / 100 * $salesMenuExtraModel->otherTax;
                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                $salesMenuExtraModel->vatValue = (float) ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) / 100 * $salesMenuExtraModel->vat;
                            } else {
                                $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                            }
                        }
                    } else {
                        $salesMenuExtraModel->otherTaxValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($otherTaxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $discountBill : 0)) / 100 * $salesMenuExtraModel->otherTax;
                        if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                            $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $discountBill : 0)) / 100 * $salesMenuExtraModel->vat;
                        } else {
                            $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $discountBill : 0) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                        }
                    }

                    $salesMenuExtraModel->total = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesMenuExtraModel->discountValue) + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue;
                    $salesMenuExtraModel->calculateTotal($discountBill);

                    if ($inclusiveMenuTemplateID) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $salesMenuExtraModel->total = ($salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice) - $salesMenuExtraModel->inclusiveDiscountValue - $discountBill;
                        }
                    }
                    if (!$salesMenuExtraModel->save()) {
                        throw new Exception('Failed to save extra');
                    }
                }
            }
        }

        return true;
    }

    public static function removeIneligiblePromotion($salesNum) {
        $salesModel = SalesHead::find()
            ->where(['salesNum' => $salesNum])
            ->one();

        $ineligiblePromotion = UpdateOrder::ineligiblePromotion($salesModel);

        if ($ineligiblePromotion) {
            $salesModel->promotionID = 0;
            $salesModel->promotionDiscount = 0;
            $salesModel->promotionVoucherCode = '';

            if (!$salesModel->save()) {
                Yii::error('Failed to save sales');
            }

            $dataLogging = [
                'tableID' => $salesModel->tableID ? $salesModel->tableID : 0,
                'salesNum' => $salesModel->salesNum
            ];

            Logging::save($salesModel->salesNum, Logging::REMOVE_BILL_PROMO, $dataLogging);

            $dataResponse = [
                'status' => 200,
                'message' => 'REMOVE_BILL_PROMO'
            ];

            return $dataResponse;
        }
    }

    private function getEventDescription($headBeforeUpdate, $headAfterUpdate)
    {
        $eventDescription = [
            'tableID' => $headAfterUpdate['tableID'],
            'salesNum' => $headAfterUpdate['salesNum'],
            'promotionID' => $headAfterUpdate['promotionID'],
            'memberID' => $headAfterUpdate['memberID'],
            'externalMembershipTypeID' => $headAfterUpdate['externalMembershipTypeID'],
            'externalMemberName' => $headAfterUpdate['externalMemberName'],
            'employeeName' => $headAfterUpdate['employeeName'],
            'employeeType' => $headAfterUpdate['employeeType'],
            'headBeforeUpdate' => [
                'subtotal' => $headBeforeUpdate['subtotal'],
                'discountTotal' => $headBeforeUpdate['discountTotal'],
                'menuDiscountTotal' => $headBeforeUpdate['menuDiscountTotal'],
                'promotionDiscount' => $headBeforeUpdate['promotionDiscount'],
                'voucherDiscountTotal' => $headBeforeUpdate['voucherDiscountTotal'],
                'otherTaxTotal' => $headBeforeUpdate['otherTaxTotal'],
                'vatTotal' => $headBeforeUpdate['vatTotal'],
                'otherVatTotal' => $headBeforeUpdate['otherVatTotal'],
                'grandTotal' => $headBeforeUpdate['grandTotal'],
                'voucherTotal' => $headBeforeUpdate['voucherTotal'],
                'roundingTotal' => $headBeforeUpdate['roundingTotal'],
                'paymentTotal' => $headBeforeUpdate['paymentTotal'],
                'promotionID' => $headBeforeUpdate['promotionID']
            ],
            'headAfterUpdate' => [
                'subtotal' => $headAfterUpdate['subtotal'],
                'discountTotal' => $headAfterUpdate['discountTotal'],
                'menuDiscountTotal' => $headAfterUpdate['menuDiscountTotal'],
                'promotionDiscount' => $headAfterUpdate['promotionDiscount'],
                'voucherDiscountTotal' => $headAfterUpdate['voucherDiscountTotal'],
                'otherTaxTotal' => $headAfterUpdate['otherTaxTotal'],
                'vatTotal' => $headAfterUpdate['vatTotal'],
                'otherVatTotal' => $headAfterUpdate['otherVatTotal'],
                'grandTotal' => $headAfterUpdate['grandTotal'],
                'voucherTotal' => $headAfterUpdate['voucherTotal'],
                'roundingTotal' => $headAfterUpdate['roundingTotal'],
                'paymentTotal' => $headAfterUpdate['paymentTotal'],
                'promotionID' => $headAfterUpdate['promotionID']
            ]
        ];

        return $eventDescription;
    }

    private function checkSalesTypeEzo($salesType) {
      return strpos($salesType, 'EZO') !== false;
    }
}
