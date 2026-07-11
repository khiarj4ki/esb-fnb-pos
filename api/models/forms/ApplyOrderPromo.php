<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\Menu;
use app\models\PromotionDetail;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesMenu;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property int $promotionID
 * @property orders[] $order
 * @property string $mode
 * $property string $errorMessage
 * 
 * PRIVATE
 * @property SalesHead $salesModel
 * @property PromotionHead $promotionModel
 * @property array $promotionCategoryIDs
 */
class ApplyOrderPromo extends Model {
    const SCENARIO_APPLY_FROM_HEAD = 'HEAD';
    const SCENARIO_APPLY_FROM_MENU = 'MENU';

    public $tableID;
    public $salesNum;
    public $promotionID;
    public $errorMessage;
    public $salesModel;
    public $promotionModel;
    public $promotionCategoryIDs;
    public $promotionCategoryDetailIDs;
    public $promotionCategoryMenuIDs;
    public $order;
    public $mode;
    public $salesUpdate;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID', 'promotionID'], 'required'],
            [['tableID', 'promotionID'], 'integer'],
            [['salesNum'], 'string', 'max' => 20],
            ['mode', 'in', 'range' => [self::SCENARIO_APPLY_FROM_HEAD, self::SCENARIO_APPLY_FROM_MENU]],
            [['tableID'], 'validateTable'],
            [['promotionID'], 'validatePromotion'],
            [['order', 'mode', 'salesUpdate'], 'safe']
        ];
    }

    public function validateTable($attribute) {
        if ($this->tableID != 0) {
            $this->salesModel = SalesHead::findMainSales($this->tableID);
        } elseif ($this->salesNum != '') {
            $this->salesModel = SalesHead::findMainSales(null, $this->salesNum);
        }

        if (!$this->salesModel) {
            $branchID = Setting::getCurrentBranch();
            $branchModel = Branch::findOne(['branchID' => $branchID]);
            if ($this->tableID != 0 && $branchModel->posModeID === 1) {
                $this->addError($attribute, 'Invalid table ID or sales number');
            } else {
                $this->salesModel = new SalesHead();
                $this->salesModel->salesNum = 'New Quick Service';
            }
        }
    }

    public function validatePromotion($attribute) {
        // @Notes: this->promotionID = 0: remove promo
        if ($this->promotionID != 0) {
            $flagExternalMemberID = isset($this['order']['flagExternalMemberID']) ? $this['order']['flagExternalMemberID'] : null;
            $this->promotionModel = PromotionHead::findActiveForBill($this['order']['memberID'],
                    $this['order']['employeeCode'],
                    $this['order']['modePromotion'],
                    $flagExternalMemberID
                )
                ->andWhere(['promotionID' => $this->promotionID])
                ->one();
            if (!$this->promotionModel) {
                $this->promotionModel = PromotionHead::findActiveForBill($this['order']['memberID'],
                    $this['order']['employeeCode'],
                    $this['order']['modePromotion'],
                    $flagExternalMemberID,
                    $this['order']['salesDateIn']
                )
                ->andWhere(['promotionID' => $this->promotionID])
                ->one();

                $this->errorMessage .= $this->promotionModel->notes;

                if (!$this->promotionModel) {
                    $this->addError($attribute, 'Invalid promotion ID');
                }
            }

            // SalesHead::calculateArrayHeadTotal($this->order);
            $subtotal = $this->order['subtotal'];
            if ($subtotal < $this->promotionModel->minSalesPrice) {
                if ($this->mode == self::SCENARIO_APPLY_FROM_HEAD) {
                    $this->errorMessage = Yii::t('app',
                            'Subtotal does not reach ') . number_format($this->promotionModel->minSalesPrice,
                            0, ',', '.');
                    $this->addError($attribute, 'Invalid subtotal');
                } else {
                    $this->promotionID = 0;
                }
            }

            $this->order['promotionID'] = $this->promotionID;
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $mainSalesModel = $this->order;
            if (isset($mainSalesModel['newPromotionPaymentMethodID']) && isset($mainSalesModel['promotionPaymentMethodID'])) {
                if ($mainSalesModel['promotionPaymentMethodID'] != $mainSalesModel['newPromotionPaymentMethodID']) {
                    $this->revertPromo($mainSalesModel);
                }
            }

            if ($this->promotionID != 0) {
                $this->promotionCategoryIDs = [];
                $this->promotionCategoryDetailIDs = [];
                $this->promotionCategoryMenuIDs = [];
                foreach ($this->promotionModel->promotionCategories as $promotionCategory) {
                    $this->promotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                    $this->promotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                    $this->promotionCategoryMenuIDs[] = $promotionCategory->menuID;
                }

                $this->applyPromo($mainSalesModel);

                Logging::save($this->salesModel->salesNum,
                    Logging::ADD_ORDER_PROMO, $this->getAttributes());
            } else {
                $this->removePromo($mainSalesModel);
                Logging::save($this->salesModel->salesNum,
                    Logging::REMOVE_ORDER_PROMO, $this->getAttributes());
            }

            $this->order = $mainSalesModel;
            SalesHead::calculateArrayHeadTotal($this->order, $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, $this->promotionCategoryMenuIDs, $this->errorMessage, $this->salesUpdate);
            if ($this->errorMessage != '') {
                $this->errorMessage .= Yii::t('app', ' is no longer valid. Please check your settings');
                if ($this->salesUpdate) {
                    $this->errorMessage = '';
                }
            }
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('promotionID', $ex->getMessage());
            return false;
        }
    }

    private function applyPromo(&$salesModel) {
        try {
            // @Notes: Untuk perhitungan total Inclusive
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

            // @Notes: 3 = Discount(Rp) always apply to bill
            $applyToBill = $this->promotionModel->promotionTypeID == 3 || $this->promotionModel->promotionTypeID == 6 || $this->promotionModel->promotionTypeID == 10 || $this->promotionModel->promotionTypeID == 12 || count($this->promotionCategoryIDs) == 0;
            $menuDiscountTotal = 0;
            $newSalesMenuQuery = [];
            $issetSpecialPrice = false;

            $newSalesMenu = SalesHead::validatePromoBillAndMenu($salesModel);
            $salesModel['salesMenu'] = $newSalesMenu;

            if($this->promotionModel->promotionTypeID != 11){
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    if (!in_array($salesMenu['statusID'], [12,19])) {
                        if ($salesMenu['originalPrice'] <> $salesMenu['price']) {
                            $salesMenu['promotionDetailID'] = -1;
                            $issetSpecialPrice = true;
                        }
                    }
                }
            }
            
            if($issetSpecialPrice != true){
                $flagIsSubs = false;
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    if (!in_array($salesMenu['statusID'], [12,19])) {
                        if(isset($salesMenu['menuPromotionID']) && $salesMenu['menuPromotionID'] && $salesMenu['menuPromotionID'] != 0){
                            $flagIsSubs = true;
                            break;
                        }
                    }
                    if ($salesMenu['packages']) {
                        foreach ($salesMenu['packages'] as $perPackage) {
                            if (!in_array($perPackage['statusID'], [12,19])) {
                                if(isset($perPackage['menuPromotionID']) && $perPackage['menuPromotionID'] != 0){
                                    $flagIsSubs = true;
                                    break;
                                }
                            }
                        }
                    }
                }
                $issetSpecialPrice = $flagIsSubs;
            }
            
            $tempApplyToBill = ($issetSpecialPrice && ($this->promotionModel->promotionTypeID !== 3 && $this->promotionModel->promotionTypeID !== 6 && $this->promotionModel->promotionTypeID !== 10 && $this->promotionModel->promotionTypeID !== 12)) || $this->promotionModel->promotionTypeID == 9 ? false : $applyToBill;
            foreach ($salesModel['salesMenu'] as $salesMenu) {
                $checkReference = isset($salesMenu['menuGroupID']) ? ($salesMenu['menuGroupID']) == 0 : true;
                $checkMenuPromotion = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] == 0 : true;
                $checkPromotionDetail = ($salesMenu['promotionDetailID'] == 0 || $salesMenu['promotionDetailID'] == $salesModel['promotionID']);
                $checkStatus = ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1);
                $validApplyPromotion = ($checkReference && $checkMenuPromotion && $checkPromotionDetail && $checkStatus) ? true : false;

                if ($validApplyPromotion) {
                    SalesHead::applyPromotion($salesMenu, $tempApplyToBill,
                        $salesMenu['menuCategoryID'],
                        $salesMenu['menuCategoryDetailID'],
                        $salesMenu['menuID'], $this->promotionCategoryIDs,
                        $this->promotionCategoryDetailIDs,
                        $this->promotionCategoryMenuIDs, $this->promotionModel,
                        $salesModel['visitPurposeID']);

                    if (!$applyToBill) {
                        if ($salesModel['flagInclusive']) {
                            if (
                                in_array($salesMenu['menuCategoryID'],
                                    $this->promotionCategoryIDs) ||
                                in_array($salesMenu['menuCategoryDetailID'],
                                    $this->promotionCategoryDetailIDs) ||
                                in_array($salesMenu['menuID'],
                                    $this->promotionCategoryMenuIDs)
                            ) {
                                $salesMenu['discountTotal'] = (float) $this->promotionModel->discount * $salesMenu['total'] / 100;
                                if ($this->promotionModel->promotionTypeID == 9) {
                                    if ($this->promotionModel->discount > ($salesMenu['total'] / $salesMenu['qty'])) {
                                        $salesMenu['discountTotal'] = (float) $salesMenu['total'];
                                    } else {
                                        $salesMenu['discountTotal'] = (float) $this->promotionModel->discount * $salesMenu['qty'];
                                    }

                                    $salesMenu['total'] = $salesMenu['total'] - $salesMenu['discountTotal'];
                                }
                            } else {
                                $salesMenu['discountTotal'] = 0;
                            }
                        } else {
                            $salesMenu['discountTotal'] = ceil($salesMenu['discount'] / 100 * $salesMenu['price'] * $salesMenu['qty']);
                            if ($this->promotionModel->promotionTypeID == 9) {
                                if ($this->promotionModel->discount > $salesMenu['price']) {
                                    $salesMenu['discountTotal'] = (float) $salesMenu['price'] * $salesMenu['qty'];
                                } else {
                                    $salesMenu['discountTotal'] = (float) $this->promotionModel->discount * $salesMenu['qty'];
                                }
                            }
                        }
                    }
                }

                $newSalesMenuQuery[] = $salesMenu;
                $menuDiscountTotal += $salesMenu['discountTotal'];
            }

            $salesModel['promotionDiscount'] = $salesModel['promotionDiscount'] > 0 ? $salesModel['promotionDiscount'] : 0;
            $salesModel['discountTotal'] = 0;
            $salesModel['menuDiscountTotal'] = $menuDiscountTotal;
            $salesModel['promotionID'] = $this->promotionModel->promotionID;
            $salesModel['promotionName'] = $this->promotionModel->notes;
            if ($applyToBill) {
                // @Notes: 1 = Discount(%)
                if ($this->promotionModel->promotionTypeID == 1) {
                    $salesModel['promotionDiscount'] = $issetSpecialPrice ? 0 : $this->promotionModel->discount;
                } else if ($this->promotionModel->promotionTypeID == 10) {
                    $salesModel['promotionDiscount'] = $this->promotionModel->discount;
                } else if ($this->promotionModel->promotionTypeID == 11 || $this->promotionModel->promotionTypeID == 12) {
                    $salesModel['promotionDiscount'] = $salesModel['promotionDiscount'];
                }

                $this->salesModel->calculateDiscountTotal($salesModel);
            }

            $salesModel['salesMenu'] = $newSalesMenuQuery;
        } catch (Exception $ex) {
            Yii::error($ex);
        }
    }

    private function removePromo(&$salesModel) {
        $menuDiscountTotal = 0;
        $newSalesMenuQuery = [];

        foreach ($salesModel['salesMenu'] as $salesMenu) {
            $checkReference = isset($salesMenu['menuGroupID']) ? ($salesMenu['menuGroupID']) == 0 : true;
            $checkMenuPromotion = isset($salesMenu['menuPromotionID']) ? $salesMenu['menuPromotionID'] == 0 : true;
            $checkPromotionDetail = (isset($salesMenu['promotionDetailID']) && ($salesMenu['promotionDetailID'] == 0 || $salesMenu['promotionDetailID'] == $salesModel['promotionID']));
            $checkStatus = ($salesMenu['statusID'] == 13 || $salesMenu['statusID'] == 14 || $salesMenu['statusID'] == 34 || $salesMenu['statusID'] == 1);
            $validRemovePromotion = ($checkReference && $checkMenuPromotion && $checkPromotionDetail && $checkStatus) ? true : false;

            if ($validRemovePromotion) {
                SalesHead::removePromotion($salesMenu,
                    $salesModel['visitPurposeID']);
                $salesMenu['discountTotal'] = 0;
            }

            $menuDiscountTotal += $salesMenu['discountTotal'];
            $newSalesMenuQuery[] = $salesMenu;
        }

        $salesModel['discountTotal'] = 0;
        $salesModel['inclusiveDiscountTotal'] = 0;
        $salesModel['menuDiscountTotal'] = $menuDiscountTotal;
        $salesModel['promotionDiscount'] = 0;
        $salesModel['promotionID'] = 0;
        $salesModel['promotionName'] = '';
        $salesModel['salesMenu'] = $newSalesMenuQuery;
        $salesModel['salesMenu'] = SalesHead::validatePromoBillAndMenu($salesModel);
    }

    public static function checkAppliedPromo($promotionID, $salesMenu, $promotionCategoryIDs = [], $promotionCategoryDetailIDs = [], $promotionMenuIDs = []) {
        $promotionCategoryIDs = array_filter($promotionCategoryIDs, function($value) { return !is_null($value) && $value > 0; });
        $promotionCategoryDetailIDs = array_filter($promotionCategoryDetailIDs, function($value) { return !is_null($value) && $value > 0; });
        $promotionMenuIDs = array_filter($promotionMenuIDs, function($value) { return !is_null($value) && $value > 0; });

        $promotionModel = PromotionHead::find()
            ->andWhere(['promotionID' => $promotionID])
            ->andWhere(['IN', 'promotionTypeID', [1, 3, 5, 6, 10, 11, 12, 14, 15]])
            ->one();

        $issetSpecialPrice = false;
        if ((isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] == 0) &&
            $salesMenu['originalPrice'] <> $salesMenu['price']) {
            $issetSpecialPrice = true;
        }

        $applyDiscount = false;
        if ($promotionModel) {
            if ($promotionModel->promotionTypeID == 3 || $promotionModel->promotionTypeID == 6 || $promotionModel->promotionTypeID == 12 || $promotionModel->promotionTypeID == 14 || $promotionModel->promotionTypeID == 15) {
                $applyDiscount = true;
            } else if ($promotionModel->promotionTypeID == 1 || $promotionModel->promotionTypeID == 5 || $promotionModel->promotionTypeID == 11) {
                if (count($promotionCategoryIDs) > 0) {
					if (isset($salesMenu['menuCategoryID'])) {
						$menuCategoryID = $salesMenu['menuCategoryID'];
					} else {
						$menuModel = Menu::find()
							->with('menuCategoryDetail')
							->where(['menuID' => $salesMenu['menuID']])
							->one();
						$menuCategoryID = $menuModel ? $menuModel->menuCategoryDetail->menuCategoryID : 0;
					}
                    $applyDiscount = in_array($menuCategoryID, $promotionCategoryIDs);
                }                
                if (count($promotionCategoryDetailIDs) > 0) {
					if (isset($salesMenu['menuCategoryDetailID'])) {
						$menuCategoryDetailID = $salesMenu['menuCategoryDetailID'];
					} else {
						$menuModel = Menu::find()
							->where(['menuID' => $salesMenu['menuID']])
							->one();
						$menuCategoryDetailID = $menuModel ? $menuModel->menuCategoryDetailID : 0;
					}
					
                    $applyDiscount = in_array($menuCategoryDetailID, $promotionCategoryDetailIDs);
                }
                if (count($promotionMenuIDs) > 0) {
                    $applyDiscount = in_array($salesMenu['menuID'], $promotionMenuIDs);
                }

                if (count($promotionCategoryIDs) == 0 && count($promotionCategoryDetailIDs) == 0 && count($promotionMenuIDs) == 0) {
                    $applyDiscount = true;
                }

                if ($issetSpecialPrice) $applyDiscount = false;

            } else if ($promotionModel->promotionTypeID == 10) {
                if (is_object($salesMenu)) {
                    $menuCategoryID = $salesMenu->menu->menuCategoryDetail->menuCategoryID;
                    $menuCategoryDetailID = $salesMenu->menu->menuCategoryDetailID;
                    $menuID = $salesMenu->menuID;
                } else {
                    if (isset($salesMenu['menuCategoryID']) && $salesMenu['menuCategoryID'] > 0) {
						$menuCategoryID = $salesMenu['menuCategoryID'];
					} else {
						$menuModel = Menu::find()
							->with('menuCategoryDetail')
							->where(['menuID' => $salesMenu['menuID']])
							->one();
						$menuCategoryID = $menuModel ? $menuModel->menuCategoryDetail->menuCategoryID : 0;
					}
                    
                    if (isset($salesMenu['menuCategoryDetailID']) && $salesMenu['menuCategoryDetailID'] > 0) {
                        $menuCategoryDetailID = $salesMenu['menuCategoryDetailID'];
                    } else {
                        $menuModel = Menu::find()
                            ->where(['menuID' => $salesMenu['menuID']])
                            ->one();
                        $menuCategoryDetailID = $menuModel ? $menuModel->menuCategoryDetailID : 0;
                    }

                    $menuID = $salesMenu['menuID'];
                }

                if (count($promotionCategoryIDs) > 0) {
                    $applyDiscount = in_array($menuCategoryID, $promotionCategoryIDs);
                }  
                if (count($promotionCategoryDetailIDs) > 0) {
                    $applyDiscount = in_array($menuCategoryDetailID, $promotionCategoryDetailIDs);
                }
                if (count($promotionMenuIDs) > 0) {
                    $applyDiscount = in_array($menuID, $promotionMenuIDs);
                }

                if ($issetSpecialPrice) $applyDiscount = false;
            }
        }

        return $applyDiscount;
    }

		public static function checkAppliedPromoArray($salesHead, $salesMenu, $promotionCategoryIDs = [], $promotionCategoryDetailIDs = [], $promotionMenuIDs = []) {
				$promotionCategoryIDs = array_filter($promotionCategoryIDs, function($value) { return !is_null($value) && $value > 0; });
				$promotionCategoryDetailIDs = array_filter($promotionCategoryDetailIDs, function($value) { return !is_null($value) && $value > 0; });
				$promotionMenuIDs = array_filter($promotionMenuIDs, function($value) { return !is_null($value) && $value > 0; });

				$issetSpecialPrice = false;
				if ((isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] == 0) &&
						$salesMenu['originalPrice'] <> $salesMenu['price']) {
						$issetSpecialPrice = true;
				}

				$applyDiscount = false;
				if ($salesHead['masterPromoID']) {
						if (in_array($salesHead['promotionTypeID'], [3, 6, 12, 14, 15])) {
								$applyDiscount = true;
						} else if (in_array($salesHead['promotionTypeID'], [1, 5, 11])) {
								if (count($promotionCategoryIDs) > 0) {
									$applyDiscount = in_array($salesMenu['menuCategoryID'], $promotionCategoryIDs);
								}                
								if (count($promotionCategoryDetailIDs) > 0) {
									$applyDiscount = in_array($salesMenu['menuCategoryDetailID'], $promotionCategoryDetailIDs);
								}
								
								if (count($promotionMenuIDs) > 0) {
									$applyDiscount = in_array($salesMenu['menuID'], $promotionMenuIDs);
								}

								if (count($promotionCategoryIDs) == 0 && count($promotionCategoryDetailIDs) == 0 && count($promotionMenuIDs) == 0) {
									$applyDiscount = true;
								}

								if ($issetSpecialPrice) $applyDiscount = false;
						} else if ($salesHead['promotionTypeID'] == 10) {
								if (count($promotionCategoryIDs) > 0) {
										$applyDiscount = in_array($salesMenu['menuCategoryID'], $promotionCategoryIDs);
								}  
								if (count($promotionCategoryDetailIDs) > 0) {
										$applyDiscount = in_array($salesMenu['menuCategoryDetailID'], $promotionCategoryDetailIDs);
								}
								if (count($promotionMenuIDs) > 0) {
										$applyDiscount = in_array($salesMenu['menuID'], $promotionMenuIDs);
								}

								if ($issetSpecialPrice) $applyDiscount = false;
						}
				}

				return $applyDiscount;
		}

    private function revertPromo(&$salesModel) {
        $oldPromotionPaymentMehtodID = $salesModel['promotionPaymentMethodID'];
        $newSalesMenu = [];
        foreach ($salesModel['salesMenu'] as $salesMenu) {
            if (isset($salesMenu['promotionPaymentMethodID']) && $salesMenu['promotionPaymentMethodID'] == $oldPromotionPaymentMehtodID) {
                
                //handler for new promo subs
                if($salesMenu['statusID'] == 1){
                    if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] != 0){
                        $promotionModel = PromotionHead::find()
                            ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                            ->one();
                        if ($promotionModel && $promotionModel->promotionTypeID == 7){
                            $promotionDetailModel = PromotionDetail::find()
                                ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                                ->andWhere(['=', 'menuSubsID', $salesMenu['menuID']])
                                ->one();
                            if($promotionDetailModel){
                                $salesMenu['menuID'] = $salesMenu['menuPromotionID'];
                                $salesMenu['originalPrice'] = $salesMenu['price'];
                                $menu = Menu::find()->where(['=', 'menuID', $salesMenu['menuPromotionID']])->one();
                                if($menu){
                                    $salesMenu['menuName'] = $menu->menuName;
                                    $salesMenu['menuShortName'] = $menu->menuShortName;
                                    $salesMenu['subsID'] = 0;
                                }
                            }
                        }
                    }
                    if (isset($salesMenu['packages'])){
                        $newPackage = [];
                        foreach ($salesMenu['packages'] as $package) {
                            if(isset($package['menuPromotionID']) && $package['menuPromotionID'] != 0){
                                $promotionModel = PromotionHead::find()
                                    ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                                    ->one();
                                if ($promotionModel && $promotionModel->promotionTypeID == 7){
                                    $promotionDetailModel = PromotionDetail::find()
                                        ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                                        ->andWhere(['=', 'menuSubsID', $package['menuID']])
                                        ->one();
                                    if($promotionDetailModel){
                                        $package['menuID'] = $package['menuPromotionID'];
                                        $package['originalPrice'] = $package['price'];
                                        $menu = Menu::find()->where(['=', 'menuID', $package['menuPromotionID']])->one();
                                        if($menu){
                                            $package['menuName'] = $menu->menuName;
                                            $package['menuShortName'] = $menu->menuShortName;
                                        }
                                    }
                                }
                            }
                            $newPackage[] = $package;
                        }
                        $salesMenu['packages'] = $newPackage;
                    }
                }
                
                //handler for free item & conditional promotion
                if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] != 0){
                    $promotionModel = PromotionHead::find()
                        ->where(['=', 'promotionID', $salesMenu['promotionDetailID']])
                        ->one();
                    if ($promotionModel && ($promotionModel->promotionTypeID == 4 || $promotionModel->promotionTypeID == 18 || $promotionModel->promotionTypeID == 19)){
                        $salesMenu['price'] = $salesMenu['originalPrice'];
                        $salesMenu['inclusivePrice'] = $salesMenu['originalPrice'];
                    }
                }
                
                $salesMenu['promotionDetailID'] = 0;
                $salesMenu['promotionDetailName'] = 0;
                $salesMenu['discount'] = 0;
                $salesMenu['menuPromotionID'] = 0;
            }

            $newSalesMenu[] = $salesMenu;
        }

        $salesModel['salesMenu'] = $newSalesMenu;
        $salesModel['promotionPaymentMethodID'] = $salesModel['newPromotionPaymentMethodID'];

        $promotionModel = PromotionHead::find()
            ->where(['promotionID' => $salesModel['promotionID']])
            ->one();
        $promotionPaymentMethodID = $promotionModel ? (int) $promotionModel->paymentMethodID : 0;
        if ($promotionPaymentMethodID != $salesModel['newPromotionPaymentMethodID']) {
            $salesModel['promotionID'] = 0;
            $this->promotionID = 0;
        }
    }

}
