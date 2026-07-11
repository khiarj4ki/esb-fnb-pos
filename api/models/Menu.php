<?php
namespace app\models;

use app\components\AppHelper;
use app\models\forms\CalculateTotal;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "ms_menu".
 *
 * @property int $menuID
 * @property int $menuCategoryDetailID
 * @property int $bomID
 * @property string $menuName
 * @property string $menuShortName
 * @property string $menuCode
 * @property string $estimatedCost
 * @property string $price
 * @property int $flagTax
 * @property int $flagOtherTax
 * @property string $zeroValueText
 * @property int $flagCustomerPrint
 * @property string $salesCoaNo
 * @property string $cogsCoaNo
 * @property string $discountCoaNo
 * @property string $notes
 * @property string $field2
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property MenuCategoryDetail $menuCategoryDetail
 * @property BranchMenu $branchMenu
 * @property MenuExtra[] $activeMenuExtras
 * @property MenuGroup[] $activeMenuGroups
 * @property MenuTemplateDetail $menuTemplateDetail
 * @property SpecialPriceMenu $activeSpecialPriceMenu
 */
class Menu extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menu';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['menuCategoryDetailID', 'menuName', 'menuShortName', 'estimatedCost', 'price', 'flagTax', 'flagOtherTax', 'zeroValueText', 'flagCustomerPrint', 'salesCoaNo', 'cogsCoaNo', 'discountCoaNo', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['menuCategoryDetailID', 'bomID', 'flagTax', 'flagOtherTax', 'flagCustomerPrint', 'flagActive', 'flagSeparatePrintPackage', 'flagLuxuryItem', 'flagSeparateTaxCalculation'], 'integer'],
            [['estimatedCost', 'price', 'originalPrice'], 'number'],
            [['menuID', 'createdDate', 'editedDate', 'imageUrl', 'description', 'openPrice', 'buttonColor', 'flagLuxuryItem'], 'safe'],
            [['menuName', 'notes', 'createdBy', 'editedBy', 'altMenuName'], 'string', 'max' => 100],
            [['menuShortName', 'menuCode', 'field1', 'field2', 'field3', 'field4', 'field5',], 'string', 'max' => 50],
            [['zeroValueText'], 'string', 'max' => 12],
            [['salesCoaNo', 'cogsCoaNo', 'discountCoaNo'], 'string', 'max' => 20]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'menuID' => 'Menu ID',
            'menuCategoryDetailID' => 'Menu Category Detail ID',
            'bomID' => 'Bom ID',
            'menuName' => 'Menu Name',
            'menuShortName' => 'Menu Short Name',
            'menuCode' => 'Menu Code',
            'estimatedCost' => 'Estimated Cost',
            'price' => 'Price',
            'originalPrice' => 'Original Price',
            'flagTax' => 'Flag Tax',
            'flagOtherTax' => 'Flag Other Tax',
            'zeroValueText' => 'Zero Value Text',
            'flagCustomerPrint' => 'Flag Customer Print',
            'salesCoaNo' => 'Sales Coa No',
            'cogsCoaNo' => 'Cogs Coa No',
            'discountCoaNo' => 'Discount Coa No',
            'notes' => 'Notes',
            'field2' => 'field2',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function getMenuCategoryDetail() {
        return $this->hasOne(MenuCategoryDetail::class,
                ['ID' => 'menuCategoryDetailID']);
    }

    public function getBranchMenu() {
        $branchID = Setting::getCurrentBranch();

        return $this->hasOne(BranchMenu::class, ['menuID' => 'menuID'])
                ->andOnCondition([BranchMenu::tableName() . '.branchID' => $branchID]);
    }

    public function getActiveMenuExtras() {
        return $this->hasMany(MenuExtra::class, ['menuID' => 'menuID'])
                ->joinWith('menu.menuCategoryDetail.menuCategory')
                ->innerJoinWith('branchMenu')
                ->andOnCondition([MenuExtra::tableName() . '.flagActive' => 1])
                ->andWhere(['ms_menu.flagActive' => 1])
                ->orderBy([
                    'ms_menuextra.orderID' => SORT_ASC,
                    'menuExtraID' => SORT_ASC
                ]);
    }

    public function getActiveMenuGroups() {
        return $this->hasMany(MenuGroup::class, ['menuID' => 'menuID'])
                ->andOnCondition([MenuGroup::tableName() . '.flagActive' => 1])
                ->orderBy([
                    'ms_menugroup.orderID' => SORT_ASC,
                    'ms_menugroup.menuGroupID' => SORT_ASC
                ]);
    }

    public function getMenuTemplateDetails() {
        return $this->hasMany(MenuTemplateDetail::class, ['menuID' => 'menuID']);
    }

    public function getActiveMenuTemplateDetails() {
        return $this->hasMany(MenuTemplateDetail::class, ['menuID' => 'menuID'])
                ->andOnCondition([MenuTemplateDetail::tableName() . '.flagActive' => 1])
                ->orderBy('ms_menutemplatedetail.orderID ASC');
    }

    public function getActiveSpecialPriceMenu() {
        return $this->hasOne(SpecialPriceMenu::class, ['menuID' => 'menuID'])
                ->innerJoinWith(['specialPriceHead' => function ($query) {
                        $query->joinWith('specialPriceTime');
                    }])
                ->innerJoinWith('specialPriceHead.specialPriceDays')
                ->andWhere([SpecialPriceHead::tableName() . '.flagActive' => 1])
                ->andWhere('CURRENT_DATE() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->andWhere(['or',
                    'startTime IS NULL AND endTime IS NULL',
                    'TIME(NOW()) BETWEEN startTime AND endTIme'
        ]);
    }

    public function getMapMenuIcon() {
        return $this->hasMany(MapMenuIcon::class, ['menuID' => 'menuID']);
    }

    public function getActiveSpecialPriceMenuTimes() {
        return $this->hasMany(SpecialPriceMenu::class, ['menuID' => 'menuID'])
                ->innerJoinWith(['specialPriceHead' => function ($query) {
                        $query->joinWith('specialPriceTime');
                    }])
                ->innerJoinWith('specialPriceHead.specialPriceDays')
                ->andWhere([SpecialPriceHead::tableName() . '.flagActive' => 1])
                ->andWhere('CURRENT_DATE() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END');
    }

    public function getProductDetailMenu() {
        return $this->hasOne(ProductDetailMenu::class, ['menuID' => 'menuID']);
    }

    public static function findMenuForEzoPayment($salesMenus) {
        $newSalesMenu = $salesMenus;
        for ($i=0; $i < count($newSalesMenu); $i++) {
            $model = Menu::find()
                ->innerJoinWith('menuCategoryDetail')
                ->innerJoinWith('menuCategoryDetail.menuCategory')
                ->where(['menuID' => $newSalesMenu[$i]['menuID']])
                ->one();
            if ($model) {
                $newSalesMenu[$i]['menuShortName'] = $model->menuShortName;
                $newSalesMenu[$i]['menuCategoryID'] = $model->menuCategoryDetail->menuCategoryID;
                $newSalesMenu[$i]['menuCategoryDesc'] = $model->menuCategoryDetail->menuCategory->menuCategoryDesc;
                if (count($newSalesMenu[$i]['packages']) > 0) {
                    for ($p=0; $p < count($newSalesMenu[$i]['packages']); $p++) { 
                        $packageModel = Menu::find()
                            ->where(['menuID' => $newSalesMenu[$i]['packages'][$p]['menuID']])
                            ->one();
                        if ($packageModel) {
                            $newSalesMenu[$i]['packages'][$p]['menuShortName'] = $packageModel->menuShortName;
                        }
                    }
                }

                if(count($newSalesMenu[$i]['extras']) > 0) {
                    for ($k=0; $k < count($newSalesMenu[$i]['extras']); $k++) { 
                        $extraModel = MenuExtra::findOne($newSalesMenu[$i]['extras'][$k]['menuExtraID']);
                        if ($extraModel) {
                            $newSalesMenu[$i]['extras'][$k]['menuExtraShortName'] = $extraModel->menuExtraShortName;
                        }
                    }
                }
            }
        }
        return $newSalesMenu;
    }

    public static function findActive() {
        return Menu::find()
                ->joinWith('branchMenu')
                ->andOnCondition([BranchMenu::tableName() . '.flagActive' => 1])
                ->andWhere([Menu::tableName() . '.flagActive' => 1])
                ->orderBy(Menu::tableName() . '.menuName');
    }

    public static function findActiveAsArray($postVisitPurposeID = null, $showKiosk = 0) {
        $settings = Setting::getPrintingSettings();
        $otherVat = Setting::getOtherVat();
        $branchID = Setting::getCurrentBranch();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        // @notes : show menu by times
        $isShowMenuTime = isset($settings['Show Menu by Time']) ? $settings['Show Menu by Time'] : 0;
        $currentTime = date('H:i:s');

        $menuData = [];
        $visitPurposeID = NULL;
        $menuTemplateID = NULL;
        $otherTaxValue = 0;
        $otherTaxOnVat = 0;
        $vatValue = 0;
        $vatSubject = 0;
        $i = 0;
        $imageMenuLoc = Url::to('@web/images/menu/', true);
        $imageMenuCategoryDetailLoc = Url::to('@web/images/menu-category-detail/',
                true);
        $imageMenuIconLoc = Url::to('@web/images/menu-icon/', true);

        $menuCategoryDetail = (new Query)
                    ->select(['*'])
                    ->from(MenuCategoryDetail::tableName())
                    ->indexBy('ID')
                    ->all();

        if (is_array($postVisitPurposeID)) {
            $visitPurposeID = $postVisitPurposeID['visitPurposeID'];
            $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $visitPurposeID])->one();
            if ($mapBranchModel) {
                $menuTemplateID = $mapBranchModel->menuTemplateID;
                $otherTaxValue = $mapBranchModel->additionalTaxValue;
                $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
                $vatValue = $mapBranchModel->taxValue;
                $vatSubject = $mapBranchModel->vatSubject;
                // if ($menuTemplateID) {
                //     $sql = "update ms_menucategorydetail a
                //     left join ms_menutemplatecategorydetail b on a.ID = b.menuCategoryDetailID
                //     set a.orderID = b.orderID
                //     where b.menuTemplateID = $menuTemplateID";
                //     $command = Yii::$app->db->createCommand($sql);
                //     $command->execute();
                // }
            }
        }

        $connection = Yii::$app->getDb();
        $querySelect = "
            SELECT DISTINCT
                ms_menu.*,
                ms_menu.flagActive AS menuFlagActive,
                ms_menucategorydetail.*,
                ms_branchmenu.*,
                lk_menusize.*,
                ms_branchmenu.flagShowEzo AS showEzoBm,
                ms_branchmenu.menuID AS branchMenuID,
                ms_menu.buttonColor AS menuBC,
                ms_menu.imageUrl AS menuImage,
                ms_menucategory.buttonColor AS categoryBC,
                ms_menucategory.menuCategoryDesc,
                ms_menucategory.orderID, 
                ms_menucategorydetail.imageUrl AS categoryDetailImage,
                ms_menucategorydetail.buttonColor AS categoryDetailBC,
                ms_menucategorydetail.ID AS categoryDetailID,
                ms_menutemplatedetail.flagShowEzo AS showEzoTd,
                ms_menutemplatedetail.menuID AS menuTemplateMenuID,
                ms_menutemplatedetail.price AS menuTemplatePrice,
                ms_menutemplatedetail.startTime AS menuTemplateStartTime,
                ms_menutemplatedetail.endTime AS menuTemplateEndTime,
                ms_menutemplatehead.flagInclusive,
                map_menutemplatelayout.posX,
                map_menutemplatelayout.posY,
                ms_productdetailmenu.menuID AS productDetailMenuID,
                ms_productdetailmenu.convertionQty,
                ms_station.stationID AS masterStationID,
                templateDetailDay.dayID";
            
        if ($menuTemplateID) {
            $querySelect .= ", 
            ms_menutemplatedetail.orderID AS menuTemplateOrderID,
            ms_menucategorydetail.orderID, 
            ms_menutemplatecategory.orderID,
            ms_menutemplatecategorydetail.orderID";
        }
        
        //@notes: retrives master menu and define in array
        $queryMenuArray = $querySelect . "
            FROM
                ms_menu
            INNER JOIN
                ms_branchmenu ON ms_menu.menuID = ms_branchmenu.menuID AND ms_branchmenu.branchID = $branchID AND ms_branchmenu.flagActive = 1
            LEFT JOIN
                ms_station ON ms_branchmenu.stationID = ms_station.stationID AND ms_branchmenu.branchID = ms_station.branchID AND ms_station.flagActive = 1
            LEFT JOIN
                ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
            LEFT JOIN
                ms_menucategory ON ms_menucategorydetail.menuCategoryID = ms_menucategory.menuCategoryID AND ms_menucategory.flagActive = 1
            LEFT JOIN
                ms_productdetailmenu ON ms_menu.menuID = ms_productdetailmenu.menuID
            LEFT JOIN
                ms_menutemplatedetail ON ms_menu.menuID = ms_menutemplatedetail.menuID AND ms_menutemplatedetail.flagActive = 1
            LEFT JOIN
                map_menutemplatelayout ON ms_menutemplatedetail.menuID = map_menutemplatelayout.menuID AND ms_menutemplatedetail.menuTemplateID = map_menutemplatelayout.menuTemplateID
            LEFT JOIN
                lk_menusize ON map_menutemplatelayout.menuSizeID = lk_menusize.menuSizeID
            LEFT JOIN
                ms_menutemplatehead ON ms_menutemplatedetail.menuTemplateID = ms_menutemplatehead.menuTemplateID
            LEFT JOIN
                ms_menutemplatecategory ON ms_menucategory.menuCategoryID = ms_menutemplatecategory.menuCategoryID
            LEFT JOIN
                ms_menutemplatecategorydetail ON ms_menucategorydetail.ID = ms_menutemplatecategorydetail.menuCategoryDetailID
            LEFT JOIN (
				SELECT
					menuTemplateID,
					menuID,
					GROUP_CONCAT(dayID, '') AS dayID
				FROM
					ms_menutemplatedetailday
				GROUP BY
					menuTemplateID, menuID
            ) as templateDetailDay
            on  templateDetailDay.menuTemplateID = ms_menutemplatedetail.menuTemplateID and templateDetailDay.menuID = ms_menutemplatedetail.menuID
            WHERE
                ms_menu.flagActive = 1
            ";

        if ($menuTemplateID) {
            $queryMenuArray .= "
                AND ms_menutemplatecategory.menuTemplateID = $menuTemplateID
                AND ms_menutemplatedetail.menuTemplateID = $menuTemplateID
                AND ms_menutemplatedetail.flagActive = 1
                AND ms_menutemplatecategorydetail.menuTemplateID = $menuTemplateID
            ORDER BY ms_menutemplatecategory.orderID , ms_menucategory.orderID , ms_menutemplatecategorydetail.orderID , ms_menucategorydetail.orderID , ms_menutemplatedetail.orderID";
        } else {
            $queryMenuArray .= "
            AND ms_menutemplatedetail.flagActive = 1
            ORDER BY ms_menucategory.orderID";
        }

        $menuAsArray = $connection->createCommand($queryMenuArray)->queryAll();

        $tempMenuID = [];
        $newMenuAsArray = [];
        $tempStationID = [];
        $menuCategoryAsArray = [];
        $menuCategoryDetailAsArray = [];
        foreach ($menuAsArray as $menu) {
            if (!isset($newMenuAsArray[$menu['menuID']])) {
                $tempMenuID[] = $menu['menuID'];
                $newMenuAsArray[$menu['menuID']] = $menu;
            }

            if (!isset($menuCategoryDetailAsArray[$menu['menuCategoryDetailID']])) {
                $menuCategoryDetailAsArray[$menu['menuCategoryDetailID']] = $menu;
            }
            if (!isset($menuCategoryAsArray[$menu['menuCategoryID']])) {
                $menuCategoryAsArray[$menu['menuCategoryID']] = $menu;
            }
            if ($menu['masterStationID'] && !in_array($menu['masterStationID'], $tempStationID)) {
                $tempStationID[] = $menu['masterStationID'];
            }
        }

        $menuIDs = implode(", ", $tempMenuID);

        //@notes: retrives special price menu and define in array
        $querySpecialPrice = "
            SELECT 
                *,
                ms_specialpricemenu.ID AS specialPriceMenuID,
                ms_specialpricemenu.price AS specialPrice,
                ms_specialpricetime.ID AS specialPriceTimeID
            FROM
                ms_specialpricemenu
            INNER JOIN
                ms_menu ON ms_specialpricemenu.menuID = ms_menu.menuID
            INNER JOIN
                ms_specialpricehead ON ms_specialpricemenu.specialPriceID = ms_specialpricehead.specialPriceID
            LEFT JOIN
                ms_specialpricetime ON ms_specialpricehead.specialPriceID = ms_specialpricetime.specialPriceID
            INNER JOIN
                ms_specialpriceday ON ms_specialpricehead.specialPriceID = ms_specialpriceday.specialPriceID
            INNER JOIN
                ms_menutemplatehead ON ms_specialpricehead.menuTemplateID = ms_menutemplatehead.menuTemplateID
            WHERE
                ms_specialpricehead.flagActive = 1
                AND (CURRENT_DATE() BETWEEN startDate AND endDate)
                AND (dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END)
                AND ((startTime IS NULL AND endTime IS NULL)
                    OR (TIME(NOW()) BETWEEN startTime AND endTIme))
                AND ms_menu.menuID IN ($menuIDs)
                ";
        
        if ($menuTemplateID) {
            $querySpecialPrice .= " AND ms_menutemplatehead.menuTemplateID = $menuTemplateID";
        }

        $specialPriceQuery = $connection->createCommand($querySpecialPrice)->queryAll();

        $specialPriceIdxByMenu = [];
        $specialPriceTimeAsArray = [];
        foreach ($specialPriceQuery as $specialPriceMenu) {
            $specialPriceIdxByMenu[$specialPriceMenu['menuID']] = $specialPriceMenu;
            $specialPriceTimes = [];
            foreach ($specialPriceQuery as $specialPriceTime) {
                if ($specialPriceTime['specialPriceTimeID'] &&
                    $specialPriceMenu['specialPriceID'] == $specialPriceTime['specialPriceID'] &&
                    !isset($specialPriceTimes[$specialPriceMenu['menuID']])) {
                    $specialPriceTimes[$specialPriceMenu['menuID']] = (object) array(
                        'ID' => $specialPriceTime['specialPriceTimeID'],
                        'specialPriceID' => $specialPriceTime['specialPriceID'],
                        'startTime' => $specialPriceTime['startTime'],
                        'endTime' => $specialPriceTime['endTime'], 
                    );
                }
            }
            $specialPriceTimeAsArray[$specialPriceMenu['menuID']][] = (object) array(
                "endTime" => $specialPriceMenu['endTime'],
                "ID" => (float) $specialPriceMenu['specialPriceMenuID'],
                "menuID" => (float) $specialPriceMenu['menuID'],
                "price" => (float) $specialPriceMenu['specialPrice'],
                "specialPriceID" => (float) $specialPriceMenu['specialPriceID'],
                "startTime" => $specialPriceMenu['startTime'],
                "times" => $specialPriceTimes
            );
        }

        //@notes: retrives menu icon and define in array
        $mapMenuIconAsArray = $connection->createCommand("
            SELECT * FROM map_menuicon
            LEFT JOIN ms_menu ON map_menuicon.menuID = ms_menu.menuID
            LEFT JOIN ms_menuicon ON map_menuicon.menuIconID = ms_menuicon.menuIconID
            WHERE map_menuicon.menuID IN ($menuIDs)
        ")->queryAll();

        //@notes: retrives menu group and define in array
        $menuGroupsAsArray = $connection->createCommand("
            SELECT * FROM ms_menugroup
            WHERE ms_menugroup.menuID IN ($menuIDs)
            AND ms_menugroup.flagActive = 1
            ORDER BY ms_menugroup.orderID ASC, ms_menugroup.menuGroupID ASC
        ")->queryAll();

        $tempMenuGroupID = [];
        foreach ($menuGroupsAsArray as $menuGroup) {
            $tempMenuGroupID[] = $menuGroup['menuGroupID'];
        }

        //@notes: retrives menu package and define in array
        $menuPackageIdxMenuGroup = [];
        if (count($tempMenuGroupID) > 0) {
            $menuGroupIDs = implode(", ", $tempMenuGroupID);
                $menuPackageAsArray = $connection->createCommand("
                    SELECT * FROM ms_menupackage
                    WHERE ms_menupackage.menuGroupID IN ($menuGroupIDs)
                    AND ms_menupackage.flagActive = 1
                    ORDER BY ms_menupackage.orderID ASC, ms_menupackage.ID ASC
            ")->queryAll();

            foreach ($menuPackageAsArray as $menuPackage) {
                $menuPackageIdxMenuGroup[$menuPackage['menuGroupID']][] = $menuPackage;
            }
        }
    
        //@notes: retrives menu extra and define in array
        $menuExtrasAsArray = $connection->createCommand("
            SELECT * FROM ms_menuextra
            WHERE ms_menuextra.menuID IN ($menuIDs)
            AND ms_menuextra.flagActive = 1
            ORDER BY ms_menuextra.orderID ASC, ms_menuextra.menuExtraID ASC
        ")->queryAll();

        $menuExtraIdxByMenuID = [];
        foreach ($menuExtrasAsArray as $menuExtra) {
            $menuExtraIdxByMenuID[$menuExtra['menuID']] = $menuExtra;
        }

        //@notes: retrives station and define in array
        $stationAsArray = [];
        if (count($tempStationID) > 0) {
            $stationIDs = implode(", ", $tempStationID);
            $stationAsArray = $connection->createCommand("
                SELECT * FROM ms_station
                WHERE ms_station.stationID IN ($stationIDs)
                AND ms_station.branchID = $branchID
            ")->queryAll();
        }

        $m = 0;
        foreach ($menuCategoryAsArray as $category) {
            $flagInclusive = $category['flagInclusive'];
            $n = 0;
            $newCategoryDetailData = [];
            foreach ($menuCategoryDetailAsArray as $categoryDetail) {
                if ($categoryDetail['menuCategoryID'] == $category['menuCategoryID']) {
                    $o = 0;
                    $menus = [];
                    foreach ($newMenuAsArray as $menu) {
                        if ($menu['menuCategoryDetailID'] == $categoryDetail['categoryDetailID']) {
                            $flagShowEzoBm = $menu['showEzoBm'] != null ? (int) $menu['showEzoBm'] : null;
                            $flagShowEzoTd = $menu['showEzoTd'] != null ? (int) $menu['showEzoTd'] : null;
        
                            if ($showKiosk === 0) {
                                $showMenu = 1;
                            } else {
                                if ($flagShowEzoBm === 0) {
                                    $showMenu = 0;
                                } else {
                                    if ($flagShowEzoTd === 0) {
                                        $showMenu = 0;
                                    } else {
                                        $showMenu = 1;
                                    }
                                }
                            }
        
                            if ($showMenu !== 0) {
                                
                                // @notes: check and takeout menu out of the time
                                if($isShowMenuTime) {
                                    $menuTemplateStartTime = $menu['menuTemplateStartTime'] ? $menu['menuTemplateStartTime'] : '';
                                    $menuTemplateEndTime = $menu['menuTemplateEndTime'] ? $menu['menuTemplateEndTime'] : '';
                                    $menuTemplateDayID = $menu['dayID'] ? $menu['dayID'] : '';

                                    $takeOutMenuOutTime = self::checkShowMenuByTimeAndDays($menuTemplateStartTime, $menuTemplateEndTime, $currentTime, $menuTemplateDayID);
                                    if ($takeOutMenuOutTime) {
                                        continue;
                                    }
                                }

                                $menus[$o]['menuCategoryID'] = (float) $category['menuCategoryID'];
                                $menus[$o]['menuCategoryDetailID'] = (float) $menu['menuCategoryDetailID'];
                                $menus[$o]['maxOrderQty'] = (float) $menuCategoryDetail[$menu['menuCategoryDetailID']]['maxOrderQty'];
                                $menus[$o]['menuCategoryDetailCode'] = $menuCategoryDetail[$menu['menuCategoryDetailID']]['menuCategoryDetailCode'];
                                $menus[$o]['menuID'] = (float) $menu['menuID'];
                                $menus[$o]['menuName'] = $menu['menuName'];
                                $menus[$o]['menuShortName'] = $menu['menuShortName'];
                                $menus[$o]['altMenuName'] = $menu['altMenuName'];
                                $menus[$o]['menuCode'] = is_null($menu['menuCode']) ? '' : $menu['menuCode'];
                                $menus[$o]['field2'] = is_null($menu['field2']) ? '' : $menu['field2'];
        
                                // $price = (float) $menu->activeMenuTemplateDetails ? self::getNetPrice($menu->activeMenuTemplateDetails,
                                //         $otherTaxValue, $otherTaxOnVat, $vatValue,
                                //         $salesDecimalSetting, $settingDecimalMode) : $menu->price;
                                $menuFlagTax = isset($menu['flagTax']) ? (int) $menu['flagTax'] : 1;
                                $newOtherTaxValue = $menu['flagOtherTax'] ? $otherTaxValue : 0;
                                $isApplyOtherVat = ($vatSubject === 1 && $menuFlagTax === 2);
                                if ($isApplyOtherVat) {
                                    $newVatValue = $otherVat ? $otherVat : 0;
                                    if (isset($menu['flagLuxuryItem'])) {
                                        $newVatValue = $otherVat ? CalculateTotal::getNotLuxuryVatValue($menu['flagLuxuryItem'], $otherVat) : 0;
                                    }
                                } else {
                                    $newVatValue = $menu['flagTax'] ? $vatValue : 0;
                                }
                                $price = (float) round($menu['menuTemplateMenuID'] ? self::newGetNetPrice($menu['menuTemplatePrice'], $flagInclusive,
                                        $newOtherTaxValue, $otherTaxOnVat, $newVatValue) : $menu['price'], 4);
        
                                $specialPrice = isset($specialPriceIdxByMenu[$menu['menuID']]) ? $specialPriceIdxByMenu[$menu['menuID']]['specialPrice'] : $menu['menuTemplatePrice'];
                                $specialPriceMenuID = isset($specialPriceIdxByMenu[$menu['menuID']]) ? $specialPriceIdxByMenu[$menu['menuID']]['menuID'] : null;
                                $menus[$o]['price'] = $price;
                                $menus[$o]['originalPrice'] = $menu['menuTemplateMenuID'] ? (float) $menu['menuTemplatePrice'] : (float) $menu['price'];
                                // @notes: promotionPrice = Special Price
                                $menus[$o]['promotionPrice'] = (float) $specialPriceMenuID ? self::newGetNetPrice($menu['menuTemplatePrice'], $flagInclusive,
                                        $newOtherTaxValue, $otherTaxOnVat, $newVatValue, $specialPrice) : $price;
                                $displayPrice = $menu['menuTemplateMenuID'] ? $menu['menuTemplatePrice'] : $menu['price'];
                                $mainPrice = $displayPrice;
        
                                $menus[$o]['normalPrice'] = [
                                    'menuID' => (float) $menu['menuID'],
                                    'displayPrice' => 
                                        '(' . number_format($displayPrice,
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator") . ')',
                                    'displayPriceValue' => $displayPrice,
                                    'promotionPrice' => (float)$menus[$o]['price'],
                                    'price' => (float)$menus[$o]['price']
                                ];
        
                                $displayPrice = $specialPriceMenuID ? $specialPrice : $displayPrice;
                                if ($specialPriceMenuID) {
                                    $menus[$o]['isPromotionPrice'] = $specialPrice != $mainPrice ? 1 : 0;
                                } else {
                                    $menus[$o]['isPromotionPrice'] = 0;
                                }
                                $menus[$o]['displayPriceValue'] = (float) $displayPrice;
                                $menus[$o]['displayPrice'] = '(' . number_format($displayPrice,
                                        $salesDecimalSetting,
                                        "$salesDecimalSeparatorSetting",
                                        "$reverseDecimalSeparator") . ')';
                                if ($menu['qty'] <= 0) {
                                    $menus[$o]['displayPrice'] = '(' . number_format($displayPrice,
                                            $salesDecimalSetting,
                                            "$salesDecimalSeparatorSetting",
                                            "$reverseDecimalSeparator") . ')';
                                } else {
                                    $convertionQty = $menu['productDetailMenuID'] ? $menu['convertionQty'] : 1;
                                    $menus[$o]['displayPrice'] = '(' . number_format($displayPrice,
                                            $salesDecimalSetting,
                                            "$salesDecimalSeparatorSetting",
                                            "$reverseDecimalSeparator") . ') [' . intval($menu['qty'] / $convertionQty) . ']';
                                }
                                $menus[$o]['flagTax'] = (float) $menu['flagTax'];
                                $menus[$o]['flagOtherTax'] = (float) $menu['flagOtherTax'];
                                $menus[$o]['zeroValueText'] = $menu['zeroValueText'];
                                $menus[$o]['flagCustomerPrint'] = (float) $menu['flagCustomerPrint'];
                                $checkerStationIDs = $menu['branchMenuID'] ? array_map('intval',
                                        explode(',', $menu['checkerStationID'])) : [];
                                $checkerStations = array_filter($stationAsArray,
                                    function($station) use ($checkerStationIDs) {
                                    return in_array($station['stationID'],
                                        $checkerStationIDs);
                                });
                                $menus[$o]['checkerStationID'] = $checkerStationIDs;
                                $menus[$o]['checkerStationName'] = array_values(array_map(function($v) {
                                        return $v['stationName'];
                                    }, $checkerStations));
                                $stationIDs = $menu['branchMenuID'] ? array_map('intval',
                                        explode(',', $menu['stationID'])) : [];
                                $stations = array_filter($stationAsArray,
                                    function($station) use ($stationIDs) {
                                    return in_array($station['stationID'], $stationIDs);
                                });
                                $menus[$o]['stationID'] = $stationIDs;
                                $menus[$o]['stationName'] = array_values(array_map(function($v) {
                                        return $v['stationName'];
                                    }, $stations));
                                $menus[$o]['qty'] = $menu['branchMenuID'] ? (float) $menu['qty'] : 0;
                                $menus[$o]['imageUrl'] = $menu['menuImage'] ? $imageMenuLoc . $menu['menuImage'] : null;
                                $menus[$o]['description'] = $menu['description'];
                                $menus[$o]['flagSoldOut'] = $menu['branchMenuID'] ? (float) $menu['flagSoldOut'] : 0;
                                $menus[$o]['openPrice'] = (float) $menu['openPrice'];
                                $menus[$o]['specialPrice'] = $specialPriceMenuID ? (float) $specialPrice : null;
                                $menus[$o]['specialPriceTimes'] = isset($specialPriceTimeAsArray[$menu['menuID']]) ? $specialPriceTimeAsArray[$menu['menuID']] : [];
                                $menus[$o]['menuSizeID'] = $menu['menuSizeID'] ? $menu['menuSizeID'] : 0;
                                $menus[$o]['menuSizeName'] = $menu['menuSizeName'] ? $menu['menuSizeName'] : '';
                                $menus[$o]['width'] = $menu['width'] ? $menu['width'] : 1;
                                $menus[$o]['height'] = $menu['height'] ? $menu['height'] : 1;
                                $menus[$o]['posX'] = $menu['posX'] ? $menu['posX'] : 0;
                                $menus[$o]['posY'] = $menu['posY'] ? $menu['posY'] : 0;
                                $menus[$o]['disabled'] = $menu['productDetailMenuID'] ? true : false;
        
                                $menuIcons = [];
                                $z = 0;
        
                                foreach ($mapMenuIconAsArray as $menuIconList) {
                                    if ($menuIconList['menuID'] == $menu['menuID']) {
                                        $menuIcons[$z]['menuIconID'] = $menuIconList ? (float) $menuIconList['menuIconID'] : 0;
                                        $menuIcons[$z]['menuIconName'] = $menuIconList ? $menuIconList['menuIconName'] : '';
                                        $menuIcons[$z]['menuIconUrl'] = $menuIconList ? $imageMenuIconLoc . $menuIconList['menuIconUrl'] : '';
                                        $z++;
                                    }
                                }

                                $newActiveMenuGroups = [];
                                if ($menuGroupsAsArray) {
                                    $newActiveMenuGroups = array_filter($menuGroupsAsArray, function($menuGroup) use ($menuPackageIdxMenuGroup, $menu) {
                                        if (isset($menuPackageIdxMenuGroup[$menuGroup['menuGroupID']]) && $menuGroup['menuID'] == $menu['menuID']) {
                                            return $menuGroup;
                                        }
                                    });
                                }
                                $menus[$o]['flagHasExtras'] = isset($menuExtraIdxByMenuID[$menu['menuID']]) ? true : false;
                                $menus[$o]['flagHasPackages'] = count($newActiveMenuGroups) > 0 ? true : false;
                                $menus[$o]['menuOrderID'] = isset($menu['menuTemplateOrderID']) ? (float) $menu['menuTemplateOrderID'] : (float) $menu['orderID'];
                                $menus[$o]['menuIcons'] = $menuIcons;
                                $menus[$o]['menuPackages'] = [];
                                $menus[$o]['menuExtras'] = [];
                                $menus[$o]['buttonColor'] = $menu['menuBC'];
                                $menus[$o]['buttonTextColor'] = self::defineMenuTextColor($menu['menuBC']);
                                $menus[$o]['flagSeparateTaxCalculation'] = (float) $menu['flagSeparateTaxCalculation'];
                                $menus[$o]['menuTemplateStartTime'] = $isShowMenuTime ? $menu['menuTemplateStartTime'] : null;
                                $menus[$o]['menuTemplateEndTime'] =  $isShowMenuTime ? $menu['menuTemplateEndTime'] : null;
                                $menus[$o]['flagActive'] = (int) $menu['menuFlagActive'];
                                $menus[$o]['flagLuxuryItem'] = isset($menu['flagLuxuryItem']) ? (float) $menu['flagLuxuryItem'] : 0;
                                $o++;
                            }
                        }
                    }
    
                    if ($menus) {
                        array_multisort(
                            array_column($menus, 'menuOrderID'), $menus
                        );
                        $newCategoryDetailData[$n]['ID'] = (float) $categoryDetail['categoryDetailID'];
                        $newCategoryDetailData[$n]['menuCategoryDetailDesc'] = $categoryDetail['menuCategoryDetailDesc'];
                        $newCategoryDetailData[$n]['menuCategoryDetailCode'] = $categoryDetail['menuCategoryDetailCode'];
                        $newCategoryDetailData[$n]['imageUrl'] = $categoryDetail['categoryDetailImage'] ? $imageMenuCategoryDetailLoc . $categoryDetail['categoryDetailImage'] : null;
                        $newCategoryDetailData[$n]['maxOrderQty'] = (float) $categoryDetail['maxOrderQty'];
                        $newCategoryDetailData[$n]['menus'] = $menus;
                        $menuCategoryDetailButtonColor = $categoryDetail['categoryDetailBC'] ? $categoryDetail['categoryDetailBC'] : '#3c8dbc';
                        $newCategoryDetailData[$n]['buttonColor'] = $menuCategoryDetailButtonColor;
                        $newCategoryDetailData[$n]['buttonTextColor'] = self::defineMenuTextColor($menuCategoryDetailButtonColor);
                        $n++;
                    }
                }
            }

            if ($newCategoryDetailData) {
                $menuData[$m]['menuCategoryID'] = (float) $category['menuCategoryID'];
                $menuData[$m]['menuCategoryDesc'] = $category['menuCategoryDesc'];
                $menuData[$m]['menuCategoryDetails'] = $newCategoryDetailData;
                $menuCategoryButtonColor = $category['categoryBC'] ? $category['categoryBC'] : '#3c8dbc';
                $menuData[$m]['buttonColor'] = $menuCategoryButtonColor;
                $menuData[$m]['buttonTextColor'] = self::defineMenuTextColor($menuCategoryButtonColor);
                $m++;
            }
        }
        return $menuData;
    }

    public static function findExtraMenu($visitPurposeID, $menuID) {
        $otherVat = Setting::getOtherVat();
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $menuTemplateID = NULL;
        $otherTaxValue = 0;
        $otherTaxOnVat = 0;
        $vatValue = 0;
        $vatSubject = 0;
        $imageMenuLoc = Url::to('@web/images/menu/', true);

        if ($visitPurposeID) {
            $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $visitPurposeID])->one();
            if ($mapBranchModel) {
                $menuTemplateID = $mapBranchModel->menuTemplateID;
                $otherTaxValue = $mapBranchModel->additionalTaxValue;
                $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
                $vatValue = $mapBranchModel->taxValue;
                $vatSubject = $mapBranchModel->vatSubject;
            }
        }

        $query = Menu::findActive()
            ->with('activeMenuExtras');

        if ($menuTemplateID) {
            $query->innerJoinWith([
                'activeMenuTemplateDetails' => function ($query) use ($menuTemplateID) {
                    $query->innerJoinWith('menuTemplateHead')->andOnCondition([
                        MenuTemplateDetail::tableName() . '.menuTemplateID' => $menuTemplateID
                    ]);
                },
            ]);
        }

        $query->andWhere([
            BranchMenu::tableName() . '.branchID' => $branchID,
            Menu::tableName() . '.menuID' => $menuID
        ]);

        $menu = $query->one();
        $menuExtraArrs = [];
        $m = 0;
        foreach ($menu->activeMenuExtras as $menuExtra) {
            $isApplyOtherVat = ($vatSubject === 1 && (isset($menu->flagTax) && $menu->flagTax === 2));
            if ($isApplyOtherVat) {
                $newVatValue = $otherVat ? $otherVat : 0;
                if (isset($menuExtra->menu->flagLuxuryItem)) {
                    $newVatValue = $otherVat ? CalculateTotal::getNotLuxuryVatValue($menuExtra->menu->flagLuxuryItem, $otherVat) : 0;
                }
            } else {
                $newVatValue = $menu->flagTax ? $vatValue : 0;
            }
            $newOtherTaxValue = $menu->flagOtherTax ? $otherTaxValue : 0;

            $displayPrice = self::getNetPrice($menu->activeMenuTemplateDetails,
                $newOtherTaxValue, $otherTaxOnVat, $newVatValue,
                $salesDecimalSetting, $settingDecimalMode,
                $menuExtra->price);

            $menuExtraArrs[$m]['menuGroupID'] = $menuExtra->menu ? $menuExtra->menu->menuCategoryDetail->menuCategory->menuCategoryID : 0;
            $menuExtraArrs[$m]['menuRefID'] = $menuExtra->menuRefID;
            $menuExtraArrs[$m]['menuGroup'] = $menuExtra->menu ? $menuExtra->menu->menuCategoryDetail->menuCategory->menuCategoryDesc : 'Uncategorized';
            $menuExtraArrs[$m]['maxQty'] = $menuExtra->menuGroup ? (float) $menuExtra->menuGroup->maxQty : 0;
            $menuExtraArrs[$m]['minQty'] = $menuExtra->menuGroup ? (float) $menuExtra->menuGroup->minQty : 0;
            $menuExtraArrs[$m]['groupNotes'] = $menuExtra->menuGroup ? $menuExtra->menuGroup->notes : '';
            $menuExtraArrs[$m]['menuExtraID'] = $menuExtra->menuExtraID;
            $menuExtraArrs[$m]['menuExtraName'] = $menuExtra->menuExtraName;
            $menuExtraArrs[$m]['menuExtraShortName'] = $menuExtra->menuExtraShortName;
            $menuExtraArrs[$m]['price'] = (float) $displayPrice;
            $menuExtraArrs[$m]['originalPrice'] = $menuExtra->price;
            $menuExtraArrs[$m]['displayPrice'] = '(' . number_format($menuExtra->price,
                    $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting",
                    "$reverseDecimalSeparator") . ')';
            $menuExtraArrs[$m]['minExtraQty'] = (float) $menuExtra->minExtraQty;
            $menuExtraArrs[$m]['maxExtraQty'] = (float) $menuExtra->maxExtraQty;
            $menuExtraArrs[$m]['imageUrl'] = ($menuExtra->menu && $menuExtra->menu->imageUrl) ? $imageMenuLoc . $menuExtra->menu->imageUrl : null;
            $menuExtraArrs[$m]['menuNotes'] = $menuExtra->notes;
            $menuExtraArrs[$m]['buttonColor'] = $menuExtra->buttonColor ? $menuExtra->buttonColor : '#F39C12';
            $menuExtraArrs[$m]['buttonTextColor'] = self::defineMenuTextColor($menuExtra->buttonColor ? $menuExtra->buttonColor : '#F39C12');
            $menuExtraArrs[$m]['flagSoldOut'] = $menuExtra->menu ? ($menuExtra->branchMenu ? $menuExtra->branchMenu->flagSoldOut : 0) : 0;
            $menuExtraArrs[$m]['flagTax'] = isset($menuExtra->menu->flagTax) ? $menuExtra->menu->flagTax : $menu->flagTax;
            $menuExtraArrs[$m]['flagLuxuryItem'] = isset($menuExtra->menu->flagLuxuryItem) ? $menuExtra->menu->flagLuxuryItem : 0;
            $m++;
        }

        $menuExtras = [];
        foreach ($menuExtraArrs as $menuExtra) {
            $index = -1;
            $n = 0;
            foreach ($menuExtras as $extra) {
                if ($extra['menuGroupID'] == $menuExtra['menuGroupID']) {
                    $index = $n;
                    break;
                }
                $n++;
            }

            if ($index >= 0) {
                $menuExtras[$index]['extras'][] = [
                    'menuGroupID' => $menuExtra['menuGroupID'],
                    'menuExtraID' => $menuExtra['menuExtraID'],
                    'menuRefID' => $menuExtra['menuRefID'],
                    'menuExtraName' => $menuExtra['menuExtraName'],
                    'menuExtraShortName' => $menuExtra['menuExtraShortName'],
                    'price' => $menuExtra['price'],
                    'originalPrice' => $menuExtra['originalPrice'],
                    'displayPrice' => $menuExtra['displayPrice'],
                    'imageUrl' => $menuExtra['imageUrl'],
                    'notes' => $menuExtra['menuNotes'],
                    'buttonColor' => $menuExtra['buttonColor'] ? $menuExtra['buttonColor'] : '#F39C12',
                    'buttonTextColor' => $menuExtra['buttonTextColor'],
                    'flagSoldOut' => $menuExtra['flagSoldOut'],
                    'flagTax' => $menuExtra['flagTax'],
                    'maxExtraQty' => $menuExtra['maxExtraQty'],
                    'minExtraQty' => $menuExtra['minExtraQty'],
                    'flagLuxuryItem' => isset($menuExtra['flagLuxuryItem']) ? $menuExtra['flagLuxuryItem'] : 0
                ];
            } else {
                $menuExtras[] = [
                    'menuGroupID' => $menuExtra['menuGroupID'],
                    'menuGroup' => $menuExtra['menuGroup'],
                    'minQty' => (float) $menuExtra['minQty'],
                    'maxQty' => (float) $menuExtra['maxQty'],
                    'notes' => $menuExtra['groupNotes'],
                    'extras' => [
                        [
                            'menuGroupID' => $menuExtra['menuGroupID'],
                            'menuExtraID' => $menuExtra['menuExtraID'],
                            'menuRefID' => $menuExtra['menuRefID'],
                            'menuExtraName' => $menuExtra['menuExtraName'],
                            'menuExtraShortName' => $menuExtra['menuExtraShortName'],
                            'price' => (float) $menuExtra['price'],
                            'originalPrice' => (float) $menuExtra['originalPrice'],
                            'displayPrice' => $menuExtra['displayPrice'],
                            'imageUrl' => $menuExtra['imageUrl'],
                            'notes' => $menuExtra['menuNotes'],
                            'buttonColor' => $menuExtra['buttonColor'] ? $menuExtra['buttonColor'] : '#F39C12',
                            'buttonTextColor' => $menuExtra['buttonTextColor'],
                            'flagSoldOut' => $menuExtra['flagSoldOut'],
                            'flagTax' => $menuExtra['flagTax'],
                            'maxExtraQty' => $menuExtra['maxExtraQty'],
                            'minExtraQty' => $menuExtra['minExtraQty'],
                            'flagLuxuryItem' => isset($menuExtra['flagLuxuryItem']) ? $menuExtra['flagLuxuryItem'] : 0
                        ]
                    ]
                ];
            }
        }
        return $menuExtras;
    }

    public static function findMenuPackage($visitPurposeID, $menuID)
    {
        $branchID = Setting::getCurrentBranch();
        $settings = Setting::getPrintingSettings();
        $otherVat = Setting::getOtherVat();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
        $menuTemplateID = NULL;
        $otherTaxValue = 0;
        $otherTaxOnVat = 0;
        $vatValue = 0;
        $menuPackages = [];
        $vatSubject = 0;
        $imageMenuLoc = Url::to('@web/images/menu/', true);

        if ($visitPurposeID) {
            $mapBranchModel = MapBranchVisitPurpose::findOne(['visitPurposeID' => $visitPurposeID]);
            if ($mapBranchModel) {
                $menuTemplateID = $mapBranchModel->menuTemplateID;
                $otherTaxValue = $mapBranchModel->additionalTaxValue;
                $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
                $vatValue = $mapBranchModel->taxValue;
                $vatSubject = $mapBranchModel->vatSubject;
            }
        }

        $query = Menu::findActive()
            ->with('activeMenuGroups.activeMenuPackages');

        if ($menuTemplateID) {
            $query->innerJoinWith([
                'activeMenuTemplateDetails' => function ($query) use ($menuTemplateID) {
                    $query->innerJoinWith('menuTemplateHead')->andOnCondition([
                        MenuTemplateDetail::tableName() . '.menuTemplateID' => $menuTemplateID
                    ]);
                },
            ]);

            $query->joinWith(['activeMenuGroups.activeMenuPackages.mapMenuTemplatePackage' => function ($query) use ($menuTemplateID) {
                $query->andOnCondition([
                    'map_menutemplatepackage.menuTemplateID' => $menuTemplateID
                ]);
            }]);
        }

        $query->andWhere([
            BranchMenu::tableName() . '.branchID' => $branchID,
            Menu::tableName() . '.menuID' => $menuID
        ]);

        $menu = $query->one();
        $i = 0;
        foreach ($menu->activeMenuGroups as $menuGroup)
        {
            if ($menuGroup->activeMenuPackages)
            {
                $menuPackages[$i]['orderID'] = $menuGroup->orderID;
                $menuPackages[$i]['menuGroupID'] = $menuGroup->menuGroupID;
                $menuPackages[$i]['menuGroup'] = $menuGroup->menuGroup;
                $menuPackages[$i]['minQty'] = (float) $menuGroup->minQty;
                $menuPackages[$i]['maxQty'] = (float) $menuGroup->maxQty;
                $menuPackages[$i]['notes'] = $menuGroup->notes;

                $menuPackagesDetail = [];
                $j = 0;
                $packagesItems = $menuGroup->activeMenuPackages;
                foreach ($packagesItems as $item)
                {
                    $menuFlagTax = isset($menu->flagSeparateTaxCalculation) && $menu->flagSeparateTaxCalculation === 0 ? $menu->flagTax : $item->menu->flagTax;
                    $menuPackagePrice = !is_null($item->mapMenuTemplatePackage) && !is_null($item->mapMenuTemplatePackage->price) ? $item->mapMenuTemplatePackage->price : $item->price;
                    $isApplyOtherVat = ($vatSubject === 1 && (isset($menuFlagTax) && $menuFlagTax === 2));
                    if ($isApplyOtherVat) {
                        $newVatValue = $otherVat ? $otherVat : 0;
                        if (isset($item->menu->flagLuxuryItem)) {
                            $newVatValue = $otherVat ? CalculateTotal::getNotLuxuryVatValue($item->menu->flagLuxuryItem, $otherVat) : 0;
                        }
                    } else {
                        $newVatValue = $menuFlagTax ? $vatValue : 0;
                    }
                    $newOtherTaxValue = $menu->flagOtherTax ? $otherTaxValue : 0;
                    $menuCategoryID = $item->menu->menuCategoryDetail->menuCategoryID ? $item->menu->menuCategoryDetail->menuCategoryID : 0;
                    $menuCategoryDetailID = $item->menu->menuCategoryDetail->ID ? $item->menu->menuCategoryDetail->ID : 0;

                    $menuPackagesDetail[$j]['orderID'] = $item->orderID;
                    $menuPackagesDetail[$j]['menuGroupID'] = $item->menuGroupID;
                    $menuPackagesDetail[$j]['menuID'] = $item->menuID;
                    $menuPackagesDetail[$j]['menuName'] = $item->menu->menuName;
                    $menuPackagesDetail[$j]['menuShortName'] = $item->menu->menuShortName;
                    $menuPackagesDetail[$j]['menuCode'] = $item->menu->menuCode ? $item->menu->menuCode : '';
                    $menuPackagesDetail[$j]['imageUrl'] = $item->menu->imageUrl ? $imageMenuLoc . $item->menu->imageUrl : null;
                    $displayPrice = self::getNetPrice($menu->activeMenuTemplateDetails,
                            $newOtherTaxValue, $otherTaxOnVat,
                            $newVatValue, $salesDecimalSetting,
                            $settingDecimalMode, $menuPackagePrice);
                    $menuPackagesDetail[$j]['qty'] = $item->branchMenu ? $item->branchMenu->qty : 0;
                    $menuPackagesDetail[$j]['price'] = (float) $displayPrice;
                    $menuPackagesDetail[$j]['displayPrice'] = '' . number_format($menuPackagePrice,
                            $salesDecimalSetting,
                            "$salesDecimalSeparatorSetting",
                            "$reverseDecimalSeparator") . '';
                    $menuPackagesDetail[$j]['displayPriceValue'] = (float) $menuPackagePrice;
                    $menuPackagesDetail[$j]['flagTax'] = $menuFlagTax;
                    $menuPackagesDetail[$j]['flagOtherTax'] = $item->menu->flagOtherTax;
                    $menuPackagesDetail[$j]['flagDefault'] = $item->flagDefault;
                    $menuPackagesDetail[$j]['flagSoldOut'] = $item->menu ? ($item->menu->branchMenu ? $item->menu->branchMenu->flagSoldOut : 0) : 0;
                    $menuPackagesDetail[$j]['flagActive'] = $item->flagActive;
                    $menuPackagesDetail[$j]['menuCategoryID'] = $menuCategoryID;
                    $menuPackagesDetail[$j]['menuCategoryDetailID'] = $menuCategoryDetailID;
                    $menuPackagesDetail[$j]['flagCustomerPrint'] = $item->menu->flagCustomerPrint;
                    $menuPackagesDetail[$j]['flagLuxuryItem'] = isset($item->menu->flagLuxuryItem) ? $item->menu->flagLuxuryItem : 0;
                    $j++;
                }
                $menuPackages[$i]['packages'] = $menuPackagesDetail;
                $i++;
            }
        }
        return $menuPackages;
    }

    public static function findActiveMenuRecommendation($visitPurposeID = null) {
        $menuTemplateID = null;
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $visitPurposeID])->one();
        if ($mapBranchModel) {
            $menuTemplateID = $mapBranchModel->menuTemplateID;
        }

        $query = MenuRecommendationDetail::findActive();
        if ($menuTemplateID) {
            $query->innerJoinWith([
                'activeMenuTemplateDetails' => function ($query) use ($menuTemplateID) {
                    $query->innerJoinWith('menuTemplateHead')->andOnCondition([
                        MenuTemplateDetail::tableName() . '.menuTemplateID' => $menuTemplateID
                    ]);
                },
            ])
            ->andWhere([MenuRecommendationHead::tableName() . '.menuTemplateID' => $menuTemplateID]);
        }

        $menuRecommendationDetailAsArray = $query->all();

        $menuData = [];
        $menuRecommendationGroup = [];
        foreach ($menuRecommendationDetailAsArray as $menu) {
            $mrg = $menu->menuRecommendationGroup;
                
            $j = 0;
            $menus = [];
            foreach ($menuRecommendationDetailAsArray as $menu) {
                if (isset($menu->activeMenuTemplateDetails) && $menu->activeMenuTemplateDetails[0]->flagShowEzo == 1) {
                    if ($menu->menuRecommendationGroupID == $mrg->menuRecommendationGroupID) {
                        $menus[$j]['menuRecommendationGroupID'] = $menu->menuRecommendationGroupID;
                        $menus[$j]['menuRecommendationID'] = $menu->menuRecommendationID;
                        $menus[$j]['menuOrderID'] = $menu->orderID;
                        $menus[$j]['menuID'] = $menu->menuID;
                        $j++;
                    }
                }
            }

            if ($menus) {
                array_multisort(
                    array_column($menus, 'menuOrderID'), $menus
                );
                $menuRecommendationGroup[$mrg->menuRecommendationGroupID]['menuRecommendationGroupID'] = $mrg->menuRecommendationGroupID;
                $menuRecommendationGroup[$mrg->menuRecommendationGroupID]['menuRecommendationID'] = $mrg->menuRecommendationGroupID;
                $menuRecommendationGroup[$mrg->menuRecommendationGroupID]['recommendationGroup'] = $mrg->recommendationGroup;
                $menuRecommendationGroup[$mrg->menuRecommendationGroupID]['menuRecommendationDetail'] = $menus;
            }
            $menuData = array_values($menuRecommendationGroup);
        }

        return $menuData;
    }

    private static function getNetPrice($menuTemplateDetails, $otherTaxValue, $otherTaxOnVat, $vatValue, $salesDecimalSetting, $settingDecimalMode, $price = null) {
        $result = 0;
        if ($menuTemplateDetails) {
            $applyPrice = is_null($price) ? $menuTemplateDetails[0]->price : $price;
            if ($menuTemplateDetails[0]->menuTemplateHead->flagInclusive == MenuTemplateHead::INCLUSIVE_YES) {
                if ($otherTaxOnVat) {
                    $result = ($applyPrice * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
                } else {
                    $result = ($applyPrice * 100 / (100 + $vatValue + $otherTaxValue));
                }
            } else {
                $result = $applyPrice;
            }
        }

        return $result;
    }

    private static function newGetNetPrice($menuTemplatePrice, $flagInclusive, $otherTaxValue, $otherTaxOnVat, $vatValue, $price = null) {
        $result = 0;
        if ($menuTemplatePrice) {
            $applyPrice = is_null($price) ? $menuTemplatePrice : $price;
            if ($flagInclusive) {
                if ($otherTaxOnVat) {
                    $result = ($applyPrice * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
                } else {
                    $result = ($applyPrice * 100 / (100 + $vatValue + $otherTaxValue));
                }
            } else {
                $result = $applyPrice;
            }
        }

        return $result;
    }

    public static function getNetPriceSpecialPrice($visitPurposeID, $price) {
        $settings = Setting::getPrintingSettings();
        $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
        $settingDecimalMode = isset($settings['Sales Decimal Mode']) ? $settings['Sales Decimal Mode'] : 'DOWN';
        $otherTaxValue = 0;
        $otherTaxOnVat = 0;
        $vatValue = 0;
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $visitPurposeID])->one();
        if ($mapBranchModel) {
            $otherTaxValue = $mapBranchModel->additionalTaxValue;
            $otherTaxOnVat = $mapBranchModel->flagOtherTaxVat;
            $vatValue = $mapBranchModel->taxValue;
        }

        $result = 0;
        if ($otherTaxOnVat) {
            $result = ($price * 100 / (100 + $vatValue) * 100 / (100 + $otherTaxValue));
        } else {
            $result = ($price * 100 / (100 + $vatValue + $otherTaxValue));
        }

        return $result;
    }

    public static function findMenu($visitPurposeID, $menuID) {
        $mapBranchModel = MapBranchVisitPurpose::find()->where(['visitPurposeID' => $visitPurposeID])->one();
        if ($mapBranchModel) {
            $menuTemplateID = $mapBranchModel->menuTemplateID;
            $menuTemplateDetailModel = MenuTemplateDetail::find()
                ->andWhere(['menuTemplateID' => $menuTemplateID, 'menuID' => $menuID])
                ->one();
            if (!$menuTemplateDetailModel) {
                return false;
            }

            return true;            
        } else {
            return false;
        }
    }

    public static function findMenuAvailableInPOS($salesMenu) {
      $menuIDs = array_column($salesMenu, 'menuID');

      $menuNotAvailableInPOS = [];
      $menuAvailableInPOS = Menu::find()
            ->where(['IN', 'menuID', $menuIDs])
            ->asArray()
            ->indexBy('menuID')
            ->all();

      foreach ($salesMenu as $sm) {
          if (!isset($menuAvailableInPOS[$sm['menuID']])) {
            $menuNotAvailableInPOS[] = [
              'menuID' => $sm['menuID'],
              'menuName' => $sm['menuName']
            ];
          }
      }

      return $menuNotAvailableInPOS;
    }

    public static function defineMenuTextColor($hexa) {
		$color = str_replace('#', '', $hexa);
		$hex = $color; //Bg color in hex, without any prefixing #!
		$r = hexdec(substr($hex,0,2));
		$g = hexdec(substr($hex,2,2));
		$b = hexdec(substr($hex,4,2));
		$average = 459;
		$ratioRgb = $r + $g + $b;
		if ($ratioRgb > $average) {
		    return '#333';
		} else {
		    return '#fff';
		}
    }

    public static function checkShowMenuByTimeAndDays(string $menuTemplateStartTime, string $menuTemplateEndTime, string $currentTime, string $dayID){

        $isValidate = false;
        if($menuTemplateStartTime && $currentTime < $menuTemplateStartTime){
            $isValidate = true;
        }

        if($menuTemplateEndTime && $currentTime > $menuTemplateEndTime){
            $isValidate = true;
        }
       
        $dayNameID = date('w', strtotime($currentTime));
        $avalaibleDays = $dayID ? explode(',', $dayID) : [];
        if( $dayID && !in_array($dayNameID, $avalaibleDays) ){
            $isValidate = true;
        }

        return $isValidate;
    }

}
