<?php

namespace app\models\forms;

use app\components\AppHelper;
use app\models\Branch;
use app\models\SalesHead;
use app\models\SalesLink;
use app\models\SalesMenu;
use app\models\SalesMenuExtra;
use app\models\SalesMergeTable;
use app\models\SalesPlatformFee;
use app\models\Setting;
use DateTime;
use Yii;
use yii\db\Exception;
use yii\base\Model;
use yii\db\Expression;
use yii\web\HttpException;

/**
 * @property string $salesNum
 * @property integer $tableID
 * 
 */
class OutstandingOrder extends Model
{

    public $salesNum;
    public $tableID;
    public $saveNewOrderEsoFs = false;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['salesNum'], 'required'],
            [['tableID'], 'safe'],
        ];
    }

    public function get()
    {
        $connection = Yii::$app->getDb();

        if (!$this->validate()) {
            return false;
        }
        $salesNum = $this->salesNum;
        $tableID = $this->tableID;
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();

        $filterSalesHead = null;
        if (is_null($tableID)) {
          $filterSalesHead = " AND ((tr_saleshead.salesNum = '$salesNum') OR (tr_salesmergetable.salesNum = '$salesNum'))";
          $filterSalesMenu = " AND (tr_saleshead.salesNum = '$salesNum')";
        } else {
          $filterSalesHead = " AND ((tr_saleshead.tableID = '$tableID') OR (tr_salesmergetable.tableID = '$tableID'))";
          $filterSalesMenu = " AND (tr_saleshead.tableID = '$tableID')";
        }

        $salesModel = $connection->createCommand(SalesHead::getFindOutstandingOrderRawQuery($branchID, 'tr_saleshead') .
          $filterSalesHead . "
          AND tr_saleshead.salesDateOut IS NULL
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryOne();

        if (!$salesModel) {
          throw new HttpException(404, Yii::t('app', 'Order not found'));
        }

        $mainSalesMenuModel = $connection->createCommand(SalesMenu::getFindOutstandingSalesMainRawQuery($branchID)
          . $filterSalesMenu . "
          AND tr_saleshead.salesDateOut IS NULL
          ORDER BY tr_salesmenu.batchID, tr_salesmenu.ID, tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $childSalesMenuModel = $connection->createCommand(SalesMenu::getFindOutstandingSalesChildRawQuery($branchID)
          . $filterSalesMenu . "
          AND tr_saleshead.salesDateOut IS NULL
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesMenuExtraModel = $connection->createCommand(SalesMenuExtra::getFindOutstandingSalesExtrasRawQuery($branchID)
          . $filterSalesMenu . "
          AND tr_saleshead.salesDateOut IS NULL
          ORDER BY tr_saleshead.salesDate, tr_saleshead.salesNum")->queryAll();

        $salesPaymentsModel = $connection->createCommand("SELECT
            tr_salespayment.*,
            ms_paymentmethod.paymentMethodTypeID,
            ms_paymentmethod.paymentMethodName,
            ms_paymentmethod.flagUseEmployeeLimit,
            ms_paymentmethod.posExternalPaymentID,
            ms_paymentmethod.depositSourceID,
            ms_paymentmethod.voucherSourceID,
            lk_paymentmethodtype.paymentMethodTypeName
          FROM
            tr_salespayment
          LEFT JOIN
            ms_paymentmethod ON tr_salespayment.paymentMethodID = ms_paymentmethod.paymentMethodID
          LEFT JOIN
            lk_paymentmethodtype ON ms_paymentmethod.paymentMethodTypeID = lk_paymentmethodtype.paymentMethodTypeID
          WHERE
            tr_salespayment.salesNum = '$salesNum'")->queryAll();


        $salesNumListArray = array_column($mainSalesMenuModel, 'salesNum');
        $salesNumList = "'" . implode("', '", $salesNumListArray) . "'";

        $salesMenuCompletionModel = $connection->createCommand("SELECT * FROM
            tr_salesmenucompletion
          WHERE
            tr_salesmenucompletion.salesNum IN ($salesNumList)")->queryAll();

        $salesProcessMenuModel = $connection->createCommand("SELECT * FROM
            tr_salesprocessmenu
          WHERE
            tr_salesprocessmenu.salesNum IN ($salesNumList)")->queryAll();

        $newFormatSalesModel = AppHelper::reformatTypeDataHead($salesModel);
        $mainSalesMenuModelArray = AppHelper::reformatTypeDataMenu($mainSalesMenuModel, 'salesNum');
        $childSalesMenuModelArray = AppHelper::reformatTypeDataMenu($childSalesMenuModel, 'menuRefID');
        $salesMenuExtraModelArray = AppHelper::reformatTypeDataMenu($salesMenuExtraModel, 'menuDetailID');
        $salesMenuCompletionModelArray = AppHelper::reformatTypeDataMenu($salesMenuCompletionModel, 'salesNum');
        $salesProcessMenuModelArray = AppHelper::reformatTypeDataMenu($salesProcessMenuModel, 'salesNum');
        $salesPaymentModelArray = AppHelper::reformatTypeDataMenu($salesPaymentsModel, 'salesNum');

        $salesModel = SalesHead::getOtherAttributeSalesHead($newFormatSalesModel, $mainSalesMenuModelArray, $settings);

        $salesNum = $salesModel['salesNum'];

        $taxInclusiveAfterDiscount = false;
        if ($salesModel['flagInclusive']) {
            if ($salesModel['posOtherTaxCalculationID'] == 2 && $salesModel['posTaxCalculationID'] == 2) {
                $taxInclusiveAfterDiscount = true;
            }
        }

        $promoOnPackage = [];
        $mainSalesMenus = [];
        if (isset($mainSalesMenuModelArray[$salesNum])) {
          $mainSalesMenu = $mainSalesMenuModelArray[$salesNum];

          $promotionDetailsModelArrays = SalesMenu::getPromotionDetailsArray($mainSalesMenu);
          
          foreach ($mainSalesMenu as $mainSales) {
            $promotionMenuSubsIDs = [];
            if (isset($promotionDetailsModelArrays[$mainSales['promotionDetailID']])) {
              $promotionMenuSubsIDs = array_column($promotionDetailsModelArrays[$mainSales['promotionDetailID']], 'menuSubsID');
            }

            $menuIDs = [];
            $tempPackages = [];
            if (isset($childSalesMenuModelArray[$mainSales['ID']])) {
              foreach ($childSalesMenuModelArray[$mainSales['ID']] as $childSales) {
                if ($mainSales['promotionTypeID'] == 7) {
                  $menuIDs[] = $childSales['menuID'];
                }

                $childSales['tempDiscountValue'] = $childSales['discountValue'];
                if ($taxInclusiveAfterDiscount) {
                  $childSales['discountValue'] = $childSales['inclusiveDiscountValue'];
                }
                $childSales = SalesMenu::getOtherAttributeSalesMenu($childSales, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $mainSales);
                $tempPackages[] = $childSales;
              }
            }
            $mainSales['packages'] = $tempPackages;

            $tempExtras = [];
            if (isset($salesMenuExtraModelArray[$mainSales['ID']])) {
              foreach ($salesMenuExtraModelArray[$mainSales['ID']] as $extraSales) {
                $extraSales['tempDiscountValue'] = $extraSales['discountValue'];
                if ($taxInclusiveAfterDiscount) {
                  $extraSales['discountValue'] = $extraSales['inclusiveDiscountValue'];
                }
                $tempExtras[] = $extraSales;
              }
            }
            $mainSales['extras'] = $tempExtras;

            $promotionDetailCount = 0;
            foreach ($menuIDs as $menu) {
              if (in_array($menu, $promotionMenuSubsIDs)) {
                $promotionDetailCount += 1;
              }
            }

            if ($promotionDetailCount > 0) {
              $promoOnPackage[] = [
                'menuID' => $mainSales['menuID'],
                'value' => true
              ];
            }

            $mainSales = SalesMenu::getOtherAttributeSalesMenu($mainSales, $salesModel, $salesMenuCompletionModelArray, $salesProcessMenuModelArray, $mainSales);
            if ($taxInclusiveAfterDiscount) {
              $mainSales['discountValue'] = $mainSales['inclusiveDiscountValue'];
            }
            $mainSalesMenus[] = $mainSales;
          }
        }

        if (!$this->saveNewOrderEsoFs) {
          $result = AppHelper::checkDataInconsistencyArray($salesModel, $mainSalesMenus);
          if (!$result['status']) {
              throw new HttpException(500, json_encode($result['message']));
          }
        }

        // check sales link
        $checkHoldStatus = $connection->createCommand("SELECT DISTINCT
            tr_saleslink.salesNum
          FROM
            tr_saleslink
          LEFT JOIN
            tr_salesmenu ON tr_saleslink.salesNum = tr_salesmenu.salesNum
          WHERE
            ((tr_saleslink.salesNum = '$salesNum') OR (tr_saleslink.linkSalesNum = '$salesNum'))
            AND tr_salesmenu.statusID = 46
          ")->queryColumn();

        $extraFields = [
            'memberName' => $salesModel['memberID'] != 0 ?  $salesModel['memberName'] : '',
            'promotionName' => $salesModel['promotionName'],
            'salesMenu' => $mainSalesMenus,
            'visitPurposeName' => $salesModel['visitPurposeName'],
            'promoOnPackage' => $promoOnPackage,
            'isOrderLinkedWithHoldMenu' => ($checkHoldStatus && count($checkHoldStatus) > 0) ? true : false,
            'isSplitBillSales' => self::checkSplitBill($this->salesNum),
            'isStandAloneTable' => self::checkStandAloneTable($this->salesNum),
            'platformFee' => self::getSalesPlatformFee($this->salesNum),
            'salesPayment' => isset($salesPaymentModelArray[$salesModel['salesNum']]) ? $salesPaymentModelArray[$salesModel['salesNum']] : null
        ];
        

        return array_merge(
            $salesModel,
            $extraFields
        );
    }

    private static function checkSplitBill($salesNum) {
        $flagSplitBill = false;

        if (strpos($salesNum, '-') !== false) {
           $flagSplitBill = true;
        }

        return $flagSplitBill;
    }

    private static function checkStandAloneTable($salesNum) {
        $connection = Yii::$app->getDb();

        $flagSplitBill = false;
        $currentSales = $connection->createCommand("SELECT
            tr_saleshead.salesNum,
            tr_saleshead.tableID 
          FROM
            tr_saleshead
          WHERE
            tr_saleshead.salesNum = '$salesNum'")->queryOne();

        $tempSalesNum = explode('-', $currentSales['salesNum'])[0];
        $tableID = $currentSales['tableID'];

        $splitBillSales = $connection->createCommand("SELECT
            tr_saleshead.salesNum
          FROM
            tr_saleshead
          WHERE
            tr_saleshead.salesNum LIKE '%$tempSalesNum%'
            AND tr_saleshead.tableID = $tableID")->queryAll();

        if (count($splitBillSales) > 1) {
            $flagSplitBill = false;
        } else {
            $flagSplitBill = true;
        }

        return $flagSplitBill;
    }

    private static function getSalesPlatformFee($salesNum) {
        $result = [];
        $currentSalesPlatformFee = SalesPlatformFee::find()
            ->where(['=', 'salesNum', $salesNum])
            ->all();

        if (count($currentSalesPlatformFee) > 0) {
            foreach ($currentSalesPlatformFee as $row) {
                $result[] = [
                    "orderID" => "",
                    "salesNum" => $row->salesNum,
                    "platformFeeTypeID" => $row->platformFeeTypeID,
                    "feeNameEN" => $row->feeNameEN,
                    "feeNameID" => $row->feeNameID,
                    "percentage" => (float) $row->percentage,
                    "amount" => (float) $row->amount,
                    "minAmount" => (float) $row->minAmount,
                    "maxAmount" => (float) $row->maxAmount
                ];
            }
        }

        return $result;
    }
}
