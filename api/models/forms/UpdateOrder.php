<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\BranchMenu;
use app\models\BranchMenuTransaction;
use app\models\BrandSetting;
use app\models\MapBranchVisitPurpose;
use app\models\MapMenuTemplatePackage;
use app\models\MapSelfOrderCampaignBranch;
use app\models\MapSelfOrderCampaignBranchDetail;
use app\models\Member;
use app\models\Menu;
use app\models\MenuExtra;
use app\models\MenuPackage;
use app\models\MenuTemplateDetail;
use app\models\MenuTemplateHead;
use app\models\MsSelfOrderCampaignHead;
use app\models\MsSelfOrderCampaignItem;
use app\models\Notification;
use app\models\ProductDetailMenu;
use app\models\PromotionDetail;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\SalesHeadVat;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMenuRecommendation;
use app\models\SalesMenuRelated;
use app\models\SalesMenuVat;
use app\models\SalesMergeTable;
use app\models\SalesOrderCampaign;
use app\models\SalesRewardHead;
use app\models\SalesRewardMenu;
use app\models\Setting;
use app\models\forms\ValidateStock;
use app\models\SalesConditionalPromo;
use app\models\SalesPayment;
use app\models\SalesPaymentGateway;
use app\models\SalesProcessMenu;
// use app\models\ShiftLog;
use app\models\SpecialPriceMenu;
use app\models\TempOrder;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\web\ServerErrorHttpException;
use yii\httpclient\Client;

/**
 * @property int $tableID
 * @property string $salesNum
 * @property string $additionalInfo
 * @property string $deliveryCost
 * @property array $salesMenu
 * @property int $batchID
 * @property int $promotionID
 * @property string $promotionVoucherCode
 * @property int $visitPurposeID
 * @property int $paxTotal
 * @property int $memberID
 * @property string $promotionDiscount
 * @property int $transactionModeID
 * 
 * PRIVATE
 * @property boolean $saveOnly
 * @property boolean $turnOffStockValidation
 * @property SalesHead $salesModel
 * @property PromotionHead $promotionModel
 */
class UpdateOrder extends Model {
    CONST DEADLOCK_ERR = "Deadlock found when trying to get lock";
    CONST LOCKWAIT_ERR = "Lock wait timeout exceeded";

    public $tableID;
    public $salesNum;
    public $additionalInfo;
    public $deliveryCost;
    public $salesMenu;
    public $batchID;
    public $promotionID;
    public $promotionVoucherCode;
    public $visitPurposeID;
    public $paxTotal;
    public $memberID;
    public $memberCode;
    public $promotionDiscount;
    public $discountTotal;
    public $voucherDiscountTotal;
    public $saveOnly = false;
    public $validateStock = true;
    public $salesModel;
    public $errMsg;
    public $employeeCode;
    public $employeeType;
    public $employeeName;
    public $external;
    public $externalMembershipTypeID;
    public $flagExternalAPI;
    public $flagExternalMemberID;
    public $flagExternalMemberPhone;
    public $flagExternalCardID;
    public $externalMemberName;
    public $externalTransID;
    public $selfOrderCampaign;
    public $orderTimeOut;
    public $transactionModeID;
    public $flagScratchWin = false;
    public $orderFee;
    public $visitorTypeID;
    public $applySplit = false;
    public $ezoQuickService = false;
    public $scanQrTakeAwayOff = false;
    public $externalApi = false;
    public $stationID;
    public $rewardType;
    public $flagFireOrderIDs;
    public $authUserName;
    public $promotionModel;
    public $menuPromotionWithAuth;
    public $flagRemoveMemberPromoFS = false;
    public $ezoFullService = false;
    public $flagAutoRemovePromotion = false;
    public $saveOrderDate;
    public $internalMemberName;
    public $saveOrderKiosk = false;
    public $isEmployeeApplied = false;
    public $tempSalesMenu;
    public $reCalculate = false;
    public $newSalesMenuFs;
    public $webSocketID;
    public $platformFee;
    public $conditionalPromoID;
    public $selfOrderIdKiosk;
    public $specialPriceHasExp = [];
    public $selfOrderPaymentMethodID = null;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableID'], 'required'],
            [['salesNum'], 'required', 'when' => function ($model) {
                    return $model->tableID == 0;
                }],
            [['tableID', 'promotionID', 'visitPurposeID', 'paxTotal', 'memberID', 'orderTimeOut', 'visitorTypeID', 'conditionalPromoID'], 'integer'],
            [['promotionDiscount', 'deliveryCost', 'discountTotal', 'voucherDiscountTotal', 'orderFee', 'stationID'], 'number'],
            [['salesNum'], 'string', 'max' => 20],
            [['additionalInfo'], 'string', 'max' => 200],
            [['externalMembershipTypeID', 'salesMenu', 'memberCode', 'employeeCode', 'employeeType', 'employeeName', 'flagExternalAPI',
                'flagExternalMemberID', 'flagExternalMemberPhone', 'flagExternalCardID', 'externalMemberName', 'externalTransID',
                'transactionModeID', 'promotionVoucherCode', 'stationID', 'rewardType', 'flagFireOrder', 'authUserName',
                'menuPromotionWithAuth', 'flagRemoveMemberPromoFS', 'ezoFullService', 'saveOrderDate', 'internalMemberName', 'platformFee', 'webSocketID', 
                'selfOrderIdKiosk', 'selfOrderPaymentMethodID'], 'safe'],
            [['tableID'], 'validateTable'],
            [['saveOnly'], 'validateTableSide'],
            [['promotionID'], 'validatePromotion']
        ];
    }

    public function validateTableSide($attribute) {
        if (isset($this->saveOnly) && $this->saveOnly === true) {
            if ($this->tableID != 0) {
                $branchID = Setting::getCurrentBranch();
                $salesTableSideModel = SalesHead::find()
                    ->where([SalesHead::tableName() . '.tableID' => $this->tableID])
                    ->andWhere([SalesHead::tableName() . '.branchID' => $branchID])
                    ->andWhere(['IS', SalesHead::tableName() . '.salesDateOut', null])
                    ->one();
                if ($salesTableSideModel) {
                    if ($salesTableSideModel->salesNum != $this->salesNum) {
                        $this->addError($attribute, 'Sales number is change');
                    }
                    $this->promotionID = $salesTableSideModel->promotionID;
                    $this->promotionDiscount = $salesTableSideModel->promotionDiscount;
                    $this->memberID = $salesTableSideModel->memberID;
                    $this->additionalInfo = $salesTableSideModel->additionalInfo;
                } else {
                    $this->addError($attribute, 'Sales not found');
                }
            } else {
                $this->addError($attribute, 'Table not found');
            }
        }
    }

    public function validateTable($attribute) {
        if ($this->tableID != 0) {
            $this->salesModel = SalesHead::findOutstanding()
                ->joinWith('salesMergeTables')
                ->andWhere(['OR',
                    [SalesHead::tableName() . '.salesNum' => $this->salesNum],
                    [SalesMergeTable::tableName() . '.salesNum' => $this->salesNum]
                ])
                ->one();
        } else {
            $this->salesModel = SalesHead::findOutstandingOrder()
                ->andWhere([salesHead::tableName() . '.salesNum' => $this->salesNum])
                ->one();
        }
        if (!$this->salesModel) {
            $this->addError($attribute, 'Invalid table ID');
        }
    }

    public function validatePromotion($attribute) {
        if ($this->promotionID != 0) {
            $this->promotionModel = PromotionHead::findActiveForBill($this->salesModel->memberID, $this->salesModel->employeeCode, 0, $this->salesModel->flagExternalMemberID)
                ->andWhere([PromotionHead::tableName() . '.promotionID' => $this->promotionID])
                ->one();
        }

        $isDoubleConditionalPromo = $this->validateConditionalPromo();
        if ($isDoubleConditionalPromo) {
            $this->errMsg = $isDoubleConditionalPromo;
            $this->addError($attribute, $this->errMsg);
        }
    }

    public function preSave() {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->salesNum) {
                // Lock Sales Head row by Sales Number
                $lockSalesHeadQuery = SalesHead::find()
                    ->where(['salesNum' => $this->salesNum])
                    ->createCommand()
                    ->getRawSql();

                SalesHead::findBySql($lockSalesHeadQuery . ' FOR UPDATE')->one();
            }

            if (!$this->save()) {
                throw new Exception(json_encode($this->errMsg), [], 400);
            }

            $currentSalesModel = SalesHead::findOne(['salesNum' => $this->salesModel->salesNum]);
            if ($currentSalesModel && ($currentSalesModel->billNum && $currentSalesModel->statusID == 8)) {
                $currentSalesPayment = SalesPayment::findOne(['salesNum' => $currentSalesModel->salesNum]);
                if ($currentSalesPayment) {
                    $this->notifSelfOrderError($this->errors, $currentSalesPayment->selfOrderID);
                }
            }

            if ($transaction->isActive) {
                $transaction->commit();
            }
            return true;
        } catch (Exception $ex) {
            if ($transaction->isActive) {
                try {
                    $transaction->rollBack();
                } catch (Exception $e_rollback) {
                    Yii::error($e_rollback, 'Rollback error');
                }
            }
            $this->addError('salesMenu', $ex->getMessage());
            return false;
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }
        $flagLoyalOnly = ($this->externalMembershipTypeID === 'memberid' || $this->externalMembershipTypeID === 'esbloyalty') ? true : false;
        $promotionModel = NULL;
        $currentIDs = [];
        $existExtraIDs = [];
        $newIDs = [];
        $newIDsCancel = [];
        $errorStockMsg = "";
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $kitchenFireManagement = isset($settings['Kitchen Fire Management']) ? $settings['Kitchen Fire Management'] : 0;
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($this->salesModel->visitPurposeID);
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $this->salesModel->visitPurposeID])->one();
        $otherTaxOnVat = 0;
        $taxCalculation = [];
        if ($mapBranchModel) {
            $otherTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatValue = $mapBranchModel->taxValue;
            $taxCalculation['otherTax'] = $otherTaxValue;
            $taxCalculation['vat'] = $vatValue;
            $taxCalculation['otherTaxOnVat'] = $otherTaxOnVat;
            $taxCalculation['salesDecimalSetting'] = $salesDecimalSetting;
            $taxCalculation['settingDecimalMode'] = $settingDecimalMode;
        }

        $menuTemplateModel = $this->getMenuTemplateModel();
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $inclusiveMenuTemplateID])
            ->indexBy("menuID")
            ->all();
        $promotionArrModel = PromotionHead::findActiveArrayValue();
        
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        $externalProcess = isset($this->external) ? $this->external : 0;

        if ($inclusiveMenuTemplateID) {
            $otherTaxCalculationType = $taxCalculationType;
        }

        if ($inclusiveMenuTemplateID) {
            if ($taxCalculationType == 1 && $otherTaxCalculationType == 1) {
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

        $promotionHeadModel = null;
        $applyBillDiscountToPackageContent = 0;
        $applyBillDiscountToExtra = 0;
        $promotionCategoryIDs = [];
        $promotionCategoryDetailIDs = [];
        $promotionMenuIDs = [];

        $specialPriceArrModel = SpecialPriceMenu::findActiveArrayValue($mapBranchModel->menuTemplateID, $this->saveOrderDate);
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $mapBranchModel = MapBranchVisitPurpose::find()
            ->where(['visitPurposeID' => $this->salesModel->visitPurposeID])
            ->one();
        $vatSubject = 0;
        if ($mapBranchModel) {
            $menuTemplateID = $mapBranchModel->menuTemplateID;
            $otherTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatValue = $mapBranchModel->taxValue;
            $vatSubject = $mapBranchModel->vatSubject;
        }

        // @Notes: Check if membership type member.id and/or tada is currently active
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $externalMember = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $isMemberID = ($externalMember == 1 && $membershipType == 'memberid' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isLoyalty = ($externalMember == 1 && $membershipType == 'esbloyalty' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isMemberTada = ($externalMember == 1 && $membershipType == 'tada' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isLoopLite = ($externalMember == 1 && $membershipType == 'looplite' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isCapillary = ($externalMember == 1 && $membershipType == 'capillary' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isCapillaryV2 = ($externalMember == 1 && $membershipType == 'capillaryV2' && $this->flagExternalMemberID) ? TRUE : FALSE;
        $isStamps = ($externalMember == 1 && $membershipType == 'stamps' && $this->flagExternalMemberID) ? TRUE : FALSE;

        try {
            $promotionPaymentMethodIDs = [];
            $promotionHeadTypeID = 0;
            if ($this->promotionID) {
                $checkPromo = PromotionHead::checkPromoActive($this->promotionID);
                $currentSalesModel = SalesHead::findOne($this->salesNum);
                if ($currentSalesModel) {
                    if ($currentSalesModel->promotionID == $this->promotionID) {
                        $checkPromo = true;
                    }
                }

                if (!$checkPromo) {
                    $checkPromo = PromotionHead::checkPromoActive($this->promotionID, $this->salesModel->salesDateIn);
                }

                if (!$checkPromo && $this->transactionModeID != 5) {
                    $this->errMsg = 'Promotion is not found on POS';
                    throw new Exception(json_encode($this->errMsg), [], 400);
                } else {
                    $promotionHeadModel = PromotionHead::findOne($this->promotionID);
                    if ($this->memberID == null && $this->employeeCode == null && (!$isMemberID && !$isMemberTada && !$isLoyalty && !$isLoopLite && !$isCapillary && !$isCapillaryV2 && !$isStamps)) {
                        if (in_array($promotionHeadModel->promotionMemberTypeID, [1, 2, 3])) {
                            $this->promotionID = null;
                            $this->promotionDiscount = null;
                            $this->promotionVoucherCode = null;
                            $promotionHeadModel = null;
                            self::setFlagEmployeeApplied();
                        }
                    } else if ($this->memberID == null && $this->employeeCode != null) {
                        if (in_array($promotionHeadModel->promotionMemberTypeID, [3])) {
                            $this->promotionID = null;
                            $this->promotionDiscount = null;
                            $this->promotionVoucherCode = null;
                            $promotionHeadModel = null;
                            self::setFlagEmployeeApplied();
                        }
                    } else if ($this->memberID != null && $this->employeeCode == null) {
                        if (in_array($promotionHeadModel->promotionMemberTypeID, [2])) {
                            $this->promotionID = null;
                            $this->promotionDiscount = null;
                            $this->promotionVoucherCode = null;
                            $promotionHeadModel = null;
                            self::setFlagEmployeeApplied();
                        }
                    } else if ($this->memberID == null && $this->employeeCode == null && ($isMemberID || $isMemberTada || $isLoyalty || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps)) {
                        if (in_array($promotionHeadModel->promotionMemberTypeID, [2])) {
                            $this->promotionID = null;
                            $this->promotionDiscount = null;
                            $this->promotionVoucherCode = null;
                            $promotionHeadModel = null;
                            self::setFlagEmployeeApplied();
                        }
                    }

                    if ($promotionHeadModel) {
                        if ($promotionHeadModel->paymentMethodID) {
                            $promotionPaymentMethodIDs[] = (int) $promotionHeadModel->paymentMethodID;
                        }

                        foreach ($promotionHeadModel->promotionCategories as $promotionCategory) {
                            $promotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                            $promotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                            $promotionMenuIDs[] = $promotionCategory->menuID;
                        }

                        $promotionHeadTypeID = $promotionHeadModel->promotionTypeID;

                        $applyToBill = $promotionHeadModel->paymentMethodTypeID == 3 || $promotionHeadModel->paymentMethodTypeID == 12 || count($promotionCategoryIDs) == 0 || 
                            count($promotionCategoryDetailIDs) == 0 || count($promotionMenuIDs) == 0;

                        $applyToBill = $promotionHeadModel->promotionTypeID == 9 ? false : $applyToBill;

                        if ($applyToBill) {
                            if (in_array($promotionHeadModel->promotionTypeID, [1, 5, 10])) {
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
                }
            }
            
            if($this->saveOnly && $this->flagScratchWin) {
                $this->selfOrderCampaign = $this->checkSelfOrderCampaign($this);
            }

            $issetSpecialPrice = false;
            $tempMenuSubtotal = 0;
            $allMenuSubtotal = 0;
            $tempMenuGrandTotal = 0;
            $tempGrandTotal = 0;
            $tempMenuDiscountTotal = 0;
            $otherTaxTotal = 0;
            $vatTotal = 0;
            $otherVatTotal = 0;
            $platformFeeIncludeOtherTax = 0;
            $totalPlatformFee = 0;
            $sumSubtotalPlatformFee = 0;
            $salesMenuIDs = [];
            $salesMenuCancelIDs = [];
            $salesMenuCancelQty = [];
            $salesMenuPckCancelQty = [];
            $salesOnApplyPromo = [];
            $allMenuDiscountTotal = 0;
            $dppValueTotal = 0;
            
            if (!isset($this->salesMenu) || !is_array($this->salesMenu) || empty($this->salesMenu)) {
                $this->salesMenu = [];
            }

            if ($this->platformFee) {
                foreach ($this->platformFee as $row) {
                    if (isset($row['platformFeeTypeID']) && $row['platformFeeTypeID'] == 2) {
                        $platformFeeIncludeOtherTax += $row['amount'];
                    }
                }
            }

         
            foreach ($this->salesMenu as $salesMenu) {
                $isSplittedPromoReward = isset($salesMenu['isSplittedPromoReward']) && $salesMenu['isSplittedPromoReward'] === true;
                if (isset($salesMenu['localID'])) {
                    if ($salesMenu['statusID'] == 12) {
                        $salesMenuCancelIDs[] = $salesMenu['localID'];
                        $salesMenuCancelQty[] = ['localID' => $salesMenu['localID'], 'qty' => $salesMenu['qty']];

                        if ($salesMenu['packages']) {
                            foreach ($salesMenu['packages'] as $packages) {
                                if ($packages['statusID'] == 12) {
                                    $salesMenuPckCancelQty[] = ['localID' => $packages['localID'], 'qty' => $packages['qty']];
                                }
                            }
                        }
                    } else {
                        $salesMenuIDs[] = $salesMenu['localID'];
                        $salesOnApplyPromo[$salesMenu['localID']]['promotionDetailID'] = $salesMenu['promotionDetailID'];
                    }
                }
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && $salesMenu['menuFlagTax'] === 2));
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
                $detailPromotionTypeID = 0;
                if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] != 0) {
                    if (in_array($salesMenu['statusID'], [13, 34, 14, 46])) {
                        $promotionModel = PromotionHead::find()
                            ->where(['promotionID' => $salesMenu['promotionDetailID']])
                            ->one();
                    } else {
                        $promotionModel = PromotionHead::findActiveForMenu($tempMenuID,
                            $this->memberID, $this->employeeCode, null, $this->flagExternalMemberID, $salesMenu['promotionDetailID'])
                        ->andWhere([PromotionHead::tableName() . '.promotionID' => $salesMenu['promotionDetailID']])
                        ->one();

                        if(!$promotionModel){
                            //check grabPromotion
                            $promotionModel = PromotionHead::find()
                                ->where(['promotionID' => $salesMenu['promotionDetailID']])
                                ->andWhere(['IN', 'promotionTypeID', [14, 15, 16]])
                            ->one();
                        }
                    }

                    if ($promotionModel) {
                        if ($promotionModel->paymentMethodID) {
                            $promotionPaymentMethodIDs[] = (int) $promotionModel->paymentMethodID;
                        }

                        if ($this->memberID == null && $this->employeeCode == null && ( !$isMemberID && !$isMemberTada && !$isLoyalty && !$isLoopLite && !$isCapillary && !$isCapillaryV2 && !$isStamps)) {
                            if (in_array($promotionModel->promotionMemberTypeID, [1, 2, 3])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID == null && $this->employeeCode != null) {
                            if (in_array($promotionModel->promotionMemberTypeID, [3])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID != null && $this->employeeCode == null) {
                            if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID == null && $this->employeeCode == null && ( $isMemberID || $isMemberTada || $isLoyalty || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps)) {
                            if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        }

                        if ($promotionModel == null) {
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
                                        $salesMenu['price'] = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat,
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
                    } else {
                        $salesMenu['promotionDetailID'] = 0;
                        $salesMenu['promotionDetailName'] = '';
                        $salesMenu['discount'] = 0;
                    }
                    
                    if ($promotionModel) {
                        $detailPromotionTypeID = $promotionModel->promotionTypeID;
                    }
                }

                if (in_array($salesMenu['statusID'], [ 1, 12 ]) || $isSplittedPromoReward) {
                    // @Notes: Status 1 = New, 12 = Cancelled, BatchID 0 = New record
                    // @Notes: Check qty from Branch Menu
                    $shouldRunValidationStock = !in_array($this->transactionModeID, SalesHead::EXTERNAL_TRANSCATION_MODE_ID);
                    if (in_array($salesMenu['statusID'], [ 1, 12]) && $shouldRunValidationStock) {
                        $validateStockModel = new ValidateStock();
                        $validateStockModel->salesNum = $this->salesModel->salesNum;
                        $validateStockModel->menuID = $salesMenu['menuID'];
                        $validateStockModel->qty = $salesMenu['qty'];
                        $validateStockModel->transactionModeID = $this->transactionModeID;
                        $validateStockModel->isCancelOrder = in_array($salesMenu['statusID'], [ 12, 19 ]);
                        $validateStockModel->salesMenuID = $salesMenu['ID'];
                        $menuName = $validateStockModel->validateStock();
                    
                        if($salesMenu['statusID'] == 12) {
                            $this->validateCancelStockMenuPackageRTS($salesMenu);
                        }

                        if ($menuName) {
                            if (!$errorStockMsg) {
                                $errorStockMsg .= $menuName;
                            } else {
                                $errorStockMsg .= ", " . $menuName;
                            }
                        }
                    }

                    if ($salesMenu['statusID'] == 1) {
                        $salesMenuModel = new SalesMenu([
                            'attributes' => $salesMenu
                        ]);
                        $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                        if (isset($salesMenu['flagLuxuryItem'])) {
                            $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                        }
                        $salesMenuModel->salesNum = $this->salesModel->salesNum;
                        if (!$salesMenuModel->createdBy && $salesMenuModel->salesType == 'POS') {
                            $salesMenuModel->createdBy = Yii::$app->user->identity->username;
                        }
    
                        if ($salesMenuModel->promotionDetailID != 0) {
                            $this->applyMenuPromo($salesMenuModel);
                        }

                        $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                        $salesMenuModel->inclusiveDiscountValue = $salesMenuModel->discountValue;
                        if ($detailPromotionTypeID == 9) {
                            if ($promotionModel->discount > $salesMenuModel->price) {
                                $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                            } else {
                                $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                            }
                        }
    
                        $applyDiscountBill = false;
                        if ($promotionHeadModel) {
                            $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                        }
    
                        if ($applyDiscountBill) {
                            if ($salesMenu['statusID'] == 1) {
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $tempMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue;
                                } else {
                                    $tempMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price;
                                }
                                $tempMenuDiscountTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice / 100 * $salesMenuModel->discount;
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
                                    $displayPriceValue = null;
                                    if (isset($salesMenu['displayPriceValue'])) {
                                        $displayPriceValue = $salesMenu['displayPriceValue'];
                                    }

                                    $inclusivePrice = isset($displayPriceValue) 
                                        ? $displayPriceValue 
                                        : (isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue);
                                }
                            }
    
                            // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                            if (strlen($salesMenuModel->customMenuName) > 0) {
                                $displayPriceValue = null;
                                if (isset($salesMenu['displayPriceValue'])) {
                                    $displayPriceValue = $salesMenu['displayPriceValue'];
                                }

                                $checkMenuTemplatePrice = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                                $checkInclusivePrice = isset($salesMenuModel->inclusivePrice) ? $salesMenuModel->inclusivePrice : $checkMenuTemplatePrice;

                                $inclusivePrice = isset($displayPriceValue) ? $displayPriceValue : $checkInclusivePrice;
                                $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $inclusivePrice;
                            }
                            
                            $salesMenuModel->price = strlen($salesMenuModel->customMenuName) > 0 ? self::getInclusivePrice($inclusivePrice,
                                    $salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                                    $salesDecimalSetting, $settingDecimalMode) : $salesMenuModel->price;
                                    
    
                            if($detailPromotionTypeID == 7) {
                                $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                            }

                            $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                            if ($salesTypeEzo) {
                                $inclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $inclusivePrice;
                            }
    
                            $salesMenuModel->inclusivePrice = $inclusivePrice;
                            if ($inclusiveMenuTemplateID && $salesMenuModel->inclusivePrice == $salesMenuModel->price && $salesMenuModel->inclusivePrice != 0) {
                                $salesMenuModel->price = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat, $salesDecimalSetting, $settingDecimalMode, $salesMenuModel->inclusivePrice);
                            }
                                    
                            //$menuDiscountVal = $salesMenuModel->promotionDetailID > 0 ? $promotionArrModel[$salesMenuModel->promotionDetailID] : 0;
                            if ($salesMenuModel->promotionDetailID > 0) {
                                if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                    $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                    $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                                } else {
                                    $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                    $detailPromotionDiscount = $promotionModel->discount;
                                }
                                if ($detailPromotionTypeID == 9) {
                                    $menuDiscountVal = 0;
                                } else {
                                    $menuDiscountVal = $detailPromotionDiscount;
                                }
                            } else {
                                $menuDiscountVal = 0;
                            }
    
                            $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                            $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;
    
                            $newDiscountVal = $menuDiscountVal;
                            $salesMenuModel->discount = $newDiscountVal;
                            $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                            $salesMenuModel->inclusiveDiscountValue = (float) $menuGrandTotal * $salesMenuModel->discount / 100;
                            if ($detailPromotionTypeID == 9) {
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
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
                                    $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                                        $salesDecimalSetting, $settingDecimalMode, $inclusivePrice);
                                    $salesMenuModel->discountValue = $netPrice * $salesMenuModel->qty * $promotionModel->discount / 100;
                                    $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                    $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                }
                            }
    
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                                    if ($applyDiscountBill) {
                                        if ($salesMenu['statusID'] == 1) {
                                            $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue;
                                        }
                                    }
                                } else {
                                    $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice - $salesMenuModel->inclusiveDiscountValue;
                                }
                            } else {
                                if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                                    if ($applyDiscountBill) {
                                        if ($salesMenu['statusID'] == 1) {
                                            $tempMenuGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice;
                                            $tempGrandTotal = $tempMenuGrandTotal;
                                        }
                                    }
                                } else {
                                    if ($applyDiscountBill) {
                                        if ($salesMenu['statusID'] == 1) {
                                            $tempGrandTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice;
                                        }
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
                                        if ($salesMenu['statusID'] == 1) {
                                            $otherTaxTotal += $salesMenuModel->otherTaxValue;
                                            $vatTotal += $salesMenuModel->vatValue;
                                            $otherVatTotal += $salesMenuModel->otherVatValue;
                                        }
                                    }
                                } else {
                                    $otherTaxTotal += $salesMenuModel->otherTaxValue;
                                    $vatTotal += $salesMenuModel->vatValue;
                                    $otherVatTotal += $salesMenuModel->otherVatValue;
                                } 
                            }
                        }
    
                        $packageItems = isset($salesMenu['packages']) ? $salesMenu['packages'] : [];
                        if ($packageItems) {
                            foreach ($salesMenu['packages'] as $package) {

                                $tempMenuID = 0;
                                $subsID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0 ;
                                if ($subsID != 0) {
                                    $tempMenuID = $subsID;
                                }
                                else{
                                    $menuPromotionID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0;
                                    $tempMenuID = $package['menuID'];
                                    if($menuPromotionID != 0 && ($package['statusID'] != 1 || $package['statusID'] != 12)){
                                        $tempMenuID = $menuPromotionID;
                                    }
                                }
                                if (in_array($salesMenu['statusID'], [ 1, 12]) && $shouldRunValidationStock) {
                                    $currentQty = (float)$package['qty'];
                                    
                                    $validateStockModel = new ValidateStock();
                                    $validateStockModel->salesNum = $this->salesModel->salesNum;
                                    $validateStockModel->menuID = $package['menuID'];
                                    $validateStockModel->qty = (float)$package['qty'] * (float)$salesMenu['qty'];
                                    $validateStockModel->transactionModeID = $this->transactionModeID;
                                    $validateStockModel->isCancelOrder = in_array($salesMenu['statusID'], [ 12, 19 ]);
                                    $validateStockModel->salesMenuID = $package['ID'];

                                    $menuName = $validateStockModel->validateStock();
                                    if ($menuName) {
                                        if (!$errorStockMsg) {
                                            $errorStockMsg .= $menuName;
                                        } else {
                                            $errorStockMsg .= ", " . $menuName;
                                        }
                                    }

                                    $package['qty'] = $currentQty;
                                }
    
                                $salesPackageModel = new SalesMenu([
                                    'attributes' => $package
                                ]);
    
                                if ($salesMenuModel->promotionDetailID != 0) {
                                    if ($promotionModel->flagPackageContent == 1) {
                                        $this->applyMenuPromo($salesPackageModel, $salesMenuModel->promotionDetailID);

                                        if ($salesPackageModel->promotionDetailID != 0) {
                                            $package['price'] = $detailPromotionTypeID == 4 ? 0 : $package['price'];
                                            $package['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                            $salesPackageModel->price = $detailPromotionTypeID == 4 ? 0 : $salesPackageModel->price;
                                            $salesPackageModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                            $salesPackageModel->promotionDetailID = $promotionModel->promotionID;
                                            if ($detailPromotionTypeID == 9) {
                                                if ($promotionModel->discount > $package['price']) {
                                                    $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'];
                                                } else {
                                                    $salesPackageModel->discountValue = (float) $package['qty'] * $promotionModel->discount;
                                                }
                                            } else {
                                                $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                            }
                                        } else {
                                            $salesPackageModel->discountValue = 0;
                                        }
                                    } else {
                                        $salesPackageModel->discountValue = 0;
                                    }
                                } else {
                                    $salesPackageModel->discountValue = 0;
                                }


                                if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                    if ($promotionHeadTypeID == 10) {
                                        if ($applyBillDiscountToPackageContent) {
                                            $applyDiscountPck = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscountPck = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscountPck) {
                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    $tempMenuSubtotal += (float)$salesMenu['qty'] * ((float)$salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue);
                                                } else {
                                                    $tempMenuSubtotal += (float)$salesMenu['qty'] * ((float)$salesPackageModel->qty * $salesPackageModel->price);
                                                }
                                                
                                                $tempMenuDiscountTotal += $salesMenuModel->qty * $salesPackageModel->qty * $salesPackageModel->inclusivePrice / 100 * $salesMenuModel->discount;
                                            }
                                        }
                                    } else {
                                        if ($applyBillDiscountToPackageContent) {
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                $tempMenuSubtotal += (float)$salesMenuModel->qty * ((float)$salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue);
                                            } else {
                                                $tempMenuSubtotal += (float)$salesMenuModel->qty * ((float)$salesPackageModel->qty * $salesPackageModel->price);
                                            }
                                        }
                                    }
                                }

                                $allMenuSubtotal += (float)$salesMenuModel->qty * ((float)$salesPackageModel->qty * $salesPackageModel->price);
                                $allMenuDiscountTotal += $salesMenuModel->qty * $salesPackageModel->discountValue;

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
                                            'ms_menupackage.menuGroupID' => $salesPackageModel->menuGroupID
                                        ])
                                    ->one();

                                    $displayPriceValue = null;
                                    if (isset($package['displayPriceValue'])) {
                                        $displayPriceValue = $package['displayPriceValue'];
                                    }

                                    $applyPackagePrice = isset($displayPriceValue)
                                        ? $displayPriceValue
                                        : ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price);

                                    $salesTypeEzo = $this->checkSalesTypeEzo($package['salesType']);
                                    if ($salesTypeEzo) {
                                        $inclusivePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $applyPackagePrice;
                                    }

                                    if ($menuPackageModel) {
                                        $salesPackageModel->inclusivePrice = $applyPackagePrice;
                                    } else {
                                        if($externalProcess) {
                                            $salesPackageModel->inclusivePrice = $package['total'] / (float)$package['qty'];
                                        }
                                    }

                                    if ($salesMenuModel->promotionDetailID != 0) {
                                        if ($promotionModel->flagPackageContent == 1) {
                                            $this->applyMenuPromo($salesPackageModel, $salesMenuModel->promotionDetailID);
                                            
                                            if ($salesPackageModel->promotionDetailID != 0) {
                                                $package['price'] = $detailPromotionTypeID == 4 ? 0 : $package['price'];
                                                $package['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                                $salesPackageModel->price = $detailPromotionTypeID == 4 ? 0 : $salesPackageModel->price;
                                                $salesPackageModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesPackageModel->inclusivePrice;
                                                $salesPackageModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                                $salesPackageModel->promotionDetailID = $promotionModel->promotionID;
                                                if ($detailPromotionTypeID == 9) {
                                                    if ($inclusiveMenuTemplateID) {
                                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                            if ($promotionModel->discount > $salesPackageModel->price) {
                                                                $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'];
                                                                $salesPackageModel->inclusiveDiscountValue = (float) $package['qty'] * $applyPackagePrice;
                                                                $discountValue = (float) $package['qty'] * $applyPackagePrice;
                                                            } else {
                                                                $percentageDiscountValue = $promotionModel->discount / $applyPackagePrice * 100;
                                                                $tempDiscountValue = $package['price'] * $percentageDiscountValue / 100;
                                                                $salesPackageModel->discountValue = (float) $package['qty'] * $tempDiscountValue;
                                                                $salesPackageModel->inclusiveDiscountValue = (float) $package['qty'] * $promotionModel->discount;
                                                                $discountValue = (float) $package['qty'] * $promotionModel->discount;
                                                            }
                                                        } else {
                                                            if ($promotionModel->discount > $applyPackagePrice) {
                                                                $salesPackageModel->inclusiveDiscountValue = $applyPackagePrice * $salesPackageModel->qty;
                                                            } else {
                                                                $salesPackageModel->inclusiveDiscountValue = $promotionModel->discount * $salesPackageModel->qty;                      
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    if ($inclusiveMenuTemplateID) {
                                                        $menuPackageSubtotal = $package['price'] * (float) $package['qty'];
                                                        $menuPackageTotal = $applyPackagePrice * (float) $package['qty'];
        
                                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                            if ($detailPromotionTypeID == 1) {
                                                                $salesPackageModel->discountValue = (float) $menuPackageSubtotal * $promotionModel->discount / 100;
                                                                $salesPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                                $discountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                            } else {
                                                                $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                                                $discountValue = $salesPackageModel->discountValue;
                                                            }
                                                        } else {
                                                            $salesPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                        }                                      
                                                    }
                                                }
                                            } else {
                                                $salesPackageModel->discountValue = 0;
                                                $salesPackageModel->inclusiveDiscountValue = 0;
                                            }                                            
                                        } else { 
                                            $salesPackageModel->discountValue = 0;
                                            $salesPackageModel->inclusiveDiscountValue = 0;
                                        }
                                    } else {
                                        $salesPackageModel->discountValue = 0;
                                        $salesPackageModel->inclusiveDiscountValue = 0;
                                    }
    
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill) {
                                                if ($applyBillDiscountToPackageContent && $salesMenu['statusID'] == 1) {
                                                    $applyDiscount = false;
                                                    if ($promotionHeadModel) {
                                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                    }
                
                                                    if ($applyDiscount) {
                                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice - $salesPackageModel->inclusiveDiscountValue);
                                                    }
                                                }
                                            }
                                        } else if ($promotionHeadTypeID == 11) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                $applyDiscount = false;
                                                if ($promotionHeadModel) {
                                                    $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                }
            
                                                if ($applyDiscount) {
                                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice - $salesPackageModel->inclusiveDiscountValue);
                                                }
                                            }
                                        } else {
                                            if ($applyBillDiscountToPackageContent) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice - $salesPackageModel->inclusiveDiscountValue);
                                            }
                                        }
                                    } else {
                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill) {
                                                if ($applyBillDiscountToPackageContent && $salesMenu['statusID'] == 1) {
                                                    $applyDiscount = false;
                                                    if ($promotionHeadModel) {
                                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                    }
                
                                                    if ($applyDiscount) {
                                                        $tempMenuGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice);
                                                        $tempGrandTotal = $tempMenuGrandTotal;
                                                    }
                                                }
                                            }
                                        } else if ($promotionHeadTypeID == 11) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                $applyDiscount = false;
                                                if ($promotionHeadModel) {
                                                    $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                }
            
                                                if ($applyDiscount) {
                                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice);
                                                    $tempGrandTotal = $tempMenuGrandTotal;
                                                }
                                            }
                                        } else {
                                            if ($applyDiscountBill) {
                                                if ($applyBillDiscountToPackageContent && $salesMenu['statusID'] == 1) {
                                                    $applyDiscount = false;
                                                    if ($promotionHeadModel) {
                                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                    }
                
                                                    if ($applyDiscount) {
                                                        $tempGrandTotal += $salesMenuModel->qty * ($salesPackageModel->qty * $salesPackageModel->inclusivePrice);
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $salesPackageModel->otherTaxValue = (float) ($salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue) / 100 * $salesPackageModel->otherTax;
                                        if ($salesPackageModel->otherTaxOnVat == 0) {
                                            $salesPackageModel->vatValue = (float) ($salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue) / 100 * $salesPackageModel->vat;
                                            $salesPackageModel->otherVatValue = (float) ($salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue) / 100 * $salesPackageModel->otherVat;
                                        } else {
                                            $salesPackageModel->vatValue = (float) (($salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue) + $salesPackageModel->otherTaxValue) / 100 * $salesPackageModel->vat;
                                            $salesPackageModel->otherVatValue = (float) (($salesPackageModel->qty * $salesPackageModel->price - $salesPackageModel->discountValue) + $salesPackageModel->otherTaxValue) / 100 * $salesPackageModel->otherVat;
                                        }

                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill) {
                                                if ($applyBillDiscountToPackageContent && $salesMenu['statusID'] == 1) {
                                                    $applyDiscount = false;
                                                    if ($promotionHeadModel) {
                                                        $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                                    }
                
                                                    if ($applyDiscount) {
                                                        $otherTaxTotal += $salesMenuModel->qty * $salesPackageModel->otherTaxValue;
                                                        $vatTotal += $salesMenuModel->qty * $salesPackageModel->vatValue;
                                                        $otherVatTotal += $salesMenuModel->qty * $salesPackageModel->otherVatValue;
                                                    }
                                                }
                                            }
                                        } else {
                                            $otherTaxTotal += $salesMenuModel->qty * $salesPackageModel->otherTaxValue;
                                            $vatTotal += $salesMenuModel->qty * $salesPackageModel->vatValue;
                                            $otherVatTotal += $salesMenuModel->qty * $salesPackageModel->otherVatValue;
                                        }
                                    }
                                }
                            }
                        }
    
                        if (isset($salesMenu['extras'])) {
                            foreach ($salesMenu['extras'] as $extra) {
                                $salesExtraModel = new SalesMenuExtra([
                                    'attributes' => $extra
                                ]);
    
                                if ($salesMenuModel->promotionDetailID != 0) {
                                    if ($promotionModel->flagMenuExtra == 1) {
                                        $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                        $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $extra['price'];
                                        $salesExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;    
                                        $salesExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesExtraModel->price;                                
                                        if ($detailPromotionTypeID == 9) {                                        
                                            if ($promotionModel->discount > $extra['price']) {
                                                $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'];
                                            } else {
                                                $salesExtraModel->discountValue = (float) $extra['qty'] * $promotionModel->discount;
                                            }                                    
                                        } else {
                                            $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                        }
                                    } else {
                                        $salesExtraModel->discountValue = 0;
                                    }
                                } else {
                                    $salesExtraModel->discountValue = 0;
                                }
                                
                                if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                    if ($promotionHeadTypeID == 10) {
                                        if ($applyBillDiscountToExtra) {
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue);
                                            } else {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->price);
                                            }
                                            
                                            $tempMenuDiscountTotal += $salesMenuModel->qty * $salesExtraModel->qty * $salesExtraModel->inclusivePrice / 100 * $salesMenuModel->discount;
                                        }
                                    } else {
                                        if ($applyBillDiscountToExtra) {
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue);
                                            } else {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->price);
                                            }
                                        }
                                    }
                                }

                                $allMenuSubtotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->price);
                                $allMenuDiscountTotal += $salesMenuModel->qty * $salesExtraModel->discountValue;
    
                                if ($inclusiveMenuTemplateID) {
                                    $menuExtraModel = MenuExtra::find()
                                    ->where([
                                        'menuExtraID' => $salesExtraModel->menuExtraID
                                    ])
                                    ->one();
    
                                    $displayPriceValue = null;
                                    if (isset($extra['displayPriceValue'])) {
                                        $displayPriceValue = $extra['displayPriceValue'];
                                    }

                                    $applyPriceExtra = isset($displayPriceValue)
                                        ? $displayPriceValue
                                        : ($menuExtraModel ? $menuExtraModel->price : 0);
                                        

                                    if(($this->scanQrTakeAwayOff || $this->externalApi) && $inclusiveMenuTemplateID){
                                        $salesExtraModel->inclusivePrice = isset($salesExtraModel->inclusivePrice) 
                                            ? $salesExtraModel->inclusivePrice : $applyPriceExtra;
                                    }else{
                                        $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                                        if (!$salesTypeEzo) {
                                          $salesExtraModel->inclusivePrice = $inclusiveMenuTemplateID ? $applyPriceExtra : 0;
                                        }
                                    }

                                    if ($salesMenuModel->promotionDetailID != 0) {
                                        if ($promotionModel->flagMenuExtra == 1) {
                                            $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                            $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $extra['price'];
                                            $salesExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;    
                                            $salesExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesExtraModel->price;                                
                                            $salesExtraModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesExtraModel->inclusivePrice;
                                            if ($detailPromotionTypeID == 9) {
                                                if ($inclusiveMenuTemplateID) {
                                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                        if ($applyPriceExtra > 0) {
                                                            $tempPromotionDiscount = $salesExtraModel->price / $applyPriceExtra * $promotionModel->discount;
                                                        } else {
                                                            $tempPromotionDiscount = 0;
                                                        }
                                                        
                                                        if ($tempPromotionDiscount > $salesExtraModel->price) {
                                                            $salesExtraModel->discountValue = (float) $extra['qty'] * $salesExtraModel->price;
                                                            $salesExtraModel->inclusiveDiscountValue = (float) $salesExtraModel->qty * $applyPriceExtra;                                             
                                                            $discountValue = (float) $salesExtraModel->inclusiveDiscountValue;
                                                        } else {
                                                            if ($applyPriceExtra > 0) {
                                                                $percentageDiscountValue = $promotionModel->discount / $applyPriceExtra * 100;
                                                                $tempDiscountValue = $salesExtraModel->price * $percentageDiscountValue / 100;
                                                                $salesExtraModel->discountValue = (float) $salesExtraModel->qty * $tempDiscountValue;
                                                                $discountValue = (float) $salesExtraModel->qty * $promotionModel->discount;
                                                                $salesExtraModel->inclusiveDiscountValue = $discountValue;
                                                            } else {
                                                                $salesExtraModel->discountValue = 0;
                                                                $discountValue = 0;
                                                                $salesExtraModel->inclusiveDiscountValue = $discountValue;
                                                            }                                            
                                                        }
                                                    } else {
                                                        if ($promotionModel->discount > $applyPriceExtra) {
                                                            $salesExtraModel->inclusiveDiscountValue = $applyPriceExtra * $salesExtraModel->qty;
                                                        } else {
                                                            $salesExtraModel->inclusiveDiscountValue = $promotionModel->discount * $salesExtraModel->qty;                      
                                                        }
                                                    }
                                                }                                    
                                            } else {
                                                if ($inclusiveMenuTemplateID) {
                                                    $menuExtraSubtotal = $extra['price'] * $extra['qty'];
                                                    $menuExtraTotal = $applyPriceExtra * $extra['qty'];
    
                                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                        if ($detailPromotionTypeID == 1) {
                                                            $salesExtraModel->discountValue = (float) $menuExtraSubtotal * $promotionModel->discount / 100;
                                                            $salesExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                            $discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                        } else {
                                                            $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                                            $discountValue = $salesExtraModel->discountValue;
                                                        }
                                                    } else {
                                                        $salesExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                    }
                                                }
                                            }
                                        } else {
                                            $salesExtraModel->discountValue = 0;
                                            $salesExtraModel->inclusiveDiscountValue = 0;
                                        }
                                    } else {
                                        $salesExtraModel->discountValue = 0;
                                        $salesExtraModel->inclusiveDiscountValue = 0;
                                    }
    
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                if ($applyBillDiscountToExtra) {
                                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice - $salesExtraModel->inclusiveDiscountValue);
                                                }
                                            }
                                        } else if ($promotionHeadTypeID == 11) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice - $salesExtraModel->inclusiveDiscountValue);
                                            }
                                        } else {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                if ($applyBillDiscountToExtra) {
                                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice - $salesExtraModel->inclusiveDiscountValue);
                                                }
                                            }
                                        }
                                    } else {
                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                if ($applyBillDiscountToExtra) {
                                                    $tempMenuGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice);
                                                    $tempGrandTotal = $tempMenuGrandTotal;
                                                }
                                            }
                                        } else if ($promotionHeadTypeID == 11) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice);
                                                $tempGrandTotal = $tempMenuGrandTotal;
                                            }
                                        } else {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                if ($applyBillDiscountToExtra) {
                                                    $tempGrandTotal += $salesMenuModel->qty * ($salesExtraModel->qty * $salesExtraModel->inclusivePrice);
                                                }
                                            }
                                        }
                                    }

                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $salesExtraModel->otherTaxValue = (float) ($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue) / 100 * $salesExtraModel->otherTax;
                                        if ($salesExtraModel->otherTaxOnVat == 0) {
                                            $salesExtraModel->vatValue = (float) ($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue) / 100 * $salesExtraModel->vat;
                                            $salesExtraModel->otherVatValue = (float) ($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue) / 100 * $salesExtraModel->otherVat;
                                        } else {
                                            $salesExtraModel->vatValue = (float) (($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue) + $salesExtraModel->otherTaxValue) / 100 * $salesExtraModel->vat;
                                            $salesExtraModel->otherVatValue = (float) (($salesExtraModel->qty * $salesExtraModel->price - $salesExtraModel->discountValue) + $salesExtraModel->otherTaxValue) / 100 * $salesExtraModel->otherVat;
                                        }

                                        if ($promotionHeadTypeID == 10) {
                                            if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                if ($applyBillDiscountToExtra) {
                                                    $otherTaxTotal += $salesMenuModel->qty * $salesExtraModel->otherTaxValue;
                                                    $vatTotal += $salesMenuModel->qty * $salesExtraModel->vatValue;
                                                    $otherVatTotal += $salesMenuModel->qty * $salesExtraModel->otherVatValue;
                                                }
                                            }
                                        } else {
                                            $otherTaxTotal += $salesMenuModel->qty * $salesExtraModel->otherTaxValue;
                                            $vatTotal += $salesMenuModel->qty * $salesExtraModel->vatValue;
                                            $otherVatTotal += $salesMenuModel->qty * $salesExtraModel->otherVatValue;
                                        }
                                    }
                                }
                            }
                        }

                        if ($detailPromotionTypeID != 4) {
                            if ($salesMenuModel->price != $salesMenuModel->originalPrice) {
                                $issetSpecialPrice = true;
                            } else {
                                $issetSpecialPrice = false;
                            }
                        }
                    }
                } else {
                    $salesMenuModel = SalesMenu::find()
                        ->andWhere(['ID' => $salesMenu['ID']])
                        ->one();

                    if ($salesMenuModel) {
                        $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                        if (isset($salesMenu['flagLuxuryItem'])) {
                            $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                        }
                        if ($salesMenuModel->qty != $salesMenu['qty']) {
                            $salesMenuModel->qty = $salesMenu['qty'];
                        }

                        $currentPromotionID = $salesMenuModel->promotionDetailID;
                        $salesMenuModel->load(['SalesMenu' => $salesMenu]);

                        // @Notes: Remove promo
                        if ($currentPromotionID != $salesMenu['promotionDetailID']) {
                            $this->removeMenuPromo($salesMenuModel,
                                $currentPromotionID, $appliedVat,
                                $inclusiveMenuTemplateID, $specialPriceArrModel);
                        }

                        // @Notes: Apply promo
                        if ($salesMenu['promotionDetailID'] != 0) {
                            if ($currentPromotionID != $salesMenu['promotionDetailID']) {
                                $this->applyMenuPromo($salesMenuModel,
                                    $salesMenu['promotionDetailID']);
                            }
                        }
                    } else {
                        $salesMenuModel = new SalesMenu([
                            'attributes' => $salesMenu
                        ]);

                        $salesMenuModel->salesNum = $this->salesModel->salesNum;
                        if ($salesMenuModel->promotionDetailID != 0) {
                            $this->applyMenuPromo($salesMenuModel);
                        }

                        $salesMenuModel->detachBehaviors();

                        $originSalesMenu = SalesMenu::findOne($salesMenu['localID']);
                        if ($originSalesMenu) {
                            $appliedVat = $isApplyOtherVat ? $originSalesMenu->otherVat : $originSalesMenu->vat;
                            if (isset($salesMenu['flagLuxuryItem'])) {
                                $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $originSalesMenu->otherVat) : $originSalesMenu->vat;
                            }
                            $salesMenuModel->batchID = $originSalesMenu->batchID;
                            $salesMenuModel->createdBy = $originSalesMenu->createdBy;
                            $salesMenuModel->createdDate = $originSalesMenu->createdDate;
                            $salesMenuModel->editedBy = Yii::$app->user->identity->username;
                            $salesMenuModel->editedDate = date('Y-m-d H:i:s');
                        } else {
                            throw new Exception('Failed to save cancel menu', [], 500);
                        }
                    }

                    $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                    if ($detailPromotionTypeID == 9) {
                        if ($promotionModel->discount > $salesMenuModel->price) {
                            $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                        } else {
                            $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                        }
                    }

                    $applyDiscountBill = false;
                    if ($promotionHeadModel) {
                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                    }

                    if ($applyDiscountBill) {
                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                            $tempMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price - $salesMenuModel->discountValue;
                        } else {
                            $tempMenuSubtotal += $salesMenuModel->qty * $salesMenuModel->price;
                        }
                        $tempMenuDiscountTotal += $salesMenuModel->qty * $salesMenuModel->inclusivePrice / 100 * $salesMenuModel->discount;
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
                            if (isset($salesMenuModel->inclusivePrice)) {
                                $inclusivePrice = $salesMenuModel->inclusivePrice;
                            } else {
                                if ($specialMenuPrice) {
                                    $inclusivePrice = $specialMenuPrice;
                                } else {
                                    $displayPriceValue = null;
                                    if (isset($salesMenu['displayPriceValue'])) {
                                        $displayPriceValue = $salesMenu['displayPriceValue'];
                                    }
                                    $inclusivePrice = isset($displayPriceValue) 
                                        ? $displayPriceValue 
                                        : (isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue);
                                }
                            }
                        }

                        if($detailPromotionTypeID == 7) {
                            $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                        }

                        //$inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                        // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                        if (strlen($salesMenuModel->customMenuName) > 0) {
                            $displayPriceValue = null;
                            if (isset($salesMenu['displayPriceValue'])) {
                                $displayPriceValue = $salesMenu['displayPriceValue'];
                            }

                            $checkMenuTemplatePrice = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                            $checkInclusivePrice = isset($salesMenuModel->inclusivePrice) ? $salesMenuModel->inclusivePrice : $checkMenuTemplatePrice;

                            $inclusivePrice = isset($displayPriceValue) ? $displayPriceValue : $checkInclusivePrice;
                            $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $inclusivePrice;
                        }

                        if ($salesMenuModel->promotionDetailID > 0) {
                            if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                            } else {
                                $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                $detailPromotionDiscount = $promotionModel->discount;
                            }
                            if ($detailPromotionTypeID == 9) {
                                $menuDiscountVal = 0;
                            } else {
                                $menuDiscountVal = $detailPromotionDiscount;
                            }
                        } else {
                            $menuDiscountVal = 0;
                        }

                        $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                        $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;

                        $newDiscountVal = $menuDiscountVal;
                        $salesMenuModel->discount = $newDiscountVal;
                        $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                        $discountValue = $salesMenuModel->discountValue;
                        $salesMenuModel->inclusiveDiscountValue = (float) $menuGrandTotal * $salesMenuModel->discount / 100;
                        if ($detailPromotionTypeID == 9) {

                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
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
                            $tempMenuID = 0;
                            $subsID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0 ;
                            if ($subsID != 0) {
                                $tempMenuID = $subsID;
                            }
                            else{
                                $menuPromotionID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0;
                                $tempMenuID = $package['menuID'];
                                if($menuPromotionID != 0 && ($package['statusID'] != 1 || $package['statusID'] != 12)){
                                    $tempMenuID = $menuPromotionID;
                                }
                            }
                            $salesMenuPackageModel = SalesMenu::find()
                                ->andWhere(['ID' => $package['ID']])
                                ->one();

                            $currentPromotionID = $salesMenuModel->promotionDetailID;
                            $salesMenuPackageModel->load(['SalesMenu' => $package]);
                            // @Notes: Remove promo
                            $discountBill = 0;
                            if ($currentPromotionID != $package['promotionDetailID']) {
                                $this->removeMenuPromo($salesMenuPackageModel,
                                    $currentPromotionID);
                                if ($promotionModel) {
                                    if ($promotionModel->flagPackageContent == 1) {
                                        // @Notes: Apply promo
                                        if ($salesMenu['promotionDetailID'] != 0) {
                                            $this->applyMenuPromo($salesMenuPackageModel,
                                                $salesMenu['promotionDetailID']);
                                        }

                                        if ($salesMenuPackageModel->promotionDetailID != 0) {
                                            if ($detailPromotionTypeID == 4) {
                                                $salesMenuPackageModel->discount = 0;
                                                $salesMenuPackageModel->price = 0;
                                            }
                                            
                                            if ($detailPromotionTypeID == 9) {
                                                $salesMenuPackageModel->discount = 0;
                                                $salesMenuPackageModel->promotionDetailID = $salesMenuModel->promotionDetailID;
                                                if ($promotionModel->discount > $salesMenuPackageModel->price) {
                                                    $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price;
                                                } else {
                                                    $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $promotionModel->discount;
                                                }
                                            } else {
                                                $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                            }
                                        } else {
                                            $salesMenuPackageModel->discountValue = 0;
                                        }
                                    } else {
                                        $salesMenuPackageModel->discountValue = 0;
                                    }
                                } else {
                                    $salesMenuPackageModel->discountValue = 0;
                                }
                            }

                            if ($applyDiscountBill) {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyBillDiscountToPackageContent) {
                                        $applyDiscountPck = false;
                                        if ($promotionHeadModel) {
                                            $applyDiscountPck = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                        }
    
                                        if ($applyDiscountPck) {
                                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue);
                                            } else {
                                                $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
                                            }
                                            
                                            $tempMenuDiscountTotal += $salesMenuModel->qty * $salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice / 100 * $salesMenuModel->discount;
                                        }
                                    }
                                } else {
                                    if ($applyBillDiscountToPackageContent) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price - $salesMenuPackageModel->discountValue);
                                        } else {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->price);
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

                                $displayPriceValue = null;
                                if (isset($package['displayPriceValue'])) {
                                    $displayPriceValue = $package['displayPriceValue'];
                                }
    
                                $applyPackagePrice = isset($displayPriceValue)
                                    ? $displayPriceValue
                                    : ($menuPackageModel ? ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price) : $displayPriceValue);

                                if ($promotionModel) {
                                    if ($promotionModel->flagPackageContent == 1) {
                                        // @Notes: Apply promo
                                        if ($salesMenu['promotionDetailID'] != 0) {
                                            $this->applyMenuPromo($salesMenuPackageModel,
                                                $salesMenu['promotionDetailID']);
                                        }

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
                                                    } else {
                                                        if ($promotionModel->discount > $applyPackagePrice) {
                                                            $salesMenuPackageModel->inclusiveDiscountValue = $applyPackagePrice * $salesMenuPackageModel->qty;
                                                        } else {
                                                            $salesMenuPackageModel->inclusiveDiscountValue = $promotionModel->discount * $salesMenuPackageModel->qty;                      
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
                                                        $salesMenuPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
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
                                        if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscount) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice - $salesMenuPackageModel->inclusiveDiscountValue);
                                            }
                                        }
                                    } else if ($promotionHeadTypeID == 11) {
                                        if ($applyDiscountBill) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
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
                                        if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscount) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
                                                $tempGrandTotal = $tempMenuGrandTotal;
                                            }
                                        }
                                    } else if ($promotionHeadTypeID == 11) {
                                        if ($applyDiscountBill) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscount) {
                                                $tempMenuGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
                                                $tempGrandTotal = $tempMenuGrandTotal;
                                            }
                                        }
                                    } else {
                                        if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscount) {
                                                $tempGrandTotal += $salesMenuModel->qty * ($salesMenuPackageModel->qty * $salesMenuPackageModel->inclusivePrice);
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
                                        if ($applyDiscountBill && $applyBillDiscountToPackageContent) {
                                            $applyDiscount = false;
                                            if ($promotionHeadModel) {
                                                $applyDiscount = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenuPackageModel, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                                            }
        
                                            if ($applyDiscount) {
                                                $otherTaxTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherTaxValue;
                                                $vatTotal += $salesMenuModel->qty * $salesMenuPackageModel->vatValue;
                                                $otherVatTotal += $salesMenuModel->qty * $salesMenuPackageModel->otherVatValue;
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

                    if (isset($salesMenu['extras'])) {
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
                                    
                            $displayPriceValue = null;
                            if (isset($extra['displayPriceValue'])) {
                                $displayPriceValue = $extra['displayPriceValue'];
                            }

                            $applyExtraPrice = isset($displayPriceValue)
                                ? $displayPriceValue
                                : ($menuExtraModel ? $menuExtraModel->price : $displayPriceValue);
    
                            $currentPromotionID = $salesMenuModel->promotionDetailID;
                            $discountBill = 0;
                            if ($salesMenuModel->promotionDetailID != 0) {
                                if ($promotionModel->flagMenuExtra == 1) {
                                    $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                    $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $applyExtraPrice;
                                    $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                    $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;                                   
                                    if ($detailPromotionTypeID == 9) {                                        
                                        if ($promotionModel->discount > $salesMenuExtraModel->price) {
                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price;
                                        } else {
                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                        }                                        
                                    } else {                                        
                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                    }
                                } else {
                                    $salesMenuExtraModel->discountValue = 0;
                                }
                            } else {
                                $salesMenuExtraModel->discount = 0;
                                $extra['discount'] = 0;
                                $salesMenuExtraModel->discountValue = 0;
                            }

                            if ($applyDiscountBill) {
                                if ($promotionHeadTypeID == 10) {
                                    if ($applyBillDiscountToExtra) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue);
                                        } else {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
                                        }
                                        
                                        $tempMenuDiscountTotal += $salesMenuModel->qty * $salesMenuExtraModel->qty * $salesMenuExtraModel->inclusivePrice / 100 * $salesMenuModel->discount;
                                    }
                                } else {
                                    if ($applyBillDiscountToExtra) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price - $salesMenuExtraModel->discountValue);
                                        } else {
                                            $tempMenuSubtotal += $salesMenuModel->qty * ($salesMenuExtraModel->qty * $salesMenuExtraModel->price);
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
                                        $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $applyExtraPrice;
                                        $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                        $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;        
                                        $salesMenuExtraModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->inclusivePrice;                           
                                        if ($detailPromotionTypeID == 9) {
                                            if ($inclusiveMenuTemplateID) {
                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    if ($applyExtraPrice > 0) {
                                                        $tempPromotionDiscount = $salesMenuExtraModel->price / $applyExtraPrice * $promotionModel->discount;
                                                    } else {
                                                        $tempPromotionDiscount = 0;
                                                    }
                                                    
                                                    if ($tempPromotionDiscount > $salesMenuExtraModel->price) {
                                                        $salesMenuExtraModel->discountValue = (float) $extra['qty'] * $salesMenuExtraModel->price;
                                                        $salesMenuExtraModel->inclusiveDiscountValue = (float) $salesMenuExtraModel->qty * $applyExtraPrice;                                             
                                                        $discountValue = (float) $salesMenuExtraModel->inclusiveDiscountValue;
                                                    } else {
                                                        if ($applyExtraPrice > 0) {
                                                            $percentageDiscountValue = $promotionModel->discount / $applyExtraPrice * 100;
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
                                                    if ($promotionModel->discount > $applyExtraPrice) {
                                                        $salesMenuExtraModel->inclusiveDiscountValue = $applyExtraPrice * $salesMenuExtraModel->qty;
                                                    } else {
                                                        $salesMenuExtraModel->inclusiveDiscountValue = $promotionModel->discount * $salesMenuExtraModel->qty;                      
                                                    }
                                                }
                                            }
                                        } else {
                                            if ($inclusiveMenuTemplateID) {
                                                $menuExtraSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                                $menuExtraTotal = $applyExtraPrice * $salesMenuExtraModel->qty;

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
                                                } else {
                                                    $salesMenuExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
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

                    if ($detailPromotionTypeID != 4) {
                        if ($salesMenuModel->price != $salesMenuModel->originalPrice) {
                            $issetSpecialPrice = true;
                        } else {
                            $issetSpecialPrice = false;
                        }
                    }
                }
            }

            if ($promotionPaymentMethodIDs) {
                $promotionPaymentMethodIDs = array_unique($promotionPaymentMethodIDs);
                if (count($promotionPaymentMethodIDs) > 1) {
                    $errPaymentMethodMessage = 'Error: Multi payment method for promotions';
                    $this->errMsg = $errPaymentMethodMessage;
                    throw new Exception(json_encode($errPaymentMethodMessage), [], 400);
                }
            }

            if ($taxInclusiveAfterDiscount) {
                $tempMenuSubtotal = $tempMenuGrandTotal;
            } else {
                if ($inclusiveMenuTemplateID) {
                    if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 11) {
                        $tempMenuSubtotal = $tempMenuGrandTotal;
                    }
                }
            }

            $newCurrentSalesMenuModel = SalesMenu::find()
                ->with('branchMenu')
                ->with('salesMenuCompletionChecker')
                ->with('salesMenuCompletionKitchen')
                ->with('childSalesMenus.salesMenuCompletionChecker')
                ->with('childSalesMenus.salesMenuCompletionKitchen')
                ->where(['IN', 'localID', $salesMenuIDs])
                ->all();

            $newCurrentSalesCancelModel = SalesMenu::find()
                ->where(['IN', 'localID', $salesMenuCancelIDs])
                ->all();

            $tempOrderIDs = [];
            $allInclusiveBillDiscount = 0;
            $statusSalesDoneArray = [];
            $isHaveNewOrder = in_array(1 , array_column($this->salesMenu, 'statusID'));
            $isFireOrder = in_array(46 , array_column($this->salesMenu, 'statusID'));
            $hasUpdatePromo = false;
            if ($promotionHeadModel) {
                if (in_array($promotionHeadModel->promotionTypeID, [1, 3, 5, 6, 10]) &&
                    $promotionHeadModel->discount != $this->promotionDiscount) {
                    $hasUpdatePromo = true;
                    $this->promotionDiscount = $promotionHeadModel->discount;
                }
            }

            $currentSalesModel = SalesHead::findOne($this->salesNum);
            if ($currentSalesModel) {
                if ($currentSalesModel->promotionID != $this->promotionID ||
                    $currentSalesModel->promotionDiscount != $this->promotionDiscount) {
                    $hasUpdatePromo = true;
                }

                if ($newCurrentSalesMenuModel && $currentSalesModel->promotionID > 0) {
                    foreach ($newCurrentSalesMenuModel as $currentSalesMenu) {
                        if (isset($salesOnApplyPromo[$currentSalesMenu->localID])) {
                            if ($salesOnApplyPromo[$currentSalesMenu->localID]['promotionDetailID'] != $currentSalesMenu->promotionDetailID) {
                                $hasUpdatePromo = true;
                            }
                        }
                    } 
                }
            }

            foreach ($this->salesMenu as $salesMenu) {
                $detailPromotionTypeID = 0;
                $detailPromotionPackage = 0;
                $detailPromotionExtra = 0;
                $tempMenuID = 0;
                $isApplyOtherVat = ($vatSubject === 1 && (isset($salesMenu['menuFlagTax']) && (int) $salesMenu['menuFlagTax'] === 2));
                $isSplittedPromoReward = isset($salesMenu['isSplittedPromoReward']) && $salesMenu['isSplittedPromoReward'] === true;

                if (isset($salesMenu['tempOrderID'])) {
                    if (!in_array($salesMenu['tempOrderID'], $tempOrderIDs)) {
                        $tempOrderIDs[] = $salesMenu['tempOrderID'];
                    }
                }

                if (isset($salesMenu['promotionDetailID'])) {
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

                    if (in_array($salesMenu['statusID'], [13, 34, 14, 46])) {
                        $promotionModel = PromotionHead::find()
                            ->where(['promotionID' => $salesMenu['promotionDetailID']])
                            ->one();
                    } else {
                        $tempPromotioModel = PromotionHead::findActiveForMenu($tempMenuID,
                            $this->memberID, $this->employeeCode, null, $this->flagExternalMemberID);

                        $promotionModel = PromotionHead::find()
                            ->from(['promotion' => $tempPromotioModel])
                            ->andWhere(['promotionID' => $salesMenu['promotionDetailID']])
                            ->one();   
                        
                        if(!$promotionModel){
                            //check grabPromotion
                            $promotionModel = PromotionHead::find()
                                ->where(['promotionID' => $salesMenu['promotionDetailID']])
                                ->andWhere(['IN', 'promotionTypeID', [14, 15, 16]])
                            ->one();
                        }
                    }

                    if ($promotionModel) {
                        if ($this->memberID == null && $this->employeeCode == null && ( !$isMemberID && !$isMemberTada && !$isLoyalty && !$isLoopLite && !$isCapillary && !$isCapillaryV2 && !$isStamps)) {
                            if (in_array($promotionModel->promotionMemberTypeID, [1, 2, 3])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID == null && $this->employeeCode != null) {
                            if (in_array($promotionModel->promotionMemberTypeID, [3])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID != null && $this->employeeCode == null) {
                            if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        } else if ($this->memberID == null && $this->employeeCode == null && ($isMemberID || $isMemberTada || $isLoyalty || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps)) {
                            if (in_array($promotionModel->promotionMemberTypeID, [2])) {
                                $salesMenu['promotionDetailID'] = 0;
                                $salesMenu['promotionDetailName'] = '';
                                $salesMenu['promotionVoucherCode'] = '';
                                $salesMenu['discount'] = 0;
                                $promotionModel = null;
                                self::setFlagEmployeeApplied();
                            }
                        }

                        if ($promotionModel == null) {
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
                                        $salesMenu['price'] = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat,
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
                    } else {
                        $promotionModel = PromotionHead::find()
                            ->andWhere(['promotionID' => $salesMenu['promotionDetailID']])
                            ->one();   
                        //handler for menuSubs
                        if($salesMenu['statusID'] == 1){
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
                                        $salesMenu['menuPromotionID'] = 0;
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
                                                    $package['menuPromotionID'] = 0;
                                                }
                                            }
                                        }
                                    }
                                    $newPackage[] = $package;
                                }
                                $salesMenu['packages'] = $newPackage;
                            }
                        }
                        
                        if (isset($salesMenu['promotionDetailID'] )) $salesMenu['promotionDetailID'] = 0;
                        if (isset($salesMenu['promotionDetailName'])) $salesMenu['promotionDetailName'] = '';
                        if (isset($salesMenu['promotionVoucherCode'])) $salesMenu['promotionVoucherCode'] = '';
                        if (isset($salesMenu['discount'])) $salesMenu['discount'] = 0;
                    }
                    
                    if ($promotionModel) {
                        $detailPromotionTypeID = $promotionModel->promotionTypeID;
                        $detailPromotionPackage = $promotionModel->flagPackageContent;
                        $detailPromotionExtra = $promotionModel->flagMenuExtra;

                        $menuPromotionCategoryIDs = [];
                        $menuPromotionCategoryDetailIDs = [];
                        $menuPromotionMenuIDs = [];
                        if ($salesMenu['promotionDetailID'] > 0) {
                            $detailPromotionModel = PromotionHead::find()
                                ->joinWith('promotionCategories')
                                ->where(['ms_promotionhead.promotionID' => $salesMenu['promotionDetailID']])
                                ->one();
                            if ($detailPromotionModel) {
                                foreach ($detailPromotionModel->promotionCategories as $promotionCategory) {
                                    $menuPromotionCategoryIDs[] = $promotionCategory->menuCategoryID;
                                    $menuPromotionCategoryDetailIDs[] = $promotionCategory->menuCategoryDetailID;
                                    $menuPromotionMenuIDs[] = $promotionCategory->menuID;
                                }
                            }
                        }

                    }

                }

                $discountBill = 0;
                if ($salesMenu['statusID'] == 1 || $salesMenu['statusID'] == 12 || $isSplittedPromoReward) {
                    // @Notes: Status 1 = New, 12 = Cancelled, BatchID 0 = New record

                    $isFullCancelQty = false;
                    $salesMenuModel = new SalesMenu([
                        'attributes' => $salesMenu
                    ]);
                    $salesMenuModel->salesNum = $this->salesModel->salesNum;
                    $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                    if (isset($salesMenu['flagLuxuryItem'])) {
                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                    }
                    if (!$salesMenuModel->createdBy && $salesMenuModel->salesType == 'POS') {
                        $salesMenuModel->createdBy = Yii::$app->user->identity->username;
                    }

                    $currentSalesMenuModel = null;
                    if ($salesMenu['statusID'] == 12) {
                        foreach ($newCurrentSalesCancelModel as $currentSales) {
                            if ($currentSales->localID == $salesMenu['localID']) {
                                $currentSalesMenuModel = $currentSales;
                            }
                        }
                        if ($currentSalesMenuModel) {
                            if ($currentSalesMenuModel->qty == $salesMenu['qty'] && 
                                ($currentSalesMenuModel->editedDate == $salesMenu['editedDate'] || in_array($currentSalesMenuModel->statusID, [34, 14]))) {
                                $salesMenuModel = $currentSalesMenuModel;
                                $salesMenuModel->statusID = 12;
                                $salesMenuModel->promotionVoucherCode = '';
                                $salesMenuModel->cancelNotes = $salesMenu['cancelNotes'];
                                $isFullCancelQty = true;
                            }
                        }
                    }

                    if (!$this->ezoQuickService) {
                        if ($salesMenuModel->promotionDetailID != 0) {
                            $this->applyMenuPromo($salesMenuModel);
                        }

                        $specialMenuPrice = null;
                        if (array_key_exists($salesMenu['menuID'],
                                $specialPriceArrModel)) {
                            $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                        }

                        $discountValue = 0;
                        if ($inclusiveMenuTemplateID) {
                            if ($salesMenuModel->price == 0 && $salesMenuModel->promotionDetailID > 0) {
                                $inclusivePrice = 0;
                            } else {
                                if ($specialMenuPrice) {
                                    $inclusivePrice = $specialMenuPrice;
                                    if (isset($salesMenu['salesType']) && $salesMenu['salesType'] == 'KIOSK') {
                                        if (isset($salesMenu['inclusivePrice'])) {
                                            if ($salesMenu['inclusivePrice'] != $inclusivePrice) {
                                                $inclusivePrice = $salesMenu['inclusivePrice'];
                                            }
                                        }
                                    }

                                    if ($salesMenu['price'] == $salesMenu['originalPrice']) {
                                        $salesMenuModel->price = self::getNetPrice($salesMenu['otherTax'], $otherTaxOnVat, $appliedVat, null, null, $specialMenuPrice);

                                        if ($salesMenu['promotionDetailID'] > 0) {
                                            $salesMenuModel->discount = 0;
                                            $salesMenuModel->discountValue = 0;
                                            $salesMenuModel->inclusiveDiscountValue = 0;
                                            $salesMenuModel->promotionDetailID = 0;
                                            $salesMenuModel->promotionVoucherCode = '';
                                        }
                                    }
                                } else {
                                    $displayPriceValue = null;
                                    if (isset($salesMenu['displayPriceValue'])) {
                                        $displayPriceValue = $salesMenu['displayPriceValue'];
                                    }

                                    if ($salesMenu['price'] != $salesMenu['originalPrice'] && $salesMenu['promotionDetailID'] == 0) {
                                        $salesMenuModel->price = $salesMenuModel->originalPrice;
                                        $displayPriceValue = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                                        if (!$this->checkSalesTypeEzo($salesMenu['salesType'])) {
                                          $this->specialPriceHasExp[] = $salesMenu['menuName'];
                                        }
                                    }

                                    $inclusivePrice = isset($displayPriceValue) 
                                        ? $displayPriceValue 
                                        : (isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue);

                                    if (isset($salesMenu['salesType']) && $salesMenu['salesType'] == 'KIOSK') {
                                        if (isset($menuTemplateDetailModel[$salesMenuModel->menuID])) {
                                            $kioskSubtotal = $salesMenu['total'];
                                            $tempSubtotal = $menuTemplateDetailModel[$salesMenuModel->menuID]->price * $salesMenu['qty'];
                                            if ($kioskSubtotal < $tempSubtotal) {
                                                $inclusivePrice = $displayPriceValue;
                                            } else {
                                                $inclusivePrice = $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                                            }
                                        }
                                    }
                                }
                            }
    
                            //$inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                            // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                            if (strlen($salesMenuModel->customMenuName) > 0) {
                                $displayPriceValue = null;
                                if (isset($salesMenu['displayPriceValue'])) {
                                    $displayPriceValue = $salesMenu['displayPriceValue'];
                                }

                                $checkMenuTemplatePrice = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                                $checkInclusivePrice = isset($salesMenuModel->inclusivePrice) ? $salesMenuModel->inclusivePrice : $checkMenuTemplatePrice;

                                $inclusivePrice = isset($displayPriceValue) ? $displayPriceValue : $checkInclusivePrice;
                                $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $inclusivePrice;
                            }
                            
                            if($detailPromotionTypeID == 7) {
                                $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                            }

                            // $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : 
                            //     (strlen($salesMenuModel->customMenuName) > 0 ? $salesMenuModel->price : $menuTemplateDetailModel[$salesMenuModel->menuID]->price);
                            $salesMenuModel->price = strlen($salesMenuModel->customMenuName) > 0 ? self::getInclusivePrice($inclusivePrice,
                                    $salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                                    $salesDecimalSetting, $settingDecimalMode) : $salesMenuModel->price;
                            
                            $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                            if ($salesTypeEzo) {
                                if ($salesMenu['salesType'] == 'EZO FS') {
                                  $salesMenuModel->price = $salesMenu['price'];
                                  $salesMenuModel->originalPrice = isset($salesMenu['originalInclusivePrice']) ? $salesMenu['originalInclusivePrice'] : $salesMenu['originalPrice'];
                                }
                                $inclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $inclusivePrice;
                            }

        
                            $salesMenuModel->inclusivePrice = $inclusivePrice;
                            if ($inclusiveMenuTemplateID && $salesMenuModel->inclusivePrice == $salesMenuModel->price && $salesMenuModel->inclusivePrice != 0) {
                                $salesMenuModel->price = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat, $salesDecimalSetting, $settingDecimalMode, $salesMenuModel->inclusivePrice);
                            }
                            
                            //$menuDiscountVal = $salesMenuModel->promotionDetailID > 0 ? $promotionArrModel[$salesMenuModel->promotionDetailID] : 0;
                            if ($salesMenuModel->promotionDetailID > 0) {
                                if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                    $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                    $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                                } else {
                                    $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                    $detailPromotionDiscount = $promotionModel->discount;
                                }
                                if ($detailPromotionTypeID == 9) {
                                    $menuDiscountVal = 0;
                                } else {
                                    $menuDiscountVal = $detailPromotionDiscount;
                                }
                            } else {
                                $menuDiscountVal = 0;
                                $detailPromotionTypeID = 0;
                                $detailPromotionDiscount = 0;
                            }
    
                            $menuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                            $menuGrandTotal = $inclusivePrice * $salesMenuModel->qty;    
                            $newDiscountVal = $menuDiscountVal; 
                            $salesMenuModel->discount = $newDiscountVal;
                            $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                            $discountValue = $salesMenuModel->discountValue;
                            $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $salesMenuModel->discount / 100;
                            if ($detailPromotionTypeID == 9) {
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
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
                            } else if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 5) {
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
                                        $salesDecimalSetting, $settingDecimalMode, $inclusivePrice);
                                    $salesMenuModel->discountValue = $netPrice * $salesMenuModel->qty * $promotionModel->discount / 100;
                                    $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                    $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                    $subtotalBeforeDiscount = $salesMenuModel->price * $salesMenuModel->qty;
                                    if ($salesMenuModel->otherTaxOnVat == 0) {                            
                                        $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                    } else {
                                        $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                    }
    
                                    $salesMenuModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                } else {
                                    $salesMenuModel->discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                }
                            }
                            
                            if (!$externalProcess) {
                                $salesMenuModel->total = $menuGrandTotal - $salesMenuModel->discountValue;
                            }
                        } else {
                            if ($specialMenuPrice) {
                                $salesMenuModel->price = $specialMenuPrice;
                                if ($salesMenu['price'] == $salesMenu['originalPrice']) {
                                    if ($salesMenu['promotionDetailID'] > 0) {
                                        $salesMenuModel->discount = 0;
                                        $salesMenuModel->discountValue = 0;
                                        $salesMenuModel->inclusiveDiscountValue = 0;
                                        $salesMenuModel->promotionDetailID = 0;
                                        $salesMenuModel->promotionVoucherCode = '';
                                    }
                                }
                            } else {
                                if ($salesMenu['price'] != $salesMenu['originalPrice'] && $salesMenu['promotionDetailID'] == 0) {
                                    $salesMenuModel->price = $salesMenuModel->originalPrice;
                                    if (!$this->checkSalesTypeEzo($salesMenu['salesType'])) {
                                        $this->specialPriceHasExp[] = $salesMenu['menuName'];
                                    }
                                }
                            }

                            $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                            if ($salesTypeEzo) {
                                $salesMenuModel->price = $salesMenu['price'];
                                $salesMenuModel->originalPrice = $salesMenu['originalPrice'];
                            }

                            $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                            if ($detailPromotionTypeID == 9) {
                                if ($promotionModel->discount > $salesMenuModel->price) {
                                    $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                                } else {
                                    $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                                }
                            }
                        }
    
                        $inclusiveDiscountBill = 0;
                        $otherTaxDiscountBill = 0;
                        if ($salesMenu['otherTax'] >= 0 || $salesMenu['vat'] >= 0 || $salesMenu['otherVat'] >= 0) {
                            if ($issetSpecialPrice) {
                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11])) {
                                    $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                        $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                                
                                    $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                }
                            } else {
                                $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                    $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode,
                                    [], 0, 0, 0, SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT, $allMenuDiscountTotal);
                                
                                if ($otherTaxCalculationType == 2) {
                                    $otherTaxDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                        $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, 
                                        [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                }
                                
                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                        $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                            }
                        }

                        $applyDiscountBill = false;
                        if ($promotionHeadModel) {
                            $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                        }
    
                        if ($inclusiveMenuTemplateID) {
                            $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                            if (isset($salesMenu['flagLuxuryItem'])) {
                                $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                            }
                            
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                $currentMenuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                                $totalAfterBillDisc = 0 > ($menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue) ? 0 : $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue;
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

                                        $salesMenuModel->dppValue = $dppValue;
                                    }
                                    $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                } else {
                                    $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                    $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                    
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

                                        $salesMenuModel->dppValue = $dppValue;
                                    }
                                    $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                }

                                if ($salesMenuModel->discountValue > 0) {
                                    $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                } else {
                                    $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                                }
                            } else {
                                $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                $tempMenuSubtotalBeforeTax = $menuGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                $totalAfterBillDisc = ($discountBill > 0 || $salesMenuModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $menuGrandTotal, $salesMenuModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandTotal;
                                $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                                if ($salesMenuModel->otherTaxOnVat == 0) {
                                    $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedVat + $salesMenuModel->otherTax));
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

                                        $salesMenuModel->dppValue = $dppValue;
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

                                        $salesMenuModel->dppValue = $dppValue;
                                    }
                                    $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                }
                            }
                        } else {
                            $menuSubtotalBeforeDiscount = ($salesMenuModel->price * $salesMenuModel->qty);
                            $menuSubtotalAfterDiscount = ($salesMenuModel->price * $salesMenuModel->qty) - $salesMenuModel->discountValue - $discountBill;
                            $menuSubtotalAfterDiscountOtherTax = ($salesMenuModel->price * $salesMenuModel->qty) - $salesMenuModel->discountValue - $otherTaxDiscountBill;
                            if ($taxCalculationType == 2 && $otherTaxCalculationType == 2) {
                                $menuSubtotalAfterDiscountOtherTax = ($salesMenuModel->price * $salesMenuModel->qty) - $salesMenuModel->discountValue - $discountBill;
                            }

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
                                $salesMenuModel->otherVatValue = (float) $menuSubtotalAfterDiscountOtherTax / 100 * $salesMenuModel->otherVat;
                                if (isset($salesMenuModel->flagLuxuryItem)) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $salesMenuModel->flagLuxuryItem,
                                        $salesMenuModel->otherTaxOnVat,
                                        $menuSubtotalAfterDiscountOtherTax,
                                        $salesMenuModel->otherTaxValue
                                    );
                                    $salesMenuModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $salesMenuModel->otherVat
                                    );

                                    $salesMenuModel->dppValue = $dppValue;
                                }
                            } else {
                                $salesMenuModel->vatValue = (float) (($taxCalculationType == 2 ? $menuSubtotalAfterDiscount : $menuSubtotalBeforeDiscount) + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->vat;
                                $salesMenuModel->otherVatValue = (float) ($menuSubtotalAfterDiscountOtherTax + $salesMenuModel->otherTaxValue) / 100 * $salesMenuModel->otherVat;
                                if (isset($salesMenuModel->flagLuxuryItem)) {
                                    $dppValue = CalculateTotal::getDppValue(
                                        $salesMenuModel->flagLuxuryItem,
                                        $salesMenuModel->otherTaxOnVat,
                                        $menuSubtotalAfterDiscountOtherTax,
                                        $salesMenuModel->otherTaxValue
                                    );
                                    $salesMenuModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                        $dppValue,
                                        $salesMenuModel->otherVat
                                    );

                                    $salesMenuModel->dppValue = $dppValue;
                                }
                            }
                        }

                        if (!$inclusiveMenuTemplateID && !$externalProcess) {
                            $promotionID = isset($salesMenuModel->promotionID) ? $salesMenuModel->promotionID : 0;
                            $salesMenuModel->calculateTotal(0, 0, $discountBill, $promotionID, $otherTaxDiscountBill);
                        }
    
                        if ($inclusiveMenuTemplateID && !$externalProcess) {
                            if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                $salesMenuModel->total = $menuGrandTotal - $discountValue - $discountBill;
                            } else {
                                $salesMenuModel->total = $menuGrandTotal - $salesMenuModel->discountValue;
                            }

                            if (0 > $salesMenuModel->total) {
                                $salesMenuModel->total = 0;
                            }
    
                            if ($discountBill > 0) {
                                $salesMenuModel->total = $salesMenuModel->price * $salesMenuModel->qty - $salesMenuModel->discountValue + $salesMenuModel->otherTaxValue + $salesMenuModel->vatValue + $salesMenuModel->otherVatValue;
                            }
                        }
                        // @Notes: Unset promo for cancelled menu
                        if ($salesMenuModel->statusID == 12) {
                            $salesMenuModel->promotionDetailID = 0;
                            $salesMenuModel->promotionVoucherCode = '';
                        }
                        // @Notes: Override original price for open price transaction
                        if (strlen($salesMenuModel->customMenuName) > 0) {
                            $salesMenuModel->originalPrice = $salesMenuModel->price;
                        }
                    }

                    if (!$salesMenuModel->save()) {
                        Yii::error($salesMenuModel->getErrors());
                        if(strlen($salesMenuModel->notes) > 300){
                            throw new Exception('Notes should contain at most 300 characters.', [], 400);
                        }
                        throw new Exception('Failed to save menu', [], 500);
                    } else {
                        if ($salesMenuModel->statusID == 19) {
                            $newIDsCancel[] = [
                                'ID' => $salesMenuModel->ID,
                                'batchID' => $salesMenu['batchID'],
                                'createdDate' => $salesMenu['createdDate']];
                        }

                        // @notes Save Menu Related
                        SalesMenuRelated::saveSalesMenuRelated($salesMenuModel->salesNum, $salesMenuModel->ID, $salesMenu, $this->salesMenu);

                        // @notes Save Menu Recommendation
                        if (isset($salesMenu['flagRecommendation']) && $salesMenu['flagRecommendation']) {
                            $salesType = isset($salesMenu['salesType']) && $salesMenu['salesType'] ? $salesMenu['salesType'] : 'POS';
                            SalesMenuRecommendation::saveSalesMenuRecommendation($salesMenuModel->salesNum, $salesMenuModel->ID, $salesType);
                        }
                        
                        SalesRewardMenu::adjustSalesRewardMenu(
                            $this->externalMembershipTypeID,
                            $salesMenuModel,
                            isset($salesMenu['rewardType']) ? $salesMenu['rewardType'] : null
                        );

                        // @notes save sales menu vat (ppn)
                        if (isset($salesMenu['flagLuxuryItem']) && $salesMenuModel->otherVat > 0) {
                            if ($salesMenuModel->statusID == 19) {
                                SalesMenuVat::deleteSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID);
                            } else {
                                SalesMenuVat::saveSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID, $salesMenuModel->dppValue, $salesMenu['flagLuxuryItem']);
                                $dppValueTotal += $salesMenuModel->dppValue;
                            }
                        }

                        if ($this->ezoFullService) {
                            $rewardType = $salesMenuModel->salesRewardMenu ? $salesMenuModel->salesRewardMenu->rewardType : null;
                            if (((isset($salesMenu['rewardType']) && $salesMenu['rewardType'] != '') || $rewardType != null)) {
                                $salesMenu['localID'] = $salesMenuModel->localID;
                            }

                            if (isset($salesMenu['statusID']) && $salesMenu['statusID'] == '1') {
                                $this->newSalesMenuFs[] = $salesMenu;
                            }
                        }
                        if ($kitchenFireManagement && $salesMenuModel->flagHoldOrder) SalesProcessMenu::saveSalesProcessMenu($salesMenuModel);
                        if ($this->isEmployeeApplied) {
                            $this->tempSalesMenu = SalesMenu::findSalesAppliedEmployee($this->salesNum);
                        }
                    }
                    if (!$isSplittedPromoReward) $newIDs[] = $salesMenuModel->ID;

                    if ($salesMenu['statusID'] == 1) {
                        $allInclusiveBillDiscount += isset($inclusiveDiscountBill) ? $inclusiveDiscountBill : 0; 
                    }

                    $packageItems = isset($salesMenu['packages']) ? $salesMenu['packages'] : [];
                    if ($packageItems) {
                        $salesMenuModel->menuRefID = $salesMenuModel->ID;
                        if (!$salesMenuModel->save()) {
                            throw new Exception('Failed to update main menu package');
                        }

                        foreach ($salesMenu['packages'] as $package) {
                            $isApplyPckOtherVat = ($vatSubject === 1 && (isset($package['menuFlagTax']) && $package['menuFlagTax'] === 2));
                            $tempMenuID = 0;
                            $subsID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0 ;
                            if ($subsID != 0) {
                                $tempMenuID = $subsID;
                            }
                            else{
                                $menuPromotionID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0;
                                $tempMenuID = $package['menuID'];
                                if($menuPromotionID != 0 && ($package['statusID'] != 1 || $package['statusID'] != 12)){
                                    $tempMenuID = $menuPromotionID;
                                }
                            }
                            $discountBillPackage = 0;
                            $salesPackageModel = new SalesMenu([
                                'attributes' => $package
                            ]);
                            $salesPackageModel->salesNum = $this->salesModel->salesNum;
                            $salesPackageModel->menuRefID = $salesMenuModel->ID;
                            
                            if ($package['statusID'] == 12) {
                                $currentSalesMenuPackageModel = SalesMenu::findOne($package['localID']);
                                if ($currentSalesMenuPackageModel) {
                                    if ($currentSalesMenuPackageModel->qty == (float)$package['qty'] && $isFullCancelQty &&
                                        ($currentSalesMenuPackageModel->editedDate == $package['editedDate'] || in_array($currentSalesMenuPackageModel->statusID, [34, 14]))) {
                                        $salesPackageModel = $currentSalesMenuPackageModel;
                                        $salesPackageModel->statusID = 12;
                                        $salesPackageModel->promotionVoucherCode = '';
                                        $salesPackageModel->cancelNotes = $package['cancelNotes'];
                                    }
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
                                    'ms_menupackage.menuGroupID' => $salesPackageModel->menuGroupID
                                ])
                            ->one();

                            $displayPriceValue = null;
                            if (isset($package['displayPriceValue'])) {
                                $displayPriceValue = $package['displayPriceValue'];
                            }

                            $applyPackagePrice = isset($displayPriceValue)
                                ? $displayPriceValue
                                : ($menuPackageModel ? ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price) : $displayPriceValue);
    
                            $salesTypeEzo = $this->checkSalesTypeEzo($package['salesType']);
                            if ($salesTypeEzo) $applyPackagePrice = isset($package['inclusivePrice']) ? $package['inclusivePrice'] : $applyPackagePrice;

                            if ($menuPackageModel) {
                                if(($this->scanQrTakeAwayOff || $this->externalApi) && $inclusiveMenuTemplateID){
                                    $salesPackageModel->inclusivePrice = isset($salesPackageModel->inclusivePrice) 
                                        ? $salesPackageModel->inclusivePrice : $applyPackagePrice;
                                }else{
                                    $salesPackageModel->inclusivePrice = $inclusiveMenuTemplateID ? $applyPackagePrice : 0;
                                }
                            } else {
                                if($externalProcess) {
                                    $salesPackageModel->inclusivePrice = $package['total'] / (float)$package['qty'];
                                }
                            }

                            if (!$this->ezoQuickService) {
                                $discountValue = 0;
                                if ($salesMenuModel->promotionDetailID != 0) {
                                    if ($promotionModel->flagPackageContent == 1) {
                                        // @Notes: Apply promo
                                        if ($salesMenu['promotionDetailID'] != 0) {
                                            $applyToPackage = true;
                                            if (count($detailPromotionModel->promotionCategories) > 0) {
                                                $menuModel = Menu::find()
                                                    ->joinWith('menuCategoryDetail')
                                                    ->where(['menuID' => $tempMenuID])
                                                    ->one();
                
                                                if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                                    $applyToPackage = true;
                                                } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                                    $applyToPackage = true;
                                                } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                                    $applyToPackage = true;
                                                } else {
                                                    $applyToPackage = false;
                                                }
                                            } else {
                                                $applyToPackage = true;
                                            }

                                            if ($applyToPackage) {
                                                $this->applyMenuPromo($salesPackageModel,
                                                    $salesMenu['promotionDetailID']);
                                            }
                                        }                    
                                        
                                        $salesPackageModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesPackageModel->inclusivePrice;
                                        if ($salesPackageModel->promotionDetailID != 0) {
                                            $package['price'] = $detailPromotionTypeID == 4 ? 0 : $package['price'];
                                            $package['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                            $salesPackageModel->price = $detailPromotionTypeID == 4 ? 0 : $salesPackageModel->price;
                                            if ($detailPromotionTypeID == 9) {
                                                if ($inclusiveMenuTemplateID) {
                                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                        if ($applyPackagePrice > 0) {
                                                            $tempPromotionDiscount = $salesPackageModel->price / $applyPackagePrice * $promotionModel->discount;
                                                        } else {
                                                            $tempPromotionDiscount = 0;
                                                        }
                
                                                        if ($tempPromotionDiscount > $salesPackageModel->price) {
                                                            $salesPackageModel->discountValue = (float) $salesPackageModel->qty * $salesPackageModel->price;
                                                            $salesPackageModel->inclusiveDiscountValue = (float) $salesPackageModel->qty * $applyPackagePrice;                                            
                                                            $discountValue = (float) $salesPackageModel->inclusiveDiscountValue;
                                                        } else {
                                                            if ($applyPackagePrice > 0) {
                                                                $percentageDiscountValue = $promotionModel->discount / $applyPackagePrice * 100;
                                                                $tempDiscountValue = $salesPackageModel->price * $percentageDiscountValue / 100;
                                                                $salesPackageModel->discountValue = (float) $salesPackageModel->qty * $tempDiscountValue;
                                                                $discountValue = (float) $salesPackageModel->qty * $promotionModel->discount;
                                                                $salesPackageModel->inclusiveDiscountValue = $discountValue;
                                                            } else {
                                                                $salesPackageModel->discountValue = 0;
                                                                $discountValue = 0;
                                                                $salesPackageModel->inclusiveDiscountValue = $discountValue;
                                                            }
                                                        }
                                                    } else {
                                                        if ($promotionModel->discount > $applyPackagePrice) {
                                                            $salesPackageModel->discountValue = (float) $package['qty'] * $applyPackagePrice;
                                                        } else {
                                                            $salesPackageModel->discountValue = (float) $package['qty'] * $promotionModel->discount;
                                                        }
                                                    }
                                                } else {
                                                    if ($promotionModel->discount > $package['price']) {
                                                        $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'];
                                                    } else {
                                                        $salesPackageModel->discountValue = (float) $package['qty'] * $promotionModel->discount;
                                                    }
                                                }
                                            } else {
                                                if ($inclusiveMenuTemplateID) {
                                                    $menuPackageSubtotal = $package['price'] * (float)$package['qty'];
                                                    $menuPackageTotal = $applyPackagePrice * (float)$package['qty'];
        
                                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                        if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 5) {
                                                            $appliedPckVat = $isApplyPckOtherVat ? $salesPackageModel->otherVat : $salesPackageModel->vat;
                                                            $salesPackageModel->discountValue = (float) $menuPackageSubtotal * $promotionModel->discount / 100;
                                                            $salesPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                            $discountValue = $menuPackageTotal * $promotionModel->discount / 100;

                                                            $subtotalBeforeDiscount = $salesPackageModel->price * $salesPackageModel->qty;
                                                            if ($salesPackageModel->otherTaxOnVat == 0) {                        
                                                                $subtotalAfterDiscount = ($menuPackageTotal - $salesPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesPackageModel->otherTax);
                                                            } else {
                                                                $subtotalAfterDiscount = ($menuPackageTotal - $salesPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesPackageModel->otherTax);
                                                            }

                                                            $salesPackageModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                                        } else {
                                                            $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                                            $discountValue = $salesPackageModel->discountValue;
                                                        }
                                                    } else {                                                
                                                        $newPackageDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuPackageSubtotal,
                                                                $menuPackageTotal, $promotionModel->discount);
                                                        $salesPackageModel->discount = $newPackageDiscountVal;
                                                        $package['discount'] = $newPackageDiscountVal;
                                                        if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 5) {
                                                            $salesPackageModel->discountValue = (float) $menuPackageTotal * $promotionModel->discount / 100;
                                                        } else {
                                                            $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                                        }
                                                    }                                            
                                                } else {
                                                    $salesPackageModel->discountValue = (float) $package['qty'] * $package['price'] / 100 * $package['discount'];
                                                }
                                            }
                                        } else {
                                            $salesPackageModel->discountValue = 0;
                                            $salesPackageModel->inclusiveDiscountValue = 0;
                                        }                                    
                                    } else { 
                                        $salesPackageModel->discountValue = 0;
                                        $salesPackageModel->inclusiveDiscountValue = 0;
                                    }
                                } else {
                                    $salesPackageModel->discountValue = 0;
                                    $salesPackageModel->inclusiveDiscountValue = 0;
                                }

                                $inclusiveDiscountBillPackage = 0;
                                $otherTaxDiscountBillPackage = 0;
                                if ($package['otherTax'] >= 0 || $package['vat'] >= 0 || $package['otherVat'] >= 0) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($this->promotionID != $salesMenuModel->promotionDetailID) {
                                            if ($issetSpecialPrice) {
                                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11])) {
                                                    if ($promotionHeadTypeID == 10) {
                                                        if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                            if ($applyBillDiscountToPackageContent) {
                                                                $discountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                                $inclusiveDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                            }
                                                        }
                                                    } else {
                                                        $discountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                        $inclusiveDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                    }
                                                }
                                            } else {
                                                if ($promotionHeadTypeID == 10) {
                                                    if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                        if ($applyBillDiscountToPackageContent) {
                                                            $discountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                            $inclusiveDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                                    $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                        }
                                                    }
                                                } else {
                                                    $discountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                    $inclusiveDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                }
                                            }
                                        }
                                    } else {
                                        if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                            if ($applyBillDiscountToPackageContent) {
                                                $discountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                if ($otherTaxCalculationType == 2) {
                                                    $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                                        [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                                }
                                                $inclusiveDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesPackageModel, $salesPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                            }
                                        }
                                    }
                                }

                                if ($inclusiveMenuTemplateID) {
                                    $appliedVat = $isApplyOtherVat ? $salesPackageModel->otherVat : $salesPackageModel->vat;
                                    if (isset($package['flagLuxuryItem'])) {
                                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $salesPackageModel->otherVat) : $salesPackageModel->vat;
                                    }
                                    $packageGrandTotal = $salesPackageModel->inclusivePrice * $salesPackageModel->qty;
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $appliedPckVat = $isApplyPckOtherVat ? $salesPackageModel->otherVat : $salesPackageModel->vat;
                                        if (isset($package['flagLuxuryItem'])) {
                                            $appliedPckVat = $isApplyPckOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $salesPackageModel->otherVat) : $salesPackageModel->vat;
                                        }
                                        
                                        $currentMenuSubtotal = $salesPackageModel->price * $salesPackageModel->qty;
                                        $totalAfterBillDisc = 0 > $packageGrandTotal - $discountBillPackage - $salesPackageModel->inclusiveDiscountValue ? 0 : $packageGrandTotal - $discountBillPackage - $salesPackageModel->inclusiveDiscountValue;
                                        if ($salesPackageModel->otherTaxOnVat == 0) {
                                            $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesPackageModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedPckVat + $salesPackageModel->otherTax);
                                            
                                            $otherTaxValue = $subtotalAfterDiscount * $salesPackageModel->otherTax / 100;
                                            $salesPackageModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $vatValue = $isApplyPckOtherVat ? 0 : $subtotalAfterDiscount * $salesPackageModel->vat / 100;
                                            $salesPackageModel->vatValue = (float) $vatValue;

                                            $otherVatValue = !$isApplyPckOtherVat ? 0 : $subtotalAfterDiscount * $salesPackageModel->otherVat / 100;
                                            if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $package['flagLuxuryItem'],
                                                    $salesPackageModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesPackageModel->otherTaxValue
                                                );
                                                $otherVatValue = !$isApplyPckOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $package['otherVat']
                                                );

                                                $salesPackageModel->dppValue = $dppValue;
                                            }
                                            $salesPackageModel->otherVatValue = (float) $otherVatValue;
                                        } else {
                                            $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesPackageModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesPackageModel->otherTax);

                                            $otherTaxValue = $subtotalAfterDiscount * $salesPackageModel->otherTax / 100;
                                            $salesPackageModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $taxValue = $isApplyPckOtherVat ? 0 : ($subtotalAfterDiscount + $salesPackageModel->otherTaxValue) * $salesPackageModel->vat / 100;
                                            $salesPackageModel->vatValue = (float) $taxValue;

                                            $otherVatValue = !$isApplyPckOtherVat ? 0 : ($subtotalAfterDiscount + $salesPackageModel->otherTaxValue) * $salesPackageModel->otherVat / 100;
                                            if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $package['flagLuxuryItem'],
                                                    $salesPackageModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesPackageModel->otherTaxValue
                                                );
                                                $otherVatValue = !$isApplyPckOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $package['otherVat']
                                                );

                                                $salesPackageModel->dppValue = $dppValue;
                                            }
                                            $salesPackageModel->otherVatValue = (float) $otherVatValue;
                                        }

                                        if ($salesPackageModel->discountValue > 0) {
                                            $inclusiveDiscountBillPackage = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                        } else {
                                            $inclusiveDiscountBillPackage = $currentMenuSubtotal - $subtotalAfterDiscount;
                                        }
                                    } else {
                                        
                                        $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                        $tempMenuSubtotalBeforeTax = $packageGrandTotal * 100 / (100 + $appliedVat + $salesPackageModel->otherTax);
                                        $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesPackageModel->otherTax);
                                        $totalAfterBillDisc = ($discountBillPackage > 0 || $salesPackageModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $packageGrandTotal, $salesPackageModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBillPackage, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $packageGrandTotal;
                                        $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                                        
                                        if ($salesPackageModel->otherTaxOnVat == 0) {
                                            $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedVat + $salesPackageModel->otherTax));
                                            $subtotalBeforeDiscount = $packageGrandTotal * (100 / (100 + $salesPackageModel->vat + $salesPackageModel->otherTax));
            
                                            $otherTaxValue = $packageGrandTotal * (100 / (100 + $appliedVat + $salesPackageModel->otherTax)) * ($salesPackageModel->otherTax / 100);
                                            $salesPackageModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $vatValue = $subtotalBeforeDiscount * $salesPackageModel->vat / 100;
                                            $salesPackageModel->vatValue = (float) $vatValue;
            
                                            $otherVatValue = $subtotalAfterDiscount * $salesPackageModel->otherVat / 100;
                                            if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $package['flagLuxuryItem'],
                                                    $salesPackageModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesPackageModel->otherTaxValue
                                                );
                                                $otherVatValue = CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $package['otherVat']
                                                );

                                                $salesPackageModel->dppValue = $dppValue;
                                            }
                                            $salesPackageModel->otherVatValue = (float) $otherVatValue;
                                        } else {
                                            $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $salesPackageModel->otherVat) * 100 / ( 100 + $salesPackageModel->otherTax));
                                            $subtotalBeforeDiscount = $packageGrandTotal *  (100 / (100 + $salesPackageModel->vat) * 100 / ( 100 + $salesPackageModel->otherTax));
            
                                            $otherTaxValue = $packageGrandTotal * (100 / (100 + $appliedVat)) * ($salesPackageModel->otherTax / (100 + $salesPackageModel->otherTax));
                                            $salesPackageModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $vatValue = ($subtotalBeforeDiscount + $salesPackageModel->otherTaxValue) * $salesPackageModel->vat / 100;
                                            $salesPackageModel->vatValue = (float) $vatValue;
            
                                            $otherVatValue = ($subtotalAfterDiscount + $salesPackageModel->otherTaxValue) * $salesPackageModel->otherVat / 100;
                                            if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $package['flagLuxuryItem'],
                                                    $salesPackageModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesPackageModel->otherTaxValue
                                                );
                                                $otherVatValue = CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $package['otherVat']
                                                );

                                                $salesPackageModel->dppValue = $dppValue;
                                            }
                                            $salesPackageModel->otherVatValue = (float) $otherVatValue;
                                        }
                                    }
                                } else {

                                    $menuPckDiscountTotalForTax = ($taxCalculationType == 2 || $isApplyPckOtherVat) ? $salesPackageModel->discountValue + $discountBillPackage : 0;
                                    $menuPckDiscountTotalForOtherTax = $otherTaxCalculationType == 2 ? $salesPackageModel->discountValue + $otherTaxDiscountBillPackage : 0;
                                    if ($taxCalculationType == 2 && $otherTaxCalculationType == 2) {
                                        $menuPckDiscountTotalForOtherTax = $otherTaxCalculationType == 2 ? $salesPackageModel->discountValue + $discountBillPackage : 0;
                                    }
                                    $menuPckSubtotal = (float)$package['qty'] * $package['price'];

                                    $menuPackagePlatformFee = 0;
                                    if ($platformFeeIncludeOtherTax > 0 && $package['price'] > 0 && $allMenuSubtotal > 0) {
                                        $menuPackagePlatformFee = round($menuPckSubtotal / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                                        $totalPlatformFee += $menuPackagePlatformFee;
                                        $sumSubtotalPlatformFee += $menuPckSubtotal;
                
                                        if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                            $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                            $menuPackagePlatformFee = $menuPackagePlatformFee + $diffPlatformFee;
                                        }
                                    }
                                    
                                    $salesPackageModel->otherTaxValue = (float) ($menuPckSubtotal - ( $otherTaxCalculationType == 2 ? $menuPckDiscountTotalForOtherTax : 0 )) / 100 * $package['otherTax'];
                                    if ($salesPackageModel->otherTaxValue < 0) {
                                        $salesPackageModel->otherTaxValue = 0;
                                    }
                                    if ($menuPackagePlatformFee > 0) {
                                        $salesPackageModel->platformFee = $menuPackagePlatformFee;
                                        $salesPackageModel->otherTaxValue = $salesPackageModel->otherTaxValue + $menuPackagePlatformFee;
                                    }

                                    if ($salesPackageModel->otherTaxOnVat == 0) {
                                        $subtotalAfterDiscPkg = $menuPckSubtotal - $menuPckDiscountTotalForOtherTax;
                                        $salesPackageModel->vatValue = (float) $isApplyPckOtherVat ? 0 : ($menuPckSubtotal - $menuPckDiscountTotalForTax) / 100 * $package['vat'];
                                        $salesPackageModel->otherVatValue = (float) !$isApplyPckOtherVat ? 0 : ($menuPckSubtotal - $menuPckDiscountTotalForOtherTax) / 100 * $package['otherVat'];
                                        if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                            $dppValue = CalculateTotal::getDppValue(
                                                $package['flagLuxuryItem'],
                                                $salesPackageModel->otherTaxOnVat,
                                                $subtotalAfterDiscPkg,
                                                $salesPackageModel->otherTaxValue
                                            );
                                            $salesPackageModel->otherVatValue = (float) !$isApplyPckOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                $dppValue,
                                                $package['otherVat']
                                            );

                                            $salesPackageModel->dppValue = $dppValue;
                                        }
                                    } else {
                                        $subtotalAfterDiscPkg = $menuPckSubtotal - $menuPckDiscountTotalForOtherTax;
                                        $salesPackageModel->vatValue = (float) $isApplyPckOtherVat ? 0 : ($menuPckSubtotal - $menuPckDiscountTotalForTax + $salesPackageModel->otherTaxValue) / 100 * $package['vat'];
                                        $salesPackageModel->otherVatValue = (float) !$isApplyPckOtherVat ? 0 : ($menuPckSubtotal - $menuPckDiscountTotalForOtherTax + $salesPackageModel->otherTaxValue) / 100 * $package['otherVat'];
                                        if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                            $dppValue = CalculateTotal::getDppValue(
                                                $package['flagLuxuryItem'],
                                                $salesPackageModel->otherTaxOnVat,
                                                $subtotalAfterDiscPkg,
                                                $salesPackageModel->otherTaxValue
                                            );
                                            $salesPackageModel->otherVatValue = (float) !$isApplyPckOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                $dppValue,
                                                $package['otherVat']
                                            );

                                            $salesPackageModel->dppValue = $dppValue;
                                        }
                                    }
                                }

                                if ($salesPackageModel->otherVatValue < 0) {
                                    $salesPackageModel->otherVatValue = 0;
                                }

                                if ($inclusiveMenuTemplateID && $menuPackageModel && !$externalProcess) {
                                    $salesPackageModel->total = $applyPackagePrice * $salesPackageModel->qty - $salesPackageModel->discountValue;
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $salesPackageModel->total = $applyPackagePrice * $salesPackageModel->qty - $discountValue - $discountBillPackage;
                                    } else {
                                        $salesPackageModel->total = ($salesPackageModel->qty * $salesPackageModel->price) - $salesPackageModel->discountValue + $salesPackageModel->otherTaxValue + $salesPackageModel->vatValue + $salesPackageModel->otherVatValue;
                                    }

                                    if (0 > $salesPackageModel->total) {
                                        $salesPackageModel->total = 0;
                                    }

                                    if ($discountBillPackage > 0) {
                                        $salesPackageModel->total = $salesPackageModel->price * $salesPackageModel->qty - $salesPackageModel->discountValue + $salesPackageModel->otherTaxValue + $salesPackageModel->vatValue + $salesPackageModel->otherVatValue;
                                    }
                                }

                                if (!$inclusiveMenuTemplateID && !$externalProcess) {
                                    // $salesPackageModel->total = ($salesPackageModel->qty * $salesPackageModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesPackageModel->discountValue + $discountBillPackage) + $salesPackageModel->otherTaxValue + $salesPackageModel->vatValue;
                                    // $salesPackageModel->calculateTotal();
                                    $salesPackageModel->total = ($salesPackageModel->qty * $salesPackageModel->price) - $salesPackageModel->discountValue + $salesPackageModel->otherTaxValue + $salesPackageModel->vatValue + $salesPackageModel->otherVatValue;
                                }
                            }

                            // @Temporary Handling
                            $salesPackageModel->salesType = $salesMenuModel->salesType;
                            $salesPackageModel->createdBy = $salesMenuModel->createdBy;

                            if (!$salesPackageModel->save()) {
                                Yii::error($salesPackageModel->errors);
                                throw new Exception('Failed to save package', [], 500);
                            } else {
                                if ($salesPackageModel->statusID == 19) {
                                    $newIDsCancel[] = [
                                        'ID' => $salesPackageModel->ID,
                                        'batchID' => $salesMenu['batchID'],
                                        'createdDate' => $salesMenu['createdDate']];
                                }

                                // @notes save sales menu vat (ppn)
                                if (isset($package['flagLuxuryItem']) && $salesPackageModel->otherVat > 0) {
                                    if ($salesPackageModel->statusID == 19) {
                                        SalesMenuVat::deleteSalesMenuVat($salesPackageModel->salesNum, $salesPackageModel->ID);
                                    } else {
                                        SalesMenuVat::saveSalesMenuVat($salesPackageModel->salesNum, $salesPackageModel->ID, $salesPackageModel->dppValue, $package['flagLuxuryItem']);
                                        $dppValueTotal += $salesPackageModel->dppValue;
                                    }
                                }
                            }
                            if (!$isSplittedPromoReward) $newIDs[] = $salesPackageModel->ID;

                            if ($salesMenu['statusID'] == 1) {
                                $allInclusiveBillDiscount += isset($inclusiveDiscountBillPackage) ? $inclusiveDiscountBillPackage : 0;
                            }
                        }
                    }

                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {
                            $discountBillExtra = 0;
                            $salesExtraModel = new SalesMenuExtra([
                                'attributes' => $extra
                            ]);
                            $salesExtraModel->salesNum = $this->salesModel->salesNum;
                            $salesExtraModel->menuDetailID = $salesMenuModel->ID;

                            if ($salesMenu['statusID'] == 12) {
                                $currentSalesMenuExtraModel = SalesMenuExtra::findOne($extra['localID']);
                                if ($isFullCancelQty) {
                                    $salesExtraModel = $currentSalesMenuExtraModel;
                                    $salesExtraModel->statusID = 12;

                                }
                            }

                            $menuExtraModel = MenuExtra::find()
                                ->where([
                                    'menuExtraID' => $salesExtraModel->menuExtraID
                                ])
                                ->one();

                            $displayPriceValue = null;
                            if (isset($extra['displayPriceValue'])) {
                                $displayPriceValue = $extra['displayPriceValue'];
                            }

                            $applyExtraPrice = isset($displayPriceValue)
                                ? $displayPriceValue
                                : ($menuExtraModel ? $menuExtraModel->price : $displayPriceValue);

                            $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                            if ($salesTypeEzo) {
                                $applyExtraPrice = isset($extra['inclusivePrice']) ? $extra['inclusivePrice'] : $applyExtraPrice;
                            }

                            if(($this->scanQrTakeAwayOff || $this->externalApi) && $inclusiveMenuTemplateID){
                                $salesExtraModel->inclusivePrice = isset($salesExtraModel->inclusivePrice) 
                                    ? $salesExtraModel->inclusivePrice : $applyExtraPrice;
                            }else{
                                $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                                if (!$salesTypeEzo) {
                                    $salesExtraModel->inclusivePrice = $inclusiveMenuTemplateID ? $applyExtraPrice : 0;
                                }
                            }

                            if (!$this->ezoQuickService) {
                                $discountValue = 0;
                                if ($salesMenuModel->promotionDetailID != 0) {
                                    if ($promotionModel->flagMenuExtra == 1) {
                                        $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                        $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $extra['price'];
                                        $salesExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;    
                                        $salesExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesExtraModel->price;                                
                                        $salesExtraModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesExtraModel->inclusivePrice;
                                        if ($detailPromotionTypeID == 9) {
                                            if ($inclusiveMenuTemplateID) {
                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    if ($applyExtraPrice > 0) {
                                                        $tempPromotionDiscount = $salesExtraModel->price / $applyExtraPrice * $promotionModel->discount;
                                                    } else {
                                                        $tempPromotionDiscount = 0;
                                                    }
                                                    
                                                    if ($tempPromotionDiscount > $salesExtraModel->price) {
                                                        $salesExtraModel->discountValue = (float) $salesExtraModel->qty * $salesExtraModel->price;
                                                        $salesExtraModel->inclusiveDiscountValue = (float) $salesExtraModel->qty * $applyExtraPrice;                                             
                                                        $discountValue = (float) $salesExtraModel->inclusiveDiscountValue;
                                                    } else {
                                                        if ($applyExtraPrice > 0) {
                                                            $percentageDiscountValue = $promotionModel->discount / $applyExtraPrice * 100;
                                                            $tempDiscountValue = $salesExtraModel->price * $percentageDiscountValue / 100;
            
                                                            $salesExtraModel->discountValue = (float) $salesExtraModel->qty * $tempDiscountValue;
                                                            $discountValue = (float) $salesExtraModel->qty * $promotionModel->discount;
                                                            $salesExtraModel->inclusiveDiscountValue = $discountValue;
                                                        } else {
                                                            $salesExtraModel->discountValue = 0;
                                                            $discountValue = 0;
                                                            $salesExtraModel->inclusiveDiscountValue = $discountValue;
                                                        }                                            
                                                    }
                                                } else {
                                                    if ($promotionModel->discount > $applyExtraPrice) {
                                                        $salesExtraModel->discountValue = (float) $extra['qty'] * $applyExtraPrice;
                                                    } else {
                                                        $salesExtraModel->discountValue = (float) $extra['qty'] * $promotionModel->discount;
                                                    }
                                                }
                                            } else {
                                                if ($promotionModel->discount > $extra['price']) {
                                                    $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'];
                                                } else {
                                                    $salesExtraModel->discountValue = (float) $extra['qty'] * $promotionModel->discount;
                                                }
                                            }                                        
                                        } else {
                                            if ($inclusiveMenuTemplateID) {
                                                $menuExtraSubtotal = $extra['price'] * $extra['qty'];
                                                $menuExtraTotal = $applyExtraPrice * $extra['qty'];

                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    if ($detailPromotionTypeID == 1 || $detailPromotionTypeID == 5) {
                                                        $salesExtraModel->discountValue = (float) $menuExtraSubtotal * $promotionModel->discount / 100;
                                                        $salesExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                        $discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;

                                                        $subtotalBeforeDiscount = $salesExtraModel->price * $salesExtraModel->qty;
                                                        if ($salesExtraModel->otherTaxOnVat == 0) {                        
                                                            $subtotalAfterDiscount = ($menuExtraTotal - $salesExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesExtraModel->otherTax);
                                                        } else {
                                                            $subtotalAfterDiscount = ($menuExtraTotal - $salesExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesExtraModel->otherTax);
                                                        }

                                                        $salesExtraModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                                    } else {
                                                        $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                                        $discountValue = $salesExtraModel->discountValue;
                                                    }
                                                } else {
                                                    $newExtraDiscountVal = SalesHead::calculateInclusiveDiscountPercentage($menuExtraSubtotal,
                                                        $menuExtraTotal, $promotionModel->discount);
                                                    $salesExtraModel->discount = $newExtraDiscountVal;
                                                    $extra['discount'] = $newExtraDiscountVal;
                                                    if ($detailPromotionTypeID == 1) {
                                                        $salesExtraModel->discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                    } else {
                                                        $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                                    }
                                                }
                                            } else {
                                                $salesExtraModel->discountValue = (float) $extra['qty'] * $extra['price'] / 100 * $extra['discount'];
                                            }
                                        }
                                    } else {
                                        $salesExtraModel->discountValue = 0;
                                        $salesExtraModel->inclusiveDiscountValue = 0;
                                    }
                                } else {
                                    $salesExtraModel->discountValue = 0;
                                    $salesExtraModel->inclusiveDiscountValue = 0;
                                }
                                
                                $inclusiveDiscountBillExtra = 0;
                                $otherTaxDiscountBillExtra = 0;
                                if ($extra['otherTax'] >= 0 || $extra['vat'] >= 0 || $extra['otherVat'] >= 0) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($this->promotionID != $salesMenuModel->promotionDetailID) {
                                            if ($issetSpecialPrice) {
                                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11])) {
                                                    if ($promotionHeadTypeID == 10) {
                                                        if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                            if ($applyBillDiscountToExtra) {
                                                                $discountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                
                                                                $inclusiveDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                            }
                                                        }
                                                    } else {
                                                        $discountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);

                                                        $inclusiveDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                    }
                                                }
                                            } else {
                                                if ($promotionHeadTypeID == 10) {
                                                    if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                        if ($applyBillDiscountToExtra) {
                                                            $discountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);

                                                            $inclusiveDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                    $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                        }
                                                    }
                                                } else {
                                                    if($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                                        if ($applyBillDiscountToExtra) {
                                                            $discountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
    
                                                            $inclusiveDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        if ($applyDiscountBill && $salesMenu['statusID'] == 1) {
                                            if ($applyBillDiscountToExtra) {
                                                $discountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                
                                                if ($otherTaxCalculationType == 2) {
                                                    $otherTaxDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, 
                                                            [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                                }

                                                $inclusiveDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesExtraModel, $salesExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                            }
                                        }
                                    }
                                }

                                if ($inclusiveMenuTemplateID) {
                                    $extraGrandTotal = $salesExtraModel->inclusivePrice * $salesExtraModel->qty;
                                    $appliedVat = $isApplyOtherVat ? $salesExtraModel->otherVat : $salesExtraModel->vat;
                                    if (isset($extra['flagLuxuryItem'])) {
                                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($extra['flagLuxuryItem'], $salesExtraModel->otherVat) : $salesExtraModel->vat;
                                    }
                                    
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $currentMenuSubtotal = $salesExtraModel->price * $salesExtraModel->qty;
                                        $totalAfterBillDisc = 0 > $extraGrandTotal - $discountBillExtra - $salesExtraModel->inclusiveDiscountValue ? 0 : $extraGrandTotal - $discountBillExtra - $salesExtraModel->inclusiveDiscountValue;
                                        if ($salesExtraModel->otherTaxOnVat == 0) {
                                            $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesExtraModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat + $salesExtraModel->otherTax);
                                            
                                            $otherTaxValue = $subtotalAfterDiscount * $salesExtraModel->otherTax / 100;
                                            $salesExtraModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $vatValue = $isApplyOtherVat ? 0 : $subtotalAfterDiscount * $salesExtraModel->vat / 100;
                                            $salesExtraModel->vatValue = (float) $vatValue;

                                            $otherVatValue = !$isApplyOtherVat ? 0 : $subtotalAfterDiscount * $salesExtraModel->otherVat / 100;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $salesExtraModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesExtraModel->otherTaxValue
                                                );
                                                $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $extra['otherVat']
                                                );

                                                $salesExtraModel->dppValue = $dppValue;
                                            }
                                            $salesExtraModel->otherVatValue = (float) $otherVatValue;
                                        } else {
                                            $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesExtraModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesExtraModel->otherTax);

                                            $otherTaxValue = $subtotalAfterDiscount * $salesExtraModel->otherTax / 100;
                                            $salesExtraModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $taxValue = $isApplyOtherVat ? 0 : ($subtotalAfterDiscount + $salesExtraModel->otherTaxValue) * $salesExtraModel->vat / 100;
                                            $salesExtraModel->vatValue = (float) $taxValue;

                                            $otherVatValue = !$isApplyOtherVat ? 0 : ($subtotalAfterDiscount + $salesExtraModel->otherTaxValue) * $salesExtraModel->otherVat / 100;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $salesExtraModel->otherTaxOnVat,
                                                    $subtotalAfterDiscount,
                                                    $salesExtraModel->otherTaxValue
                                                );
                                                $otherVatValue = !$isApplyOtherVat ? 0 : CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $extra['otherVat']
                                                );

                                                $salesExtraModel->dppValue = $dppValue;
                                            }
                                            $salesExtraModel->otherVatValue = (float) $otherVatValue;
                                        }

                                        if ($salesExtraModel->discountValue > 0) {
                                            $inclusiveDiscountBillExtra = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                        } else {
                                            $inclusiveDiscountBillExtra = $currentMenuSubtotal - $subtotalAfterDiscount;
                                        }
                                    } else {
                                        $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                        $tempMenuSubtotalBeforeTax = $extraGrandTotal * 100 / (100 + $appliedVat + $salesExtraModel->otherTax);
                                        $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesExtraModel->otherTax);
                                        $totalAfterBillDisc = ($discountBillExtra > 0 || $salesExtraModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $extraGrandTotal, $salesExtraModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBillExtra, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $extraGrandTotal;
                                        $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                                        
                                        $menuSubtotalBeforeDiscount = $salesExtraModel->price * $salesExtraModel->qty;
                                        $salesExtraModel->otherTaxValue = (float) $menuSubtotalBeforeDiscount / 100 * $salesExtraModel->otherTax;

                                        if ($salesExtraModel->otherTaxOnVat == 0) {
                                            $menuSubtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $salesExtraModel->otherTax + $appliedVat);

                                            $salesExtraModel->vatValue = (float) $menuSubtotalBeforeDiscount / 100 * $salesExtraModel->vat;
                                            $salesExtraModel->otherVatValue = (float) $menuSubtotalAfterDiscount / 100 * $salesExtraModel->otherVat;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $salesExtraModel->otherTaxOnVat,
                                                    $menuSubtotalAfterDiscount,
                                                    $salesExtraModel->otherTaxValue
                                                );
                                                $salesExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $extra['otherVat']
                                                );
    
                                                $salesExtraModel->dppValue = $dppValue;
                                            }
                                        } else {
                                            $menuSubtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $appliedVat) * 100 / ( 100 + $salesExtraModel->otherTax));

                                            $salesExtraModel->vatValue = (float) ($menuSubtotalBeforeDiscount + $salesExtraModel->otherTaxValue) / 100 * $salesExtraModel->vat;
                                            $salesExtraModel->otherVatValue = (float) ($menuSubtotalAfterDiscount + $salesExtraModel->otherTaxValue) / 100 * $salesExtraModel->otherVat;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $salesExtraModel->otherTaxOnVat,
                                                    $menuSubtotalAfterDiscount,
                                                    $salesExtraModel->otherTaxValue
                                                );
                                                $salesExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $extra['otherVat']
                                                );
    
                                                $salesExtraModel->dppValue = $dppValue;
                                            }
                                        }
                                    }
                                } else {
                                    $menuExtrDiscountTotalForTax = ($taxCalculationType == 2 || $isApplyOtherVat) ? $salesExtraModel->discountValue + $discountBillExtra : 0;
                                    $menuExtrDiscountTotalForOtherTax = $otherTaxCalculationType == 2 ? $salesExtraModel->discountValue + $otherTaxDiscountBillExtra : 0;
                                    if ($taxCalculationType == 2 && $otherTaxCalculationType == 2) {
                                        $menuExtrDiscountTotalForOtherTax = $otherTaxCalculationType == 2 ? $salesExtraModel->discountValue + $discountBillExtra : 0;
                                    }
                                    $menuExtSubtotal = $extra['qty'] * $extra['price'];

                                    $menuExtraPlatformFee = 0;
                                    if ($platformFeeIncludeOtherTax > 0 && $menuExtSubtotal > 0 && $allMenuSubtotal > 0) {
                                        $menuExtraPlatformFee = round($menuExtSubtotal / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                                        $totalPlatformFee += $menuExtraPlatformFee;
                                        $sumSubtotalPlatformFee += $menuExtSubtotal;
                
                                        if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                            $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                            $menuExtraPlatformFee = $menuExtraPlatformFee + $diffPlatformFee;
                                        }
                                    }
                                    
                                    $salesExtraModel->otherTaxValue = (float) ($menuExtSubtotal - ( $otherTaxCalculationType == 2 ? $menuExtrDiscountTotalForOtherTax : 0 )) / 100 * $extra['otherTax'];
                                    if ($salesExtraModel->otherTaxValue < 0) {
                                        $salesExtraModel->otherTaxValue = 0;
                                    }
                                    if ($menuExtraPlatformFee > 0) {
                                        $salesExtraModel->otherTaxValue = $salesExtraModel->otherTaxValue + $menuExtraPlatformFee;
                                    }

                                    if ($salesExtraModel->otherTaxOnVat == 0) {
                                        $subtotalAfterDiscExt = $menuExtSubtotal - $menuExtrDiscountTotalForOtherTax;
                                        $salesExtraModel->vatValue = $isApplyOtherVat ? 0 : (float) ($menuExtSubtotal - $menuExtrDiscountTotalForTax) / 100 * $extra['vat'];
                                        $salesExtraModel->otherVatValue = !$isApplyOtherVat ? 0 : (float) ($menuExtSubtotal - $menuExtrDiscountTotalForOtherTax) / 100 * $extra['otherVat'];
                                        if (isset($extra['flagLuxuryItem'])) {
                                            $dppValue = CalculateTotal::getDppValue(
                                                $extra['flagLuxuryItem'],
                                                $salesExtraModel->otherTaxOnVat,
                                                $subtotalAfterDiscExt,
                                                $salesExtraModel->otherTaxValue
                                            );
                                            $salesExtraModel->otherVatValue = !$isApplyOtherVat ? 0 : (float) CalculateTotal::getOtherVatValue(
                                                $dppValue,
                                                $extra['otherVat']
                                            );
    
                                            $salesExtraModel->dppValue = $dppValue;
                                        }
                                    } else {
                                        $subtotalAfterDiscExt = $menuExtSubtotal - $menuExtrDiscountTotalForOtherTax;
                                        $salesExtraModel->vatValue = $isApplyOtherVat ? 0 : (float) ($menuExtSubtotal - $menuExtrDiscountTotalForTax + $salesExtraModel->otherTaxValue) / 100 * $extra['vat'];
                                        $salesExtraModel->otherVatValue = !$isApplyOtherVat ? 0 : (float) ($menuExtSubtotal - $menuExtrDiscountTotalForOtherTax + $salesExtraModel->otherTaxValue) / 100 * $extra['otherVat'];
                                        if (isset($extra['flagLuxuryItem'])) {
                                            $dppValue = CalculateTotal::getDppValue(
                                                $extra['flagLuxuryItem'],
                                                $salesExtraModel->otherTaxOnVat,
                                                $subtotalAfterDiscExt,
                                                $salesExtraModel->otherTaxValue
                                            );
                                            $salesExtraModel->otherVatValue = !$isApplyOtherVat ? 0 : (float) CalculateTotal::getOtherVatValue(
                                                $dppValue,
                                                $extra['otherVat']
                                            );
    
                                            $salesExtraModel->dppValue = $dppValue;
                                        }
                                    }
                                }

                                if ($salesExtraModel->otherVatValue < 0) {
                                    $salesExtraModel->otherVatValue = 0;
                                }

                                if (!$externalProcess) {
                                    $salesExtraModel->total = ($salesExtraModel->qty * $salesExtraModel->price) - $salesExtraModel->discountValue + $salesExtraModel->otherTaxValue + $salesExtraModel->vatValue + $salesExtraModel->otherVatValue;
                                    if ($inclusiveMenuTemplateID) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $salesExtraModel->total = ($salesExtraModel->qty * $salesExtraModel->price) - $discountValue - $discountBillExtra + $salesExtraModel->otherTaxValue + $salesExtraModel->vatValue + $salesExtraModel->otherVatValue;
                                        } else {
                                            $salesExtraModel->total = ($salesExtraModel->qty * $salesExtraModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesExtraModel->discountValue) + $salesExtraModel->otherTaxValue + $salesExtraModel->vatValue + $salesExtraModel->otherVatValue;
                                        }

                                        if (0 > $salesExtraModel->total) {
                                            $salesExtraModel->total = 0;
                                        }

                                        if ($discountBillExtra > 0) {
                                            $salesExtraModel->total = $salesExtraModel->price * $salesExtraModel->qty - $salesExtraModel->discountValue + $salesExtraModel->otherTaxValue + $salesExtraModel->vatValue + $salesExtraModel->otherVatValue;
                                        }
                                    }
                                }
                            }

                            if (!$salesExtraModel->save()) {
                                Yii::error($salesExtraModel->errors);
                                throw new Exception('Failed to save extra', [], 500);
                            } else {
                                // @notes save sales menu vat (ppn)
                                if (isset($extra['flagLuxuryItem']) && $salesExtraModel->otherVat > 0 && $salesExtraModel->statusID != 19) {
                                    if ($salesExtraModel->statusID == 19) {
                                        SalesMenuVat::deleteSalesMenuVat($salesExtraModel->salesNum, $salesExtraModel->ID);
                                    } else {
                                        SalesMenuVat::saveSalesMenuVat($salesExtraModel->salesNum, $salesExtraModel->ID, $salesExtraModel->dppValue, $extra['flagLuxuryItem']);
                                        $dppValueTotal += $salesExtraModel->dppValue;
                                    }
                                }
                            }

                            if ($salesMenu['statusID'] == 1) {
                                $inclusiveDiscountBillExtra = isset($inclusiveDiscountBillExtra) ? $inclusiveDiscountBillExtra * $salesMenuModel->qty : 0;
                                $allInclusiveBillDiscount += $inclusiveDiscountBillExtra;
                            }
                        }
                    }
                } elseif ($salesMenu['statusID'] == 12) {
                    $salesMenuModel = SalesMenu::find()
                        ->andWhere([
                            'ID' => $salesMenu['localID']
                        ])
                        ->one();

                    if ($salesMenuModel) {
                        if (($salesMenuModel->qty - $salesMenu['qty']) > 0) {
                            $salesMenuModel = new SalesMenu([
                                'attributes' => $salesMenu
                            ]);
                        }
                    }

                    // @Notes: Unset promo for cancelled menu
                    $salesMenuModel->promotionDetailID = 0;
                    $salesMenuModel->promotionVoucherCode = '';

                    $salesMenuModel->detachBehaviors();
                    $salesMenuModel->editedBy = Yii::$app->user->identity->username;
                    $salesMenuModel->editedDate = date('Y-m-d H:i:s');
                    $salesMenuModel->cancelNotes = $salesMenu['cancelNotes'];
                    $salesMenuModel->statusID = $salesMenu['statusID'];
                    if (!$salesMenuModel->save()) {
                        throw new Exception('Failed to save menu', [], 500);
                    } else {
                        SalesRewardMenu::adjustSalesRewardMenu(
                            $this->externalMembershipTypeID,
                            $salesMenuModel,
                            isset($salesMenu['rewardType']) ? $salesMenu['rewardType'] : null
                        );
                        if ($this->ezoFullService) {
                            $rewardType = $salesMenuModel->salesRewardMenu ? $salesMenuModel->salesRewardMenu->rewardType : null;
                            if (((isset($salesMenu['rewardType']) && $salesMenu['rewardType'] != '') || $rewardType != null)) {
                                $salesMenu['localID'] = $salesMenuModel->localID;
                            }
                            if (isset($salesMenu['statusID']) && $salesMenu['statusID'] == '1') {
                                $this->newSalesMenuFs[] = $salesMenu;
                            }
                        }
                    }
                    $newIDs[] = $salesMenuModel->ID;

                    if ($salesMenu['packages']) {
                        foreach ($salesMenu['packages'] as $package) {
                            $salesPackageModel = SalesMenu::find()
                                ->andWhere([
                                    'ID' => $package['localID'],
                                    'menuRefID' => $package['menuRefID']
                                ])
                                ->one();

                            $salesPackageModel->editedBy = Yii::$app->user->identity->username;
                            $salesPackageModel->editedDate = date('Y-m-d H:i:s');
                            $salesPackageModel->cancelNotes = $salesMenu['cancelNotes'];
                            $salesPackageModel->statusID = $salesMenu['statusID'];
                            if (!$salesPackageModel->save()) {
                                throw new Exception('Failed to save package', [], 500);
                            }
                            $currentIDs[] = $salesPackageModel->ID;
                        }
                    }

                    if ($salesMenu['extras']) {
                        foreach ($salesMenu['extras'] as $extra) {
                            $salesExtraModel = SalesMenuExtra::find()
                                ->andWhere([
                                    'ID' => $extra['localID'],
                                    'salesNum' => $extra['salesNum']
                                ])
                                ->one();

                            $salesExtraModel->statusID = $salesMenu['statusID'];
                            if (!$salesExtraModel->save()) {
                                throw new Exception('Failed to save extra', [], 500);
                            }
                        }
                    }
                } else {

                    $salesMenuModel = SalesMenu::find()
                        ->andWhere(['ID' => $salesMenu['ID']])
                        ->one();
                    
                    if ($salesMenuModel) {
                        $salesMenuUpdated = false;
                        $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                        if (isset($salesMenu['flagLuxuryItem'])) {
                            $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                        }

                        if (isset($this->applySplit)) {
                            if ($this->applySplit == 1) {
                                $salesMenuUpdated = true;
                            }
                        }

                        if ($this->reCalculate) {
                            $salesMenuUpdated = true;
                        }

                        if ($salesMenuModel->qty != $salesMenu['qty']) {
                            $salesMenuModel->qty = $salesMenu['qty'];
                            $salesMenuUpdated = true;
                        }

                        if ($isHaveNewOrder || $isFireOrder) {
                            $salesMenuUpdated = true;
                        }

                        if ($salesMenuCancelQty) {
                            $salesMenuUpdated = true;
                        }

                        if ($hasUpdatePromo) {
                            $salesMenuUpdated = true;
                        }

                        if ($promotionModel) {
                            $discount = $salesMenuModel->discount;
                            $discountValue = $inclusiveMenuTemplateID ? $salesMenuModel->inclusiveDiscountValue : $salesMenuModel->discountValue;
                            $menuDiscountVal = $promotionModel->promotionTypeID == 9 ? ($discountValue / $salesMenuModel->qty) : $discount;

                            if ($promotionModel->discount != $menuDiscountVal) {
                                $salesMenuUpdated = true;
                            }
                        }

                        $currentPromotionID = $salesMenuModel->promotionDetailID;

                        if (in_array($salesMenuModel->statusID, [34,14])) {
                            $salesMenu['statusID'] = $salesMenuModel->statusID;
                        }

                        $mode = 0;
                        $qtyDoneChecker = 0;
                        $qtyDoneKitchen = 0;
                        $qtyDoneCheckerPck = [];
                        $qtyDoneKitchenPck = [];
                        if ($newCurrentSalesMenuModel && $salesMenuCancelQty) {
                            foreach ($newCurrentSalesMenuModel as $currentSales) {
                                if ($currentSales->salesMenuCompletionChecker) {
                                    foreach ($currentSales->salesMenuCompletionChecker as $salesCompleteChecker) {
                                        if ($salesCompleteChecker->salesMenuID == $salesMenu['localID']) {
                                            $qtyDoneChecker += $salesCompleteChecker->qty;
                                        }
                                    }
                                    $mode = 2;
                                } else if ($currentSales->salesMenuCompletionKitchen) {
                                    foreach ($currentSales->salesMenuCompletionKitchen as $salesCompleteKitchen) {
                                        if ($salesCompleteKitchen->salesMenuID == $salesMenu['localID']) {
                                            $qtyDoneKitchen += $salesCompleteKitchen->qty;
                                        }
                                    }
                                    $mode = 1;
                                } else if ($currentSales->childSalesMenus) {
                                    foreach ($currentSales->childSalesMenus as $menuPackages) {
                                        if ($menuPackages->salesMenuCompletionChecker) {
                                            $qtyDonePckChecker = 0;
                                            foreach ($menuPackages->salesMenuCompletionChecker as $salesCompleteCheckerPck) {
                                                if ($salesCompleteCheckerPck->salesMenuID == $menuPackages->localID) {
                                                    $qtyDonePckChecker += $salesCompleteCheckerPck->qty;
                                                    $qtyDoneCheckerPck[$salesCompleteCheckerPck->salesMenuID]['localID'] = $salesCompleteCheckerPck->salesMenuID;
                                                    $qtyDoneCheckerPck[$salesCompleteCheckerPck->salesMenuID]['qty'] = $qtyDonePckChecker;
                                                }
                                            }
                                            $mode = 2;
                                        } else if ($menuPackages->salesMenuCompletionKitchen) {
                                            $qtyDonePckKitchen = 0;
                                            foreach ($menuPackages->salesMenuCompletionKitchen as $salesCompleteKitchenPck) {
                                                if ($salesCompleteKitchenPck->salesMenuID == $menuPackages->localID) {
                                                    $qtyDonePckKitchen += $salesCompleteKitchenPck->qty;
                                                    $qtyDoneKitchenPck[$salesCompleteKitchenPck->salesMenuID]['localID'] = $salesCompleteKitchenPck->salesMenuID;
                                                    $qtyDoneKitchenPck[$salesCompleteKitchenPck->salesMenuID]['qty'] = $qtyDonePckKitchen;
                                                }
                                            }
                                            $mode = 1;
                                        }
                                    }
                                }
                            }

                            $i = 0;
                            $j = 0;
                            foreach ($newCurrentSalesMenuModel as $currentSales) {
                                if ($currentSales->localID == $salesMenu['localID'] && $currentSales->statusID != 19) {
                                    $qtyHasCancel = $currentSales->qty - $salesMenu['qty'];
                                    if ($currentSales->childSalesMenus && ($qtyDoneCheckerPck || $qtyDoneKitchenPck)) {
                                        $finishAll = false;
                                        $finishPartial = false;
                                        foreach ($currentSales->childSalesMenus as $currentMenuPck) {
                                            if ($qtyDoneCheckerPck) {
                                                foreach (array_values($qtyDoneCheckerPck) as $pckChecker) {
                                                    if ($currentMenuPck->localID != $pckChecker['localID'] && $currentMenuPck->branchMenu && $currentMenuPck->branchMenu->stationID == 0) unset($salesMenuPckCancelQty[$i]);
                                                    foreach ($salesMenuPckCancelQty as $salesCancelPck) {
                                                        if ($currentMenuPck->localID == $pckChecker['localID'] && $salesCancelPck['localID'] == $pckChecker['localID']) {
                                                            if ((($currentMenuPck->qty * $currentSales->qty) - (($salesCancelPck['qty'] * $qtyHasCancel) + $pckChecker['qty'])) < 1) {
                                                                $finishAll = true;
                                                            } else {
                                                                $finishPartial = true;
                                                            }
                                                        }
                                                    }
                                                    $i++;
                                                } 
                                            } else if ($qtyDoneKitchenPck) {
                                                foreach (array_values($qtyDoneKitchenPck) as $pckKitchen) {
                                                    if ($currentMenuPck->localID != $pckKitchen['localID'] && $currentMenuPck->branchMenu && $currentMenuPck->branchMenu->stationID == 0) unset($salesMenuPckCancelQty[$i]);
                                                    foreach ($salesMenuPckCancelQty as $salesCancelPck) {
                                                        if ($currentMenuPck->localID == $pckKitchen['localID'] && $salesCancelPck['localID'] == $pckKitchen['localID']) {
                                                            if ((($currentMenuPck->qty * $currentSales->qty) - (($salesCancelPck['qty'] * $qtyHasCancel) + $pckKitchen['qty'])) < 1) {
                                                                $finishAll = true;
                                                            } else {
                                                                $finishPartial = true;
                                                            }
                                                        }
                                                    }
                                                    $i++;
                                                } 
                                            }
                                        }
                                        $validateFinishAll = ((count($qtyDoneKitchenPck) == count($salesMenuPckCancelQty)) || (count($qtyDoneCheckerPck) == count($salesMenuPckCancelQty)));
                                        if (($finishAll && $validateFinishAll) && !$finishPartial) {
                                            $salesMenu['statusID'] = count($qtyDoneCheckerPck) > 0 ? 14 : 34;
                                        }
                                    } else {
                                        $applyQtyDone = $qtyDoneChecker > 0 ? $qtyDoneChecker : $qtyDoneKitchen;
                                        foreach ($salesMenuCancelQty as $salesCancel) {
                                            if ($salesCancel['localID'] == $salesMenu['localID'] && ($currentSales->qty - ($salesCancel['qty'] + $applyQtyDone)) < 1) {
                                                $salesMenu['statusID'] = $qtyDoneChecker > 0 ? 14 : 34;
                                            }
                                        }
                                        if ($currentSales->branchMenu && $currentSales->branchMenu->stationID == 0) unset($salesMenuIDs[$j]);
                                    }
                                    if ($currentSales->branchMenu && $currentSales->branchMenu->stationID != 0) $statusSalesDoneArray[] = $salesMenu['statusID'];
                                }
                                $j++;
                            }

                            foreach ($newCurrentSalesMenuModel as $currentSales) {
                                if ($currentSales->localID == $salesMenu['localID'] && $currentSales->branchMenu && $currentSales->branchMenu->stationID == 0 && $currentSales->statusID != 19) {
                                    if (!in_array(13, $statusSalesDoneArray) && count($statusSalesDoneArray) == count($salesMenuIDs) && $mode == 2) $salesMenu['statusID'] = 14;
                                }
                            }
                        }

                        $salesMenuModel->load(['SalesMenu' => $salesMenu]);

                        // @Notes: Remove promo
                        if ($currentPromotionID != $salesMenu['promotionDetailID']) {
                            $this->removeMenuPromo($salesMenuModel,
                                $currentPromotionID, $appliedVat,
                                $inclusiveMenuTemplateID, $specialPriceArrModel);

                            // @Notes: Apply promo
                            if ($salesMenu['promotionDetailID'] != 0) {
                                $this->applyMenuPromo($salesMenuModel,
                                    $salesMenu['promotionDetailID']);
                            }

                            $salesMenuUpdated = true;
                        }

                        if (!$this->ezoQuickService) {
                            $inclusivePrice = 0;
                            $discountValue = 0;

                            if ((isset($salesMenu['flagFireOrder']) && $salesMenu['flagFireOrder'] === true) && $salesMenuModel->statusID === 46) {
                                $newIDs[] = $salesMenuModel->ID;
                            }

                            if ($inclusiveMenuTemplateID) {
                                $specialMenuPrice = null;
                                if (array_key_exists($salesMenu['menuID'],
                                        $specialPriceArrModel)) {
                                    $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                                }

                                if ($salesMenuModel->price == 0 && $salesMenuModel->promotionDetailID > 0) {
                                    $inclusivePrice = 0;
                                } else {
                                    if (isset($salesMenuModel->inclusivePrice) && $salesMenuModel->inclusivePrice > 0) {
                                        $inclusivePrice = $salesMenuModel->inclusivePrice;
                                    } else {
                                        if ($specialMenuPrice) {
                                            $inclusivePrice = $specialMenuPrice;
                                        } else {
                                            $displayPriceValue = null;
                                            if (isset($salesMenu['displayPriceValue'])) {
                                                $displayPriceValue = $salesMenu['displayPriceValue'];
                                            }
                                            $inclusivePrice = isset($displayPriceValue) 
                                                ? $displayPriceValue 
                                                : (isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue);
                                        }
                                    }
                                }

                                //$inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                                // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                                if (strlen($salesMenuModel->customMenuName) > 0) {
                                    $displayPriceValue = null;
                                    if (isset($salesMenu['displayPriceValue'])) {
                                        $displayPriceValue = $salesMenu['displayPriceValue'];
                                    }
    
                                    $checkMenuTemplatePrice = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                                    $checkInclusivePrice = isset($salesMenuModel->inclusivePrice) ? $salesMenuModel->inclusivePrice : $checkMenuTemplatePrice;
    
                                    $inclusivePrice = isset($displayPriceValue) ? $displayPriceValue : $checkInclusivePrice;
                                    $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $inclusivePrice;
                                }

                                if($detailPromotionTypeID == 7) {
                                    $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                                }

                                if ($this->flagRemoveMemberPromoFS) {
                                    $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                                }

                                $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                                if ($salesTypeEzo) {
                                    $inclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $inclusivePrice;
                                }
                                // $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : 
                                //     (strlen($salesMenuModel->customMenuName) > 0 ? $salesMenuModel->price : $menuTemplateDetailModel[$salesMenuModel->menuID]->price);
                                // $salesMenuModel->price = strlen($salesMenuModel->customMenuName) > 0 ? self::getInclusivePrice($inclusivePrice,
                                //         $otherTaxValue, $otherTaxOnVat, $vatValue,
                                //         $salesDecimalSetting, $settingDecimalMode) : $salesMenuModel->price;
                                        
                                //$menuDiscountVal = $salesMenuModel->promotionDetailID > 0 ? $promotionArrModel[$salesMenuModel->promotionDetailID] : 0;
                                if ($salesMenuModel->price == 0 && $salesMenuModel->promotionDetailID == 0 && !$specialMenuPrice) {
                                    $salesMenuModel->price = $salesMenuModel->originalPrice;
                                }
                                $salesMenuModel->inclusivePrice = $inclusivePrice;
                                if ($salesMenuModel->promotionDetailID > 0) {
                                    if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                        $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                        $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                                    } else {
                                        $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                        $detailPromotionDiscount = $promotionModel->discount;
                                    }
                                    if ($detailPromotionTypeID == 9) {
                                        $menuDiscountVal = 0;
                                    } else {
                                        $menuDiscountVal = $detailPromotionDiscount;
                                    }
                                } else {
                                    $menuDiscountVal = 0;
                                }

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
                                $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $salesMenuModel->discount / 100;
                                if ($detailPromotionTypeID == 9) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
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
                                        $salesMenuModel->discountValue = $menuSubtotal * $promotionModel->discount / 100;
                                        $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                        $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                        $subtotalBeforeDiscount = $salesMenuModel->price * $salesMenuModel->qty;
                                        if ($salesMenuModel->otherTaxOnVat == 0) {                            
                                            $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                        } else {
                                            $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                        }

                                        $salesMenuModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                    } else {
                                        $salesMenuModel->discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                    }                                
                                }
                            } else {
                                if ($salesMenuModel->promotionDetailID > 0) {
                                    if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                        $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                        $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                                    } else {
                                        $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                        $detailPromotionDiscount = $promotionModel->discount;
                                    }

                                    $menuDiscountVal = $detailPromotionTypeID == 9 ? 0 : $detailPromotionDiscount;
                                } else {
                                    $menuDiscountVal = 0;
                                }

                                $salesMenuModel->discount = $menuDiscountVal;
                                $salesMenuModel->discountValue = (float) $salesMenuModel->qty * $salesMenuModel->price / 100 * $salesMenuModel->discount;
                                if ($detailPromotionTypeID == 9) {
                                    if ($promotionModel->discount > $salesMenuModel->price) {
                                        $salesMenuModel->discountValue = $salesMenuModel->price * $salesMenuModel->qty;
                                    } else {
                                        $salesMenuModel->discountValue = $promotionModel->discount * $salesMenuModel->qty;
                                    }
                                }

                                if ($platformFeeIncludeOtherTax > 0 && $allMenuSubtotal > 0) {
                                    $menuPlatformFee = round((float) $salesMenuModel->qty * $salesMenuModel->price / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                                    $totalPlatformFee += $menuPlatformFee;
                                    $sumSubtotalPlatformFee += $salesMenuModel->qty * $salesMenuModel->price;

                                    if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                        $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                        $menuPlatformFee = $menuPlatformFee + $diffPlatformFee;
                                    }
                                    $salesMenuModel->platformFee = $menuPlatformFee;
                                }
                            }

                            $discountBill = 0;
                            $inclusiveDiscountBill = 0;
                            $otherTaxDiscountBill = 0;
                            if ($salesMenuModel->otherTax >= 0 || $salesMenuModel->vat >= 0 || $salesMenuModel->otherVat >= 0) {
                                if ($issetSpecialPrice) {
                                    if (in_array($promotionHeadTypeID, [3, 6, 10, 11, 12, 14, 15, 16])) {                                    
                                        $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                } else {               
                                    $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode,
                                            [], 0, 0, 0, SalesHead::NON_INCLUSIVE_BEFORE_DISCOUNT, $allMenuDiscountTotal);
                                    
                                    if ($otherTaxCalculationType == 2) {
                                        $otherTaxDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, 
                                            [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                    }

                                    $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                }
                            }

                            if ($inclusiveMenuTemplateID) {
                                $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                                if (isset($salesMenu['flagLuxuryItem'])) {
                                    $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                                }
                                
                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                    $currentMenuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                                    $totalAfterBillDisc = 0 > $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue ? 0 : $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue;
                                    if ($salesMenuModel->otherTaxOnVat == 0) {
                                        $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                        $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                        
                                        $otherTaxValue = $subtotalAfterDiscount * $salesMenuModel->otherTax / 100;
                                        $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                                        
                                        $vatValue = $subtotalAfterDiscount * $salesMenuModel->vat / 100;
                                        $salesMenuModel->vatValue = (float) $vatValue;

                                        $otherVatValue = $subtotalAfterDiscount * $salesMenuModel->otherVat / 100;
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

                                            $salesMenuModel->dppValue = $dppValue;
                                        }
                                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                    } else {
                                        $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                        $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                        
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

                                            $salesMenuModel->dppValue = $dppValue;
                                        }
                                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                    }

                                    if ($salesMenuModel->discountValue > 0) {
                                        $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                    } else {
                                        $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                                    }
                                } else {
                                    $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                    $tempMenuSubtotalBeforeTax = $menuGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                    $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                    $totalAfterBillDisc = ($discountBill > 0 || $salesMenuModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $menuGrandTotal, $salesMenuModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandTotal;
                                    $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                                    if ($salesMenuModel->otherTaxOnVat == 0) {
                                        $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedVat + $salesMenuModel->otherTax));
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

                                            $salesMenuModel->dppValue = $dppValue;
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

                                            $salesMenuModel->dppValue = $dppValue;
                                        }
                                        $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                    }
                                }          
                            }

                            if (!$externalProcess) {
                                if ($inclusiveMenuTemplateID) {
                                    $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $discountValue - $discountBill;
                                    } else {
                                        $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                                    }

                                    if ($discountBill > 0) {
                                        $salesMenuModel->total = $salesMenuModel->price * $salesMenuModel->qty - $salesMenuModel->discountValue + $salesMenuModel->otherTaxValue + $salesMenuModel->vatValue  + $salesMenuModel->otherVatValue;
                                    }

                                    if ($this->flagRemoveMemberPromoFS) {
                                        $salesMenuModel->promotionVoucherCode = '';
                                    }
                                } else {
                                    $salesMenuModel->calculateTotal(0, 0, $discountBill, 0, $otherTaxDiscountBill);
                                    if ($salesMenuModel->otherVatValue < 0) {
                                        $salesMenuModel->otherVatValue = 0;
                                    }
                                }
                            }   
                        }

                        if ($salesMenuUpdated) {
                            $salesMenuModel->promotionVoucherCode = isset($salesMenu['promotionVoucherCode']) ? $salesMenu['promotionVoucherCode'] : '';
                            if (!$salesMenuModel->save()) {
                                throw new Exception('Failed to update menu', [], 500);
                            } else {
                                if (isset($salesMenu['flagFireOrder']) && $salesMenu['flagFireOrder'] === true ) {
                                    $this->flagFireOrderIDs[] = $salesMenuModel->ID;
                                    $salesMenuModel->flagFireOrder = true;
                                }

                                SalesRewardMenu::adjustSalesRewardMenu(
                                    $this->externalMembershipTypeID,
                                    $salesMenuModel,
                                    isset($salesMenu['rewardType']) ? $salesMenu['rewardType'] : null
                                );

                                if ($this->ezoFullService) {
                                    $rewardType = $salesMenuModel->salesRewardMenu ? $salesMenuModel->salesRewardMenu->rewardType : null;
                                    if (((isset($salesMenu['rewardType']) && $salesMenu['rewardType'] != '') || $rewardType != null)) {
                                        $salesMenu['localID'] = $salesMenuModel->localID;
                                    }
                                    if (isset($salesMenu['statusID']) && $salesMenu['statusID'] == '1') {
                                        $this->newSalesMenuFs[] = $salesMenu;
                                    }
                                }
                                if ($kitchenFireManagement && $salesMenuModel->flagHoldOrder) SalesProcessMenu::saveSalesProcessMenu($salesMenuModel);
                                if ($this->isEmployeeApplied) {
                                    $this->tempSalesMenu = SalesMenu::findSalesAppliedEmployee($this->salesNum);
                                } 

                                // @notes update sales menu vat (ppn)
                                if (isset($salesMenu['flagLuxuryItem']) && $salesMenuModel->otherVat > 0) {
                                    if ($salesMenuModel->statusID == 19) {
                                        SalesMenuVat::deleteSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID);
                                    } else {
                                        SalesMenuVat::saveSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID, $salesMenuModel->dppValue, $salesMenu['flagLuxuryItem']);
                                        $dppValueTotal += $salesMenuModel->dppValue;
                                    }
                                }
                            }
                        }

                        $currentIDs[] = $salesMenuModel->ID;
                        if ($salesMenuModel->statusID != 19) {
                            $allInclusiveBillDiscount += isset($inclusiveDiscountBill) ? $inclusiveDiscountBill : 0;
                        }
                    } else {
                        $salesMenuModel = new SalesMenu([
                            'attributes' => $salesMenu
                        ]);

                        $salesMenuModel->salesNum = $this->salesModel->salesNum;

                        if ($salesMenuModel->promotionDetailID != 0) {
                            $this->applyMenuPromo($salesMenuModel);
                        }

                        $salesMenuModel->detachBehaviors();
                        $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                        if (isset($salesMenu['flagLuxuryItem'])) {
                            $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                        }
                        $originSalesMenu = SalesMenu::findOne($salesMenu['localID']);
                        if ($originSalesMenu) {
                            $appliedVat = $isApplyOtherVat ? $originSalesMenu->otherVat : $originSalesMenu->vat;
                            if (isset($salesMenu['flagLuxuryItem'])) {
                                $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $originSalesMenu->otherVat) : $originSalesMenu->vat;
                            }
                            $salesMenuModel->batchID = $originSalesMenu->batchID;
                            $salesMenuModel->createdBy = $originSalesMenu->createdBy;
                            $salesMenuModel->createdDate = $originSalesMenu->createdDate;
                            $salesMenuModel->editedBy = Yii::$app->user->identity->username;
                            $salesMenuModel->editedDate = date('Y-m-d H:i:s');
                        } else {
                            throw new Exception('Failed to save cancel menu', [], 500);
                        }

                        if(!isset($salesMenu['subsID'])){
                            //$salesMenuModel->calculateTotal();  
                            if (!$this->ezoQuickService) {
                                $inclusivePrice = 0;
                                $discountValue = 0;

                                if ($inclusiveMenuTemplateID) {
                                    $specialMenuPrice = null;
                                    if (array_key_exists($salesMenu['menuID'],
                                            $specialPriceArrModel)) {
                                        $specialMenuPrice = $specialPriceArrModel[$salesMenu['menuID']];
                                    }
    
                                    if ($salesMenuModel->price == 0 && $salesMenuModel->promotionDetailID > 0) {
                                        $inclusivePrice = 0;
                                    } else {
                                        if (isset($salesMenuModel->inclusivePrice) && $salesMenuModel->inclusivePrice > 0) {
                                            $inclusivePrice = $salesMenuModel->inclusivePrice;
                                        } else {
                                            if ($specialMenuPrice) {
                                                $inclusivePrice = $specialMenuPrice;
                                            } else {
                                                $inclusivePrice = $menuTemplateDetailModel[$salesMenuModel->menuID]->price;
                                            }
                                        }
                                    }
    
                                    // ketika inclusive untuk open price harus update nilai salesmenuModel-price, untuk harga sebelum tax
                                    if (strlen($salesMenuModel->customMenuName) > 0) {
                                        $displayPriceValue = null;
                                        if (isset($salesMenu['displayPriceValue'])) {
                                            $displayPriceValue = $salesMenu['displayPriceValue'];
                                        }
        
                                        $checkMenuTemplatePrice = isset($menuTemplateDetailModel[$salesMenuModel->menuID]) ? $menuTemplateDetailModel[$salesMenuModel->menuID]->price : $displayPriceValue;
                                        $checkInclusivePrice = isset($salesMenuModel->inclusivePrice) ? $salesMenuModel->inclusivePrice : $checkMenuTemplatePrice;
        
                                        $inclusivePrice = isset($displayPriceValue) ? $displayPriceValue : $checkInclusivePrice;
                                        $inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $inclusivePrice;
                                    }
    
                                    if($detailPromotionTypeID == 7) {
                                        $inclusivePrice = $menuTemplateDetailModel[$tempMenuID]->price;
                                    }

                                    $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                                    if ($salesTypeEzo) {
                                        $inclusivePrice = isset($salesMenu['inclusivePrice']) ? $salesMenu['inclusivePrice'] : $inclusivePrice;
                                    }

                                    $salesMenuModel->inclusivePrice = $inclusivePrice;
                                    if ($salesMenuModel->promotionDetailID > 0) {
                                        if (isset($promotionArrModel[$salesMenuModel->promotionDetailID])) {
                                            $detailPromotionTypeID = $promotionArrModel[$salesMenuModel->promotionDetailID]['promotionTypeID'];
                                            $detailPromotionDiscount = $promotionArrModel[$salesMenuModel->promotionDetailID]['discount'];
                                        } else {
                                            $detailPromotionTypeID = $promotionModel->promotionTypeID;
                                            $detailPromotionDiscount = $promotionModel->discount;
                                        }
                                        if ($detailPromotionTypeID == 9) {
                                            $menuDiscountVal = 0;
                                        } else {
                                            $menuDiscountVal = $detailPromotionDiscount;
                                        }
                                    } else {
                                        $menuDiscountVal = 0;
                                    }
    
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
                                    $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $salesMenuModel->discount / 100;
                                    if ($detailPromotionTypeID == 9) {
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $netPrice = self::getNetPrice($salesMenuModel->otherTax, $otherTaxOnVat, $appliedVat,
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
                                            $salesMenuModel->discountValue = $menuSubtotal * $promotionModel->discount / 100;
                                            $salesMenuModel->inclusiveDiscountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                            $discountValue = $menuGrandTotal * $promotionModel->discount / 100;
                                            $subtotalBeforeDiscount = $salesMenuModel->price * $salesMenuModel->qty;
                                            if ($salesMenuModel->otherTaxOnVat == 0) {                            
                                                $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                            } else {
                                                $subtotalAfterDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                            }
    
                                            $salesMenuModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                        } else {
                                            $salesMenuModel->discountValue = $menuGrandTotal * $promotionModel->discount / 100;
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
    
                                $discountBill = 0;
                                $inclusiveDiscountBill = 0;
                                if ($salesMenuModel->otherTax >= 0 || $salesMenuModel->vat >= 0 || $salesMenuModel->otherVat >= 0) {
                                    if ($issetSpecialPrice) {
                                        if ($promotionHeadTypeID == 3 || $promotionHeadTypeID == 6 || $promotionHeadTypeID == 10 || $promotionHeadTypeID == 12 || $promotionHeadTypeID == 14 || $promotionHeadTypeID == 15 || $promotionHeadTypeID == 16) {                                    
                                            $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                    $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                                            $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                    $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                        }
                                    } else {                     
                                        $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode);
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                $salesMenuModel, $salesMenuModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Main', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                    }
                                }
    
                                if ($inclusiveMenuTemplateID) {
                                    $appliedVat = $isApplyOtherVat ? $salesMenuModel->otherVat : $salesMenuModel->vat;
                                    if (isset($salesMenu['flagLuxuryItem'])) {
                                        $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($salesMenu['flagLuxuryItem'], $salesMenuModel->otherVat) : $salesMenuModel->vat;
                                    }
                                    
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        $currentMenuSubtotal = $salesMenuModel->price * $salesMenuModel->qty;
                                        $totalAfterBillDisc = 0 > $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue ? 0 : $menuGrandTotal - $discountBill - $salesMenuModel->inclusiveDiscountValue; 
                                        if ($salesMenuModel->otherTaxOnVat == 0) {
                                            $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                            
                                            $otherTaxValue = $subtotalAfterDiscount * $salesMenuModel->otherTax / 100;
                                            $salesMenuModel->otherTaxValue = (float) $otherTaxValue;
                                            
                                            $vatValue = $subtotalAfterDiscount * $salesMenuModel->vat / 100;
                                            $salesMenuModel->vatValue = (float) $vatValue;

                                            $otherVatValue = $subtotalAfterDiscount * $salesMenuModel->otherVat / 100;
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

                                                $salesMenuModel->dppValue = $dppValue;
                                            }
                                            $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                        } else {
                                            $subtotalAfterMenuDiscount = ($menuGrandTotal - $salesMenuModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                            $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuModel->otherTax);
                                            
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

                                                $salesMenuModel->dppValue = $dppValue;
                                            }
                                            $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                        }
    
                                        if ($salesMenuModel->discountValue > 0) {
                                            $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                        } else {
                                            $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                                        }
                                    } else {
                                        $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                        $tempMenuSubtotalBeforeTax = $menuGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                        $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesMenuModel->otherTax);
                                        $totalAfterBillDisc = ($discountBill > 0 || $salesMenuModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $menuGrandTotal, $salesMenuModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $menuGrandTotal;
                                        $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;
                                        if ($salesMenuModel->otherTaxOnVat == 0) {
                                            $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedVat + $salesMenuModel->otherTax));
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

                                                $salesMenuModel->dppValue = $dppValue;
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

                                                $salesMenuModel->dppValue = $dppValue;
                                            }
                                            $salesMenuModel->otherVatValue = (float) $otherVatValue;
                                        }
                                    }                            
                                }
    
                                if (!$externalProcess) {
                                    if ($inclusiveMenuTemplateID) {
                                        $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $discountValue - $discountBill;
                                        } else {
                                            $salesMenuModel->total = $inclusivePrice * $salesMenuModel->qty - $salesMenuModel->discountValue;
                                        }
    
                                        if ($discountBill > 0) {
                                            $salesMenuModel->total = $salesMenuModel->price * $salesMenuModel->qty - $salesMenuModel->discountValue + $salesMenuModel->otherTaxValue + $salesMenuModel->vatValue + $salesMenuModel->otherVatValue;
                                        }
                                    } else {
                                        $salesMenuModel->calculateTotal(0, 0, $discountBill);
                                    }
                                }   
                            }
    
                            if (!$salesMenuModel->save()) {
                                throw new Exception('Failed to update menu', [], 500);
                            } else {
                                // @notes update sales menu vat (ppn)
                                if (isset($salesMenu['flagLuxuryItem']) && $salesMenuModel->otherVat > 0) {
                                    if ($salesMenuModel->statusID == 19) {
                                        SalesMenuVat::deleteSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID);
                                    } else {
                                        SalesMenuVat::saveSalesMenuVat($salesMenuModel->salesNum, $salesMenuModel->ID, $salesMenuModel->dppValue, $salesMenu['flagLuxuryItem']);
                                        $dppValueTotal += $salesMenuModel->dppValue;
                                    }
                                }
                            }
    
                            $currentIDs[] = $salesMenuModel->ID;
                            if ($salesMenuModel->statusID != 19) {
                                $allInclusiveBillDiscount += isset($inclusiveDiscountBill) ? $inclusiveDiscountBill : 0;
                            }
                        }
                    }

                    $applyDiscountBill = false;
                    if ($promotionHeadModel) {
                        $applyDiscountBill = ApplyOrderPromo::checkAppliedPromo($this->promotionID, $salesMenu, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs);
                    }

                    if (isset($salesMenu['packages'])) {
                        foreach ($salesMenu['packages'] as $package) {
                            $isApplyPckOtherVat = ($vatSubject === 1 && (isset($package['menuFlagTax']) && $package['menuFlagTax'] === 2));
                            $salesMenuPackageModel = SalesMenu::find()
                                ->andWhere(['ID' => $package['ID']])
                                ->one();

                            $tempMenuID = 0;
                            $subsID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0 ;
                            if ($subsID != 0) {
                                $tempMenuID = $subsID;
                            }
                            else{
                                $menuPromotionID = isset($package['menuPromotionID']) ? $package['menuPromotionID'] : 0;
                                $tempMenuID = $package['menuID'];
                                if($menuPromotionID != 0 && ($package['statusID'] != 1 || $package['statusID'] != 12)){
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
                            
                            if (in_array($salesMenuPackageModel->statusID, [34, 14])) {
                                $package['statusID'] = $salesMenuPackageModel->statusID;
                            }

                            $mode = 0;
                            $qtyDoneCheckerPck = 0;
                            $qtyDoneKitchenPck = 0;
                            if ($newCurrentSalesMenuModel) {
                                foreach ($newCurrentSalesMenuModel as $currentSales) {
                                    foreach ($currentSales->childSalesMenus as $currentMenuPck) {
                                        if ($currentMenuPck->salesMenuCompletionChecker) {
                                            foreach ($currentMenuPck->salesMenuCompletionChecker as $salesCompletionChecker) {
                                                if ($salesCompletionChecker->salesMenuID == $package['localID'] && $currentMenuPck->localID == $package['localID']) {
                                                    $qtyDoneCheckerPck += $salesCompletionChecker->qty;
                                                }
                                            }
                                            $mode = 2;
                                        } else if ($currentMenuPck->salesMenuCompletionKitchen) {
                                            foreach ($currentMenuPck->salesMenuCompletionKitchen as $salesCompletionKitchen) {
                                                if ($salesCompletionKitchen->salesMenuID == $package['localID'] && $currentMenuPck->localID == $package['localID']) {
                                                    $qtyDoneKitchenPck += $salesCompletionKitchen->qty;
                                                }
                                            }
                                            $mode = 1;
                                        }
                                    }
                                }
                            }

                            $applyQtyDone = $qtyDoneCheckerPck > 0 ? $qtyDoneCheckerPck : $qtyDoneKitchenPck;
                            if ($applyQtyDone > 0) {
                                if ($newCurrentSalesMenuModel) {
                                    foreach ($newCurrentSalesMenuModel as $currentSales) {
                                        if ($currentSales->localID == $salesMenu['localID']) {
                                            foreach ($currentSales->childSalesMenus as $currentMenuPck) {
                                                if ($currentMenuPck->localID == $package['localID']) {
                                                    if ($salesMenuPckCancelQty) {
                                                        foreach ($salesMenuPckCancelQty as $salesCancelPck) {
                                                            if ($salesCancelPck['localID'] == $package['localID'] && (($currentMenuPck->qty * $currentSales->qty) - (($salesCancelPck['qty'] * $qtyHasCancel) + $applyQtyDone)) < 1) {
                                                                $package['statusID'] = $qtyDoneCheckerPck > 0 ? 14 : 34;
                                                            } else {
                                                                if ($currentMenuPck->branchMenu && $currentMenuPck->branchMenu->stationID == 0) $package['statusID'] = $mode == 2 ? 14 : 34;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            $displayPriceValue = null;
                            if (isset($package['displayPriceValue'])) {
                                $displayPriceValue = $package['displayPriceValue'];
                            }

                            $applyPackagePrice = isset($displayPriceValue)
                                ? $displayPriceValue
                                : ($menuPackageModel ? ($menuPackageModel->mapMenuTemplatePackage ? $menuPackageModel->mapMenuTemplatePackage->price : $menuPackageModel->price) : $displayPriceValue);

                            $currentPromotionID = $salesMenuModel->promotionDetailID;
                            $salesMenuPackageModel->load(['SalesMenu' => $package]);

                            $appliedPckVat = $isApplyPckOtherVat ? $salesMenuPackageModel->otherVat : $salesMenuPackageModel->vat;
                            if (isset($package['flagLuxuryItem'])) {
                                $appliedPckVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $salesMenuPackageModel->otherVat) : $salesMenuPackageModel->vat;
                            }
                            // @Notes: Remove promo
                            if ($currentPromotionID != $package['promotionDetailID'] || $package['promotionDetailID'] == 0) {
                                $this->removeMenuPromo($salesMenuPackageModel,
                                    $currentPromotionID);
                            }

                            if (!$this->ezoQuickService) {
                                $discountBill = 0;
                                $discountValue = 0;

                                if ((isset($package['flagFireOrder']) && $package['flagFireOrder'] === true) && $salesMenuPackageModel->statusID === 46) {
                                    $newIDs[] = $salesMenuPackageModel->ID;
                                }

                                if ($promotionModel) {
                                    if ($promotionModel->flagPackageContent == 1) {
                                        // @Notes: Apply promo
                                        if ($salesMenu['promotionDetailID'] != 0) {
                                            $applyToPackage = true;
                                            if (count($detailPromotionModel->promotionCategories) > 0) {
                                                $menuModel = Menu::find()
                                                    ->joinWith('menuCategoryDetail')
                                                    ->where(['menuID' => $tempMenuID])
                                                    ->one();
                
                                                if (in_array($menuModel->menuCategoryDetail->menuCategoryID, $menuPromotionCategoryIDs)) {
                                                    $applyToPackage = true;
                                                } else if (in_array($menuModel->menuCategoryDetail->ID, $menuPromotionCategoryDetailIDs)) {
                                                    $applyToPackage = true;
                                                } else if (in_array($menuModel->menuID, $menuPromotionMenuIDs)) {
                                                    $applyToPackage = true;
                                                } else {
                                                    $applyToPackage = false;
                                                }
                                            } else {
                                                $applyToPackage = true;
                                            }

                                            if ($applyToPackage) {
                                                $this->applyMenuPromo($salesMenuPackageModel,
                                                    $salesMenu['promotionDetailID']);
                                            }
                                        }

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
                                                            $salesMenuPackageModel->discountValue = (float) $package['qty'] * $salesMenuPackageModel->price;
                                                            $salesMenuPackageModel->inclusiveDiscountValue = (float) $salesMenuPackageModel->qty * $applyPackagePrice;                                            
                                                            $discountValue = (float) $salesMenuPackageModel->inclusiveDiscountValue;
                                                        } else {
                                                            if ($applyPackagePrice > 0) {
                                                                $percentageDiscountValue = $promotionModel->discount / $applyPackagePrice * 100;
                                                                $tempDiscountValue = $salesMenuPackageModel->price * $percentageDiscountValue / 100;
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
                                                        $salesMenuPackageModel->discount = $promotionModel->discount;
                                                        if ($detailPromotionTypeID == 1) {
                                                            $salesMenuPackageModel->discountValue = (float) $menuPackageSubtotal * $promotionModel->discount / 100;
                                                            $salesMenuPackageModel->inclusiveDiscountValue = $menuPackageTotal * $promotionModel->discount / 100;
                                                            $discountValue = $menuPackageTotal * $promotionModel->discount / 100;

                                                            $subtotalBeforeDiscount = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                                            if ($salesMenuPackageModel->otherTaxOnVat == 0) {                        
                                                                $subtotalAfterDiscount = ($menuPackageTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                                            } else {
                                                                $subtotalAfterDiscount = ($menuPackageTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                                            }

                                                            $salesMenuPackageModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                                        } else {
                                                            $salesMenuPackageModel->discountValue = (float) $salesMenuPackageModel->qty * $salesMenuPackageModel->price / 100 * $salesMenuPackageModel->discount;
                                                            $discountValue = $salesMenuPackageModel->discountValue;
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

                                $discountBill = 0;
                                $inclusiveDiscountBill = 0;
                                $otherTaxDiscountBillPackage = 0;
                                if ($salesMenuPackageModel->otherTax >= 0 || $salesMenuPackageModel->vat >= 0 || $salesMenuPackageModel->otherVat >= 0) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($this->promotionID != $salesMenuModel->promotionDetailID) {
                                            if ($issetSpecialPrice) {
                                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11])) {
                                                    if ($promotionHeadTypeID == 10) {
                                                        if ($applyDiscountBill) {
                                                            if ($applyBillDiscountToPackageContent) {
                                                                $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                            }
                                                        }
                                                    } else {
                                                        $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                    }
                                                }
                                            } else {
                                                if ($promotionHeadTypeID == 10) {
                                                    if ($applyDiscountBill) { 
                                                        if ($applyBillDiscountToPackageContent) {
                                                            $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                            $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                    $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                        }
                                                    }
                                                } else {
                                                    $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                                    $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                                        [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                                    $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                }
                                            }
                                        }
                                    } else {
                                        $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode);
                                        
                                        if ($otherTaxCalculationType == 2) {
                                            $otherTaxDiscountBillPackage = SalesHead::calculateDiscountArrayHead($this,
                                                $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, 
                                                [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                        }
                                        
                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                            $salesMenuPackageModel, $salesMenuPackageModel->discountValue, false, 0, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Package', $calculationMode, $taxCalculation, $tempMenuGrandTotal);
                                    }
                                }

                                $menuPackagePlatformFee = 0;
                                if ($platformFeeIncludeOtherTax > 0 && $salesMenuPackageModel->price > 0 && $allMenuSubtotal > 0) {
                                    $menuPackageSubtotal = $salesMenuPackageModel->qty * $salesMenuPackageModel->price;
                                    $menuPackagePlatformFee = round($menuPackageSubtotal / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                                    $totalPlatformFee += $menuPackagePlatformFee;
                                    $sumSubtotalPlatformFee += $menuPackageSubtotal;

                                    if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                        $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                        $menuPackagePlatformFee = $menuPackagePlatformFee + $diffPlatformFee;
                                    }
                                    $salesMenuPackageModel->platformFee = $menuPackagePlatformFee;
                                }

                                $salesMenuPackageModel->otherTaxValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($otherTaxCalculationType == 2 ? $salesMenuPackageModel->discountValue + $otherTaxDiscountBillPackage : 0)) / 100 * $salesMenuPackageModel->otherTax;
                                if ($salesMenuPackageModel->otherTaxValue < 0) {
                                    $salesMenuPackageModel->otherTaxValue = 0;
                                }
                                if ($menuPackagePlatformFee > 0) {
                                    $salesMenuPackageModel->otherTaxValue = $salesMenuPackageModel->otherTaxValue + $menuPackagePlatformFee;
                                }

                                if ($salesMenuPackageModel->otherTaxOnVat == 0) {
                                    $subtotalPpn = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue;
                                    $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 ? $salesMenuPackageModel->discountValue : 0)) / 100 * $salesMenuPackageModel->vat;
                                    $salesMenuPackageModel->otherVatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue) / 100 * $salesMenuPackageModel->otherVat;
                                    if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $package['flagLuxuryItem'],
                                            $salesMenuPackageModel->otherTaxOnVat,
                                            $subtotalPpn,
                                            $salesMenuPackageModel->otherTaxValue
                                        );
                                        $salesMenuPackageModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $salesMenuPackageModel->otherVat
                                        );

                                        $salesMenuPackageModel->dppValue = $dppValue;
                                    }
                                } else {
                                    $subtotalPpn = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue;
                                    $salesMenuPackageModel->vatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 ? $salesMenuPackageModel->discountValue : 0) + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->vat;
                                    $salesMenuPackageModel->otherVatValue = (float) (($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue + $salesMenuPackageModel->otherTaxValue) / 100 * $salesMenuPackageModel->otherVat;
                                    if ($isApplyPckOtherVat && isset($package['flagLuxuryItem'])) {
                                        $dppValue = CalculateTotal::getDppValue(
                                            $package['flagLuxuryItem'],
                                            $salesMenuPackageModel->otherTaxOnVat,
                                            $subtotalPpn,
                                            $salesMenuPackageModel->otherTaxValue
                                        );
                                        $salesMenuPackageModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                            $dppValue,
                                            $salesMenuPackageModel->otherVat
                                        );
    
                                        $salesMenuPackageModel->dppValue = $dppValue;
                                    }
                                }

                                if (!$externalProcess) {
                                    $salesMenuPackageModel->total = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? 0 : $salesMenuPackageModel->discountValue) + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;

                                    if ($inclusiveMenuTemplateID) {
                                        $appliedPckVat = $isApplyOtherVat ? $salesMenuPackageModel->otherVat : $salesMenuPackageModel->vat;
                                        if (isset($package['flagLuxuryItem'])) {
                                            $appliedPckVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($package['flagLuxuryItem'], $salesMenuPackageModel->otherVat) : $salesMenuPackageModel->vat;
                                        }
                                        
                                        $packageGrandTotal = $salesMenuPackageModel->inclusivePrice * $salesMenuPackageModel->qty;
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $currentMenuSubtotal = $salesMenuPackageModel->price * $salesMenuPackageModel->qty;
                                            $totalAfterBillDisc = 0 > $packageGrandTotal - $discountBill - $salesMenuPackageModel->inclusiveDiscountValue ? 0 : $packageGrandTotal - $discountBill - $salesMenuPackageModel->inclusiveDiscountValue;
                                            if ($salesMenuModel->otherTaxOnVat == 0) {
                                                $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                                $subtotalAfterDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue - ($discountBill * $salesMenuModel->qty)) * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                                $newSubtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                                
                                                $otherTaxValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherTax / 100;
                                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $vatValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->vat / 100;
                                                $salesMenuPackageModel->vatValue = (float) $vatValue;

                                                $otherVatValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherVat / 100;
                                                if ($isApplyOtherVat && isset($package['flagLuxuryItem'])) {
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

                                                    $salesMenuPackageModel->dppValue = $dppValue;
                                                }
                                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                                            } else { 
                                                $subtotalAfterMenuDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                                $subtotalAfterDiscount = ($packageGrandTotal - $salesMenuPackageModel->inclusiveDiscountValue - ($discountBill * $salesMenuModel->qty)) * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                                $newSubtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedPckVat) * 100 / (100 + $salesMenuPackageModel->otherTax);
                                                
                                                $otherTaxValue = $newSubtotalAfterDiscount * $salesMenuPackageModel->otherTax / 100;
                                                $salesMenuPackageModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $taxValue = ($newSubtotalAfterDiscount + $salesMenuPackageModel->otherTaxValue) * $salesMenuPackageModel->vat / 100;
                                                $salesMenuPackageModel->vatValue = (float) $taxValue;

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

                                                    $salesMenuPackageModel->dppValue = $dppValue;
                                                }
                                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                                            }

                                            if ($salesMenuPackageModel->discountValue > 0) {
                                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                            } else {
                                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                                            }
                                        } else {                          
                                            $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                            $tempMenuSubtotalBeforeTax = $packageGrandTotal * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                            $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedPckVat + $salesMenuPackageModel->otherTax);
                                            $totalAfterBillDisc = ($discountBill > 0 || $salesMenuPackageModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $packageGrandTotal, $salesMenuPackageModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $packageGrandTotal;
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

                                                    $salesMenuPackageModel->dppValue = $dppValue;
                                                }
                                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                                            } else {
                                                $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $salesMenuPackageModel->otherVat) * 100 / ( 100 + $salesMenuPackageModel->otherTax));
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

                                                    $salesMenuPackageModel->dppValue = $dppValue;
                                                }
                                                $salesMenuPackageModel->otherVatValue = (float) $otherVatValue;
                                            }
                                        }

                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $salesMenuPackageModel->total = (float) ($salesMenuPackageModel->qty * $applyPackagePrice) - $salesMenuPackageModel->inclusiveDiscountValue - $discountBill;
                                        } else {
                                            $salesMenuPackageModel->total = ($salesMenuPackageModel->qty * $salesMenuPackageModel->price) - $salesMenuPackageModel->discountValue + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;
                                        }

                                        if ($discountBill > 0) {
                                            $salesMenuPackageModel->total = $salesMenuPackageModel->price * $salesMenuPackageModel->qty - $salesMenuPackageModel->discountValue + $salesMenuPackageModel->otherTaxValue + $salesMenuPackageModel->vatValue + $salesMenuPackageModel->otherVatValue;
                                        }
                                    } else {
                                        $salesMenuPackageModel->calculateTotal(0, 0, $discountBill, 0, $otherTaxDiscountBillPackage);
                                        if ($salesMenuPackageModel->otherTaxValue < 0) {
                                            $salesMenuPackageModel->otherTaxValue = 0;
                                        }
                                    }   
                                }
                            }                               


                            if ($salesMenuUpdated) {
                                $salesMenuPackageModel->promotionVoucherCode = isset($package['promotionVoucherCode']) ? $package['promotionVoucherCode'] : '';
                                if (!$salesMenuPackageModel->save()) {
                                    throw new Exception('Failed to update menu', [], 500);
                                } else {
                                    // @notes update sales menu vat (ppn)
                                    if (isset($package['flagLuxuryItem']) && $salesMenuPackageModel->otherVat > 0) {
                                        if ($salesMenuModel->statusID == 19) {
                                            SalesMenuVat::deleteSalesMenuVat($salesMenuPackageModel->salesNum, $salesPackageModel->ID);
                                        } else {
                                            SalesMenuVat::saveSalesMenuVat($salesMenuPackageModel->salesNum, $salesMenuPackageModel->ID, $salesMenuPackageModel->dppValue, $package['flagLuxuryItem']);
                                            $dppValueTotal += $salesMenuPackageModel->dppValue;
                                        }
                                    }
                                }
                            }

                            $currentIDs[] = $package['ID'];
                            if ($salesMenuPackageModel->statusID != 19) {
                                $allInclusiveBillDiscount += isset($inclusiveDiscountBill) ? $inclusiveDiscountBill : 0;
                            }
                        }
                    }

                    if (isset($salesMenu['extras'])) {
                        foreach ($salesMenu['extras'] as $extra) {
                            $salesMenuExtraModel = SalesMenuExtra::find()
                                ->andWhere([
                                    'ID' => $extra['localID'],
                                    'salesNum' => $extra['salesNum']
                                ])
                                ->one();

                            if (!$this->ezoQuickService) {
                                $menuExtraModel = MenuExtra::find()
                                    ->where(['menuExtraID' => $salesMenuExtraModel->menuExtraID])
                                    ->one();                                    
                                $currentPromotionID = $salesMenuModel->promotionDetailID;
                                $discountBill = 0;
                                $discountValue = 0;
                                $applyExtVat = $isApplyOtherVat ? $salesMenuExtraModel->otherVat : $salesMenuExtraModel->vat;
                                
                                $displayPriceValue = null;
                                if (isset($extra['displayPriceValue'])) {
                                    $displayPriceValue = $extra['displayPriceValue'];
                                }

                                $applyExtraPrice = isset($displayPriceValue)
                                    ? $displayPriceValue
                                    : ($menuExtraModel ? $menuExtraModel->price : $displayPriceValue);

                                $salesTypeEzo = $this->checkSalesTypeEzo($salesMenu['salesType']);
                                if ($salesTypeEzo) {
                                    $applyExtraPrice = isset($extra['inclusivePrice']) ? $extra['inclusivePrice'] : $applyExtraPrice;
                                }
                                if ($salesMenuModel->promotionDetailID != 0) {
                                    if ($promotionModel->flagMenuExtra == 1) {
                                        $extra['discount'] = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                        $extra['price'] = $detailPromotionTypeID == 4 ? 0 : $applyExtraPrice;
                                        $salesMenuExtraModel->discount = $detailPromotionTypeID == 9 ? 0 : $promotionModel->discount;
                                        $salesMenuExtraModel->price = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->price;        
                                        $salesMenuExtraModel->inclusivePrice = $detailPromotionTypeID == 4 ? 0 : $salesMenuExtraModel->inclusivePrice;                           
                                        if ($detailPromotionTypeID == 9) {
                                            if ($inclusiveMenuTemplateID) {
                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    if ($applyExtraPrice > 0) {
                                                        $tempPromotionDiscount = $salesMenuExtraModel->price / $applyExtraPrice * $promotionModel->discount;
                                                    } else {
                                                        $tempPromotionDiscount = 0;
                                                    }
                                                    
                                                    if ($tempPromotionDiscount > $salesMenuExtraModel->price) {
                                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price;
                                                        $salesMenuExtraModel->inclusiveDiscountValue = (float) $salesMenuExtraModel->qty * $applyExtraPrice;                                             
                                                        $discountValue = (float) $salesMenuExtraModel->inclusiveDiscountValue;
                                                    } else {
                                                        if ($applyExtraPrice > 0) {
                                                            $percentageDiscountValue = $promotionModel->discount / $applyExtraPrice * 100;
                                                            $tempDiscountValue = $salesMenuExtraModel->price * $percentageDiscountValue / 100;
                                                            $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $tempDiscountValue;
                                                            $discountValue = (float) $salesMenuExtraModel->qty * $promotionModel->discount;
                                                            $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                                        } else {
                                                            $salesMenuExtraModel->discountValue = 0;
                                                            $discountValue = 0;
                                                            $salesMenuExtraModel->inclusiveDiscountValue = $discountValue;
                                                        }                                            
                                                    }
                                                } else {
                                                    if ($promotionModel->discount > $applyExtraPrice) {
                                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $applyExtraPrice;
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
                                                $menuExtraTotal = $applyExtraPrice * $salesMenuExtraModel->qty;

                                                if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                                    $salesMenuExtraModel->discount = $promotionModel->discount;
                                                    if ($detailPromotionTypeID == 1) {
                                                        $salesMenuExtraModel->discountValue = (float) $menuExtraSubtotal * $promotionModel->discount / 100;
                                                        $salesMenuExtraModel->inclusiveDiscountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;
                                                        $discountValue = (float) $menuExtraTotal * $promotionModel->discount / 100;

                                                        $subtotalBeforeDiscount = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                                        if ($salesMenuExtraModel->otherTaxOnVat == 0) {                        
                                                            $subtotalAfterDiscount = ($menuExtraTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $applyExtVat + $salesMenuExtraModel->otherTax);
                                                        } else {
                                                            $subtotalAfterDiscount = ($menuExtraTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $applyExtVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                                        } 

                                                        $salesMenuExtraModel->discountValue = (float) $subtotalBeforeDiscount - $subtotalAfterDiscount;
                                                    } else {
                                                        $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
                                                        $discountValue = $salesMenuExtraModel->discountValue;
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
                                                }
                                            } else {
                                                $salesMenuExtraModel->discountValue = (float) $salesMenuExtraModel->qty * $salesMenuExtraModel->price / 100 * $salesMenuExtraModel->discount;
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

                                $discountBill = 0;
                                $inclusiveDiscountBill = 0;
                                $otherTaxDiscountBillExtra = 0;
                                if ($salesMenuExtraModel->otherTax >= 0 || $salesMenuExtraModel->vat >= 0 || $salesMenuExtraModel->otherVat >= 0) {
                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($this->promotionID != $salesMenuModel->promotionDetailID) {
                                            if ($issetSpecialPrice) {
                                                if (in_array($promotionHeadTypeID, [3, 6, 10, 11])) {
                                                    if ($promotionHeadTypeID == 10) {
                                                        if ($applyDiscountBill) {
                                                            if ($applyBillDiscountToExtra) {
                                                                $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                    $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                            }
                                                        }
                                                    } else {
                                                        $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                        $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                    }
                                                }
                                            } else {
                                                if ($promotionHeadTypeID == 10) {
                                                    if ($applyDiscountBill) {
                                                        if ($applyBillDiscountToExtra) {
                                                            $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                            $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                                    $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                        }
                                                    }
                                                } else {
                                                    $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                    $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                            $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal, $otherTaxTotal, $vatTotal);
                                                }
                                            }
                                        }
                                    } else {
                                        if ($applyDiscountBill) {
                                            if ($applyBillDiscountToExtra) {
                                                $discountBill = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode);
                                                
                                                if ($otherTaxCalculationType == 2) {
                                                    $otherTaxDiscountBillExtra = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, 
                                                        [], 0, 0, 0, SalesHead::NON_INCLUSIVE_AFTER_DISCOUNT, $allMenuDiscountTotal);
                                                }
                                                
                                                $inclusiveDiscountBill = SalesHead::calculateDiscountArrayHead($this,
                                                        $salesMenuExtraModel, $salesMenuExtraModel->discountValue, true, $salesMenuModel->promotionDetailID, $promotionCategoryIDs, $promotionCategoryDetailIDs, $promotionMenuIDs, $tempMenuSubtotal, 'Extra', $calculationMode, $taxCalculation, $tempMenuGrandTotal);
                                            }
                                        }
                                    }
                                }

                                if (!$externalProcess) {
                                    if ($inclusiveMenuTemplateID) {
                                        $appliedVat = $isApplyOtherVat ? $salesMenuExtraModel->otherVat : $salesMenuExtraModel->vat;
                                        if (isset($extra['flagLuxuryItem'])) {
                                            $appliedVat = $isApplyOtherVat ? CalculateTotal::getNotLuxuryVatValue($extra['flagLuxuryItem'], $salesMenuExtraModel->otherVat) : $salesMenuExtraModel->vat;
                                        }
                                        
                                        $extraGrandTotal = $salesMenuExtraModel->inclusivePrice * $salesMenuExtraModel->qty;
                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $currentMenuSubtotal = $salesMenuExtraModel->price * $salesMenuExtraModel->qty;
                                            $totalAfterBillDisc = 0 > $extraGrandTotal - $discountBill - $salesMenuExtraModel->inclusiveDiscountValue ? 0 : $extraGrandTotal - $discountBill - $salesMenuExtraModel->inclusiveDiscountValue;
                                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                                $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax);
                                                $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax);
                                                
                                                $otherTaxValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherTax / 100;
                                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $vatValue = $subtotalAfterDiscount * $salesMenuExtraModel->vat / 100;
                                                $salesMenuExtraModel->vatValue = (float) $vatValue;

                                                $otherVatValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherVat / 100;
                                                if (isset($extra['flagLuxuryItem'])) {
                                                    $dppValue = CalculateTotal::getDppValue(
                                                        $extra['flagLuxuryItem'],
                                                        $salesMenuExtraModel->otherTaxOnVat,
                                                        $subtotalAfterDiscount,
                                                        $salesMenuExtraModel->otherTaxValue
                                                    );
                                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                                        $dppValue,
                                                        $extra['otherVat']
                                                    );

                                                    $salesMenuExtraModel->dppValue = $dppValue;
                                                }
                                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                                            } else { 
                                                $subtotalAfterMenuDiscount = ($extraGrandTotal - $salesMenuExtraModel->inclusiveDiscountValue) * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                                $subtotalAfterDiscount = $totalAfterBillDisc * 100 / (100 + $appliedVat) * 100 / (100 + $salesMenuExtraModel->otherTax);
                                                
                                                $otherTaxValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherTax / 100;
                                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $taxValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->vat / 100;
                                                $salesMenuExtraModel->vatValue = (float) $taxValue;

                                                $otherVatValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->otherVat / 100;
                                                if (isset($extra['flagLuxuryItem'])) {
                                                    $dppValue = CalculateTotal::getDppValue(
                                                        $extra['flagLuxuryItem'],
                                                        $salesMenuExtraModel->otherTaxOnVat,
                                                        $subtotalAfterDiscount,
                                                        $salesMenuExtraModel->otherTaxValue
                                                    );
                                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                                        $dppValue,
                                                        $extra['otherVat']
                                                    );

                                                    $salesMenuExtraModel->dppValue = $dppValue;
                                                }
                                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                                            }

                                            if ($salesMenuExtraModel->discountValue > 0) {
                                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount - ($currentMenuSubtotal - $subtotalAfterMenuDiscount);
                                            } else {
                                                $inclusiveDiscountBill = $currentMenuSubtotal - $subtotalAfterDiscount;
                                            }
                                        } else {         
                                            $menuDiscountTotal = $this->salesModel->menuDiscountTotal > 0 ? $this->salesModel->menuDiscountTotal : $tempMenuDiscountTotal;
                                            $tempMenuSubtotalBeforeTax = $extraGrandTotal * 100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax);
                                            $tempSubtotalBeforeTax = $tempGrandTotal * 100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax);
                                            $totalAfterBillDisc = ($discountBill > 0 || $salesMenuExtraModel->discountValue > 0) ? SalesHead::getTotalAfterDisc($promotionHeadModel, $this->promotionDiscount, $extraGrandTotal, $salesMenuExtraModel->discountValue, $tempGrandTotal, $menuDiscountTotal, $discountBill, $tempMenuSubtotalBeforeTax, $tempSubtotalBeforeTax) : $extraGrandTotal;
                                            $totalAfterBillDisc = 0 > $totalAfterBillDisc ? 0 : $totalAfterBillDisc;             
                                            if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                                $subtotalAfterDiscount = $totalAfterBillDisc * (100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax));
                                                $subtotalBeforeDiscount = $extraGrandTotal * (100 / (100 + $salesMenuExtraModel->vat + $salesMenuExtraModel->otherTax));
                
                                                $otherTaxValue = $extraGrandTotal * (100 / (100 + $appliedVat + $salesMenuExtraModel->otherTax)) * ($salesMenuExtraModel->otherTax / 100);
                                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $vatValue = $subtotalBeforeDiscount * $salesMenuExtraModel->vat / 100;
                                                $salesMenuExtraModel->vatValue = (float) $vatValue;
                
                                                $otherVatValue = $subtotalAfterDiscount * $salesMenuExtraModel->otherVat / 100;
                                                if (isset($extra['flagLuxuryItem'])) {
                                                    $dppValue = CalculateTotal::getDppValue(
                                                        $extra['flagLuxuryItem'],
                                                        $salesMenuExtraModel->otherTaxOnVat,
                                                        $subtotalAfterDiscount,
                                                        $salesMenuExtraModel->otherTaxValue
                                                    );
                                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                                        $dppValue,
                                                        $extra['otherVat']
                                                    );

                                                    $salesMenuExtraModel->dppValue = $dppValue;
                                                }
                                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                                            } else {
                                                $subtotalAfterDiscount = $totalAfterBillDisc *  (100 / (100 + $salesMenuExtraModel->otherVat) * 100 / ( 100 + $salesMenuExtraModel->otherTax));
                                                $subtotalBeforeDiscount = $extraGrandTotal *  (100 / (100 + $salesMenuExtraModel->vat) * 100 / ( 100 + $salesMenuExtraModel->otherTax));

                                                $otherTaxValue = $extraGrandTotal * (100 / (100 + $appliedVat)) * ($salesMenuExtraModel->otherTax / (100 + $salesMenuExtraModel->otherTax));
                                                $salesMenuExtraModel->otherTaxValue = (float) $otherTaxValue;
                                                
                                                $vatValue = ($subtotalBeforeDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->vat / 100;
                                                $salesMenuExtraModel->vatValue = (float) $vatValue;
                
                                                $otherVatValue = ($subtotalAfterDiscount + $salesMenuExtraModel->otherTaxValue) * $salesMenuExtraModel->otherVat / 100;
                                                if (isset($extra['flagLuxuryItem'])) {
                                                    $dppValue = CalculateTotal::getDppValue(
                                                        $extra['flagLuxuryItem'],
                                                        $salesMenuExtraModel->otherTaxOnVat,
                                                        $subtotalAfterDiscount,
                                                        $salesMenuExtraModel->otherTaxValue
                                                    );
                                                    $otherVatValue = CalculateTotal::getOtherVatValue(
                                                        $dppValue,
                                                        $extra['otherVat']
                                                    );

                                                    $salesMenuExtraModel->dppValue = $dppValue;
                                                }
                                                $salesMenuExtraModel->otherVatValue = (float) $otherVatValue;
                                            }
                                        }
                                    } else {
                                        $menuExtraPlatformFee = 0;
                                        if ($platformFeeIncludeOtherTax > 0 && $salesMenuExtraModel->qty * $salesMenuExtraModel->price > 0 && $allMenuSubtotal > 0) {
                                            $menuExtraPlatformFee = round($salesMenuExtraModel->qty * $salesMenuExtraModel->price / $allMenuSubtotal * $platformFeeIncludeOtherTax);
                                            $totalPlatformFee += $menuExtraPlatformFee;
                                            $sumSubtotalPlatformFee += $salesMenuExtraModel->qty * $salesMenuExtraModel->price;

                                            if ($allMenuSubtotal == $sumSubtotalPlatformFee) {
                                                $diffPlatformFee = $platformFeeIncludeOtherTax - $totalPlatformFee;
                                                $menuExtraPlatformFee = $menuExtraPlatformFee + $diffPlatformFee;
                                            }
                                        }

                                        if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                            $otherTaxDiscountBillExtra = $discountBill;
                                        }

                                        $salesMenuExtraModel->otherTaxValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($otherTaxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $otherTaxDiscountBillExtra : 0)) / 100 * $salesMenuExtraModel->otherTax;
                                        if ($salesMenuExtraModel->otherTaxValue < 0) {
                                            $salesMenuExtraModel->otherTaxValue = 0;
                                        }
                                        if ($menuExtraPlatformFee > 0) {
                                            $salesMenuExtraModel->otherTaxValue = $salesMenuExtraModel->otherTaxValue + $menuExtraPlatformFee;
                                        }

                                        $subtotalExtPpn = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($salesMenuExtraModel->discountValue + $discountBill);
                                        if ($salesMenuExtraModel->otherTaxOnVat == 0) {
                                            $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $discountBill : 0)) / 100 * $salesMenuExtraModel->vat;
                                            $salesMenuExtraModel->otherVatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($salesMenuExtraModel->discountValue + $discountBill)) / 100 * $salesMenuExtraModel->otherVat;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $extra['otherTaxOnVat'],
                                                    $subtotalExtPpn,
                                                    $salesMenuExtraModel->otherTaxValue
                                                );
                                                $salesMenuExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $salesMenuExtraModel->otherVat
                                                );
    
                                                $salesMenuExtraModel->dppValue = $dppValue;
                                            }
                                        } else {
                                            $salesMenuExtraModel->vatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($taxCalculationType == 2 ? $salesMenuExtraModel->discountValue + $discountBill : 0) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->vat;
                                            $salesMenuExtraModel->otherVatValue = (float) (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - ($salesMenuExtraModel->discountValue + $discountBill) + $salesMenuExtraModel->otherTaxValue) / 100 * $salesMenuExtraModel->otherVat;
                                            if (isset($extra['flagLuxuryItem'])) {
                                                $dppValue = CalculateTotal::getDppValue(
                                                    $extra['flagLuxuryItem'],
                                                    $extra['otherTaxOnVat'],
                                                    $subtotalExtPpn,
                                                    $salesMenuExtraModel->otherTaxValue
                                                );
                                                $salesMenuExtraModel->otherVatValue = (float) CalculateTotal::getOtherVatValue(
                                                    $dppValue,
                                                    $salesMenuExtraModel->otherVat
                                                );
    
                                                $salesMenuExtraModel->dppValue = $dppValue;
                                            }
                                        }
                                        $salesMenuExtraModel->total = (($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - $salesMenuExtraModel->discountValue) + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue + $salesMenuExtraModel->otherVatValue;
                                    }
                                    

                                    if ($salesMenuExtraModel->otherVatValue < 0) {
                                        $salesMenuExtraModel->otherVatValue = 0;
                                    }

                                    if ($otherTaxCalculationType == 2 && $taxCalculationType == 2) {
                                        if ($inclusiveMenuTemplateID) {
                                            $salesMenuExtraModel->total = ($salesMenuExtraModel->qty * $salesMenuExtraModel->price) - $discountValue - $discountBill + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue + $salesMenuExtraModel->otherVatValue;
                                        }
                                    }

                                    if ($inclusiveMenuTemplateID) {
                                        if ($discountBill > 0) {
                                            $salesMenuExtraModel->total = $salesMenuExtraModel->price * $salesMenuExtraModel->qty - $salesMenuExtraModel->discountValue + $salesMenuExtraModel->otherTaxValue + $salesMenuExtraModel->vatValue + $salesMenuExtraModel->otherVatValue;
                                        }
                                    }

                                    if ($salesMenuExtraModel->total < 0) {
                                        $salesMenuExtraModel->total = 0;
                                    }
                                }
                            }
                            if (isset($extra['flagFireOrder']) && $extra['flagFireOrder'] === true ) {
                                $salesMenuExtraModel->flagFireOrder = true;
                            }
                            if (!$salesMenuExtraModel->save()) {
                                throw new Exception('Failed to save extra', [], 500);
                            } else {
                                // @notes update sales menu vat (ppn)
                                if (isset($extra['flagLuxuryItem']) && $salesMenuExtraModel->otherVat > 0) {
                                    if ($salesMenuExtraModel->statusID == 19) {
                                        SalesMenuVat::deleteSalesMenuVat($salesMenuExtraModel->salesNum, $salesMenuExtraModel->ID);
                                    } else {
                                        SalesMenuVat::saveSalesMenuVat($salesMenuExtraModel->salesNum, $salesMenuExtraModel->ID, $salesMenuExtraModel->dppValue, $extra['flagLuxuryItem']);
                                        $dppValueTotal += $salesMenuExtraModel->dppValue;
                                    }
                                }
                            }
                            $existExtraIDs[] = $salesMenuExtraModel->ID;
                            if ($salesMenuExtraModel->statusID != 19) {
                                $inclusiveDiscountBill = isset($inclusiveDiscountBill) ? $inclusiveDiscountBill * $salesMenuModel->qty : 0;
                                $allInclusiveBillDiscount += $inclusiveDiscountBill;
                            }
                        }
                    }
                }
            }

            // @Notes: Show error message when validation order stock failed
            if ($this->validateStock && $errorStockMsg) {
                $errMessage = 'Insufficient qty for: ' . $errorStockMsg;
                $this->errMsg = $errMessage;
                throw new Exception(json_encode($errMessage), [], 400);
            }

            if (count($newIDs) > 0) {
                $this->batchID = SalesMenu::getNewBatchID($this->salesModel->salesNum);
                SalesMenu::updateAll(['batchID' => $this->batchID],
                    ['IN', 'ID', $newIDs]);

                SalesMenu::updateAll(['syncDate' => null],
                    ['salesNum' => $this->salesModel->salesNum]);

                SalesMenuExtra::updateAll(['syncDate' => null],
                    ['salesNum' => $this->salesModel->salesNum]);
            } else {
                // @Notes: prevent print if no new menu submitted
                $this->batchID = 0;
            }

            if (count($newIDsCancel) > 0) {
                foreach ($newIDsCancel as $cancelIDs) {
                    $cancelSalesMenuModel = SalesMenu::findOne($cancelIDs['ID']);
                    if ($cancelSalesMenuModel) {
                        // $cancelSalesMenuModel->batchID = $cancelIDs['batchID'];
                        $cancelSalesMenuModel->createdDate = $cancelIDs['createdDate'];

                        if (!$cancelSalesMenuModel->save()) {
                            throw new Exception('Failed to update cancel menu', [], 500);
                        }

                        if ($cancelSalesMenuModel->childSalesMenus) {
                            SalesMenu::updateAll(['createdDate' => $cancelIDs['createdDate']],
                                ['=', 'menuRefID', $cancelIDs['ID']]);
                        }
                    }
                }
            }

            if (count($tempOrderIDs) > 0) {
                TempOrder::deleteAll(['IN', 'orderID', $tempOrderIDs]);
            }

            if ($this->selfOrderIdKiosk) {
                $salesPaymentGatewayModel = SalesPaymentGateway::findOne(['salesNum' => $this->salesModel->salesNum]);
                if ($salesPaymentGatewayModel) {
                    $salesPaymentGatewayModel->selfOrderIdKiosk = $this->selfOrderIdKiosk;
                } else {
                    $salesPaymentGatewayModel = new SalesPaymentGateway();
                    $salesPaymentGatewayModel->salesNum = $this->salesModel->salesNum;
                    $salesPaymentGatewayModel->selfOrderIdKiosk = $this->selfOrderIdKiosk;
                }

                if (!$salesPaymentGatewayModel->save()) {
                    // tidak di throw karena sifatnya tidak boleh stopper
                    Yii::error($salesPaymentGatewayModel->getErrors());
                }
            }

            $this->salesModel->visitPurposeID = $this->visitPurposeID;
            $this->salesModel->visitorTypeID = $this->visitorTypeID;
            $this->salesModel->paxTotal = $this->paxTotal;
            $this->salesModel->memberID = $this->memberID;
            $this->salesModel->employeeCode = $this->employeeCode;
            $this->salesModel->employeeType = $this->employeeType;
            $this->salesModel->employeeName = $this->employeeName;
            $memberModel = self::onCheckInternalMember($this->memberCode);
            $this->salesModel->memberCode = $memberModel ? $memberModel->memberCode : '';
            $this->salesModel->promotionID = $this->promotionID;
            $this->salesModel->promotionVoucherCode = $this->promotionVoucherCode;
            $this->salesModel->promotionDiscount = $this->promotionDiscount;
            $this->salesModel->discountTotal = $this->discountTotal;
            $this->salesModel->additionalInfo = $this->additionalInfo;
            $this->salesModel->flagExternalAPI = $this->flagExternalAPI;
            $this->salesModel->flagExternalMemberID = $this->flagExternalMemberID;
            $this->salesModel->flagExternalMemberPhone = $this->flagExternalMemberPhone ? substr($this->flagExternalMemberPhone, 0, 20) : $this->flagExternalMemberPhone;
            $this->salesModel->flagExternalCardID = $this->flagExternalCardID;
            $this->salesModel->externalMemberName = $this->externalMemberName;
            $this->salesModel->externalTransID = $this->externalTransID;
            $this->salesModel->deliveryCost = $this->deliveryCost;
            $this->salesModel->orderFee = $this->orderFee ? $this->orderFee : 0;
            $this->salesModel->orderTimeOut = $this->orderTimeOut ? date('Y-m-d H:i:s', strtotime($this->salesModel->salesDateIn . ' + ' . $this->orderTimeOut . ' minutes')) : null;
            $this->salesModel->lockTable = 0;
            $this->salesModel->transactionModeID = $this->transactionModeID;
            $this->salesModel->tableID = $this->tableID;
            $this->salesModel->externalMembershipTypeID = $this->flagExternalAPI ? $this->externalMembershipTypeID : null;
            if ($promotionHeadTypeID == 10 || $promotionHeadTypeID == 1 || $promotionHeadTypeID == 5 || $promotionHeadTypeID == 11) {
                $this->salesModel->tempMenuSubtotal = $tempMenuSubtotal;
            }
            $this->salesModel->inclusiveDiscountTotal = $this->ezoQuickService ? $this->salesModel->inclusiveDiscountTotal : $allInclusiveBillDiscount;
            $this->salesModel->platformFee = $this->platformFee;
             
            $checkPromoBeforeSave = $this->inEligiblePromotion($this->salesModel);
            $this->salesModel->flagAutoRemovePromotion = true;
            $this->salesModel->selfOrderPaymentMethodID = $this->selfOrderPaymentMethodID;

            if (!$this->salesModel->save()) {
                throw new Exception('Failed to update sales head', [], 500);
            } else {
                SalesRewardHead::adjustSalesRewardHead(
                    $this->salesModel->externalMembershipTypeID,
                    $this->promotionVoucherCode,
                    $this->salesModel->salesNum,
                    $this->rewardType
                );

                $salesCondPromoModel = SalesConditionalPromo::find()
                    ->where(['salesNum' => $this->salesModel->salesNum])
                    ->one();

                if ($salesCondPromoModel) {
                    if ($this->conditionalPromoID > 0) {
                        $salesCondPromoModel->salesNum = $this->salesModel->salesNum;
                        $salesCondPromoModel->conditionalPromoID = $this->conditionalPromoID;
                        if (!$salesCondPromoModel->save()) {
                            throw new Exception('Failed to update sales conditional promo', [], 500);
                        }
                    } else {
                        SalesConditionalPromo::deleteAll(['=', 'salesNum', $this->salesModel->salesNum]);
                    }
                } else {
                    if ($this->conditionalPromoID > 0) {
                        $salesCondPromoModel = new SalesConditionalPromo();
                        $salesCondPromoModel->salesNum = $this->salesModel->salesNum;
                        $salesCondPromoModel->conditionalPromoID = $this->conditionalPromoID;
                        if (!$salesCondPromoModel->save()) {
                            throw new Exception('Failed to update sales conditional promo', [], 500);
                        }
                    }
                }

                // @notes save sales head vat (ppn)
                SalesHeadVat::saveSalesHeadVat($this->salesModel->salesNum, $dppValueTotal);
            }

            if ($this->tableID != 0) {
                $mainSalesModel = SalesHead::findMainSales($this->tableID);
            } else {
                $mainSalesModel = SalesHead::findMainSales(null, $this->salesNum);
            }
            $linkSalesNums = SalesLink::find()
                ->select('linkSalesNum')
                ->andWhere(['salesNum' => $mainSalesModel->salesNum])
                ->column();
            $salesNums = array_merge([$mainSalesModel->salesNum], $linkSalesNums);

            // @Notes: When save order if take away mode and no item changes, do not update billingPrintCount
            if ($this->tableID != 0 || ($this->tableID == 0 && count($newIDs) > 0)) {
                SalesHead::updateAll([
                    'billingPrintCount' => 0
                    ], ['IN', 'salesNum', $salesNums]);
            }

            $orderSubject = Logging::SAVE_ORDER;
            if ($this->ezoFullService) {
                $orderSubject = Logging::SAVE_ORDER_ESO_FS;
            } elseif ($this->saveOnly) {
                $orderSubject = Logging::SAVE_ORDER_TABLESIDE;
            } elseif ($this->saveOrderKiosk) {
                $orderSubject = Logging::SAVE_ORDER_KIOSK;
            }

            if (!$this->tempSalesMenu) $this->tempSalesMenu = SalesMenu::findSalesAppliedEmployee($this->salesNum);
            $eventDescription = self::getEventDescription();
            Logging::save($this->salesModel->salesNum, $orderSubject, $eventDescription);
            
            if ($this->authUserName && $this->authUserName != null) {
                Logging::save($this->salesModel->salesNum, Logging::APPLY_PROMOTION_WITH_PIN, $this->getAttributes());
            }

            $checkPromoAfterSave = $mainSalesModel && $mainSalesModel->promotionID === 0;
            if ($checkPromoBeforeSave && $checkPromoAfterSave) {
                $this->flagAutoRemovePromotion = true;
                Logging::save($this->salesModel->salesNum, Logging::REMOVE_BILL_PROMO, $this->getAttributes());
            }

            $menuPromotionAuth = $this->menuPromotionWithAuth;
            if ($menuPromotionAuth && count($menuPromotionAuth) > 0) {
                $this->saveLoggingMenuPromotionAuth($menuPromotionAuth);
            }

            if ($this->ezoFullService && $this->flagAutoRemovePromotion) {
                $this->reCalculateSales($this->salesModel->salesNum, $this->promotionID, $this->promotionDiscount, $this->promotionVoucherCode, $this->ezoFullService);
            }

            return true;
        } catch (Exception $ex) {
            Yii::error($ex);
            throw $ex;
        }
    }

    public function saveCampaign()
    {

        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $updateModel = new UpdateOrder();
            $updateModel->saveOnly = true;
            $updateModel->validateStock = false;
            $updateModel->tableID = $this->salesModel->tableID;
            $updateModel->salesNum = $this->salesModel->salesNum;
            $updateModel->salesMenu = $this->salesMenu;
            $updateModel->visitPurposeID = $this->salesModel->visitPurposeID;
            $updateModel->paxTotal = $this->salesModel->paxTotal;
            $updateModel->promotionID = $this->salesModel->promotionID;
            $updateModel->promotionDiscount = $this->salesModel->promotionDiscount;
            $updateModel->orderTimeOut = $this->salesModel->orderTimeOut ? SalesHead::getOrderTimeOut(
                date_create($this->salesModel->salesDateIn),
                date_create($this->salesModel->orderTimeOut)
            )
                : null;
            $updateModel->orderFee = $this->salesModel->orderFee;

            if (!$updateModel->save()) {
                Yii::error(json_encode($updateModel->getErrors()));
                throw new Exception('Failed to save data');
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            $this->addError('salesMenu', $ex->getMessage() . ' ' . $ex->getLine() . ' ' . $ex->getFile());
            return false;
        }
    }

    private function applyMenuPromo(&$newSalesMenuModel, $newPromotionID = null) {
        $promotionID = $newPromotionID ? $newPromotionID : $newSalesMenuModel->promotionDetailID;
        $menuID = $newSalesMenuModel->menuID;
        $flagLoyalOnly = ($this->externalMembershipTypeID === 'memberid' || $this->externalMembershipTypeID === 'esbloyalty') ? true : false;

        //for Promotion Substitution Menu
        if($newSalesMenuModel->menuPromotionID){
            $promotionDetailModel = PromotionDetail::find()
                ->where(['=','promotionID',$promotionID])
                ->andWhere(['=','menuID',$newSalesMenuModel->menuPromotionID])
                ->andWhere(['=','menuSubsID',$menuID])
                ->one();
            if($promotionDetailModel){
                $menuID = $newSalesMenuModel->menuPromotionID;
            }
        };        

        $promotionModel = PromotionHead::findActiveForMenu($menuID,
                $this->memberID, $this->employeeCode, $newSalesMenuModel->menuPromotionID, $this->flagExternalMemberID, $promotionID)
            ->andWhere([PromotionHead::tableName() . '.promotionID' => $promotionID])
            ->one();

        if (!$promotionModel) {
            return;
        }

        // @Notes: 1 = Discount(%), 4 = Free Item, 7 = Menu Substitution
        if ($promotionModel->promotionTypeID == 1 || $promotionModel->promotionTypeID == 5) {
            $newSalesMenuModel->discount = $promotionModel->discount;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        } else if (($promotionModel->promotionTypeID == 4 || $promotionModel->promotionTypeID == 18 || $promotionModel->promotionTypeID == 19)) {
            $newSalesMenuModel->total = 0;
            $newSalesMenuModel->price = 0;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        } else if ($promotionModel->promotionTypeID == 9) {
            $newSalesMenuModel->discount = 0;
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
        } else if ($promotionModel->promotionTypeID == 7) {
            $newSalesMenuModel->promotionDetailID = $promotionModel->promotionID;
            $newSalesMenuModel->discount = 0;
            $newSalesMenuModel->discountValue = 0;
        }
    }

    private function removeMenuPromo(
        &$newSalesMenuModel,
        $currentPromotionID,
        $appliedVat = 0,
        $inclusiveMenuTemplateID = 0,
        $specialPriceArrModel = null
        ) {
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
        if ($currentPromotionModel->promotionTypeID == 4 || $currentPromotionModel->promotionTypeID == 18 || $currentPromotionModel->promotionTypeID == 19) {
            if ($specialPriceArrModel) {
                $specialMenuPrice = null;
                if (array_key_exists($newSalesMenuModel->menuID,
                        $specialPriceArrModel)) {
                    $specialMenuPrice = $specialPriceArrModel[$newSalesMenuModel->menuID];
                }
                if ($specialMenuPrice) {
                    if ($inclusiveMenuTemplateID) {
                        $newSalesMenuModel->price = self::getNetPrice(
                            $newSalesMenuModel->otherTax, $newSalesMenuModel->otherTaxOnVat,
                            $appliedVat, null, null, $specialMenuPrice);
                    } else {
                        $newSalesMenuModel->price = $specialMenuPrice;
                    }

                } else {
                    $newSalesMenuModel->price = $newSalesMenuModel->originalPrice;
                }
            } else {
                $newSalesMenuModel->price = $newSalesMenuModel->originalPrice;
            }
        }
    }

    private function validateStock($menuID, $qty) {
        $productDetailMenuModel = ProductDetailMenu::find()
            ->where(['menuID' => $menuID])
            ->one();
        if ($productDetailMenuModel) {

            $menuIDs = ProductDetailMenu::find()
                ->select('menuID')
                ->where(['productID' => $productDetailMenuModel->productID])
                ->column();
 
            if ($this->validateStock) {
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
                $qty = $qty * $productDetailMenuModel->convertionQty;
                $salesHeadModel = SalesHead::find()->where(['salesNum' => $this->salesModel->salesNum])->one();
                if ($this->transactionModeID === NULL || $this->transactionModeID === 0) {
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

                        $branchMenu->flagSoldOut = ($stockQty - $qty) <= 0 ? 1 : $branchMenu->flagSoldOut;

                        if (!$branchMenu->save()) {
                            $menuModel = Menu::find()->andWhere(['menuID' => $menuID])->one();
                            if ($menuModel) {
                                $stockQty = intval($stockQty / $productDetailMenuModel->convertionQty);
                                return $menuModel->menuShortName . "($stockQty)";
                            }
                        }
                    }
                }
                $branchMenuTransactionModel = new BranchMenuTransaction();
                $branchMenuTransactionModel->transactionDate = date('Y-m-d H:i:s');
                $branchMenuTransactionModel->branchID = $this->salesModel->branchID;
                $branchMenuTransactionModel->salesNum = $this->salesNum;
                $branchMenuTransactionModel->menuID = $productDetailMenuModel->menuID;
                $branchMenuTransactionModel->qty = $qty;
                $branchMenuTransactionModel->save();
            } else {
                //check if sold out
                $branchMenuModel = BranchMenu::find()
                    ->andWhere(['menuID' => $menuID])
                    ->andWhere(['=', 'flagSoldOut', true])
                    ->one();
                if ($branchMenuModel) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuShortName . "(Sold Out)";
                    }
                }
            }
        } else {
            if ($this->validateStock) {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $menuID])
                ->andWhere(['>', 'qty', 0])
                ->andWhere(['<>', 'flagSoldOut', true])
                ->one();
            } else {
                $branchMenuModel = BranchMenu::find()
                ->andWhere(['IN', 'menuID', $menuID])
                ->one();
            }

            if ($branchMenuModel) {
                $stockQty = $branchMenuModel->qty;
                $currQty = $stockQty - $qty;

                if ($stockQty != 0 && !$this->validateStock) {
                    $branchMenuModel->qty = $currQty <= 0 ? 0 : $currQty;
                } else {
                    $branchMenuModel->qty = $currQty;
                }

                $branchMenuModel->flagSoldOut = ($stockQty - $qty) <= 0 ? 1 : $branchMenuModel->flagSoldOut;

                if (!$branchMenuModel->save()) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuShortName . "($stockQty)";
                    }
                }
            } else {
                //check if sold out
                $branchMenuModel = BranchMenu::find()
                    ->andWhere(['menuID' => $menuID])
                    ->andWhere(['=', 'flagSoldOut', true])
                    ->one();
                if ($branchMenuModel) {
                    $menuModel = Menu::find()->andWhere(['menuID' => $menuID])->one();
                    if ($menuModel) {
                        return $menuModel->menuShortName . "(Sold Out)";
                    }
                }
            }
        }

        return null;
    }

    private function calculateInclusiveTotal($menuID, $qty, $price, $discountVal, $promotionDetailID) {
        $mapBranchModel = $this->getMapBranchModel();
        $subtotal = (float) $qty * $price;
        $discount = (float) ceil($discountVal / 100 * $subtotal);
        $menuTemplateDetailModel = MenuTemplateDetail::find()
            ->andWhere(['menuTemplateID' => $mapBranchModel->menuTemplateID, 'menuID' => $menuID])
            ->one();
        $applyPrice = ($price == 0 && $promotionDetailID > 0) ? 0 : $menuTemplateDetailModel->price;
        $total = (float) (($qty * $applyPrice) - $discount);
        return $total;
    }

    private function getMapBranchModel() {
        $branchID = Setting::getCurrentBranch();
        $mapBranchModel = MapBranchVisitPurpose::find()
            ->andWhere(['branchID' => $branchID, 'visitPurposeID' => $this->visitPurposeID])
            ->one();

        if ($mapBranchModel) {
            return $mapBranchModel;
        }
        return NULL;
    }

    private function getMenuTemplateModel() {
        $mapBranchModel = $this->getMapBranchModel();
        $menuTemplateModel = MenuTemplateHead::findOne($mapBranchModel->menuTemplateID);
        if ($menuTemplateModel) {
            return $menuTemplateModel;
        }
        return NULL;
    }
    
    private function checkSelfOrderCampaign($data) {
        $branchID = Setting::getCurrentBranch();
        $campaignModel = '';
        $selectCampaign = '';
        $newTotal = 0;
        $allTotal = 0;
        $totalQtyCampaign = 0;

        $checkTotal = SalesMenu::find()
            ->select(['SUM(price * qty) as menuSubtotal'])
            ->where(['salesNum' => $data->salesNum])
            ->andWhere(['<>', 'statusID', 12])
            ->scalar();
        
        foreach($data->salesMenu as $salesMenu){
            if ($salesMenu['statusID'] !== 12) {
                $newTotal += ($salesMenu['qty'] * $salesMenu['price']);
            }
            $allTotal = $checkTotal + $newTotal;
        }

        $totalQty = 0;
        foreach($data->salesMenu as $salesMenu){
            $totalSalesMenuQty = SalesMenu::find()
                ->select(['SUM(qty)'])
                ->where(['salesNum' => $data->salesNum])
                ->andWhere(['menuID' => $salesMenu['menuID']])
                ->andWhere(['<>', 'statusID', 12])
                ->groupBy(['salesNum', 'menuID'])
                ->scalar();
            $totalQty += ($salesMenu['qty'] + $totalSalesMenuQty);
            $selfOrderCampaignModel = MsSelfOrderCampaignHead::find()
                ->where(['menuID' => $salesMenu['menuID']])
                ->andWhere(['<=', 'minQty' , $totalQty])
                ->andWhere('NOW() BETWEEN activeDateFrom AND activeDateTo')
                ->andWhere(['flagActive' => 1])
                ->one();
            if($selfOrderCampaignModel){
                if(($selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Amount' || 
                    $selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Item & Amount') &&
                    $selfOrderCampaignModel->minAmountVal <= $allTotal){
                    $campaignModel = $selfOrderCampaignModel;
                    break;
                } else if($selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Item') {
                    $campaignModel = $selfOrderCampaignModel;
                    $totalQtyCampaign = $totalQty;
                    break;
                }
                
            }

            if(!$selfOrderCampaignModel && $campaignModel == '') {
                $selfOrderCampaignModel = MsSelfOrderCampaignHead::find()
                    ->where(['<=', 'minAmountVal', $allTotal])
                    ->andWhere(['selfOrderCampaignType' => 'Minimum Amount'])
                    ->andWhere('NOW() BETWEEN activeDateFrom AND activeDateTo')
                    ->andWhere(['flagActive' => 1])
                    ->one();
                $campaignModel = $selfOrderCampaignModel ? $selfOrderCampaignModel : '';
            }
        }

        if($campaignModel){
            $selfOrderDetailModel = (new Query())
                ->select([
                    'a.ID',
                    'b.selfOrderCampaignID',
                    'itemType',
                    'itemPromotionID',
                    'stockQty' => new Expression('COALESCE((a.itemQty-b.usedQty), 0)'),
                    'itemMenuID',
                    'menuName',
                    'itemDiscountVal',
                    'b.usedQty',
                    'itemText',
                    'd.preAmountMsg',
                    'd.postAmountMsg',
                    'd.effectType',
                    'd.minQty'
                    ])
                ->from(MsSelfOrderCampaignItem::tableName() . ' a')
                ->innerJoin(MapSelfOrderCampaignBranchDetail::tableName() . ' b',
                    "a.ID = b.detailID")
                ->leftJoin(Menu::tableName() . ' c',
                    "c.menuID = a.itemMenuID")
                ->innerJoin(MsSelfOrderCampaignHead::tableName() . ' d',
                    "a.selfOrderCampaignID = d.selfOrderCampaignID")
                ->where(['a.selfOrderCampaignID' => $campaignModel['selfOrderCampaignID']])
                ->andWhere(['b.branchID' => $branchID])
                ->all();
            $max = (new Query())
                ->select([
                    'stockQty' => new Expression('COALESCE(SUM(a.itemQty - b.usedQty), 0)'),
                    ])
                ->from(MsSelfOrderCampaignItem::tableName() . ' a')
                ->innerJoin(MapSelfOrderCampaignBranchDetail::tableName() . ' b',
                    "a.ID = b.detailID")
                ->where(['a.selfOrderCampaignID' => $campaignModel['selfOrderCampaignID']])
                ->andWhere(['b.branchID' => $branchID])
                ->scalar();
            $randNumber = rand(1, $max);
            
            $dataCount = 0;
            foreach($selfOrderDetailModel as $dataDetail){
                $dataCount += $dataDetail['stockQty'];
                if($randNumber <= $dataCount && $randNumber > 0){
                    $selectCampaign = $dataDetail;
                    break;
                }
            }
            
            $selfOrderCampaignID = isset($selectCampaign['selfOrderCampaignID']) ? $selectCampaign['selfOrderCampaignID'] : '';
            $itemType = isset($selectCampaign['itemType']) ? $selectCampaign['itemType'] : '';
            
            $salesOrderModel = SalesOrderCampaign::find()
                ->where(['salesNum' => $data->salesNum])
                ->andWhere(['selfOrderCampaignID' => $selfOrderCampaignID])
                ->one();
            if(!$salesOrderModel && $selfOrderCampaignID){
                $salesOrderModel = new SalesOrderCampaign();
                $salesOrderModel->salesNum = $data->salesNum;
                $salesOrderModel->selfOrderCampaignID = $selfOrderCampaignID;

                if (!$salesOrderModel->save()) {
                    throw new Exception('Failed to save sales order');
                }
            }

            $countSalesCampaign = SalesOrderCampaign::find()
                ->select([
                    'count' => new Expression('IFNULL(SUM(count), 0)')
                ])->where(['selfOrderCampaignID' => $campaignModel->selfOrderCampaignID])
                ->one();
            
            if ($salesOrderModel) {
                $salesOrderModel->count = $salesOrderModel->count + 1;
            }

            if (!$campaignModel->maxUsage) {
                $campaignModel->maxUsage = 9999;
            }
            
            if( $campaignModel->maxUsage > $countSalesCampaign->count && 
                (($salesOrderModel && $salesOrderModel->count == 1) || 
                $campaignModel->flagMultiple == 1)) {

                $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
                $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);

                if($itemType == 'Item'){
                    $addMenuModel = Menu::find()
                        ->where(['menuID' => $selectCampaign['itemMenuID']])
                        ->one();
                    $salesMenuArray = [
                        'ID' => 0,
                        'menuID' => $addMenuModel->menuID,
                        'menuName' => $addMenuModel->menuName,
                        'menuShortName' => $addMenuModel->menuShortName,
                        'qty' => 1,
                        'notes' => '',
                        'price' => (float) 0,
                        'packages' => [],
                        'extras' => [],
                        'otherTax' => (float) $otherTaxCalculationType,
                        'vat' => (float) $taxCalculationType,
                        'otherTaxOnVat' => (float) 1,
                        'total' => (float) 0,
                        'statusID' => 1,
                        'discount' => 0,
                        'originalPrice' => $addMenuModel->price
                    ];
                    
                } else if($itemType == 'Discount'){
                    $this->salesModel->promotionDiscount = $selectCampaign['itemDiscountVal'];
                }
                
                if($salesOrderModel && ($campaignModel->selfOrderCampaignType == 'Minimum Item & Amount' ||
                    $campaignModel->selfOrderCampaignType == 'Minimum Amount')){
                    if(($campaignModel->minAmountVal * $salesOrderModel->count) <= $allTotal){
                        
                    } else {
                        $selectCampaign = '';
                        
                        if ($selectCampaign == '') {
                            $selfOrderDetailModel = (new Query())
                                ->select([
                                    'ID' => new Expression('"0"'),
                                    'selfOrderCampaignID' => new Expression('a.selfOrderCampaignID'),
                                    'itemType' => new Expression('a.selfOrderCampaignType'),
                                    'stockQty' => new Expression('1'),
                                    'itemMenuID' => new Expression('a.menuID'),
                                    'menuName' => new Expression('c.menuName'),
                                    'itemDiscountVal' => new Expression("0"),
                                    'usedQty' => new Expression('1'),
                                    'itemText' => new Expression('""'),
                                    'preAmountMsg' => new Expression('a.preAmountMsg'),
                                    'postAmountMsg' => new Expression('a.postAmountMsg'),
                                    'effectType' => new Expression('"Pre Scratch"'),
                                    'flagMultiple' => new Expression('a.flagMultiple')
                                    ])
                                ->from(MsSelfOrderCampaignHead::tableName() . ' a')
                                ->innerJoin(MapSelfOrderCampaignBranch::tableName() . ' b',
                                    "a.selfOrderCampaignID = b.selfOrderCampaignID")
                                ->leftJoin(Menu::tableName() . ' c',
                                    "c.menuID = a.menuID")
                                ->where(['b.branchID' => $branchID])
                                ->andWhere(['a.selfOrderCampaignType' => 'Minimum Amount'])
                                ->andWhere(['a.flagActive' => 1])
                                ->andWhere(['<=', "($salesOrderModel->count * preAmountVal)" , $allTotal])
                                ->andWhere(['>',  "($salesOrderModel->count * minAmountVal)" , $allTotal])
                                ->one();
                            if($selfOrderDetailModel){
                                $salesOrderModel = SalesOrderCampaign::find()
                                    ->where(['salesNum' => $data->salesNum])
                                    ->andWhere(['selfOrderCampaignID' => $selfOrderDetailModel['selfOrderCampaignID']])
                                    ->one();
                                if (!$salesOrderModel || ($salesOrderModel && $selfOrderDetailModel['flagMultiple'] === '1')) {
                                    $selectCampaign = $selfOrderDetailModel;
                                }
                            }
                        }
                        return $selectCampaign;
                    }
                }
                
                if($salesOrderModel && ($campaignModel->selfOrderCampaignType == 'Minimum Item')){
                    
                    if(($campaignModel->minQty * $salesOrderModel->count) <= $totalQtyCampaign){
                        
                    } else {
                        $selectCampaign = '';
                        
                        if ($selectCampaign == '') {
                            $selfOrderDetailModel = (new Query())
                                ->select([
                                    'ID' => new Expression('"0"'),
                                    'selfOrderCampaignID' => new Expression('a.selfOrderCampaignID'),
                                    'itemType' => new Expression('a.selfOrderCampaignType'),
                                    'stockQty' => new Expression('1'),
                                    'itemMenuID' => new Expression('a.menuID'),
                                    'menuName' => new Expression('c.menuName'),
                                    'itemDiscountVal' => new Expression("0"),
                                    'usedQty' => new Expression('1'),
                                    'itemText' => new Expression('""'),
                                    'preAmountMsg' => new Expression('a.preAmountMsg'),
                                    'postAmountMsg' => new Expression('a.postAmountMsg'),
                                    'effectType' => new Expression('"Pre Scratch"'),
                                    'flagMultiple' => new Expression('a.flagMultiple')
                                    ])
                                ->from(MsSelfOrderCampaignHead::tableName() . ' a')
                                ->innerJoin(MapSelfOrderCampaignBranch::tableName() . ' b',
                                    "a.selfOrderCampaignID = b.selfOrderCampaignID")
                                ->leftJoin(Menu::tableName() . ' c',
                                    "c.menuID = a.menuID")
                                ->where(['b.branchID' => $branchID])
                                ->andWhere(['a.selfOrderCampaignType' => 'Minimum Amount'])
                                ->andWhere(['a.flagActive' => 1])
                                ->andWhere(['<=', "($salesOrderModel->count * preAmountVal)" , $allTotal])
                                ->andWhere(['>',  "($salesOrderModel->count * minAmountVal)" , $allTotal])
                                ->one();
                            if($selfOrderDetailModel){
                                $salesOrderModel = SalesOrderCampaign::find()
                                    ->where(['salesNum' => $data->salesNum])
                                    ->andWhere(['selfOrderCampaignID' => $selfOrderDetailModel['selfOrderCampaignID']])
                                    ->one();
                                if (!$salesOrderModel || ($salesOrderModel && $selfOrderDetailModel['flagMultiple'] === '1')) {
                                    $selectCampaign = $selfOrderDetailModel;
                                }
                            }
                        }
                        return $selectCampaign;
                    }
                }

                if ($salesOrderModel && !$salesOrderModel->save()) {
                    throw new Exception('Failed to save sales order');
                }

                $itemType = isset($selectCampaign['itemType']) ? $selectCampaign['itemType'] : '';
                if ($itemType == 'Discount' || $itemType == 'Item') {
                    if (!Notification::saveNotif($this->tableID, Notification::ACTION_CAMPAIGN)) {
                        throw new ServerErrorHttpException(Yii::t('app',
                                'Failed to save data'));
                    }  
                }
                
                if (isset($selectCampaign['usedQty']) && isset($selectCampaign['ID'])) {
                    MapSelfOrderCampaignBranchDetail::updateAll([
                        'usedQty' => $selectCampaign['usedQty'] + 1
                        ],
                        ['AND', ['branchID' => $branchID], ['detailID' => $selectCampaign['ID']]
                    ]);
                }
            } else {
                $selectCampaign = '';
            }
        }
        
        if ($selectCampaign == '') {
            $selfOrderDetailModel = (new Query())
                ->select([
                    'ID' => new Expression('"0"'),
                    'selfOrderCampaignID' => new Expression('a.selfOrderCampaignID'),
                    'itemType' => new Expression('a.selfOrderCampaignType'),
                    'stockQty' => new Expression('1'),
                    'itemMenuID' => new Expression('a.menuID'),
                    'menuName' => new Expression('c.menuName'),
                    'itemDiscountVal' => new Expression("0"),
                    'usedQty' => new Expression('1'),
                    'itemText' => new Expression('""'),
                    'preAmountMsg' => new Expression('a.preAmountMsg'),
                    'postAmountMsg' => new Expression('a.postAmountMsg'),
                    'effectType' => new Expression('"Pre Scratch"'),
                    'flagMultiple' => new Expression('a.flagMultiple')
                    ])
                ->from(MsSelfOrderCampaignHead::tableName() . ' a')
                ->innerJoin(MapSelfOrderCampaignBranch::tableName() . ' b',
                    "a.selfOrderCampaignID = b.selfOrderCampaignID")
                ->leftJoin(Menu::tableName() . ' c',
                    "c.menuID = a.menuID")
                ->where(['b.branchID' => $branchID])
                ->andWhere(['a.flagActive' => 1])
                ->andWhere(['a.selfOrderCampaignType' => 'Minimum Amount'])
                ->andWhere(['<=', 'preAmountVal' , $allTotal])
                ->andWhere(['>', 'minAmountVal' , $allTotal])
                ->one();
            if($selfOrderDetailModel){
                $salesOrderModel = SalesOrderCampaign::find()
                    ->where(['salesNum' => $data->salesNum])
                    ->andWhere(['selfOrderCampaignID' => $selfOrderDetailModel['selfOrderCampaignID']])
                    ->one();
                if (!$salesOrderModel || ($salesOrderModel && $selfOrderDetailModel['flagMultiple'] === '1')) {
                    $selectCampaign = $selfOrderDetailModel;
                }
            }
        }

        return $selectCampaign;
    }

    public function reCalculateSales($salesNumHead, $promotionID = 0, $promotionDiscount = 0, $promotionVoucherCode = '', $esoFs = false) {
        $findOutstanding = function ($salesNum = null, $tableID = null) use ($esoFs){
            $model = new OutstandingOrder();
            $model->salesNum = $salesNum;
            $model->tableID = $tableID;
            $model->saveNewOrderEsoFs = $esoFs;
            return $model->get();
        };

        $salesNums = [$salesNumHead];
        foreach ($salesNums as $salesNum) {
            $salesModel = $findOutstanding($salesNum);
            $newSalesMenus = [];
            foreach ($salesModel['salesMenu'] as $salesMenu) {
                $newSalesMenu = [];
                foreach ($salesMenu as $key => $value) {
                    $newSalesMenu[$key] = $value;
                }
                $newSalesMenu['menuFlagTax'] = $salesMenu['flagTax'];

                $convertDataChild = function ($salesModel, $type) use ($salesMenu) {
                    $salesArray = [];
                    if ($salesModel) {
                        foreach ($salesModel as $salesChild) {
                            foreach ($salesChild as $key => $value) {
                                $newSalesChild[$key] = $value;
                            }
                            if ($type == 'package') {
                                $newSalesChild['menuFlagTax'] = $salesMenu['flagSeparateTaxCalculation'] === 0 ? $salesMenu['flagTax'] : $salesChild['flagTax'];
                            } else {
                                $newSalesChild['menuFlagTax'] = $salesMenu['flagTax'];
                            }
                            $salesArray[] = $newSalesChild;
                        }
                    }
                    return $salesArray;
                };

                $newSalesMenu['packages'] = $convertDataChild($salesMenu['packages'], 'package');
                $newSalesMenu['extras'] = $convertDataChild($salesMenu['extras'], 'extra');
                $newSalesMenus[] = $newSalesMenu;
            }

            $salesModel['salesMenu'] = $newSalesMenus;
            if ($promotionID > 0) {
                $salesModel['promotionID'] = $promotionID;
                $salesModel['promotionDiscount'] = $promotionDiscount;
                $salesModel['promotionVoucherCode'] = $promotionVoucherCode;
            }
            $updateModel = new UpdateOrder([
                'attributes' => $salesModel
            ]);
            $updateModel->reCalculate = 1;
            $updateModel->preSave();

            self::syncSelfOrder($updateModel->salesNum);
        }
    }

    public static function syncSelfOrder($salesNum) {
        try {
            // Sync self order
            $ezoSettings = Setting::getEZOSetting();
            if ($ezoSettings['Activate EZO'] == 1) {
                $apiUrl = Setting::getEsoFsApiUrl();
                if ($apiUrl) {
                    $syncSelfOrderModel = new SyncSelfOrder();
                    $syncSelfOrderModel->refNum = $salesNum;
                    $syncSelfOrderModel->type = 'salesNum';
                    $syncSelfOrderModel->addQueue();
                }
            }
        } catch (\Throwable $th) {
            Yii::error($th);
        }
    }
    
    public function updateVisitPurpose(){
        
        $salesModel = SalesHead::find()
            ->where(['salesNum' => $this->salesNum])
            ->one();
        $salesModel->visitPurposeID = $this->visitPurposeID;
        $inclusiveMenuTemplateID = MapBranchVisitPurpose::getInclusiveMenuTemplateID($this->visitPurposeID);
        $salesModel->flagInclusive = $inclusiveMenuTemplateID ? 1 : 0;
        $salesModel->scenario = SalesHead::SCENARIO_NOT_CALCULATE;
        if (!$salesModel->save()) {
            Yii::error($salesModel->errors);
            throw new Exception('Failed to update sales head');
        }
        Logging::save($this->salesNum, Logging::CHANGE_VISIT_PURPOSE,
                    $this->getAttributes());
        return $salesModel->visitPurposeID;
    }
    
    private static function getInclusivePrice($applyPrice, $otherTaxValue, $otherTaxOnVat, $vatValue, $salesDecimalSetting, $settingDecimalMode) {
        $result = 0;
        if ($otherTaxOnVat) {
            $result = ($applyPrice * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
        } else {
            $result = ($applyPrice * 100 / (100 + $vatValue + $otherTaxValue));
        }

        return $result;
    }

    public static function getNetPrice($otherTaxValue, $otherTaxOnVat, $vatValue, $salesDecimalSetting, $settingDecimalMode, $price = 0) {
        $result = 0;
        $applyPrice = $price;
        if ($otherTaxOnVat) {
            $result = ($applyPrice * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
        } else {
            $result = ($applyPrice * 100 / (100 + $vatValue + $otherTaxValue));
        }

        return $result;
    }

    public function saveLoggingMenuPromotionAuth($menuPromotionAuth) {
        $promoAuthModel = array_unique($menuPromotionAuth, SORT_REGULAR);
        if (count($promoAuthModel) > 0) {
            foreach($promoAuthModel as $data) {
                $dataLogging = [
                    "authUserName" => isset($data['userName']) ? $data['userName'] : null,
                    "branchID" => isset($this->salesModel->branchID) ? $this->salesModel->branchID : 0,
                    "promotionID" => isset($data['promotionDetailID']) ? $data['promotionDetailID'] : 0,
                    "promotionName" => isset($data['promotionDetailName']) ? $data['promotionDetailName'] : null,
                    "tableID" => isset($this->salesModel->tableID) ? $this->salesModel->tableID : 0
                ];
    
                Logging::save($this->salesModel->salesNum, Logging::APPLY_PROMOTION_WITH_PIN, $dataLogging);
            }
        }
    }

    public static function reCalculateWhenRemoveSplit($salesNumHead) {
        $findOutstanding = function ($salesNum = null, $tableID = null) {
            $model = new OutstandingOrder();
            $model->salesNum = $salesNum;
            $model->tableID = $tableID;
            return $model->get();
        };

        $salesNums = [$salesNumHead];
            foreach ($salesNums as $salesNum) {
                $salesModel = $findOutstanding($salesNum);
                $newSalesMenus = [];
                foreach ($salesModel['salesMenu'] as $salesMenu) {
                    $newSalesMenu = [];
                    foreach ($salesMenu as $key => $value) {
                        $newSalesMenu[$key] = $value;
                    }
                    $newSalesMenu['menuFlagTax'] = $salesMenu['flagTax'];

                    $convertDataChild = function ($salesModel, $type) use ($salesMenu) {
                        $salesArray = [];
                        if ($salesModel) {
                            foreach ($salesModel as $salesChild) {
                                foreach ($salesChild as $key => $value) {
                                    $newSalesChild[$key] = $value;
                                }
                                if ($type == 'package') {
                                    $newSalesChild['menuFlagTax'] = $salesMenu['flagSeparateTaxCalculation'] === 0 ? $salesMenu['flagTax'] : $salesChild['flagTax'];
                                } else {
                                    $newSalesChild['menuFlagTax'] = $salesMenu['flagTax'];
                                }
                                $salesArray[] = $newSalesChild;
                            }
                        }
                        return $salesArray;
                    };

                    $newSalesMenu['packages'] = $convertDataChild($salesMenu['packages'], 'package');
                    $newSalesMenu['extras'] = $convertDataChild($salesMenu['extras'], 'extra');
                    $newSalesMenus[] = $newSalesMenu;
                }

                $salesModel['salesMenu'] = $newSalesMenus;
                $updateModel = new UpdateOrder([
                    'attributes' => $salesModel
                ]);
                $updateModel->applySplit = 1;
                $updateModel->preSave();

                try {
                    // Sync self order
                    $ezoSettings = Setting::getEZOSetting();
                    if ($ezoSettings['Activate EZO'] == 1) {
                        $apiUrl = Setting::getEsoFsApiUrl();
                        if ($apiUrl) {
                            $syncSelfOrderModel = new SyncSelfOrder();
                            $syncSelfOrderModel->refNum = $updateModel->salesNum;
                            $syncSelfOrderModel->type = 'salesNum';
                            $syncSelfOrderModel->addQueue();
                        }
                    }
                } catch (\Throwable $th) {
                    Yii::error($th);
                }
            }
    }

    public static function ineligiblePromotion($salesModel) {
        return $salesModel && ($salesModel->promotionID > 0 && $salesModel->discountTotal == 0 && $salesModel->menuDiscountTotal == 0);
    }

    private function setFlagEmployeeApplied() {
        $this->isEmployeeApplied = true;
    }

    private function getEventDescription() {
        $salesHeadModel = SalesHead::findPromotionSalesHead($this->salesNum);
        return [
            'tableID' => $this->tableID,
            'salesNum' => $this->salesNum,
            'batchID' => $this->batchID,
            'additionalInfo' => $this->additionalInfo,
            'promotionID' => $this->promotionID,
            'salesHead' => $salesHeadModel,
            'salesMenu' => $this->tempSalesMenu,
            'employeeName' => $this->employeeName,
            'employeeType' => $this->employeeType,
            'externalMemberName' => $this->externalMemberName,
            'externalMembershipTypeID' => $this->externalMembershipTypeID,
            'internalMemberName' => $this->internalMemberName
        ];
    }

    public function notifSelfOrderError($errMsg, $refNum) {
        if ($refNum && $errMsg) {
            $selfOrderApi = Setting::getEsoQsApiUrl();
            $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
            $companyCode = $branch->companyCode;
            $authKey = Setting::getApiKey();
            $client = new Client(['baseUrl' => $selfOrderApi]);
            $response = $client->createRequest()
                ->setUrl('pos-error-log')
                ->setMethod('POST')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                    'data-branch' => $branch->branchCode,
                    'data-company' => $companyCode
                ])
                ->setData([
                    'orderID' => $refNum,
                    'branchID' => $branch->branchID,
                    'companyCode' => $companyCode,
                    'errorMessage' => $errMsg,
                    'errorTime' => date('Y-m-d H:i:s')
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->send();
    
            try {
                $content = json_decode($response->getContent(), true);
                if ($content && $content['status'] == '00') {
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $ex) {
                Yii::warning($ex);
                return false;
            }
        }
    }

    private static function onCheckInternalMember($memberCode) {
        if (!$memberCode || $memberCode == '') return null;

        return Member::find()
            ->select(['ms_member.memberCode'])
            ->where(['=', 'ms_member.memberCode', $memberCode])
            ->one();
    }

    private function validateConditionalPromo()
    {
        $isDoublePromo = false;
        $currentMenuIdPromoApplied = [];
        $newMenuIdPromoApplied = [];
        $newPromoName = null;

        if ($this->salesModel) {
            foreach ($this->salesModel->salesMenus as $salesMenu) {
                if ($salesMenu['promotionDetailID'] && $salesMenu['statusID'] != 19 && $salesMenu['salesType'] != 'POS') {
                    $promotionModel = PromotionHead::findOne($salesMenu['promotionDetailID']);
                    if ($promotionModel && ($promotionModel->promotionTypeID == 18 || $promotionModel->promotionTypeID == 19)) {
                        $currentMenuIdPromoApplied[] = $salesMenu['menuID'];
                        foreach ($this->salesMenu as $newSalesMenu) {
                            if ($newSalesMenu['ID'] == $salesMenu['ID'] && $newSalesMenu['promotionDetailID'] == 0) {
                                if (($key = array_search($salesMenu['menuID'], $currentMenuIdPromoApplied)) !== false) {
                                    unset($currentMenuIdPromoApplied[$key]);
                                }
                            }
                        }
                    }
                }
            }
            sort($currentMenuIdPromoApplied);
        }

        if ($this->salesMenu) {
            foreach ($this->salesMenu as $salesMenu) {
                if (isset($salesMenu['promotionDetailID']) && $salesMenu['promotionDetailID'] && $salesMenu['salesType'] == 'POS') {
                    $promotionModel = PromotionHead::findOne($salesMenu['promotionDetailID']);
                    if ($promotionModel && ($promotionModel->promotionTypeID == 18 || $promotionModel->promotionTypeID == 19)) {
                        $newMenuIdPromoApplied[] = $salesMenu['menuID'];
                        $newPromoName = $promotionModel->notes;
                    }
                }
            }
            sort($newMenuIdPromoApplied);
        }
        $isDoublePromo = (!empty($currentMenuIdPromoApplied) && !empty($newMenuIdPromoApplied)) && ($currentMenuIdPromoApplied !== $newMenuIdPromoApplied);

        return $isDoublePromo ? $newPromoName : null;
    }

    private function checkSalesTypeEzo($salesType) {
      return strpos($salesType, 'EZO') !== false;
    }

    private function validateCancelStockMenuPackageRTS($salesMenu){

        if($salesMenu['statusID'] == 12 && $salesMenu['localID'] === $salesMenu['menuRefID'] && $salesMenu['menuGroupID'] == 0) {
            
            $salesMenuPackageModel = SalesMenu::find()
                ->where(['salesNum' => $this->salesModel->salesNum])
                ->andWhere(['menuRefID' => $salesMenu['localID']])
                ->andWhere(['<>', 'menuGroupID', 0])
                ->all();
            
            if($salesMenuPackageModel) {
                foreach ($salesMenuPackageModel as $menuPackage) {
                        $validateStockModel = new ValidateStock();
                        $validateStockModel->salesNum = $this->salesModel->salesNum;
                        $validateStockModel->menuID = $menuPackage['menuID'];
                        $validateStockModel->qty = ($menuPackage['qty'] * $salesMenu['qty']);
                        $validateStockModel->transactionModeID = $this->transactionModeID;
                        $validateStockModel->isCancelOrder = in_array($salesMenu['statusID'], [ 12, 19 ]); 
                        $validateStockModel->salesMenuID = $menuPackage['ID'];
        
                       $validateStockModel->validateStock();
                }
            }
        }
    }
}
