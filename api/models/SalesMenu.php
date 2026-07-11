<?php
namespace app\models;

use app\components\AppHelper;
use app\models\forms\CalculateTotal;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "tr_salesmenu".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $batchID
 * @property int $menuRefID
 * @property int $menuGroupID
 * @property int $menuID
 * @property string $qty
 * @property string $price
 * @property string $originalPrice
 * @property string $discount
 * @property string $discountValue
 * @property string $inclusiveDiscountValue
 * @property string $otherTax
 * @property string $otherTaxValue
 * @property string $vat
 * @property string $vatValue
 * @property int $otherTaxOnVat
 * @property string $total
 * @property string $notes
 * @property int $statusID
 * @property int $promotionDetailID
 * @property int $menuPromotionID
 * @property string $cancelNotes
 * @property string $salesType
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * @property string $netPrice
 * 
 * @property SalesHead $salesHead
 * @property Menu $menu
 * @property BranchMenu $branchMenu
 * @property SalesMenu $parentSalesMenu
 * @property SalesMenu[] $childSalesMenus
 * @property SalesMenuExtra[] $salesExtras
 * @property Status $status
 * @property PosUser $creator
 * @property PosUser $editor
 */
class SalesMenu extends ActiveRecord {

    public $subsID;
    public $pendingOrder;
    public $flagHoldOrder;
    public $flagFireOrder;
    public $tempOrderID;
    public $flagMemberPromotionVoucher;
    public $platformFee;
    public $dppValue;
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesmenu';
    }

    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => function () {
                    if (!empty($this->getDirtyAttributes())) {
                        if ($this->getDirtyAttributes(['localID']) || $this->statusID != 19) {
                            return date('Y-m-d H:i:s');
                        } else {
                            if ($this->statusID != 19) {
                                return date('Y-m-d H:i:s');
                            } else {
                                return $this->getOldAttribute('editedDate');
                            }
                        }
                    }
                }
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    //ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
                'value' => function () {
                    if (!empty($this->getDirtyAttributes())) {
                        if ($this->getDirtyAttributes(['localID']) || $this->statusID != 19) {
                            return Yii::$app->user->identity->username;
                        } else {
                            if ($this->statusID != 19) {
                                return Yii::$app->user->identity->username;
                            } else {
                                return $this->getOldAttribute('editedBy');
                            }
                        }
                    }
                }
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'menuID', 'qty', 'price', 'discount', 'discountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'otherTaxOnVat', 'total', 'statusID', 'originalPrice'], 'required'],
            [['localID', 'batchID', 'menuRefID', 'menuGroupID', 'menuID', 'otherTaxOnVat', 'statusID', 'promotionDetailID', 'menuPromotionID', 'flagPending'], 'integer'],
            [['batchID', 'menuRefID', 'menuGroupID', 'promotionDetailID', 'menuPromotionID', 'otherVat', 'otherVatValue',], 'default', 'value' => 0],
            [['statusID'], 'default', 'value' => 13],
            [['qty', 'price', 'discount', 'discountValue', 'inclusiveDiscountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'otherVat', 'otherVatValue', 'total', 'originalPrice', 'inclusivePrice'], 'number'],
            [['createdDate', 'editedDate', 'syncDate','subsID', 'pendingOrder', 'flagPending', 'promotionVoucherCode', 'tempOrderID', 'localID', 'flagHoldOrder', 'flagFireOrder', 'flagMemberPromotionVoucher', 
                'platformFee', 'dppValue'], 'safe'],
            [['salesNum', 'salesType', 'promotionVoucherCode'], 'string', 'max' => 50],
            [['ID'], 'safe', 'on' => 'NEW_INSTALL'],
            [['cancelNotes', 'customMenuName', 'createdBy', 'editedBy'], 'string', 'max' => 100],
            [['notes'], 'string', 'max' => 300],
            [['notes', 'cancelNotes'], 'default', 'value' => ''],
            [['customMenuName', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'batchID' => 'Batch ID',
            'menuRefID' => 'Menu Ref ID',
            'menuGroupID' => 'Menu Group ID',
            'menuID' => 'Menu ID',
            'customMenuName' => 'Custom Menu Name',
            'qty' => 'Qty',
            'price' => 'Price',
            'originalPrice' => 'Original Price',
            'discount' => 'Discount',
            'otherTax' => 'Other Tax',
            'vat' => 'Vat',
            'otherTaxOnVat' => 'Other Tax On Vat',
            'total' => 'Total',
            'notes' => 'Notes',
            'statusID' => 'Status ID',
            'promotionDetailID' => 'Promotion Detail ID',
            'menuPromotionID' => 'Menu Promotion ID',
            'cancelNotes' => 'Cancel Notes',
            'salesType' => 'Sales Type',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['menuCategoryID'] = function ($model) {
            return $model->menu->menuCategoryDetail->menuCategoryID;
        };
        $fields['menuCategoryCode'] = function ($model) {
            return $model->menu->menuCategoryDetail->menuCategory->menuCategoryCode;
        };
        $fields['menuCategoryDetailID'] = function ($model) {
            return $model->menu->menuCategoryDetailID;
        };
        $fields['menuCategoryDetailCode'] = function ($model) {
            return $model->menu->menuCategoryDetail->menuCategoryDetailCode;
        };
        $fields['menuID'] = function ($model) {
            return $model->menu->menuID;
        };
        $fields['menuName'] = function ($model) {
            return $model->menu->menuName;
        };
        $fields['menuShortName'] = function ($model) {
            return $model->menu->menuShortName;
        };
        $fields['menuFlagTax'] = function ($model) {
            return $model->getFlagTax();
        };
        $fields['customMenuName'] = function ($model) {
            return $model->customMenuName;
        };
        $fields['menuCode'] = function ($model) {
            return $model->menu->menuCode;
        };
        $fields['qty'] = function ($model) {
            return (float) $model->qty;
        };
        $fields['price'] = function ($model) {
            return (float) $model->price;
        };
        $fields['displayPriceValue'] = function ($model) {
            return (float) $model->getDisplayPriceValue();
        };
        $fields['originalPrice'] = function ($model) {
            return (float) $model->originalPrice;
        };
        $fields['priceTotal'] = function ($model) {
            return (float) $model->getMenuPrice();
        };
        $fields['discount'] = function ($model) {
            return (float) $model->discount;
        };
        $fields['discountTotal'] = function ($model) {
            return (float) $model->getMenuDiscount($model->promotion);
        };
        $fields['otherTax'] = function ($model) {
            return (float) $model->otherTax;
        };
        $fields['otherTaxTotal'] = function ($model) {
            return (float) $model->getMenuOtherTax();
        };
        $fields['vat'] = function ($model) {
            return (float) $model->vat;
        };
        $fields['vatTotal'] = function ($model) {
            return (float) $model->getMenuTax();
        };
        $fields['otherVat'] = function ($model) {
            return (float) $model->otherVat;
        };
        $fields['otherVatTotal'] = function ($model) {
            return (float) $model->getMenuOtherVat();
        };
        $fields['total'] = function ($model) {
            return (float) $model->getTotal();
        };
        $fields['menuTotal'] = function ($model) {
            return (float) $model->getMenuTotal($model->salesHead);
        };
        $fields['statusName'] = function ($model) {
            return $model->status->statusName;
        };
        $fields['promotionTypeID'] = function ($model) {
            return $model->promotion ? $model->promotion->promotionTypeID : 0;
        };
        $fields['promotionDetailName'] = function ($model) {
            $promotionDetailName = null;
            if ($model->promotion) {
                $promotionDetailName = $model->promotion->notes;
                if ($model->promotionVoucherCode && $model->promotionVoucherCode != '') {
                    $promotionDetailName = $model->promotion->notes . " - " . $model->promotionVoucherCode;
                    if (strlen($model->promotionVoucherCode) >= 6) {
                        $promotionVoucherCode = substr($model->promotionVoucherCode, -6);
                        $promotionDetailName = $model->promotion->notes . " - " . substr_replace($promotionVoucherCode, str_repeat('x', strlen($promotionVoucherCode)-3), 0, -3);
                    }
                }
            }
            return $promotionDetailName;
        };
        $fields['promotionPaymentMethodID'] = function ($model) {
            return $model->promotion ? (int) $model->promotion->paymentMethodID : 0;
        };
        $fields['packages'] = function ($model) {
            return $model->childSalesMenus;
        };
        $fields['extras'] = function ($model) {
            return $model->salesExtras;
        };
        $fields['lastModifiedBy'] = function ($model) {
            $creator = $model->createdBy ? ($model->creator ? $model->creator->fullName : $model->createdBy) : '-';
            $editor = $model->editedBy ? ($model->editor ? $model->editor->fullName : $model->editedBy) : '-';
            return $model->editedBy !== '' ? $editor : $creator;
        };
        $fields['lastCreatedBy'] = function ($model) {
            $creator = $model->createdBy ? ($model->creator ? $model->creator->fullName : $model->createdBy) : '-';
            return $creator;
        };
        
        $fields['stationID'] = function ($model) {
            return $model->branchMenu ? $model->branchMenu->stationID : 0;
        };
        $fields['allMenuDiscountTotal'] = function ($model) {
            return (float) $model->getAllMenuDiscountTotal();
        };
        $fields['imageUrl'] = function ($model) {
            return $model->menu->imageUrl;
        };
        $fields['flagCustomerPrint'] = function ($model) {
            return $model->menu->flagCustomerPrint;
        };

        $fields['salesMenuCompletionKitchen'] = function ($model) {
            return $model->salesMenuCompletionKitchen;
        };
        
        $fields['salesMenuCompletionChecker'] = function ($model) {
            return $model->salesMenuCompletionChecker;
        };
        
        $fields['mainMenuID'] = function ($model) {
            return $model->salesMenuRelated ? $model->salesMenuRelated->mainMenuID : null;
        };
        $fields['flagHoldOrder'] = function ($model) {
            return $model->statusID === 46;
        };
        $fields['rewardType'] = function ($model) {
            return $model->salesRewardMenu ? $model->salesRewardMenu->rewardType : null;
        };
        $fields['flagMemberPromotionVoucher'] = function ($model) {
            return ($model->promotionVoucherCode && $model->promotionVoucherCode != '') && ($model->promotion && !in_array($model->promotion->voucherSourceID, [1,7]));
        };
        $fields['voucherSourceID'] = function ($model) {
            if ($model->promotion) return $model->promotion->voucherSourceID;
        };
        $fields['salesProcessMenu'] = function ($model) {
            return $model->salesProcessMenu;
        };
        $fields['flagLuxuryItem'] = function ($model) {
            return $model->getFlagLuxuryItem();
        };

        return $fields;
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function getMenu() {
        return $this->hasOne(Menu::class, ['menuID' => 'menuID']);
    }

    public function getBranchMenu() {
        $branchID = Setting::getCurrentBranch();

        return $this->hasOne(BranchMenu::class, ['menuID' => 'menuID'])
                ->andOnCondition([BranchMenu::tableName().'.branchID' => $branchID]);
    }

    public function getParentSalesMenu() {
        return $this->hasOne(SalesMenu::class, ['ID' => 'menuRefID']);
    }

    public function getChildSalesMenus() {
        return $this->hasMany(SalesMenu::class, ['menuRefID' => 'ID'])
                ->andOnCondition(['AND', 'ID <> menuRefID', 'menuRefID <> 0']);
    }

    public function getSalesMenuCompletionKitchen() {
        return $this->hasMany(SalesMenuCompletion::class, ['salesMenuID' => 'ID'])
                ->andOnCondition(['typeID' => 1])
                ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesMenuCompletionChecker() {
        return $this->hasMany(SalesMenuCompletion::class, ['salesMenuID' => 'ID'])
                ->andOnCondition(['typeID' => 2])
                ->orderBy(SalesMenuCompletion::tableName() . '.completedDate DESC');
    }

    public function getSalesExtras() {
        return $this->hasMany(SalesMenuExtra::class,
                ['salesNum' => 'salesNum', 'menuDetailID' => 'ID']);
    }

    public function getPromotion() {
        return $this->hasOne(PromotionHead::class,
                ['promotionID' => 'promotionDetailID']);
    }

    public function getStatus() {
        return $this->hasOne(Status::class, ['statusID' => 'statusID']);
    }

    public function getCreator() {
        return $this->hasOne(PosUser::class, ['username' => 'createdBy']);
    }

    public function getEditor() {
        return $this->hasOne(PosUser::class, ['username' => 'editedBy']);
    }

    public function getMenuGroup() {
        return $this->hasOne(MenuGroup::class, ['menuGroupID' => 'menuGroupID']);
    }

    public function getSalesMenuRelated() {
        return $this->hasOne(SalesMenuRelated::class, ['salesMenuID' => 'localID']);
    }

    public function getSalesRewardMenu() {
        return $this->hasOne(SalesRewardMenu::class, ['ID' => 'ID', 'localID' => 'localID']);
    }

    public function getSalesProcessMenu() {
        return $this->hasOne(SalesProcessMenu::class, ['salesMenuID' => 'ID', 'salesNum' => 'salesNum']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;
        $setting = Setting::find()
            ->where(['key1' => 'POS'])
            ->andWhere(['key2' => 'ODS Mode'])
            ->one();
        
        $status = 13;
        if ($setting) {
            $mode = $setting->value1;
            if ($mode) {
                if ($mode == 3) {
                    $status = 34;
                }
                if ($mode == 4) {
                    $status = 14;
                }
            }
        }
        
        // @Notes: Status 1 = New, 12 = Cancelled, 13 = Preparing, 19 = Print Cancelled
        if ($this->statusID == 1) {
            $this->statusID = $status;
            if((isset($this->flagHoldOrder) && $this->flagHoldOrder === true) && !isset($this->flagFireOrder)) {
                $this->statusID = 46;
            }
        } else if ($this->statusID == 12) {
            $this->statusID = 19;
        } else if((isset($this->flagFireOrder) && $this->flagFireOrder === true) && $this->statusID == 46) {
            $this->statusID = $status;
        } else if(isset($this->pendingOrder)) {
            if ($this->pendingOrder == true) {
                $this->flagPending = 1;
            }else{
                $this->statusID = 14;
                $this->flagPending = 0;
            }
        } else {
            $this->flagPending = 1;
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public function calculateTotal($inclusiveMenuTemplateID = 0, $inclusivePrice = 0, $billDiscount = 0, $promotionID = 0, $otherTaxBillDiscount = 0) {
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';

        $discountBill = 0;
        if($this->otherTax >= 0 || $this->vat >= 0 || $this->otherVat >= 0){
            $discountBill = SalesHead::calculateDiscountHead($this, $promotionID);
        }

        $mapBranchModel = $this->getMapBranchModel();
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $mapBranchModel->menuTemplateID, 'menuID' => $this->menuID])
            ->one();

        $specialPriceIdxByMenu = SpecialPriceHead::getSpecialPriceMenuList($this->menuID, $mapBranchModel->menuTemplateID);
        $specialPrice = isset($specialPriceIdxByMenu[$this->menuID]) ? $specialPriceIdxByMenu[$this->menuID]['specialPrice'] : 0;
        $flagInclusive = SalesHead::getInclusiveFlag($this->salesHead->branchID, $this->salesHead->visitPurposeID);

        $applyPrice = $this->price;
        if ($this->price == 0 && $this->promotionDetailID > 0) {
            $applyPrice = 0;
        } else if ($specialPrice > 0) {
            $applyPrice = $specialPrice;
            if ($this->price != $this->originalPrice && $this->price != $specialPrice ||
                $this->price == $this->originalPrice && $this->statusID != 1) {
                $applyPrice = $this->price;
            }
        } else {
            if ($this->price != $this->originalPrice && $this->statusID != 1) {
                $applyPrice = $this->price;
            } else {
                $applyPrice = $flagInclusive == MenuTemplateHead::INCLUSIVE_YES ? $this->inclusivePrice : $this->price;
            }
        }

        $subtotal = (float) $this->qty * $applyPrice;
        $discount = (float) $this->discount / 100 * $subtotal;

        $detailPromotionModel = PromotionHead::findOne($this->promotionDetailID);
        if ($detailPromotionModel) {
            if ($detailPromotionModel->promotionTypeID == 9) {
                if ($inclusiveMenuTemplateID > 0) {
                    if ($detailPromotionModel->discount > $inclusivePrice) {
                        $discount = $inclusivePrice * $this->qty;
                    } else {
                        $discount = $detailPromotionModel->discount * $this->qty;
                    }
                } else {
                    if ($detailPromotionModel->discount > $this->price) {
                        $discount = $this->price * $this->qty;
                    } else {
                        $discount = $detailPromotionModel->discount * $this->qty;
                    }
                }                
            } else if ($detailPromotionModel->promotionTypeID == 1 || $detailPromotionModel->promotionTypeID == 10) {
                if ($inclusiveMenuTemplateID > 0) {
                    $discount = ($this->qty * $inclusivePrice) * $detailPromotionModel->discount / 100;
                }
            }
        }

        if ($flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            if($this->menuGroupID == 0) {
                $this->total = (float) ($this->qty * $applyPrice) - $discount;
            } else if ($this->menuGroupID > 0) {
                $this->total = (float) ($this->qty * $inclusivePrice) - $discount;
            }
            
            $this->discountValue = $discount;
            
            $this->otherTaxValue = (float) ($this->qty * $this->price) / 100 * $this->otherTax;
            
            if ($taxCalculationType == 2) {
                $subtotalAfterDisc = $subtotal - ($discount + $discountBill + $billDiscount);
            } else {
                $subtotalAfterDisc = $subtotal;
            }

            $subtotalPpn = $subtotal - ($discount + $discountBill + $billDiscount);
            if ($this->otherTaxOnVat == 0) {
                $this->vatValue = (float) $subtotalAfterDisc / 100 * $this->vat;
                $this->otherVatValue = (float) ($subtotal - ($discount + $discountBill + $billDiscount)) / 100 * $this->otherVat;   
                if (isset($this->flagLuxuryItem)) {
                    $dppValue = CalculateTotal::getDppValue(
                        $this->flagLuxuryItem,
                        $this->otherTaxOnVat,
                        $subtotalPpn,
                        $this->otherTaxValue
                    );
                    $this->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                        $dppValue,
                        $this->otherVat
                    );
                }
            } else {
                $this->vatValue = (float) ($subtotalAfterDisc + $this->otherTaxValue) / 100 * $this->vat;
                $this->otherVatValue = (float) (($subtotal - ($discount + $discountBill + $billDiscount)) + $this->otherTaxValue) / 100 * $this->otherVat;
                if (isset($this->flagLuxuryItem)) {
                    $dppValue = CalculateTotal::getDppValue(
                        $this->flagLuxuryItem,
                        $this->otherTaxOnVat,
                        $subtotalPpn,
                        $this->otherTaxValue
                    );
                    $this->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                        $dppValue,
                        $this->otherVat
                    );
                }
            }
        } else {
            if ($this->menuGroupID == 0 && $this->price != $this->originalPrice && $this->promotionDetailID == 0) {
                $this->price = $applyPrice;
            }

            $otherTax = (float) $this->otherTax / 100 * ($subtotal - ($otherTaxCalculationType == 2 ? $discount + $discountBill + $billDiscount : 0));
            if ($otherTaxBillDiscount > 0) {
                $otherTax = (float) $this->otherTax / 100 * ($subtotal - ($otherTaxCalculationType == 2 ? $discount + $discountBill + $otherTaxBillDiscount : 0));
            }
            if ($otherTax < 0) {
                $otherTax = 0;
            }
            if ($this->platformFee > 0) {
                $otherTax = $otherTax + $this->platformFee;
            }

            if ($this->otherTaxOnVat == 0) {
                $vat = (float) $this->vat / 100 * ($subtotal - ($taxCalculationType == 2 ? $discount + $discountBill : 0));
            } else {
                $vat = (float) $this->vat / 100 * ($subtotal - ($taxCalculationType == 2 ? $discount + $discountBill : 0) + $otherTax);
            }

            //$this->total = $subtotal - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $discount + $discountBill) + $otherTax + $vat;   
            $this->discountValue = $discount; //(float) $this->qty * $this->price / 100 * $this->discount;
            $this->otherTaxValue = (float) (($this->qty * $this->price) - ($otherTaxCalculationType == 2 ? $discount + $discountBill + $billDiscount : 0)) / 100 * $this->otherTax;
            if ($otherTaxBillDiscount > 0) {
                $this->otherTaxValue = (float) (($this->qty * $this->price) - ($otherTaxCalculationType == 2 ? $discount + $discountBill + $otherTaxBillDiscount : 0)) / 100 * $this->otherTax;
            }
            if ($this->otherTaxValue < 0) {
                $this->otherTaxValue = 0;
            }
            if ($this->platformFee > 0) {
                $this->otherTaxValue = $this->otherTaxValue + $this->platformFee;
            }
            
            if ($taxCalculationType == 2) {
                $subtotalAfterDisc = $subtotal - ($discount + $discountBill + $billDiscount);
            } else {
                $subtotalAfterDisc = $subtotal;
            }

            if ($otherTaxCalculationType == 1) {
                $otherTaxBillDiscount = $billDiscount;
            }
            
            if ($this->otherTaxOnVat == 0) {
                $subtotalAfterDiscount = $subtotal - ($discount + $discountBill + $otherTaxBillDiscount);
                $this->vatValue = (float) $this->vat / 100 * $subtotalAfterDisc;
                $this->otherVatValue = (float) $this->otherVat / 100 * ($subtotal - ($discount + $discountBill + $otherTaxBillDiscount));
                if (isset($this->flagLuxuryItem)) {
                    $dppValue = CalculateTotal::getDppValue(
                        $this->flagLuxuryItem,
                        $this->otherTaxOnVat,
                        $subtotalAfterDiscount,
                        $this->otherTaxValue
                    );
                    $this->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                        $dppValue,
                        $this->otherVat
                    );
                }
                

                $this->dppValue = $dppValue;
            } else {
                $subtotalAfterDiscount = $subtotal - ($discount + $discountBill + $otherTaxBillDiscount);
                $this->vatValue = (float) $this->vat / 100 * ($subtotalAfterDisc + $otherTax);
                $this->otherVatValue = (float) $this->otherVat / 100 * ($subtotal - ($discount + $discountBill + $otherTaxBillDiscount) + $otherTax);
                if (isset($this->flagLuxuryItem)) {
                    $dppValue = CalculateTotal::getDppValue(
                        $this->flagLuxuryItem,
                        $this->otherTaxOnVat,
                        $subtotalAfterDiscount,
                        $this->otherTaxValue
                    );
                    $this->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                        $dppValue,
                        $this->otherVat
                    );
                }
                

                $this->dppValue = $dppValue;
            }

            if ($this->otherVatValue < 0) {
                $this->otherVatValue = 0;
            }

            $this->total = ($this->qty * $this->price) - $this->discountValue + $this->otherTaxValue + $this->vatValue + $this->otherVatValue;
        }
    }

    public function getMenuPrice() {
        $parentQty = 1;
        $netPrice = $this->price;

        if ($this->childSalesMenus) {
            foreach ($this->childSalesMenus as $salesMenu) {
                $netPrice += $salesMenu->qty * $salesMenu->price;
            }
        }

        if ($this->salesExtras) {
            foreach ($this->salesExtras as $salesExtra) {
                $netPrice += $salesExtra->qty * $salesExtra->price;
            }
        }

        if ($this->parentSalesMenu) {
            if ($this->ID <> $this->parentSalesMenu->ID) {
                $parentQty = $this->parentSalesMenu->qty;
            }
        }

        return (float) $netPrice * $this->qty * $parentQty;
    }

    // @Notes: if price = 0, then it will fetch the zeroValueText of the menu
    public function getMenuPriceDisplay() {
        $netPrice = $this->getMenuPrice();

        if ($netPrice == 0) {
            return $this->menu->zeroValueText;
        }
        return $netPrice;
    }

    public function getMenuDiscount($promotionModel) {
        $discount = 0;
        if ($promotionModel) {
            if (in_array($promotionModel->promotionTypeID,[9, 12, 14, 15, 17])) {
                $discount = $this->discountValue;
            } else {
                $discount = $this->discount / 100 * $this->price * $this->qty;
            }
        }

        return (float) $discount;
    }

    public function getMenuOtherTax() {
        $parentQty = $this->qty;
        $otherTaxValue = $this->otherTaxValue;

        if ($this->childSalesMenus) {
            foreach ($this->childSalesMenus as $salesMenu) {
                $otherTaxValue += $salesMenu->otherTaxValue * $parentQty;
            }
        }

        if ($this->salesExtras) {
            foreach ($this->salesExtras as $salesExtra) {
                $otherTaxValue += $salesExtra->otherTaxValue * $parentQty;
            }
        }

        if ($this->parentSalesMenu) {
            if ($this->ID <> $this->parentSalesMenu->ID) {
                $otherTaxValue = $this->parentSalesMenu->otherTaxValue * $parentQty;
            }
        }

        return (float) $otherTaxValue;
    }

    public function getMenuTax() {
        $parentQty = $this->qty;
        $vatValue = $this->vatValue;

        if ($this->childSalesMenus) {
            foreach ($this->childSalesMenus as $salesMenu) {
                $vatValue += $salesMenu->vatValue * $parentQty;
            }
        }

        if ($this->salesExtras) {
            foreach ($this->salesExtras as $salesExtra) {
                $vatValue += $salesExtra->vatValue * $parentQty;
            }
        }

        if ($this->parentSalesMenu) {
            if ($this->ID <> $this->parentSalesMenu->ID) {
                $vatValue = $this->parentSalesMenu->vatValue * $parentQty;
            }
        }

        return (float) $vatValue;
    }

    public function getFlagTax() {
        if ($this->menuRefID > 0 && $this->menuGroupID > 0) {
            $salesMenuParent = SalesMenu::find()
                ->with('menu')
                ->where(['localID' => $this->menuRefID])
                ->one();
            if ($salesMenuParent) {
                $parentFlagTax = $salesMenuParent->menu->flagTax;
                $flagSeparateTaxCalculation = $salesMenuParent->menu->flagSeparateTaxCalculation;
                if ($flagSeparateTaxCalculation === 0) {
                    return $parentFlagTax;
                }
            }
        }
        return $this->menu->flagTax;
    }

    public function getFlagLuxuryItem() {
        return $this->menu->flagLuxuryItem ? $this->menu->flagLuxuryItem : 0;
    }

    public function getMenuOtherVat() {
        $parentQty = $this->qty;
        $otherVatValue = $this->otherVatValue;

        if ($this->childSalesMenus) {
            foreach ($this->childSalesMenus as $salesMenu) {
                $otherVatValue += $salesMenu->otherVatValue * $parentQty;
            }
        }

        if ($this->salesExtras) {
            foreach ($this->salesExtras as $salesExtra) {
                $otherVatValue += $salesExtra->otherVatValue * $parentQty;
            }
        }

        if ($this->parentSalesMenu) {
            if ($this->ID <> $this->parentSalesMenu->ID) {
                $otherVatValue = $this->parentSalesMenu->otherVatValue * $parentQty;
            }
        }

        return (float) $otherVatValue;
    }

    public function getMenuTotal($salesHead) {
        $netPrice = $this->getMenuPrice();
        $discount = $this->getMenuDiscount($this->promotion);
        $otherTax = $this->getMenuOtherTax();
        $vat = $this->getMenuTax();
        $taxCalculationType = $salesHead->branch['posTaxCalculationID'];
        $otherTaxCalculationType = $salesHead->branch['posOtherTaxCalculationID'];
        $promotionID = $this->salesHead->promotionID;

        $discountBill = 0;
        if($this->otherTax >= 0 || $this->vat >= 0){
            $discountBill = SalesHead::calculateDiscountHead($this, $promotionID);
        }
        return $netPrice - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $discount + $discountBill) + $otherTax + $vat;
    }
    
    public function getDisplayPriceValue() {
        $price = 0;
        if ($this->salesHead->flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            $price = $this->inclusivePrice;
        } else {
            $price = $this->price;
        }
        return $price;
    }

    public function getTotal() {
        $total = 0;
        if ($this->salesHead->flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            $total = $this->inclusivePrice * $this->qty - $this->inclusiveDiscountValue;
        } else {
            $total = $this->total;
        }
        return $total;
    }

    public static function findActive() {
        return SalesMenu::find()->where(['IN', SalesMenu::tableName() .'.statusID', [13, 14, 34, 46]]);
    }

    public static function findMainMenus($salesNum, $statusID, $batchID, $salesMenuID = null, $printOnlyPackage = false, $flagFireOrderIDs = []) {
        $statusID = $statusID == 13 ? [13,14,34, 46] : [$statusID];

        $batchIDFilter = $batchID == -1 ? [] : ['batchID' => $batchID];
        $salesMenu = SalesMenu::find()
                ->with('menu')
                ->with('childSalesMenus.menu')
                ->with('salesExtras.menuExtra')
                ->joinWith('branchMenu')
                ->andWhere(['salesNum' => $salesNum]);
        if (!$printOnlyPackage) {
            $salesMenu->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ' . SalesMenu::tableName() . '.ID'
            ]);
        }
        $salesMenu->andFilterWhere(['IN', SalesMenu::tableName() .'.statusID', $statusID])
            ->andFilterWhere($batchIDFilter);
        if ($salesMenuID) {
            $salesMenu->andFilterWhere(['=', SalesMenu::tableName() .'.ID', SalesMenu::checkSalesMenuPackagePrint($salesMenuID, $printOnlyPackage)]);
        }

        if ($flagFireOrderIDs && count($flagFireOrderIDs) > 0) {
            $salesMenuFireKitchen = SalesMenu::find()
                ->where(['IN', 'ID', $flagFireOrderIDs])
                ->andWhere(['IN', 'statusID', [13, 14, 34]]);
            $salesMenu = $salesMenu->union($salesMenuFireKitchen);
        }

        $salesMenu->orderBy(SalesMenu::tableName() . '.ID');
        
        $hubMenu = HubMenu::find()->all();
        if ($hubMenu && !$printOnlyPackage) {
            $salesLink = SalesLink::find()
                    ->andWhere(['OR',
                    ['salesNum' => $salesNum],
                    ['linkSalesNum' => $salesNum],
                ])->one();
            
            $linkSalesNum = $salesLink ? $salesLink->linkSalesNum : '';

            $linkSalesMenu = SalesMenu::find()
                ->with('menu')
                ->with('childSalesMenus.menu')
                ->with('salesExtras.menuExtra')
                ->joinWith('branchMenu')
                ->andWhere(['salesNum' => $linkSalesNum])
                ->andWhere(['OR',
                    ['menuRefID' => 0],
                    'menuRefID = ' . SalesMenu::tableName() . '.ID'
                ])
                ->andFilterWhere(['IN', SalesMenu::tableName() .'.statusID', $statusID])
                ->andFilterWhere($batchIDFilter)
                ->andFilterWhere(['=', SalesMenu::tableName() .'.ID', $salesMenuID])
                ->orderBy(SalesMenu::tableName() . '.ID');

                $salesMenu = $salesMenu->union($linkSalesMenu);
        }
        return $salesMenu;
    }

    public static function checkSalesMenuPackagePrint($salesMenuID, $printOnlyPackage) {
        if (!$printOnlyPackage) {
            return SalesMenu::findOne($salesMenuID)->ID;
        } else {
            $salesMenuHead = SalesMenu::findOne($salesMenuID);
            $salesMenu = SalesMenu::find()
                ->where(['=', SalesMenu::tableName() . '.ID', $salesMenuHead->menuRefID])
                ->andWhere([SalesMenu::tableName() . '.menuGroupID' => 0])
                ->one();
            return $salesMenu->ID;
        }
    }

    public static function getNewBatchID($salesNum) {
        $model = SalesMenu::find()
            ->andWhere(['salesNum' => $salesNum])
            ->orderBy('batchID DESC')
            ->one();

        if ($model) {
            return $model->batchID + 1;
        }
        return 1;
    }

    private function getMapBranchModel() {
        $salesHead = SalesHead::find()->andWhere(['salesNum' => $this->salesNum])->one();
        $mapBranchModel = MapBranchVisitPurpose::find()
            ->andWhere(['branchID' => $salesHead->branchID, 'visitPurposeID' => $salesHead->visitPurposeID])
            ->one();

        if ($mapBranchModel) {
            return $mapBranchModel;
        }
        return NULL;
    }
    
    public function getDataAsArray() {
        $salesNum = $this->salesNum;
        $connection = Yii::$app->getDb();

        $salesMenuExtraRawQuery = "SELECT a.*, d.menuExtraName, d.menuExtraShortName,
        CASE WHEN b.flagInclusive = 1 THEN (a.total + a.discountValue) / a.qty ELSE a.price END AS displayPriceValue,
        (a.qty * a.price * c.qty) AS priceTotal
        FROM tr_salesmenuextra a
        JOIN tr_saleshead b on a.salesNum = b.salesNum
        JOIn tr_salesmenu c on a.menuDetailID = c.ID AND a.salesNum = c.salesNum
        JOIN ms_menuextra d on a.menuExtraID = d.menuExtraID
        WHERE a.salesNum = '$salesNum';";

        $extraResult = $connection->createCommand($salesMenuExtraRawQuery)->queryAll();
        $extraData = [];
        foreach ($extraResult as $detail) {
            $detail['ID'] = (int) $detail['ID'];
            $detail['localID'] = (int) $detail['localID'];
            $detail['menuDetailID'] = (int) $detail['menuDetailID'];
            $detail['menuExtraID'] = (int) $detail['menuExtraID'];
            $detail['statusID'] = (int) $detail['statusID'];

            $detail['qty'] = (float) $detail['qty'];
            $detail['price'] = (float) $detail['price'];
            $detail['displayPriceValue'] = (float) $detail['displayPriceValue'];
            $detail['priceTotal'] = (float) $detail['priceTotal'];
            $detail['discount'] = (float) $detail['discount'];
            $detail['discountValue'] = (float) $detail['discountValue'];
            $detail['otherTax'] = (float) $detail['otherTax'];
            $detail['otherTaxValue'] = (float) $detail['otherTaxValue'];
            $detail['vat'] = (float) $detail['vat'];
            $detail['vatValue'] = (float) $detail['vatValue'];
            $detail['otherVat'] = (float) $detail['otherVat'];
            $detail['otherVatValue'] = (float) $detail['otherVatValue'];
            $detail['total'] = (float) $detail['total'];

            $extraData[$detail['menuDetailID']][] = $detail;
        }

        $baseSalesMenuQuery = "SELECT a.*, c.menuCategoryID, d.menuCategoryCode, b.menuCategoryDetailID, c.menuCategoryDetailCode, b.menuName, b.menuShortName, 
        CASE 
            WHEN l.menuID IS NULL THEN b.flagTax 
            ELSE (CASE WHEN m.flagSeparateTaxCalculation = 0 THEN m.flagTax ELSE b.flagTax END) 
        END AS menuFlagTax,
        b.menuCode, 
        CASE WHEN sh.flagInclusive = 1 THEN a.inclusivePrice ELSE a.price END AS displayPriceValue, 
        (a.price * a.qty) + (a.qty * COALESCE(smc.childPriceTotal, 0)) + (a.qty * COALESCE(sme.menuExtraTotal, 0)) AS priceTotal, 
        CASE 
            WHEN f.promotionID IS NOT NULL THEN 
                CASE 
                    WHEN f.promotionTypeID IN (9, 12, 14, 15, 17) THEN a.discountValue
                    ELSE (a.discount / 100 * a.price * a.qty)
                END
            ELSE 0 
        END AS discountTotal,
        (a.otherTaxValue) + (a.qty * COALESCE(smc.childOtherTaxTotal, 0)) + (a.qty * COALESCE(sme.extraOtherTaxTotal, 0)) AS otherTaxTotal, 
        (a.vatValue) + (a.qty * COALESCE(smc.childVatTotal, 0)) + (a.qty * COALESCE(sme.extraVatTotal, 0)) AS vatTotal,
        (a.otherVatValue) + (a.qty * COALESCE(smc.childOtherVatTotal, 0)) + (a.qty * COALESCE(sme.extraOtherVatTotal, 0)) AS otherVatTotal, 
        CASE
            WHEN sh.flagInclusive = 1 THEN (a.inclusivePrice * a.qty) - a.inclusiveDiscountValue
            ELSE a.total
        END AS total,
        ((a.price * a.qty) + (a.qty * COALESCE(smc.childPriceTotal, 0)) + (a.qty * COALESCE(sme.menuExtraTotal, 0))) 
        +
        ((a.otherTaxValue) + (a.qty * COALESCE(smc.childOtherTaxTotal, 0)) + (a.qty * COALESCE(sme.extraOtherTaxTotal, 0)))
        +
        ((a.vatValue) + (a.qty * COALESCE(smc.childVatTotal, 0)) + (a.qty * COALESCE(sme.extraVatTotal, 0)))
        +
        ((a.otherVatValue) + (a.qty * COALESCE(smc.childOtherVatTotal, 0)) + (a.qty * COALESCE(sme.extraOtherVatTotal, 0)))
        - 
        ((CASE 
            WHEN f.promotionID IS NOT NULL THEN 
                CASE 
                    WHEN f.promotionTypeID IN (9, 12, 14, 15, 17) THEN a.discountValue
                    ELSE (a.discount / 100 * a.price * a.qty)
                END
            ELSE 0 
        END) + COALESCE(smc.childDiscountTotal, 0) + COALESCE(sme.extraDiscountTotal, 0)) AS menuTotal, 
        e.statusName, COALESCE(f.promotionTypeID, 0) AS promotionTypeID, 
        CASE 
            WHEN f.notes IS NOT NULL THEN
                CONCAT(f.notes, CASE 
                    WHEN a.promotionVoucherCode IS NOT NULL AND a.promotionVoucherCode != '' THEN
                    CONCAT(' - ',
                        CASE WHEN LENGTH(a.promotionVoucherCode) >= 6 THEN
                            CONCAT(SUBSTRING(a.promotionVoucherCode, -6),
                                    SUBSTRING(REPEAT('x', LENGTH(a.promotionVoucherCode) - 3), 1))
                        ELSE
                            a.promotionVoucherCode
                        END)
            END)
        END AS promotionDetailName,
        COALESCE(f.paymentMethodID, 0) AS promotionPaymentMethodID, 
        CASE 
            WHEN a.editedBy IS NOT NULL THEN COALESCE(h.fullName, a.editedBy)
            ELSE COALESCE(g.fullName, a.createdBy)
        END AS lastModifiedBy,
        CASE 
	    WHEN a.createdBy = '' THEN '-'
            ELSE COALESCE(g.fullName, a.createdBy) 
        END AS lastCreatedBy,
        i.stationID, 
        (CASE 
            WHEN f.promotionID IS NOT NULL THEN 
                CASE 
                    WHEN f.promotionTypeID IN (9, 12, 14, 15, 17) THEN a.discountValue
                    ELSE (a.discount / 100 * a.price * a.qty)
                END
            ELSE 0 
        END) + COALESCE(smc.childDiscountTotal, 0) + COALESCE(sme.extraDiscountTotal, 0) AS allMenuDiscountTotal,
        b.imageUrl, b.flagCustomerPrint, 
        k.mainMenuID, 
        CASE WHEN a.statusID = 46 THEN TRUE ELSE FALSE END AS flagHoldOrder, 
        n.rewardType, 
        CASE 
            WHEN a.promotionVoucherCode IS NOT NULL AND a.promotionVoucherCode != ''AND f.voucherSourceID NOT IN (1, 7) 
            THEN TRUE
            ELSE FALSE
        END AS flagMemberPromotionVoucher, 
        f.voucherSourceID
        FROM tr_salesmenu a 
        JOIN tr_saleshead sh ON a.salesNum = sh.salesNum
        JOIN ms_menu b ON a.menuID = b.menuID
        JOIN ms_menucategorydetail c ON b.menuCategoryDetailID = c.ID
        JOIN ms_menucategory d ON c.menuCategoryID = d.menuCategoryID
        JOIN lk_status e ON a.statusID = e.statusID
        LEFT JOIN ms_promotionhead f ON a.promotionDetailID = f.promotionID
        LEFT JOIN ms_posuser g ON a.createdBy = g.username
        LEFT JOIN ms_posuser h ON a.editedBy = h.username
        LEFT JOIN ms_branchmenu i ON a.menuID = i.menuID
        LEFT JOIN tr_salesmenurelated k ON a.localID = k.salesMenuID
        LEFT JOIN tr_salesmenu l ON a.menuRefID = l.localID AND a.salesNum = l.salesNum
        LEFT JOIN ms_menu m ON l.menuID = m.menuID
        LEFT JOIN tr_salesrewardmenu n ON a.salesNum = n.salesNum AND a.localID = n.localID
        LEFT JOIN 
        (
            SELECT menuRefID, SUM(price * qty) 'childPriceTotal', SUM(discountValue) 'childDiscountTotal',
            SUM(otherTaxValue) 'childOtherTaxTotal', SUM(vatValue) 'childVatTotal', SUM(otherVatValue) 'childOtherVatTotal'
            FROM tr_salesmenu
            WHERE salesNum = '$salesNum' and localID <> menuRefID
            GROUP BY menuRefID
        ) smc ON a.localID = smc.menuRefID
        LEFT JOIN 
        (
            SELECT menuDetailID, SUM(price * qty) 'menuExtraTotal', SUM(discountValue) 'extraDiscountTotal',
            SUM(otherTaxValue) 'extraOtherTaxTotal', SUM(vatValue) 'extraVatTotal', SUM(otherVatValue) 'extraOtherVatTotal'
            FROM tr_salesmenuextra
            WHERE salesNum = '$salesNum'
            GROUP BY menuDetailID
        ) sme ON a.localID = sme.menuDetailID";

        $childMenuRawQuery = $baseSalesMenuQuery . " WHERE a.menuGroupID > 0 AND a.localID <> a.menuRefID AND a.salesNum = '$salesNum';";
        $childMenuResult = $connection->createCommand($childMenuRawQuery)->queryAll();
        $childMenuData = [];
        foreach ($childMenuResult as $detail) {
            $detail['ID'] = (int) $detail['ID'];
            $detail['localID'] = (int) $detail['localID'];
            $detail['batchID'] = (int) $detail['batchID'];
            $detail['menuRefID'] = (int) $detail['menuRefID'];
            $detail['menuGroupID'] = (int) $detail['menuGroupID'];
            $detail['menuID'] = (int) $detail['menuID'];
            $detail['otherTaxOnVat'] = (int) $detail['otherTaxOnVat'];
            $detail['statusID'] = (int) $detail['statusID'];
            $detail['promotionDetailID'] = (int) $detail['promotionDetailID'];
            $detail['menuPromotionID'] = (int) $detail['menuPromotionID'];
            $detail['flagPending'] = (int) $detail['flagPending'];
            $detail['menuCategoryID'] = (int) $detail['menuCategoryID'];
            $detail['menuCategoryDetailID'] = (int) $detail['menuCategoryDetailID'];
            $detail['menuFlagTax'] = (int) $detail['menuFlagTax'];
            $detail['flagLuxuryItem'] = isset($detail['flagLuxuryItem']) ? (int) $detail['flagLuxuryItem'] : 0;
            $detail['promotionTypeID'] = (int) $detail['promotionTypeID'];
            $detail['promotionPaymentMethodID'] = (int) $detail['promotionPaymentMethodID'];
            $detail['flagCustomerPrint'] = (int) $detail['flagCustomerPrint'];
            
            $detail['flagMemberPromotionVoucher'] = (bool) $detail['flagMemberPromotionVoucher'];
            $detail['flagHoldOrder'] = (bool) $detail['flagHoldOrder'];

            $detail['qty'] = (float) $detail['qty'];
            $detail['price'] = (float) $detail['price'];
            $detail['displayPriceValue'] = (float) $detail['displayPriceValue'];
            $detail['originalPrice'] = (float) $detail['originalPrice'];
            $detail['priceTotal'] = (float) $detail['priceTotal'];
            $detail['discount'] = (float) $detail['discount'];
            $detail['discountTotal'] = (float) $detail['discountTotal'];
            $detail['otherTax'] = (float) $detail['otherTax'];
            $detail['otherTaxTotal'] = (float) $detail['otherTaxTotal'];
            $detail['vat'] = (float) $detail['vat'];
            $detail['vatTotal'] = (float) $detail['vatTotal'];
            $detail['otherVat'] = (float) $detail['otherVat'];
            $detail['otherVatTotal'] = (float) $detail['otherVatTotal'];
            $detail['total'] = (float) $detail['total'];
            $detail['menuTotal'] = (float) $detail['menuTotal'];
            $detail['allMenuDiscountTotal'] = (float) $detail['allMenuDiscountTotal'];

            $childMenuData[$detail['menuRefID']][] = $detail;
        }

        $salesMenuRawQuery = $baseSalesMenuQuery . " WHERE a.menuGroupID = 0 AND a.salesNum = '$salesNum';";
        $salesMenuResult = $connection->createCommand($salesMenuRawQuery)->queryAll();
        $salesMenuData = [];
        foreach ($salesMenuResult as $detail) {
            $detail['ID'] = (int) $detail['ID'];
            $detail['localID'] = (int) $detail['localID'];
            $detail['batchID'] = (int) $detail['batchID'];
            $detail['menuRefID'] = (int) $detail['menuRefID'];
            $detail['menuGroupID'] = (int) $detail['menuGroupID'];
            $detail['menuID'] = (int) $detail['menuID'];
            $detail['otherTaxOnVat'] = (int) $detail['otherTaxOnVat'];
            $detail['statusID'] = (int) $detail['statusID'];
            $detail['promotionDetailID'] = (int) $detail['promotionDetailID'];
            $detail['menuPromotionID'] = (int) $detail['menuPromotionID'];
            $detail['flagPending'] = (int) $detail['flagPending'];
            $detail['menuCategoryID'] = (int) $detail['menuCategoryID'];
            $detail['menuCategoryDetailID'] = (int) $detail['menuCategoryDetailID'];
            $detail['menuFlagTax'] = (int) $detail['menuFlagTax'];
            $detail['flagLuxuryItem'] = isset($detail['flagLuxuryItem']) ? (int) $detail['flagLuxuryItem'] : 0;
            $detail['promotionTypeID'] = (int) $detail['promotionTypeID'];
            $detail['promotionPaymentMethodID'] = (int) $detail['promotionPaymentMethodID'];
            $detail['flagCustomerPrint'] = (int) $detail['flagCustomerPrint'];

            $detail['flagMemberPromotionVoucher'] = (bool) $detail['flagMemberPromotionVoucher'];
            $detail['flagHoldOrder'] = (bool) $detail['flagHoldOrder'];

            $detail['qty'] = (float) $detail['qty'];
            $detail['price'] = (float) $detail['price'];
            $detail['displayPriceValue'] = (float) $detail['displayPriceValue'];
            $detail['originalPrice'] = (float) $detail['originalPrice'];
            $detail['priceTotal'] = (float) $detail['priceTotal'];
            $detail['discount'] = (float) $detail['discount'];
            $detail['discountTotal'] = (float) $detail['discountTotal'];
            $detail['otherTax'] = (float) $detail['otherTax'];
            $detail['otherTaxTotal'] = (float) $detail['otherTaxTotal'];
            $detail['vat'] = (float) $detail['vat'];
            $detail['vatTotal'] = (float) $detail['vatTotal'];
            $detail['otherVat'] = (float) $detail['otherVat'];
            $detail['otherVatTotal'] = (float) $detail['otherVatTotal'];
            $detail['total'] = (float) $detail['total'];
            $detail['menuTotal'] = (float) $detail['menuTotal'];
            $detail['allMenuDiscountTotal'] = (float) $detail['allMenuDiscountTotal'];
            $detail['packages'] = isset($childMenuData[$detail['menuRefID']]) ? $childMenuData[$detail['menuRefID']] : [];
            $detail['extras'] = isset($extraData[$detail['menuRefID']]) ? $extraData[$detail['menuRefID']] : [];

            $salesMenuData[] = $detail;
        }

        return $salesMenuData;
    }
    
    public function getDataAllAsArray() {
        $salesNum = $this->salesNum;
        $salesNumChild = $this->salesNum . "-";
        $salesMenuData = [];
        $salesHeadParentModel = SalesHead::find()
                ->where(['salesNum' => $salesNum])
                ->one();
        $salesMenuData[0]['salesNum'] = $salesHeadParentModel->salesNum;
        $salesMenuData[0]['salesDateOut'] = $salesHeadParentModel->salesDateOut;
        $salesMenuData[0]['statusID'] = $salesHeadParentModel->statusID;
        $salesMenuData[0]['additionalInfo'] = $salesHeadParentModel->additionalInfo;
        $salesHeadChildModel = SalesHead::find()
            ->where(['like', 'salesNum', $salesNumChild])
            ->orderBy('salesNum')
            ->all();
        $i = 1;
        foreach ($salesHeadChildModel as $detailSalesMenu) {
            if ($salesHeadParentModel->tableID == $detailSalesMenu->tableID) {
                $salesMenuData[$i]['salesNum'] = $detailSalesMenu->salesNum;
                $salesMenuData[$i]['salesDateOut'] = $detailSalesMenu->salesDateOut;
                $salesMenuData[$i]['statusID'] = $detailSalesMenu->statusID;
                $salesMenuData[$i]['additionalInfo'] = $detailSalesMenu->additionalInfo;
                $i++;
            }
        }
        return $salesMenuData;
    }
    
    public function getDataChildAsArray($tableID) {
        $salesNumChild = $this->salesNum . "-";
        
        $salesChildHeadData = [];
        $salesChildHeadModel = SalesHead::find()
            ->where(['like', 'salesNum', $salesNumChild])
            ->andWhere(['is', 'salesDateOut' , null])
            ->andWhere(['tableID' => $tableID])
            ->orderBy('salesNum')
            ->all();
        $index = 0;
        foreach ($salesChildHeadModel as $detailSalesChild) {
            $salesChildMenu = [];
            $salesChildHeadData[$index]['salesNumChild'] = $detailSalesChild->salesNum;
            $salesChildHeadData[$index]['additionalInfo'] = $detailSalesChild->additionalInfo;
            $salesMenuModel = SalesMenu::find()
                ->where(['salesNum' => $detailSalesChild->salesNum])
                ->andWhere(['=', 'menuGroupID', 0])
                ->orderBy('salesNum')
                ->all();
            foreach ($salesMenuModel as $detailSalesMenu) {
                $salesChildMenu[] = $detailSalesMenu;
            }
            $salesChildHeadData[$index]['salesMenuChild'] = $salesChildMenu;
            $salesChildHeadData[$index]['salesPaymentChild'] = $detailSalesChild->salesPayments && count($detailSalesChild->salesPayments) > 0 
                ? $detailSalesChild->salesPayments
                : null;
            $index++;
        }
        return $salesChildHeadData;
    }
    
    public function getAllMenuDiscountTotal() {
        $discountAllTotal = 0;
        $discountMenuPackageChild = 0;
        $discountMenuExtraChild = 0;
        $discountMenuHead = $this->getMenuDiscount($this->promotion);

        $discountMenuPackageChild = SalesMenu::find()
            ->select(['sumPckDiscountValue' => new Expression('SUM(discountValue * ' . $this->qty . ')')])
            ->where(['menuRefID' => $this->ID])
            ->andWhere(['<>', 'ID', $this->ID])
            ->andWhere(['<>', 'menuRefID', 0])
            ->scalar();

        $discountMenuExtraChild = SalesMenuExtra::find()
            ->select(['sumExtDiscountValue' => new Expression('SUM(discountValue * ' . $this->qty . ')')])
            ->where(['salesNum' => $this->salesNum])
            ->andWhere(['menuDetailID' => $this->ID])
            ->scalar();

        $discountAllTotal = $discountMenuHead + $discountMenuPackageChild + $discountMenuExtraChild;
        return (float) $discountAllTotal;
    }

    public static function getMenuPackages($salesNum, $localID){
        $packages = self::find()
            ->select("ms_menu.menuID, ms_menu.menuName, tr_salesmenu.qty, tr_salesmenu.price, tr_salesmenu.originalPrice, tr_salesmenu.inclusivePrice, tr_salesmenu.discount, tr_salesmenu.total")
            ->leftJoin("ms_menu", "ms_menu.menuID = tr_salesmenu.menuID")
            ->andWhere(['!=', 'menuGroupID', 0])
            ->andWhere(['=', 'salesNum', $salesNum])
            ->andWhere(['=', 'menuRefID', $localID])
            ->asArray()->all();
        
        return $packages;
    }

    public static function findLinkSalesHeadsHold($salesNum) {
        $salesLinkArray = SalesLink::find()
            ->select('linkSalesNum')
            ->andWhere(['salesNum' => $salesNum]);

        return self::find()
                ->andWhere(['IN', 'salesNum', $salesLinkArray])
                ->andWhere(['statusID' => 46])
                ->all();
    }

    public static function findSalesOnHold($salesNum) {
        return SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['statusID' => 46])
            ->one();
    }

    public static function findSalesPromotionEsbVoucher($salesNum) {
        $salesModel = SalesMenu::find()
            ->joinWith('promotion')
            ->where([PromotionHead::tableName() . '.voucherSourceID' => 1])
            ->andWhere(['IN', SalesMenu::tableName() .'.salesNum', $salesNum])
            ->all();

        return $salesModel ? true : false;
    }

    public static function findActiveSalesmenu($salesNum) {
        $salesHeadModel = SalesHead::find()
            ->where(['IN', 'salesNum', $salesNum])
            ->orderBy(['tableID' => SORT_ASC])
            ->all();

        $headSales = [];
        $tableID = 0;
        if ($salesHeadModel) {
            foreach ($salesHeadModel as $salesHead) {
                $tempTableID = $salesHead->tableID;
                if ($tempTableID == 0) {
                    array_push($headSales, $salesHead->salesNum);
                } else {
                    if ($tableID != $tempTableID) {
                        array_push($headSales, $salesHead->salesNum);
                        $tableID = $tempTableID;
                    }
                }
            }
        }

        $salesLinkModel = SalesLink::find()
            ->select([
                'salesNum',
                'linkSalesNum',
            ])
            ->where(['IN', 'salesNum', $headSales])
            ->all();

        $linkSales = [];
        if ($salesLinkModel) {
            foreach ($salesLinkModel as $salesLink) {
                array_push($linkSales, $salesLink->linkSalesNum);
            }
        }

        $tempSalesNums = array_diff($headSales, $linkSales);
        $query = Salesmenu::find()
        ->select([
            'mainSalesNum' => new Expression('tr_saleshead.salesNum'),
            'totalQty' => new Expression('SUM(tr_salesmenu.qty)'),
            'tableID' => 'tr_saleshead.tableID'
        ])
        ->from(['tr_saleshead'])
        ->leftJoin("tr_salesmenu", "tr_saleshead.salesNum = tr_salesmenu.salesNum")
        ->where(['IN', 'tr_saleshead.salesNum', $tempSalesNums])
        ->andWhere([
            'OR',
            ['IN', 'tr_salesmenu.statusID', [13, 14, 34]],
            ['tr_saleshead.statusID' => 1]
        ])
        ->groupBy('mainSalesNum');

        $command = $query->createCommand();
        $results = $command->queryAll();
        return $results;
    }

    public static function findActiveSaleslink($salesNum) {
        $query = SalesLink::find()
            ->select([
                'salesNum',
                'linkSalesNum',
            ])
            ->where(['IN', 'salesNum', $salesNum]);

        $command = $query->createCommand();
        $results = $command->queryAll();
        return $results;
    }

    public static function findSalesAppliedEmployee($salesNum) {
        return SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->asArray()
            ->all();
    }

    public static function getFindOutstandingSalesMainRawQuery($branchID) {
      return "SELECT
        tr_salesmenu.*,
        ms_menu.menuID AS masterMenuID,
        ms_menu.menuName,
        ms_menu.menuShortName,
        ms_menu.menuCode,
        ms_menu.flagActive AS flagMenuActive,
        ms_menu.menuCategoryDetailID,
        ms_menu.imageUrl,
        ms_menu.flagCustomerPrint,
        ms_menu.flagTax,
        ms_menu.flagLuxuryItem,
        ms_menu.zeroValueText,
        ms_menu.flagSeparateTaxCalculation,
        ms_menucategory.menuCategoryID,
        ms_menucategory.menuCategoryCode,
        ms_menucategory.menuCategoryDesc,
        ms_menucategorydetail.menuCategoryDetailCode,
        (CASE WHEN tr_saleshead.flagInclusive = 1 THEN tr_salesmenu.inclusivePrice ELSE tr_salesmenu.price END) AS displayPriceValue,
        ROUND(CASE WHEN ms_promotionhead.promotionTypeID IN (9, 12, 14, 15, 17) THEN tr_salesmenu.discountValue ELSE tr_salesmenu.discount / 100 * tr_salesmenu.price * tr_salesmenu.qty END, 4) AS discountTotal,
        ROUND(CASE WHEN tr_saleshead.flagInclusive = 1 THEN tr_salesmenu.inclusivePrice * tr_salesmenu.qty - tr_salesmenu.inclusiveDiscountValue ELSE tr_salesmenu.total END, 4) AS total,
        COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
        ms_promotionhead.paymentMethodID,
        ms_promotionhead.flagPackageContent,
        ms_promotionhead.flagMenuExtra,
        ms_promotionhead.maxSalesPrice,
        ms_promotionhead.promotionID AS masterPromoID,
        ms_promotionhead.flagActive AS flagPromoActive,
        ms_promotionhead.notes AS promotionDetailName,
        ms_promotionhead.voucherSourceID,
        COALESCE(ms_promotionhead.paymentMethodID, 0) AS promotionPaymentMethodID,
        lk_status.statusName,
        ms_branchmenu.stationID,
        tr_salesmenurelated.mainMenuID,
        (CASE WHEN tr_salesmenu.statusID = 46 THEN 1 ELSE 0 END) AS flagHoldOrder,
        tr_salesrewardmenu.rewardType,
        (CASE WHEN tr_salesmenu.promotionVoucherCode AND tr_salesmenu.promotionVoucherCode != '' AND ms_promotionhead.voucherSourceID NOT IN (1, 7) THEN 1 ELSE 0 END) AS flagMemberPromotionVoucher,
        creator.fullName AS creatorFullName,
        editor.fullName AS editorFullName
      FROM
        tr_salesmenu
      LEFT JOIN
        tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
      LEFT JOIN
        tr_salesmenurelated ON tr_salesmenu.localID = tr_salesmenurelated.salesMenuID
      LEFT JOIN
        tr_salesrewardmenu ON tr_salesmenu.ID = tr_salesrewardmenu.ID AND tr_salesmenu.localID = tr_salesrewardmenu.ID
      LEFT JOIN
        tr_salesprocessmenu ON tr_salesmenu.ID = tr_salesprocessmenu.salesMenuID AND tr_salesmenu.salesNum = tr_salesprocessmenu.salesNum
      LEFT JOIN
        ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
      LEFT JOIN
        ms_branchmenu ON tr_salesmenu.menuID = ms_branchmenu.menuID
      LEFT JOIN
        ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
      LEFT JOIN
        ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
      LEFT JOIN
        ms_promotionhead ON tr_salesmenu.promotionDetailID = ms_promotionhead.promotionID
      LEFT JOIN
        ms_posuser creator ON tr_salesmenu.createdBy = creator.username
      LEFT JOIN
        ms_posuser editor ON tr_salesmenu.editedBy = editor.username
      LEFT JOIN
        lk_status ON tr_salesmenu.statusID = lk_status.statusID
      WHERE
        ((tr_salesmenu.menuRefID = 0) OR (tr_salesmenu.menuRefID = tr_salesmenu.ID))
        AND tr_saleshead.branchID = $branchID";
    }

    public static function getFindOutstandingSalesChildRawQuery($branchID) {
      return "SELECT
        childSalesMenus.*,
        ms_menu.menuID AS masterMenuID,
        ms_menu.menuName,
        ms_menu.menuShortName,
        ms_menu.menuCode,
        ms_menu.flagActive AS flagMenuActive,
        ms_menu.menuCategoryDetailID,
        ms_menu.imageUrl,
        ms_menu.flagCustomerPrint,
        ms_menu.flagTax,
        ms_menu.flagLuxuryItem,
        ms_menu.zeroValueText,
        ms_menu.flagSeparateTaxCalculation,
        ms_menucategory.menuCategoryID,
        ms_menucategory.menuCategoryCode,
        ms_menucategory.menuCategoryDesc,
        ms_menucategorydetail.menuCategoryDetailCode,
        ms_menugroup.menuGroupID AS masterGroupID,
        ms_menugroup.menuGroup AS masterGroupName,
        ms_menugroup.flagActive AS masterGroupActive,
        ms_menupackage.menuGroupID AS masterGroupPackageID,
        (CASE WHEN tr_saleshead.flagInclusive = 1 THEN childSalesMenus.inclusivePrice ELSE childSalesMenus.price END) AS displayPriceValue,
        ROUND(CASE WHEN ms_promotionhead.promotionTypeID IN (9, 12, 14, 15, 17) THEN childSalesMenus.discountValue ELSE childSalesMenus.discount / 100 * childSalesMenus.price * childSalesMenus.qty END, 4) AS discountTotal,
        ROUND(CASE WHEN tr_saleshead.flagInclusive = 1 THEN childSalesMenus.inclusivePrice * childSalesMenus.qty - childSalesMenus.inclusiveDiscountValue ELSE childSalesMenus.total END, 4) AS total,
        COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
        ms_promotionhead.paymentMethodID,
        ms_promotionhead.flagPackageContent,
        ms_promotionhead.flagMenuExtra,
        ms_promotionhead.maxSalesPrice,
        ms_promotionhead.promotionID AS masterPromoID,
        ms_promotionhead.flagActive AS flagPromoActive,
        ms_promotionhead.notes AS promotionDetailName,
        ms_promotionhead.voucherSourceID,
        COALESCE(ms_promotionhead.paymentMethodID, 0) AS promotionPaymentMethodID,
        lk_status.statusName,
        ms_branchmenu.stationID,
        tr_salesmenurelated.mainMenuID,
        (CASE WHEN childSalesMenus.statusID = 46 THEN 1 ELSE 0 END) AS flagHoldOrder,
        tr_salesrewardmenu.rewardType,
        (CASE WHEN childSalesMenus.promotionVoucherCode AND childSalesMenus.promotionVoucherCode != '' AND ms_promotionhead.voucherSourceID NOT IN (1, 7) THEN 1 ELSE 0 END) AS flagMemberPromotionVoucher,
        creator.fullName AS creatorFullName,
        editor.fullName AS editorFullName
      FROM
        tr_salesmenu
      LEFT JOIN
        tr_salesmenu childSalesMenus ON tr_salesmenu.ID = childSalesMenus.menuRefID AND childSalesMenus.ID <> childSalesMenus.menuRefID AND childSalesMenus.menuRefID <> 0
      LEFT JOIN
        tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
      LEFT JOIN
        tr_salesmenurelated ON childSalesMenus.localID = tr_salesmenurelated.salesMenuID
      LEFT JOIN
        tr_salesrewardmenu ON childSalesMenus.ID = tr_salesrewardmenu.ID AND childSalesMenus.localID = tr_salesrewardmenu.ID
      LEFT JOIN
        tr_salesprocessmenu ON childSalesMenus.ID = tr_salesprocessmenu.salesMenuID AND childSalesMenus.salesNum = tr_salesprocessmenu.salesNum
      LEFT JOIN
        ms_menu ON childSalesMenus.menuID = ms_menu.menuID
      LEFT JOIN
        ms_branchmenu ON childSalesMenus.menuID = ms_branchmenu.menuID
      LEFT JOIN
        ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
      LEFT JOIN
        ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
      LEFT JOIN
        ms_menugroup ON childSalesMenus.menuGroupID = ms_menugroup.menuGroupID AND tr_salesmenu.menuID = ms_menugroup.menuID
      LEFT JOIN
        ms_menupackage ON ms_menugroup.menuGroupID = ms_menupackage.menuGroupID AND childSalesMenus.menuID = ms_menupackage.menuID
      LEFT JOIN
        ms_promotionhead ON childSalesMenus.promotionDetailID = ms_promotionhead.promotionID
      LEFT JOIN
        ms_posuser creator ON childSalesMenus.createdBy = creator.username
      LEFT JOIN
        ms_posuser editor ON childSalesMenus.editedBy = editor.username
      LEFT JOIN
        lk_status ON childSalesMenus.statusID = lk_status.statusID
      WHERE
        tr_saleshead.branchID = $branchID
        AND childSalesMenus.salesNum IS NOT NULL";
    }

    public static function getSalesMenuMainRawQuery($salesNum) {
        return "SELECT
          tr_salesmenu.*,
          ms_menu.menuID AS masterMenuID,
          ms_menu.menuName,
          ms_menu.menuShortName,
          ms_menu.menuCode,
          ms_menu.flagActive AS flagMenuActive,
          ms_menu.menuCategoryDetailID,
          ms_menu.imageUrl,
          ms_menu.flagCustomerPrint,
          ms_menu.flagTax,
          ms_menu.zeroValueText,
          ms_menu.flagSeparateTaxCalculation,
          ms_menucategory.menuCategoryID,
          ms_menucategory.menuCategoryCode,
          ms_menucategory.menuCategoryDesc,
          ms_menucategorydetail.menuCategoryDetailCode,
          (CASE WHEN tr_saleshead.flagInclusive = 1 THEN tr_salesmenu.inclusivePrice ELSE tr_salesmenu.price END) AS displayPriceValue,
          ROUND(CASE WHEN ms_promotionhead.promotionTypeID IN (9, 12, 14, 15, 17) THEN tr_salesmenu.discountValue ELSE tr_salesmenu.discount / 100 * tr_salesmenu.price * tr_salesmenu.qty END, 4) AS discountTotal,
          ROUND(CASE WHEN tr_saleshead.flagInclusive = 1 THEN tr_salesmenu.inclusivePrice * tr_salesmenu.qty - tr_salesmenu.inclusiveDiscountValue ELSE tr_salesmenu.total END, 4) AS total,
          COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
          ms_promotionhead.paymentMethodID,
          ms_promotionhead.flagPackageContent,
          ms_promotionhead.flagMenuExtra,
          ms_promotionhead.maxSalesPrice,
          ms_promotionhead.promotionID AS masterPromoID,
          ms_promotionhead.flagActive AS flagPromoActive,
          ms_promotionhead.notes AS promotionDetailName,
          ms_promotionhead.voucherSourceID,
          COALESCE(ms_promotionhead.paymentMethodID, 0) AS promotionPaymentMethodID,
          lk_status.statusName,
          ms_branchmenu.stationID,
          tr_salesmenurelated.mainMenuID,
          (CASE WHEN tr_salesmenu.statusID = 46 THEN 1 ELSE 0 END) AS flagHoldOrder,
          tr_salesrewardmenu.rewardType,
          (CASE WHEN tr_salesmenu.promotionVoucherCode AND tr_salesmenu.promotionVoucherCode != '' AND ms_promotionhead.voucherSourceID NOT IN (1, 7) THEN 1 ELSE 0 END) AS flagMemberPromotionVoucher,
          creator.fullName AS creatorFullName,
          editor.fullName AS editorFullName
        FROM
          tr_salesmenu
        LEFT JOIN
          tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
        LEFT JOIN
          tr_salesmenurelated ON tr_salesmenu.localID = tr_salesmenurelated.salesMenuID
        LEFT JOIN
          tr_salesrewardmenu ON tr_salesmenu.ID = tr_salesrewardmenu.ID AND tr_salesmenu.localID = tr_salesrewardmenu.ID
        LEFT JOIN
          tr_salesprocessmenu ON tr_salesmenu.ID = tr_salesprocessmenu.salesMenuID AND tr_salesmenu.salesNum = tr_salesprocessmenu.salesNum
        LEFT JOIN
          ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
        LEFT JOIN
          ms_branchmenu ON tr_salesmenu.menuID = ms_branchmenu.menuID
        LEFT JOIN
          ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
        LEFT JOIN
          ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
        LEFT JOIN
          ms_promotionhead ON tr_salesmenu.promotionDetailID = ms_promotionhead.promotionID
        LEFT JOIN
          ms_posuser creator ON tr_salesmenu.createdBy = creator.username
        LEFT JOIN
          ms_posuser editor ON tr_salesmenu.editedBy = editor.username
        LEFT JOIN
          lk_status ON tr_salesmenu.statusID = lk_status.statusID
        WHERE
          ((tr_salesmenu.menuRefID = 0) OR (tr_salesmenu.menuRefID = tr_salesmenu.ID))
          AND tr_saleshead.salesNum = '$salesNum'";
    }

    public static function getSalesMenuChildRawQuery($salesNum) {
        return "SELECT
          childSalesMenus.*,
          ms_menu.menuID AS masterMenuID,
          ms_menu.menuName,
          ms_menu.menuShortName,
          ms_menu.menuCode,
          ms_menu.flagActive AS flagMenuActive,
          ms_menu.menuCategoryDetailID,
          ms_menu.imageUrl,
          ms_menu.flagCustomerPrint,
          ms_menu.flagTax,
          ms_menu.zeroValueText,
          ms_menu.flagSeparateTaxCalculation,
          ms_menucategory.menuCategoryID,
          ms_menucategory.menuCategoryCode,
          ms_menucategory.menuCategoryDesc,
          ms_menucategorydetail.menuCategoryDetailCode,
          ms_menugroup.menuGroupID AS masterGroupID,
          ms_menugroup.menuGroup AS masterGroupName,
          ms_menugroup.flagActive AS masterGroupActive,
          ms_menupackage.menuGroupID AS masterGroupPackageID,
          (CASE WHEN tr_saleshead.flagInclusive = 1 THEN childSalesMenus.inclusivePrice ELSE childSalesMenus.price END) AS displayPriceValue,
          ROUND(CASE WHEN ms_promotionhead.promotionTypeID IN (9, 12, 14, 15, 17) THEN childSalesMenus.discountValue ELSE childSalesMenus.discount / 100 * childSalesMenus.price * childSalesMenus.qty END, 4) AS discountTotal,
          ROUND(CASE WHEN tr_saleshead.flagInclusive = 1 THEN childSalesMenus.inclusivePrice * childSalesMenus.qty - childSalesMenus.inclusiveDiscountValue ELSE childSalesMenus.total END, 4) AS total,
          COALESCE(ms_promotionhead.promotionTypeID, 0) AS promotionTypeID,
          ms_promotionhead.paymentMethodID,
          ms_promotionhead.flagPackageContent,
          ms_promotionhead.flagMenuExtra,
          ms_promotionhead.maxSalesPrice,
          ms_promotionhead.promotionID AS masterPromoID,
          ms_promotionhead.flagActive AS flagPromoActive,
          ms_promotionhead.notes AS promotionDetailName,
          ms_promotionhead.voucherSourceID,
          COALESCE(ms_promotionhead.paymentMethodID, 0) AS promotionPaymentMethodID,
          lk_status.statusName,
          ms_branchmenu.stationID,
          tr_salesmenurelated.mainMenuID,
          (CASE WHEN childSalesMenus.statusID = 46 THEN 1 ELSE 0 END) AS flagHoldOrder,
          tr_salesrewardmenu.rewardType,
          (CASE WHEN childSalesMenus.promotionVoucherCode AND childSalesMenus.promotionVoucherCode != '' AND ms_promotionhead.voucherSourceID NOT IN (1, 7) THEN 1 ELSE 0 END) AS flagMemberPromotionVoucher,
          creator.fullName AS creatorFullName,
          editor.fullName AS editorFullName
        FROM
          tr_salesmenu
        LEFT JOIN
          tr_salesmenu childSalesMenus ON tr_salesmenu.ID = childSalesMenus.menuRefID AND childSalesMenus.ID <> childSalesMenus.menuRefID AND childSalesMenus.menuRefID <> 0
        LEFT JOIN
          tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
        LEFT JOIN
          tr_salesmenurelated ON childSalesMenus.localID = tr_salesmenurelated.salesMenuID
        LEFT JOIN
          tr_salesrewardmenu ON childSalesMenus.ID = tr_salesrewardmenu.ID AND childSalesMenus.localID = tr_salesrewardmenu.ID
        LEFT JOIN
          tr_salesprocessmenu ON childSalesMenus.ID = tr_salesprocessmenu.salesMenuID AND childSalesMenus.salesNum = tr_salesprocessmenu.salesNum
        LEFT JOIN
          ms_menu ON childSalesMenus.menuID = ms_menu.menuID
        LEFT JOIN
          ms_branchmenu ON childSalesMenus.menuID = ms_branchmenu.menuID
        LEFT JOIN
          ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
        LEFT JOIN
          ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
        LEFT JOIN
          ms_menugroup ON childSalesMenus.menuGroupID = ms_menugroup.menuGroupID AND tr_salesmenu.menuID = ms_menugroup.menuID
        LEFT JOIN
          ms_menupackage ON ms_menugroup.menuGroupID = ms_menupackage.menuGroupID AND childSalesMenus.menuID = ms_menupackage.menuID
        LEFT JOIN
          ms_promotionhead ON childSalesMenus.promotionDetailID = ms_promotionhead.promotionID
        LEFT JOIN
          ms_posuser creator ON childSalesMenus.createdBy = creator.username
        LEFT JOIN
          ms_posuser editor ON childSalesMenus.editedBy = editor.username
        LEFT JOIN
          lk_status ON childSalesMenus.statusID = lk_status.statusID
        WHERE
          tr_saleshead.salesNum = '$salesNum'";
    }

    public static function getOtherAttributeSalesMenu($salesModel, $salesHead, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $mainSalesModel) {
      $salesModel['discountTotal'] = self::getMenuDiscountArray($salesModel);
      $salesModel['allMenuDiscountTotal'] = self::getAllMenuDiscountTotalArray($salesModel, $mainSalesModel);
      $salesModel['menuFlagTax'] = self::getMenuFlagTax($salesModel, $mainSalesModel);
      $salesModel['priceTotal'] = self::getPriceTotal($salesModel, $mainSalesModel);
      $salesModel['otherTaxTotal'] = self::getTaxOrOtherTaxTotal('otherTaxValue', $salesModel, $mainSalesModel);
      $salesModel['vatTotal'] = self::getTaxOrOtherTaxTotal('vatValue', $salesModel, $mainSalesModel);
      $salesModel['otherVatTotal'] = self::getTaxOrOtherTaxTotal('otherVatValue', $salesModel, $mainSalesModel);
      $salesModel['menuTotal'] = self::getMenuTotalArray($salesHead, $salesModel, $mainSalesModel);
      $salesModel['promotionDetailName'] = self::getPromotionDetailName($salesModel);
      $salesModel['flagHoldOrder'] = $salesModel['statusID'] === 46;
      $salesModel['flagMemberPromotionVoucher'] = ($salesModel['promotionVoucherCode'] && $salesModel['promotionVoucherCode'] != '') && ($salesModel['masterPromoID'] && !in_array($salesModel['voucherSourceID'], [1,7]));
      $salesModel['lastModifiedBy'] = self::getLastModifiedBy($salesModel);
      $salesModel['lastCreatedBy'] = self::getLastCreatedBy($salesModel);
      $salesModel['salesMenuCompletionKitchen'] = self::checkSalesMenuHasComplete($salesMenuCompletionModelArray, 'salesMenuID', $salesModel['salesNum'], $salesModel['ID'], 1);
      $salesModel['salesMenuCompletionChecker'] = self::checkSalesMenuHasComplete($salesMenuCompletionModelArray, 'salesMenuID', $salesModel['salesNum'], $salesModel['ID'], 2);
      $salesModel['salesProcessMenu'] = self::checkSalesProcessMenu($salesProcessMenuModelArray, $salesModel['salesNum'], $salesModel['ID']);

      return $salesModel;
    }

    public static function getPromotionDetailsArray($salesMenu) {
      $connection = Yii::$app->getDb();

      $promotionDetailsModelArrays = [];
      if (in_array(7, array_column($salesMenu, 'promotionTypeID'))) {
        $promotionDetailIDs = implode(", ", array_column($salesMenu, 'promotionDetailID'));
        $promotionDetailsModel =
          $connection->createCommand("SELECT
            ms_promotiondetail.*
          FROM
            ms_promotiondetail
          WHERE
            promotionID IN ($promotionDetailIDs)")->queryAll();

        foreach ($promotionDetailsModel as $item) {
          $tempItem = [];
          foreach ($item as $key => $value) {
            if (strpos($key, 'ID') !== false) {
              $tempItem[$key] = (int) $value;
            } else {
              $tempItem[$key] = $value;
            }
          }
          $promotionDetailsModelArrays[$item['promotionID']][] = $tempItem;
        }
      }
      
      return $promotionDetailsModelArrays;
    }

    public static function getMenuDiscountArray($salesModel) {
      $discount = 0;
      if (in_array($salesModel['promotionTypeID'], [9, 12, 14, 15, 17])) {
          $discount = $salesModel['discountValue'];
      } else {
          $discount = $salesModel['discount'] / 100 * $salesModel['price'] * $salesModel['qty'];
      }
      return (float) $discount;
    }

    public static function getAllMenuDiscountTotalArray($salesModel, $mainSalesModel) {
      $discountAllTotal = 0;
      $discountMenuPackageChild = 0;
      $discountMenuExtraChild = 0;
      $discountMenuHead = self::getMenuDiscountArray($salesModel);

      $discountMenuPackageChild = 0;
      if (isset($mainSalesModel['packages'])) {
        foreach ($mainSalesModel['packages'] as $package) {
          $discountMenuPackageChild += $package['tempDiscountValue'] * $mainSalesModel['qty'];
        }
      }

      $discountMenuExtraChild = 0;
      if (isset($mainSalesModel['extras'])) {
        foreach ($mainSalesModel['extras'] as $extra) {
          $discountMenuExtraChild += $extra['tempDiscountValue'] * $mainSalesModel['qty'];
        }
      }

      $discountAllTotal = $discountMenuHead + $discountMenuPackageChild + $discountMenuExtraChild;
      return (float) $discountAllTotal;
  }

    public static function getMenuFlagTax($salesMenu, $salesMenuParent) {
      $menuRefID = $salesMenu['menuRefID'];
      $menuGroupID = $salesMenu['menuGroupID'];
      $flagTax = (int) $salesMenu['flagTax'];

      if ($menuRefID > 0 && $menuGroupID > 0) {
        $parentFlagTax = (int) $salesMenuParent['flagTax'];
        $flagSeparateTaxCalculation = (int) $salesMenuParent['flagSeparateTaxCalculation'];
        if ($flagSeparateTaxCalculation == 0) {
          $flagTax = $parentFlagTax;
        }
      }
      return $flagTax;
    }

    public static function getPriceTotal($salesModel, $mainSalesModel) {
      $parentQty = 1;
      $netPrice = $salesModel['price'];

      if (isset($salesModel['packages'])) {
        foreach ($salesModel['packages'] as $packages) {
          $netPrice += $packages['qty'] * $packages['price'];
        }
      }

      if (isset($salesModel['extras'])) {
        foreach ($salesModel['extras'] as $extra) {
          $netPrice += $extra['qty'] * $extra['price'];
        }
      }

      if ($mainSalesModel) {
        if ($salesModel['ID'] <> $mainSalesModel['ID']){
          $parentQty = $mainSalesModel['qty'];
        }
      }

      return (float) $netPrice * $salesModel['qty'] * $parentQty;
    }

    public static function getTaxOrOtherTaxTotal($field, $salesModel, $mainSalesModel) {
      $value = $salesModel[$field];

      if (isset($salesModel['packages'])) {
        foreach ($salesModel['packages'] as $packages) {
          $value += $packages[$field] * $salesModel['qty'];
        }
      }

      if (isset($salesModel['packages'])) {
        foreach ($salesModel['extras'] as $extra) {
          $value += $extra[$field] * $salesModel['qty'];
        }
      }

      if ($mainSalesModel) {
        if ($salesModel['ID'] <> $mainSalesModel['ID']){
          $value = $mainSalesModel[$field] * $salesModel['qty'];
        }
      }

      return (float) $value;
    }

    public static function getMenuTotalArray($salesHead, $salesModel, $mainSalesModel) {
      $netPrice = self::getPriceTotal($salesModel, $mainSalesModel);
      $discount = self::getMenuDiscountArray($salesModel);
      $otherTax = self::getTaxOrOtherTaxTotal('otherTaxValue', $salesModel, $mainSalesModel);
      $vat = self::getTaxOrOtherTaxTotal('vatValue', $salesModel, $mainSalesModel);
      $taxCalculationType = $salesHead['posTaxCalculationID'];
      $otherTaxCalculationType = $salesHead['posOtherTaxCalculationID'];

      $discountBill = 0;
      if($salesModel['otherTax'] >= 0 || $salesModel['vat'] >= 0){
          $discountBill = self::getBillDiscount($salesHead, $salesModel, $mainSalesModel);
      }
      return $netPrice - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $discount + $discountBill) + $otherTax + $vat;
    }

    public static function getLastModifiedBy($salesModel) {
      $creator = $salesModel['createdBy'] ? ($salesModel['creatorFullName'] ? $salesModel['creatorFullName'] : $salesModel['createdBy']) : '-';
      $editor = $salesModel['editedBy'] ? ($salesModel['editorFullName'] ? $salesModel['editorFullName'] : $salesModel['editedBy']) : '-';
      return $salesModel['editedBy'] !== '' ? $editor : $creator;
    }

    public static function getLastCreatedBy($salesModel) {
      $creator = $salesModel['createdBy'] ? ($salesModel['creatorFullName'] ? $salesModel['creatorFullName'] : $salesModel['createdBy']) : '-';
      return $creator;
    }


    public static function getBillDiscount($salesHead, $salesModel, $mainSalesModel) {
      $discountBill = 0;
      if ($salesHead['promotionID'] == 0) {
          return $discountBill;
      }
  
      $subtotalData = $mainSalesModel['price'] * $mainSalesModel['qty'];
  
      if (isset($mainSalesModel['packages'])) {
        foreach ($mainSalesModel['packages'] as $package) {
          $subtotalData += $package['price'] * $package['qty'];
        }
      }
  
      if (isset($mainSalesModel['extras'])) {
        foreach ($mainSalesModel['extras'] as $extra) {
          $subtotalData += $extra['price'] * $extra['qty'];
        }
      }
  
      if ($salesHead['promotionTypeID'] == 3) {
        $subTotal = $subtotalData > 0 ? $subtotalData : 1;              
        $discountBill = ($salesModel['qty'] * $salesModel['price'] / $subTotal) * $salesHead['discountTotal'];
      }
  
      return $discountBill;
    }
  
    public static function getPromotionDetailName($salesModel) {
      $promotionDetailName = null;
      if ($salesModel['masterPromoID']) {
          $promotionDetailName = $salesModel['promotionDetailName'];
          if ($salesModel['promotionVoucherCode'] && $salesModel['promotionVoucherCode'] != '') {
              $promotionDetailName = $salesModel['promotionDetailName'] . " - " . $salesModel['promotionVoucherCode'];
              if (strlen($salesModel['promotionVoucherCode']) >= 6) {
                  $promotionVoucherCode = substr($salesModel['promotionVoucherCode'], -6);
                  $promotionDetailName = $salesModel['promotionDetailName'] . " - " . substr_replace($promotionVoucherCode, str_repeat('x', strlen($promotionVoucherCode)-3), 0, -3);
              }
          }
      }
      return $promotionDetailName;
    }

    public static function checkSalesMenuHasComplete($salesMenuCompletionModelArray, $field, $salesNum, $value, $typeID) {
      $salesMenuHasComplete = [];
      if (isset($salesMenuCompletionModelArray[$salesNum])) {
        foreach ($salesMenuCompletionModelArray[$salesNum] as $hasComplete) {
          if ($hasComplete[$field] == $value && $hasComplete['typeID'] == $typeID) {
            $salesMenuHasComplete[] = $hasComplete;
          }
        }
      }
      return $salesMenuHasComplete;
    }

    public static function checkSalesProcessMenu($salesProcessMenuModelArray, $salesNum, $ID) {
      $salesProcessMenu = null;
      if (isset($salesProcessMenuModelArray[$salesNum])) {
        foreach ($salesProcessMenuModelArray[$salesNum] as $salesProcess) {
          if ($salesProcess['salesMenuID'] == $ID) {
            $salesProcessMenu = $salesProcess;
          }
        }
      }
      return $salesProcessMenu;
    }
}
