<?php

namespace app\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Query;

/**
 * This is the model class for table "ms_branchmenu".
 *
 * @property int $ID
 * @property int $branchID
 * @property int $menuID
 * @property int $checkerStationID
 * @property int $stationID
 * @property int $qty
 * @property int $flagSoldOut
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * 
 * @property Station $activeStation
 * @property Station $activeCheckerStation
 */
class BranchMenu extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_branchmenu';
    }

    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['createdDate'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdBy'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['editedBy'],
                ],
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['branchID', 'menuID', 'flagSoldOut', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['branchID', 'menuID', 'flagSoldOut', 'flagActive', 'flagShowEzo', 'qty'], 'integer'],
            [['ID', 'checkerStationID', 'stationID', 'flagShowEzo', 'createdDate', 'editedDate', 'syncDate'], 'safe'],
            [['createdBy', 'editedBy'], 'string', 'max' => 100],
            [['qty'], 'validateQty']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'branchID' => 'Branch ID',
            'menuID' => 'Menu ID',
            'checkerStationID' => 'Checker Station ID',
            'stationID' => 'Station ID',
            'qty' => 'Quantity',
            'flagSoldOut' => 'Flag Sold Out',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function getActiveStation() {
        return $this->hasOne(Station::class,
                    ['branchID' => 'branchID', 'stationID' => 'stationID'])
                ->andOnCondition([Station::tableName() . '.flagActive' => 1]);
    }

    public function getActiveCheckerStation() {
        return $this->hasOne(Station::class,
                    ['branchID' => 'branchID', 'stationID' => 'checkerStationID'])
                ->andOnCondition([Station::tableName() . '.flagActive' => 1]);
    }

    public function getActiveStations() {
        return $this->hasMany(Station::class,
                    ['branchID' => 'branchID'])
                ->andOnCondition([Station::tableName() . '.flagActive' => 1]);
    }

    public function getMenu()
    {
        return $this->hasOne(Menu::className(), ['menuID' => 'menuID']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        
        $this->syncDate = null;

        return true;
    }
    
    public static function syncUpdate($ID, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            BranchMenu::updateAll([
                'syncDate' => $syncDate
                ],
                ['AND', ['branchID' => $branchID], ['ID' => $ID]
            ]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

    public function validateQty($attribute) {
        if ($this->$attribute < 0) {
            $menuModel = Menu::find()->andWhere(['menuID' => $this->menuID])->one();
            if ($menuModel) {
                $this->addError($attribute,
                    $menuModel->menuShortName . ' is not sufficient');
            }
        }
    }

    public static function getBranchMenuMapQty() {
        $mapArr = [];

        foreach (BranchMenu::find()->all() as $detail) {
            $mapArr[$detail->menuID] = $detail->qty;
        }

        return $mapArr;
    }

    public static function applyBranchMenuFilters($query, $showLimitInfo, $showSoldOutInfo) {

        if ($showLimitInfo) {
            $query->andWhere(['>', BranchMenu::tableName() . '.qty', 0]);
        }
    
        if ($showSoldOutInfo) {
            $query->andWhere([BranchMenu::tableName() . '.flagSoldOut' => 1]);
        }

    }

    public static function findActiveBranchMenu($showLimitInfo = 0, $showSoldOutInfo = 0, $showBranchMenu = 0) {
        $menuData = [];
        $i = 0;

        $menuCategoryDetail = (new Query)
                    ->select(['*'])
                    ->from(MenuCategoryDetail::tableName())
                    ->indexBy('ID')
                    ->all();
        
        $query = MenuCategory::findActive(null)
            ->select([
                'ms_menucategory.menuCategoryID',
                'ms_menucategory.menuCategoryDesc',
                'ms_menutemplatecategory.orderID',
                'ms_menucategory.orderID',
                'ms_menutemplatecategorydetail.orderID',
                'ms_menucategorydetail.orderID',
                'b.orderID'
            ]);
        $query->distinct();
        $query->innerJoinWith([
            'menuCategoryDetails.activeBranchMenus' => function($query) use ($showLimitInfo, $showSoldOutInfo) {
                SELF::applyBranchMenuFilters($query, $showLimitInfo, $showSoldOutInfo);
            }
        ])
        ->with('menuCategoryDetails.activeBranchMenus.branchMenu.activeStations')
        ->with('menuCategoryDetails.activeBranchMenus.activeMenuGroups')
        ->with('menuCategoryDetails.activeBranchMenus.activeMenuTemplateDetails.menuTemplateHead');

        $branchMenuModel = $query->all();
        $branchMenuDetail = (new Query)
            ->select(['*'])
            ->from(BranchMenuDetail::tableName())
            ->indexBy('menuID')
            ->all();
        foreach($branchMenuModel as $branchMenu) {
            $categoryDetailsData = [];
            $j = 0;
            $categoryDetailsModel = $branchMenu->menuCategoryDetails;
            foreach ($categoryDetailsModel as $categoryDetail) {
                $menusModel = $categoryDetail->activeBranchMenus;
                $menus = [];
                $k = 0;
                foreach ($menusModel as $menu) {
                    if ($menu->activeMenuTemplateDetails) {
                        $flagShowEzoBm = $menu->branchMenu->flagShowEzo;
                        $flagShowEzoTd = $menu->activeMenuTemplateDetails[0]->flagShowEzo;
                        
                        if ($showBranchMenu === 0) {
                            $showMenu = 1;
                        }else{
                            if ($flagShowEzoBm === 0) {
                                $showMenu = 0;
                            }else{
                                if ($flagShowEzoTd === 0) {
                                    $showMenu = 0;
                                }else{
                                    $showMenu = 1;
                                }
                            }
                        }

                        if ($showMenu !== 0) {
                            $menus[$k]['menuCategoryID'] = $branchMenu->menuCategoryID;
                            $menus[$k]['menuCategoryDetailID'] = $menu->menuCategoryDetailID;
                            $menus[$k]['menuCategoryDetailCode'] = $menuCategoryDetail[$menu->menuCategoryDetailID]['menuCategoryDetailCode'];
                            $menus[$k]['menuID'] = $menu->menuID;
                            $menus[$k]['menuName'] = $menu->menuName;
                            $menus[$k]['menuShortName'] = $menu->menuShortName;
                            $menus[$k]['menuCode'] = is_null($menu->menuCode) ? '' : $menu->menuCode;
                            $checkerStationIDs = $menu->branchMenu ? array_map('intval',
                                    explode(',', $menu->branchMenu->checkerStationID)) : [];
                            $checkerStations = array_filter($menu->branchMenu->activeStations,
                                function($station) use ($checkerStationIDs) {
                                return in_array($station->stationID,
                                    $checkerStationIDs);
                            });
                            $menus[$k]['checkerStationID'] = $checkerStationIDs;
                            $menus[$k]['checkerStationName'] = array_values(array_map(function($v) {
                                    return $v->stationName;
                                }, $checkerStations));
                            $stationIDs = $menu->branchMenu ? array_map('intval',
                                    explode(',', $menu->branchMenu->stationID)) : [];
                            $stations = array_filter($menu->branchMenu->activeStations,
                                function($station) use ($stationIDs) {
                                return in_array($station->stationID, $stationIDs);
                            });
                            $menus[$k]['stationID'] = $stationIDs;
                            $menus[$k]['stationName'] = array_values(array_map(function($v) {
                                    return $v->stationName;
                                }, $stations));
                            $convertionQty = $menu->productDetailMenu ? $menu->productDetailMenu->convertionQty : 1;
                            $menus[$k]['qty'] = $menu->branchMenu ? ($menu->productDetailMenu ? intval($menu->branchMenu->qty / $convertionQty) : $menu->branchMenu->qty) : 0;
                            $menus[$k]['flagSoldOut'] = $menu->branchMenu ? $menu->branchMenu->flagSoldOut : 0;
                            $menus[$k]['readyToSell'] = $menu->productDetailMenu ? 1 : 0;
                            $menus[$k]['flagNewMenu'] = (empty($branchMenuDetail) || isset($branchMenuDetail[$menu->menuID])) ? 0 : 1;
                            $k++;
                        }
                    }
                }

                if ($menus) {
                    array_multisort(
                        array_column($menus, 'menuName'), $menus
                    );
                    $categoryDetailsData[$j]['ID'] = $categoryDetail->ID;
                    $categoryDetailsData[$j]['menuCategoryDetailDesc'] = $categoryDetail->menuCategoryDetailDesc;
                    $categoryDetailsData[$j]['menuCategoryDetailCode'] = $categoryDetail->menuCategoryDetailCode;
                    $categoryDetailsData[$j]['menus'] = $menus;

                    $j++;
                }
            }

            if ($categoryDetailsData) {
                $menuData[$i]['menuCategoryID'] = $branchMenu->menuCategoryID;
                $menuData[$i]['menuCategoryDesc'] = $branchMenu->menuCategoryDesc;
                $menuData[$i]['menuCategoryDetails'] = $categoryDetailsData;

                $i++;
            }
        }
        return $menuData;
    }
}
