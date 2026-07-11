<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\MapBranchVisitPurpose;
use app\models\Branch;
use app\models\BranchSetting;
use app\models\Menu;
use app\models\MenuExtra;
use app\models\MenuPackage;
use app\models\MenuTemplateHead;
use app\models\PaymentMethod;
use app\models\PromotionHead;
use app\models\SalesHead;
use app\models\Setting;
use app\models\VisitPurpose;
use app\models\Voucher;
use Yii;
use yii\base\Model;
use yii\bootstrap\Modal;

class CalculateTotal extends Model {
    public $salesMenus;
    public $branchID;
    public $visitPurposeID;
    //private variable
    public $rounding = 0;
    public $subtotal = 0;
    public $deliveryCost = 0;
    public $otherTaxTotal = 0;
    public $taxTotal = 0;
    public $grandTotal = 0;
    public $roundingTotal = 0;
    public $flagInclusive;
    public $distance;
    public $minimumOrderTotal;
    public $paymentValidation;
    public $vouchers;
    public $paymentVoucherTotal = 0;
    public $voucherDiscountTotal = 0;
    public $orderPayment = [];
    public $orderVoucherUsage = [];
    public $promotionID = 0;
    public $promotionCode = '';
    public $promotionDiscount = 0;
    public $promotionModel;
    public $promotionCategoryIDs;
    public $promotionCategoryDetailIDs;
    public $promotionCategoryMenuIDs;
    public $discountTotal = 0;
    public $currentOrder;
    public $platformFees;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesMenus'], 'required'],
            [['promotionID'], 'validatePromotion'],
            [['distance', 'vouchers', 'deliveryCost', 'promotionID', 'visitPurposeID', 'applyPromotionType', 'currentOrder', 'platformFee'], 'safe']
        ];
    }

    public function validatePromotion($attribute) {
        // @Notes: this->promotionID = 0: remove promo
        if ($this->promotionID != 0) {
            $this->promotionModel = PromotionHead::find()
                ->andWhere(['promotionID' => $this->promotionID])
                ->one();
            if (!$this->promotionModel) {
                $this->addError($attribute, 'Invalid promotion ID');
            }
        }
    }

    public function calculate() {
        if (!$this->validate()) {
            return false;
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
        }
  
        SalesHead::calculateArrayHeadTotal($this->currentOrder, $this->promotionCategoryIDs, $this->promotionCategoryDetailIDs, $this->promotionCategoryMenuIDs);
        $this->salesMenus = $this->currentOrder['salesMenu'];
        $this->subtotal = $this->currentOrder['subtotal'];
        $this->grandTotal = $this->currentOrder['grandTotal'];  
        $this->roundingTotal = $this->currentOrder['roundingTotal'];   
        if (isset($this->currentOrder['platformFee'])) {
            $this->platformFees = $this->currentOrder['platformFee'];
        }   

        return true;
    }

    public static function getDppValue($flagLuxuryItem, $otherTaxOnVat, $subtotal, $otherTaxTotal)
    {
        $dppValue = 0;
        $dppRatio1 = Setting::getValue1("VAT", "DPP Calculation Ratio") ? 
            Setting::getValue1("VAT", "DPP Calculation Ratio") : 1;
        $dppRatio2 = Setting::getValue2("VAT", "DPP Calculation Ratio") ? 
            Setting::getValue2("VAT", "DPP Calculation Ratio") : 1;

        if ($flagLuxuryItem == 1) {

            $dppValue = (
                $subtotal +
                ($otherTaxOnVat == 1 ? $otherTaxTotal : 0)
            );

        } else {

            $dppValue = (
                $subtotal +
                ($otherTaxOnVat == 1 ? $otherTaxTotal : 0)
            ) * ($dppRatio1 / $dppRatio2);

        }

        return $dppValue;
    }

    public static function getOtherVatValue($dppValue, $otherVat)
    {
        $result = $dppValue * ($otherVat / 100);

        return $result;
    }

    public static function getNotLuxuryVatValue($flagLuxuryItem, $vat)
    {
        $dppRatio1 = Setting::getValue1("VAT", "DPP Calculation Ratio") ? 
            Setting::getValue1("VAT", "DPP Calculation Ratio") : 1;
        $dppRatio2 = Setting::getValue2("VAT", "DPP Calculation Ratio") ? 
            Setting::getValue2("VAT", "DPP Calculation Ratio") : 1;

        if ($flagLuxuryItem == 1) {
            return $vat;
        } else {
            return $vat * ($dppRatio1 / $dppRatio2);
        }
    }

    public static function getReverseDiscountTotal($salesHead)
    {
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $salesHead['visitPurposeID']])->one();
        $discountTotal = 0;
        $taxValue = 0;
        if ($mapBranchModel) {
            $taxValue = $mapBranchModel->taxValue;
            $additionalTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            if ($otherTaxOnVat) {
                $discountTotal = round($salesHead['discountTotal'] / 100 * (100 + $taxValue) / 100 * (100 + $additionalTaxValue));
            } else {
                $discountTotal = round($salesHead['discountTotal'] / 100 * (100 + $taxValue + $additionalTaxValue));
            }
        }

        return (int) $discountTotal;
    }

}
