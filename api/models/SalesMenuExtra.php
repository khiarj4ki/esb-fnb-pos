<?php
namespace app\models;

use app\components\AppHelper;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesmenuextra".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property int $menuDetailID
 * @property int $menuExtraID
 * @property string $qty
 * @property string $price
 * @property string $inclusivePrice
 * @property string $discount
 * @property string $discountValue
 * @property string $inclusiveDiscountValue
 * @property string $otherTax
 * @property string $otherTaxValue
 * @property string $vat
 * @property string $vatValue
 * @property string $otherVat
 * @property string $otherVatValue
 * @property int $otherTaxOnVat
 * @property string $total
 * @property int $statusID
 * @property string $syncDate
 * 
 * @property MenuExtra $menuExtra
 * @property SalesMenu $salesMenu
 */
class SalesMenuExtra extends ActiveRecord {
    public $pendingOrder;
    public $flagHoldOrder;
    public $flagFireOrder;
    public $dppValue;
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesmenuextra';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID', 'menuDetailID', 'menuExtraID', 'otherTaxOnVat', 'statusID'], 'integer'],
            [['statusID'], 'default', 'value' => 13],
            [['salesNum', 'menuDetailID', 'menuExtraID', 'qty', 'price', 'inclusivePrice', 'discount', 'discountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'otherTaxOnVat', 'total', 'statusID'], 'required'],
            [['qty', 'price', 'discount', 'discountValue', 'inclusiveDiscountValue', 'otherTax', 'otherTaxValue', 'vat', 'vatValue', 'otherVat', 'otherVatValue', 'total'], 'number'],
            [['otherVat', 'otherVatValue'], 'default', 'value' => 0],
            [['syncDate', 'pendingOrder', 'flagHoldOrder', 'flagFireOrder', 'dppValue'], 'safe'],
            [['salesNum'], 'string', 'max' => 20]
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
            'menuDetailID' => 'Menu Detail ID',
            'menuExtraID' => 'Menu Extra ID',
            'qty' => 'Qty',
            'price' => 'Price',
            'discount' => 'Discount',
            'discountValue' => 'Discount Value',
            'otherTax' => 'Other Tax',
            'otherTaxValue' => 'Other Tax Value',
            'vat' => 'Vat',
            'vatValue' => 'Vat Value',
            'otherTaxOnVat' => 'Other Tax On Vat',
            'total' => 'Total',
            'statusID' => 'Status ID',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['menuExtraName'] = function($model) {
            return $model->menuExtra->menuExtraName;
        };
        $fields['menuExtraShortName'] = function($model) {
            return $model->menuExtra->menuExtraShortName;
        };
        $fields['qty'] = function($model) {
            return (float) $model->qty;
        };
        $fields['price'] = function($model) {
            return (float) $model->price;
        };
        $fields['displayPriceValue'] = function ($model) {
            return (float) $model->getDisplayPriceValue();
        };
        $fields['priceTotal'] = function ($model) {
            return (float) $model->price * $model->qty * $model->salesMenu->qty;
        };
        $fields['discount'] = function($model) {
            return (float) $model->discount;
        };
        $fields['otherTax'] = function($model) {
            return (float) $model->otherTax;
        };
        $fields['vat'] = function($model) {
            return (float) $model->vat;
        };
        $fields['total'] = function($model) {
            return (float) $model->total;
        };
        $fields['flagLuxuryItem'] = function ($model) {
            return $model->getFlagLuxuryItem();
        };

        return $fields;
    }

    public function getMenuExtra() {
        return $this->hasOne(MenuExtra::class, ['menuExtraID' => 'menuExtraID']);
    }

    public function getSalesMenu() {
        return $this->hasOne(SalesMenu::class, ['ID' => 'menuDetailID']);
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;
        // @Notes: Status 1 = New, 12 = Cancelled, 13 = Preparing, 19 = Print Cancelled
        if ($this->statusID == 1) {
            $this->statusID = 13;
            if((isset($this->flagHoldOrder) && $this->flagHoldOrder === true) && !isset($this->flagFireOrder)) {
                $this->statusID = 46;
            }
        } else if ($this->statusID == 12) {
            $this->statusID = 19;
        } else if((isset($this->flagFireOrder) && $this->flagFireOrder === true) && $this->statusID == 46) {
            $this->statusID = 13;
            if(isset($this->pendingOrder)) {
                if ($this->pendingOrder == false) {
                    $this->statusID = 14;
                }
            }
        } else if(isset($this->pendingOrder)) {
            if ($this->pendingOrder == false) {
                $this->statusID = 14;
            }
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

    public function calculateTotal($billDiscount = 0) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $branchID = Setting::getCurrentBranch();
        $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
        $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);
        $subtotal = (float) $this->qty * $this->price;
        $discount = $this->discountValue;
        if (SalesHead::getInclusiveFlag($this->salesMenu->salesHead->branchID, $this->salesMenu->salesHead->visitPurposeID) == MenuTemplateHead::INCLUSIVE_YES) {
            $menuExtraModel = MenuExtra::find()
                ->andWhere(['menuExtraID' => $this->menuExtraID])
                ->one();
            $this->total = ($this->qty * $menuExtraModel->price) - $discount;
        } else {
            $otherTax = (float) $this->otherTax / 100 * ($subtotal - ($otherTaxCalculationType == 2 ? $discount + $billDiscount : 0));
            if ($this->otherTaxOnVat == 0) {
                $vat = (float) $this->vat / 100 * ($subtotal - ($taxCalculationType == 2 ? $discount + $billDiscount : 0));
            } else {
                $vat = (float) $this->vat / 100 * ($subtotal - ($taxCalculationType == 2 ? $discount + $billDiscount : 0) + $otherTax);
            }
            $this->total = $subtotal - ($taxCalculationType == 2 || $otherTaxCalculationType == 2 ? $discount : $discount) + $otherTax + $vat;
            
        }
    }

    public static function findActive() {
        return SalesMenuExtra::find()->andWhere(['statusID' => 13]);
    }
    
    private function getMenuTemplateModel() {
        $salesHead = self::find()->andWhere(['salesNum' => $this->salesNum])->one();
        $menuTemplateDetailModel = MapBranchVisitPurpose::find()
            ->andWhere(['branchID' => $salesHead->branchID, 'visitPurposeID' => $salesHead->visitPurposeID])
            ->one();
        
        if ($menuTemplateDetailModel) {
            return $menuTemplateDetailModel;
        }
        return NULL;
    }
    
    public function getDisplayPriceValue() {
        $price = 0;
        $salesHead = SalesHead::find()->andWhere(['salesNum' => $this->salesNum])->one();
        if ($salesHead->flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
            $price = ($this->total + $this->discountValue) / $this->qty;
        } else {
            $price = $this->price;
        }
        return $price;
    }

    public function getFlagLuxuryItem() {
        $flagLuxuryItem = isset($this->menuExtra->menu) ? $this->menuExtra->menu->flagLuxuryItem : 0;
        return $flagLuxuryItem;
    }

    public static function getMenuExtras($menuID, $localID){
        $where = ($localID != "")? ['=', 'tr_salesmenuextra.menuDetailID', $localID] : []; 

        $extras = self::find()
            ->select("ms_menuextra.menuExtraID, ms_menuextra.menuID, ms_menuextra.menuExtraName, tr_salesmenuextra.qty, tr_salesmenuextra.price, tr_salesmenuextra.inclusivePrice, tr_salesmenuextra.discount, tr_salesmenuextra.total")
            ->leftJoin("tr_salesmenu", "tr_salesmenu.localID = tr_salesmenuextra.menuDetailID")
            ->leftJoin("ms_menuextra", "ms_menuextra.menuExtraID = tr_salesmenuextra.menuExtraID")
            ->where("tr_salesmenu.menuID = $menuID")
            ->andWhere($where)
            ->asArray()->all();
        
        return $extras;
    }

    public static function getFindOutstandingSalesExtrasRawQuery($branchID) {
      return "SELECT
        salesExtra.*,
        (CASE WHEN tr_saleshead.flagInclusive = 1 THEN salesExtra.inclusivePrice ELSE salesExtra.price END) AS displayPriceValue,
        (salesExtra.price * salesExtra.qty * tr_salesmenu.qty) AS priceTotal,
        ms_menuextra.menuExtraID AS masterMenuExtraID,
        ms_menuextra.flagActive AS masterExtraActive,
        ms_menuextra.menuExtraName,
        ms_menuextra.menuExtraShortName,
        ms_menu.menuID AS masterMenuID,
        ms_menu.menuName,
        ms_menu.menuShortName,
        ms_menucategorydetail.menuCategoryID,
        ms_menucategory.menuCategoryDesc,
        ms_menu.flagActive AS masterMenuActive,
        ms_menu.flagLuxuryItem
      FROM
        tr_salesmenu
      LEFT JOIN
        tr_salesmenuextra salesExtra ON tr_salesmenu.salesNum = salesExtra.salesNum AND tr_salesmenu.ID = salesExtra.menuDetailID
      LEFT JOIN
        tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
      LEFT JOIN
        ms_menuextra ON salesExtra.menuExtraID = ms_menuextra.menuExtraID
      LEFT JOIN
        ms_menu ON ms_menuextra.menuRefID = ms_menu.menuID
      LEFT JOIN
        ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
      LEFT JOIN
        ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
      WHERE
        tr_saleshead.branchID = $branchID
        AND salesExtra.salesNum IS NOT NULL";
    }

    public static function getSalesExtrasRawQuery($salesNum) {
        return "SELECT
          salesExtra.*,
          (CASE WHEN tr_saleshead.flagInclusive = 1 THEN (salesExtra.total + salesExtra.inclusiveDiscountValue) / salesExtra.qty ELSE salesExtra.price END) AS displayPriceValue,
          (salesExtra.price * salesExtra.qty * tr_salesmenu.qty) AS priceTotal,
          ms_menuextra.menuExtraID AS masterMenuExtraID,
          ms_menuextra.flagActive AS masterExtraActive,
          ms_menuextra.menuExtraName,
          ms_menuextra.menuExtraShortName,
          ms_menu.menuID AS masterMenuID,
          ms_menu.menuName,
          ms_menu.menuShortName,
          ms_menucategorydetail.menuCategoryID,
          ms_menucategory.menuCategoryDesc,
          ms_menu.flagActive AS masterMenuActive
        FROM
          tr_salesmenu
        LEFT JOIN
          tr_salesmenuextra salesExtra ON tr_salesmenu.salesNum = salesExtra.salesNum AND tr_salesmenu.ID = salesExtra.menuDetailID
        LEFT JOIN
          tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
        LEFT JOIN
          ms_menuextra ON salesExtra.menuExtraID = ms_menuextra.menuExtraID
        LEFT JOIN
          ms_menu ON ms_menuextra.menuRefID = ms_menu.menuID
        LEFT JOIN
          ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
        LEFT JOIN
          ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID
        WHERE
          tr_saleshead.salesNum = '$salesNum'";
    }

}
