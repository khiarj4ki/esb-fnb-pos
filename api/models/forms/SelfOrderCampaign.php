<?php

namespace app\models\forms;

use app\models\Branch;
use app\models\MapSelfOrderCampaignBranch;
use app\models\MapSelfOrderCampaignBranchDetail;
use app\models\Menu;
use app\models\MsSelfOrderCampaignHead;
use app\models\MsSelfOrderCampaignItem;
use app\models\Notification;
use app\models\SalesMenu;
use app\models\SalesOrderCampaign;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\web\ServerErrorHttpException;

/**
 * @property array $salesMenu
 */
class SelfOrderCampaign extends Model
{

    public $salesMenu;
    public $flagCampaign = false;

    public function checkSelfOrderCampaign($salesModel, $salesMenus)
    {
        $branchID = Setting::getCurrentBranch();
        $campaignModel = '';
        $selectCampaign = '';
        $newTotal = 0;
        $allTotal = 0;
        $totalQtyCampaign = 0;
        $counter = 0;
        $latestBatch = -1;
        $salesMenuArray = [];

        $checkTotal = SalesMenu::find()
            ->select(['SUM(price * qty) as menuSubtotal'])
            ->where(['salesNum' => $salesModel->salesNum])
            ->andWhere(['NOT IN', 'statusID', [12, 19]])
            ->scalar();

        foreach ($salesMenus as $salesMenu) {
            if ($salesMenu['statusID'] !== 12 && $salesMenu['statusID'] !== 19) {
                $newTotal += ($salesMenu['qty'] * $salesMenu['price']);
            }
            if (isset($salesMenu['packages'])) {
                foreach ($salesMenu['packages'] as $menuPackage) {
                    $newTotal += ($menuPackage['qty'] * $menuPackage['price']);
                }
            }
            if (isset($salesMenu['extras'])) {
                foreach ($salesMenu['extras'] as $menuExtra) {
                    $newTotal += ($menuExtra['qty'] * $menuExtra['price']);
                }
            }
            if ($salesMenu['batchID'] > $latestBatch) {
                $latestBatch = $salesMenu['batchID'];
            }
            $allTotal = $checkTotal + $newTotal;
        }

        foreach ($salesMenus as $salesMenu) {
            $totalQty = 0;
            $totalQty = SalesMenu::find()
                ->select(['SUM(qty)'])
                ->where(['salesNum' => $salesModel->salesNum])
                ->andWhere(['menuID' => $salesMenu['menuID']])
                ->andWhere(['NOT IN', 'statusID', [12, 19]])
                ->groupBy(['salesNum', 'menuID'])
                ->scalar();
            $totalQty += $salesMenu['qty'];
            $selfOrderCampaignModel = MsSelfOrderCampaignHead::find()
                ->where(['menuID' => $salesMenu['menuID']])
                ->andWhere(['<=', 'minQty', $totalQty])
                ->andWhere(['flagActive' => 1])
                ->one();
            if ($selfOrderCampaignModel) {
                $selfOrderCampaignDetail = MapSelfOrderCampaignBranchDetail::find()
                    ->select([
                        'total' => new Expression('SUM(usedQty)')
                    ])
                    ->where(['=', 'selfOrderCampaignID', $selfOrderCampaignModel->selfOrderCampaignID])
                    ->asArray()
                    ->one();
                if ($selfOrderCampaignModel->maxUsage != null && $selfOrderCampaignDetail && ($selfOrderCampaignDetail['total'] >= $selfOrderCampaignModel->maxUsage)) {
                    continue;
                } else if (($selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Amount' ||
                        $selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Item & Amount') &&
                    $selfOrderCampaignModel->minAmountVal <= $allTotal
                ) {
                    $campaignModel = $selfOrderCampaignModel;
                    break;
                } else if ($selfOrderCampaignModel->selfOrderCampaignType == 'Minimum Item') {
                    $campaignModel = $selfOrderCampaignModel;
                    $totalQtyCampaign = $totalQty;
                    break;
                }
            }

            if (!$selfOrderCampaignModel && $campaignModel == '') {
                $selfOrderCampaignModel = MsSelfOrderCampaignHead::find()
                    ->where(['<=', 'minAmountVal', $allTotal])
                    ->andWhere(['selfOrderCampaignType' => 'Minimum Amount'])
                    ->andWhere(['flagActive' => 1])
                    ->andWhere('NOW() BETWEEN activeDateFrom AND activeDateTo')
                    ->one();
                $campaignModel = $selfOrderCampaignModel ? $selfOrderCampaignModel : '';

                if ($selfOrderCampaignModel) {
                    $selfOrderCampaignDetail = MapSelfOrderCampaignBranchDetail::find()
                        ->select([
                            'total' => new Expression('SUM(usedQty)')
                        ])
                        ->where(['=', 'selfOrderCampaignID', $selfOrderCampaignModel->selfOrderCampaignID])
                        ->asArray()
                        ->one();

                    if ($selfOrderCampaignModel->maxUsage != null && $selfOrderCampaignDetail && ($selfOrderCampaignDetail['total'] >= $selfOrderCampaignModel->maxUsage)) {
                        $campaignModel = "";
                    }
                }
            }
        }

        if ($campaignModel) {
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
                ->innerJoin(
                    MapSelfOrderCampaignBranchDetail::tableName() . ' b',
                    "a.ID = b.detailID"
                )
                ->leftJoin(
                    Menu::tableName() . ' c',
                    "c.menuID = a.itemMenuID"
                )
                ->innerJoin(
                    MsSelfOrderCampaignHead::tableName() . ' d',
                    "a.selfOrderCampaignID = d.selfOrderCampaignID"
                )
                ->where(['a.selfOrderCampaignID' => $campaignModel['selfOrderCampaignID']])
                ->andWhere(['b.branchID' => $branchID])
                ->all();
            $max = (new Query())
                ->select([
                    'stockQty' => new Expression('COALESCE(SUM(a.itemQty - b.usedQty), 0)'),
                ])
                ->from(MsSelfOrderCampaignItem::tableName() . ' a')
                ->innerJoin(
                    MapSelfOrderCampaignBranchDetail::tableName() . ' b',
                    "a.ID = b.detailID"
                )
                ->where(['a.selfOrderCampaignID' => $campaignModel['selfOrderCampaignID']])
                ->andWhere(['b.branchID' => $branchID])
                ->scalar();
            $randNumber = rand(1, $max);

            $dataCount = 0;
            foreach ($selfOrderDetailModel as $dataDetail) {
                $dataCount += $dataDetail['stockQty'];
                if ($randNumber <= $dataCount && $randNumber > 0) {
                    $selectCampaign = $dataDetail;
                    break;
                }
            }

            $selfOrderCampaignID = isset($selectCampaign['selfOrderCampaignID']) ? $selectCampaign['selfOrderCampaignID'] : '';
            $itemType = isset($selectCampaign['itemType']) ? $selectCampaign['itemType'] : '';

            $salesOrderModel = SalesOrderCampaign::find()
                ->where(['salesNum' => $salesModel->salesNum])
                ->andWhere(['selfOrderCampaignID' => $selfOrderCampaignID])
                ->one();

            if (!$salesOrderModel && $selfOrderCampaignID) {
                $salesOrderModel = new SalesOrderCampaign();
                $salesOrderModel->salesNum = $salesModel->salesNum;
                $salesOrderModel->selfOrderCampaignID = $selfOrderCampaignID;

                if (!$salesOrderModel->save()) {
                    throw new Exception('Failed to save sales order');
                }
            }

            if (($salesOrderModel && $salesOrderModel->count == 0) || $campaignModel->flagMultiple == 1) {
                $flaggerCount = 0;
                $taxCalculationType = Branch::getPosTaxCalculationType($branchID);
                $otherTaxCalculationType = Branch::getPosOtherTaxCalculationType($branchID);

                if ($itemType == 'Item') {
                    $addMenuModel = Menu::find()
                        ->where(['menuID' => $selectCampaign['itemMenuID']])
                        ->one();
                    $salesMenuArray = [
                        'ID' => 0,
                        'batchID' => $latestBatch + 1,
                        'menuID' => $addMenuModel->menuID,
                        'menuName' => $addMenuModel->menuName,
                        'customMenuName' => $addMenuModel->menuName,
                        'menuShortName' => $addMenuModel->menuShortName,
                        'menuGroupID' => 0,
                        'originalPrice' => $addMenuModel->price,
                        'inclusivePrice' => (float) 0,
                        'price' => (float) 0,
                        'sellPrice' => (float) 0,
                        'qty' => 1,
                        'notes' => '',
                        'packages' => [],
                        'extras' => [],
                        'statusID' => 1,
                        'salesType' => 'EZO FS',
                        'menuTypeID' => 0,
                        'createdBy' => 0,
                        'otherTax' => (float) $otherTaxCalculationType,
                        'vat' => (float) $taxCalculationType,
                        'otherTaxOnVat' => (float) 1,
                        'total' => (float) 0,
                        'discount' => 0,
                    ];
                    if ($salesOrderModel) {
                        if ($campaignModel->selfOrderCampaignType == 'Minimum Amount' && ($campaignModel->minAmountVal * ($salesOrderModel->count + 1)) <= $allTotal) {
                            $salesOrderModel->count = $salesOrderModel->count + 1;
                            $flaggerCount = $salesOrderModel->count;
                        } else if ($campaignModel->selfOrderCampaignType == 'Minimum Item' && ($campaignModel->minQty * ($salesOrderModel->count + 1)) <= $totalQtyCampaign) {
                            $salesOrderModel->count = $salesOrderModel->count + 1;
                            $flaggerCount = $salesOrderModel->count;
                        }
                    }
                } else if ($itemType == 'Discount') {
                    $salesOrderModel->count = $salesOrderModel->count + 1;
                    $flaggerCount = $salesOrderModel->count;
                    $salesModel->promotionDiscount = $selectCampaign['itemDiscountVal'];
                } else if ($itemType == 'Text') {
                    $salesOrderModel->count = $salesOrderModel->count + 1;
                    $flaggerCount = $salesOrderModel->count;
                }

                if ($salesOrderModel && ($campaignModel->selfOrderCampaignType == 'Minimum Item & Amount' ||
                    $campaignModel->selfOrderCampaignType == 'Minimum Amount')) {
                    $counter = $salesOrderModel->count + 1;
                    if ($flaggerCount != 0) {
                        $counter = $flaggerCount;
                    }
                    if (($campaignModel->minAmountVal * $counter) <= $allTotal) {
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
                                ->innerJoin(
                                    MapSelfOrderCampaignBranch::tableName() . ' b',
                                    "a.selfOrderCampaignID = b.selfOrderCampaignID"
                                )
                                ->leftJoin(
                                    Menu::tableName() . ' c',
                                    "c.menuID = a.menuID"
                                )
                                ->where(['b.branchID' => $branchID])
                                ->andWhere(['a.selfOrderCampaignType' => 'Minimum Amount'])
                                ->andWhere(['a.flagActive' => 1])
                                ->andWhere("preAmountVal <= $allTotal - (($counter-1) * minAmountVal)")
                                ->one();
                            if ($selfOrderDetailModel) {
                                $salesOrderModel = SalesOrderCampaign::find()
                                    ->where(['salesNum' => $salesModel->salesNum])
                                    ->andWhere(['selfOrderCampaignID' => $selfOrderDetailModel['selfOrderCampaignID']])
                                    ->one();
                                if (!$salesOrderModel || ($salesOrderModel && $selfOrderDetailModel['flagMultiple'] === '1')) {
                                    $selectCampaign = $selfOrderDetailModel;
                                }
                            }
                        }
                        return [
                            'selectCampaign' => $selectCampaign,
                            'salesMenu' => $salesMenus,
                            'promotionDiscount' => $salesModel->promotionDiscount,
                        ];
                    }
                } else if ($salesOrderModel && $campaignModel->selfOrderCampaignType == 'Minimum Item') {
                    $counter = $salesOrderModel->count + 1;
                    if ($flaggerCount != 0) {
                        $counter = $flaggerCount;
                    }
                    if (($campaignModel->minQty * $counter) <= $totalQtyCampaign) {
                    } else {
                        return [
                            'selectCampaign' => '',
                            'salesMenu' => $salesMenus,
                            'promotionDiscount' => $salesModel->promotionDiscount,
                        ];
                    }
                }

                if ($salesOrderModel && !$salesOrderModel->save()) {
                    throw new Exception('Failed to save sales order');
                }

                $itemType = isset($selectCampaign['itemType']) ? $selectCampaign['itemType'] : '';
                if ($itemType == 'Discount' || $itemType == 'Item') {
                    if (!Notification::saveNotif($salesModel->tableID, Notification::ACTION_CAMPAIGN)) {
                        throw new ServerErrorHttpException(Yii::t(
                            'app',
                            'Failed to save data'
                        ));
                    }
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
                ->innerJoin(
                    MapSelfOrderCampaignBranch::tableName() . ' b',
                    "a.selfOrderCampaignID = b.selfOrderCampaignID"
                )
                ->leftJoin(
                    Menu::tableName() . ' c',
                    "c.menuID = a.menuID"
                )
                ->where(['b.branchID' => $branchID])
                ->andWhere(['a.flagActive' => 1])
                ->andWhere(['a.selfOrderCampaignType' => 'Minimum Amount'])
                ->andWhere("preAmountVal <= $allTotal")
                ->andWhere("minAmountVal > $allTotal")
                ->andWhere('NOW() BETWEEN activeDateFrom AND activeDateTo')
                ->one();
            if ($selfOrderDetailModel) {
                $salesOrderModel = SalesOrderCampaign::find()
                    ->where(['salesNum' => $salesModel->salesNum])
                    ->andWhere(['selfOrderCampaignID' => $selfOrderDetailModel['selfOrderCampaignID']])
                    ->one();
                if (!$salesOrderModel || ($salesOrderModel && $selfOrderDetailModel['flagMultiple'] === '1')) {
                    $selectCampaign = $selfOrderDetailModel;
                }

                if ($selfOrderCampaignModel) {
                    $selfOrderCampaignDetail = MapSelfOrderCampaignBranchDetail::find()
                        ->select([
                            'total' => new Expression('SUM(usedQty)')
                        ])
                        ->where(['=', 'selfOrderCampaignID', $selfOrderCampaignModel->selfOrderCampaignID])
                        ->asArray()
                        ->one();
                    if ($selfOrderCampaignModel->maxUsage != null && ($selfOrderCampaignDetail['total'] >= $selfOrderCampaignModel->maxUsage)) {
                        $selectCampaign = '';
                    }
                }
            }
        }

        if ($selectCampaign != '') {
            $this->flagCampaign = true;
        }
        return [
            'selectCampaign' => $selectCampaign,
            'salesMenu' => $salesMenus,
            'salesMenuCampaign' => [
                'salesNum' => $salesModel->salesNum,
                'salesMenu' => $salesMenuArray
            ],
            'promotionDiscount' => $salesModel->promotionDiscount,
        ];
    }

    public function incrementUsedQty($selectCampaign)
    {
        if ($this->flagCampaign) {
            if (isset($selectCampaign['usedQty']) && isset($selectCampaign['ID'])) {
                MapSelfOrderCampaignBranchDetail::updateAll(
                    [
                        'usedQty' => $selectCampaign['usedQty'] + 1
                    ],
                    [
                        'AND', ['branchID' => Setting::getCurrentBranch()], ['detailID' => $selectCampaign['ID']]
                    ]
                );
            }
        }
    }
}
