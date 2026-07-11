<?php

namespace app\models\forms;

use app\models\Menu;
use app\models\SalesHead;
use app\models\SalesInfo;
use app\models\SalesMenu;
use app\models\SalesMenuCompletion;
use app\models\SalesProcessMenu;
use app\models\Setting;
use app\models\ShiftLog;
use app\models\Station;
use app\models\VisitPurpose;
use app\models\Branch;
use app\models\SalesPayment;
use app\models\EsoPickupOrder;
use yii\httpclient\Client;
use yii\base\Exception;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * @property string $salesNum
 * @property int $salesMenuID
 * @property int $qty
 * @property string $errorMessage
 * 
 */
class OrderCompletion extends Model {

    public $salesNum;
    public $salesMenuID;
    public $qty;
    public $errorMessage;
    private $maxQty;
    public $viewMode;
    public $completedDate;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'salesMenuID', 'qty'], 'required'],
            [['qty', 'salesMenuID', 'viewMode'], 'number'],
            [['salesNum'], 'string', 'max' => 20],
            [['qty'], 'validateQty'],
        ];
    }

    public function validateQty($attribute) {
        $salesMenuModel = SalesMenu::find()
            ->where([
                "tr_salesmenu.salesNum" => $this->salesNum,
                "tr_salesmenu.ID" => $this->salesMenuID
            ])
            ->one();
        
        if ($salesMenuModel) {
            if ($salesMenuModel->menuGroupID != 0) {
                $totalQty = SalesMenu::find()   
                    ->leftJoin(SalesMenu::tableName(). ' b', 'tr_salesmenu.menuRefID = b.ID')
                    ->where([
                        "tr_salesmenu.salesNum" => $this->salesNum,
                        "tr_salesmenu.ID" => $this->salesMenuID
                    ])
                    ->sum("tr_salesmenu.qty * b.qty");
            } else {
                $totalQty = SalesMenu::find()                    
                    ->where([
                        "salesNum" => $this->salesNum,
                        "ID" => $this->salesMenuID
                    ])
                    ->sum("qty");
            }
        }

        $finishedQty = SalesMenuCompletion::find()
                ->where([
                    "salesNum" => $this->salesNum,
                    "salesMenuID" => $this->salesMenuID,
                    "typeID" => $this->viewMode
                ])
                ->sum("qty");

        if (!$finishedQty) {
            $finishedQty = 0;
        }

        $this->maxQty = $totalQty - $finishedQty;
        
        $QTY = number_format($this->qty, 4);
        $MAXQTY = number_format($this->maxQty, 4);

        if ($QTY > $MAXQTY) {
            $this->addError($attribute, "Invalid qty");
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $allPackageSetting = Setting::getValue1('POS', 'Finish All Packages');
        $salesMenuModel = SalesMenu::find()
                ->with('salesProcessMenu')
                ->where(['ID' => $this->salesMenuID])
                ->one();

        $createDateOrder = $salesMenuModel->salesProcessMenu && $salesMenuModel->salesProcessMenu->fireTime ? $salesMenuModel->salesProcessMenu->fireTime : $salesMenuModel->createdDate;
        $startDate = $this->viewMode == 1 ? $createDateOrder : $salesMenuModel->editedDate;
		
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($this->viewMode == 2) {
                if ($salesMenuModel->statusID == 13 || $salesMenuModel->statusID == 34) {
                    $this->processKitchenOrderFromChecker($createDateOrder, $salesMenuModel->qty);
                    $salesMenuModel = SalesMenu::find()
                    ->where(['ID' => $this->salesMenuID])
                    ->one();
    
                    $startDate = $salesMenuModel->editedDate;
                }
            }
    
            $model = new SalesMenuCompletion();
            $model->salesMenuID = $this->salesMenuID;
            $model->salesNum = $this->salesNum;
            $model->typeID = $this->viewMode;
            $model->qty = $this->qty;
            $model->startDate = $startDate;
            $model->completedDate = isset($this->completedDate) ? $this->completedDate : new Expression("NOW()");
    
            /*if ($this->maxQty == $this->qty) {
                $salesMenu = SalesMenu::find()
                        ->where([
                            "salesNum" => $this->salesNum,
                            "ID" => $this->salesMenuID
                        ])
                        ->one();
    
                $salesMenu->statusID = 14;
                $salesMenu->save();
            }*/
    
            if (!$model->save()) {
                throw new Exception('Failed to save menu completion');
            } else {
                if ($allPackageSetting) {
                    $salesMenuPackageModel = SalesMenu::find()
                        ->where(['menuRefID' => $this->salesMenuID])
                        ->andWhere(['<>', 'ID', $this->salesMenuID])
                        ->all();
    
                    foreach ($salesMenuPackageModel as $data) {
                        $childMenuModel = new SalesMenuCompletion();
                        $childMenuModel->salesMenuID = $data->ID;
                        $childMenuModel->salesNum = $this->salesNum;
                        $childMenuModel->typeID = $this->viewMode;
                        $childMenuModel->qty = $this->qty * $data->qty; // di kali $data->qty untuk mendapatkan hasil pengurangan
                        $childMenuModel->startDate = $startDate;
                        $childMenuModel->completedDate = isset($this->completedDate) ? $this->completedDate : new Expression("NOW()");
                        
                        if (!$childMenuModel->save()) {
                            throw new Exception('Failed to save child menu completion');
                        }
                    }
                }
                
                $mode = \app\models\Setting::find()
                    ->where(['key1' => 'POS'])
                    ->andWhere(['key2' => 'ODS Mode'])
                    ->one()
                    ->value1;
    
                $status = 34;
                if ($mode) {
                    if ($mode == 2) {
                        $status = 14;
                    } else if ($this->viewMode == 2) {
                        $status = 14;
                    }
                }
                
                $completionModel = SalesMenuCompletion::find()
                    ->select(['qty' => 'SUM(qty)'])
                    ->where(['salesMenuID' => $this->salesMenuID])
                    ->andWhere(['typeID' => $this->viewMode])
                    ->one();
                
                $salesMenuModel = SalesMenu::find()
                    ->where(['ID' => $this->salesMenuID])
                    ->one();
                    
                if ($salesMenuModel->menuRefID != 0 && $salesMenuModel->menuGroupID != 0) {
                    $refModel = SalesMenu::find()
                        ->where(['ID' => $salesMenuModel->menuRefID])
                        ->one();
                    
                    if ($refModel) {
                        $salesMenuModel->qty = $salesMenuModel->qty * $refModel->qty;
                    }
                }
                
                if ($completionModel->qty == $salesMenuModel->qty) {
                    SalesMenu::updateAll([
                        'statusID' => $status,
                        'editedDate' => date("Y-m-d H:i:s")
                    ], [
                        'AND',
                        ['ID' => $salesMenuModel->ID],
                        ['NOT IN', 'statusID', [19]]
                    ]);
                        
                    if ($salesMenuModel->menuRefID != 0) {
                        if ($salesMenuModel->menuRefID == $salesMenuModel->ID) {
                            $salesMenuPackageModel = SalesMenu::find()
                                ->where(['menuRefID' => $salesMenuModel->ID])
                                ->andWhere(['<>', 'menuGroupID', 0])
                                ->all();
                            
                            if ($salesMenuPackageModel) {
                                foreach ($salesMenuPackageModel as $salesMenu) {
                                    SalesMenu::updateAll([
                                        'statusID' => $status,
                                        'editedDate' => date("Y-m-d H:i:s")
                                    ], [
                                        'AND',
                                        ['ID' => $salesMenu->ID],
                                        ['NOT IN', 'statusID', [19]]
                                    ]);
                                }
                            }
                        } else {
                            $salesMenuPackageModel = SalesMenu::find()
                            ->where(['menuRefID' => $salesMenuModel->menuRefID])
                            ->andWhere(['<>', 'menuGroupID', 0])
                            ->count();
                    
                            $currentSalesMenuPackageModel = SalesMenu::find()
                                ->where(['menuRefID' => $salesMenuModel->menuRefID])
                                ->andWhere(['<>', 'menuGroupID', 0])
                                ->andWhere(['statusID' => $status])
                                ->count();
    
                            if ($salesMenuPackageModel == $currentSalesMenuPackageModel) {
                                $headSalesMenuModel = SalesMenu::find()
                                    ->where(['menuRefID' => $salesMenuModel->menuRefID])
                                    ->andWhere(['menuGroupID' => 0])
                                    ->one();
                                SalesMenu::updateAll([
                                    'statusID' => $status,
                                    'editedDate' => date("Y-m-d H:i:s")
                                ], [
                                    'AND', 
                                    ['ID' => $headSalesMenuModel->ID],
                                    ['NOT IN', 'statusID', [19]]
                                ]);
                            }
                        }
                        
                        $salesPackageAll = SalesMenu::find()
                            ->select([
                                'tr_salesmenu.menuRefID',
                                'qty' => new Expression('SUM(tr_salesmenu.qty)')
                                ])
                            ->innerJoinWith('menu.branchMenu')
                            ->where(['menuRefID' => $salesMenuModel->menuRefID])
                            ->andWhere(['<>', 'menuGroupID', 0])
                            ->andWhere(['<>', 'stationID', 0])
                            ->groupBy(['tr_salesmenu.menuRefID']);
                        
                        $salesPackageDone = SalesMenu::find()
                            ->select([
                                'tr_salesmenu.menuRefID',
                                'qty' => new Expression('SUM(tr_salesmenu.qty)')
                                ])
                            ->innerJoinWith('menu.branchMenu')
                            ->where(['menuRefID' => $salesMenuModel->menuRefID])
                            ->andWhere(['<>', 'menuGroupID', 0])
                            ->andWhere(['<>', 'stationID', 0])
                            ->andWhere(['=', 'tr_salesmenu.statusID', 14])
                            ->groupBy(['tr_salesmenu.menuRefID']);
                        
                        $finishedOrder = (new Query)
                            ->select([
                                    'menuRefID' => new Expression('subQuery.menuRefID')
                            ])
                            ->from(['subQuery' => $salesPackageAll])
                            ->join("INNER JOIN", ["queries" => $salesPackageDone],
                                "subQuery.menuRefID = queries.menuRefID AND subQuery.qty = queries.qty")
                            ->one();
                        
                        if ($finishedOrder) {
                            SalesMenu::updateAll([
                                'statusID' => 14,
                                'editedDate' => date("Y-m-d H:i:s")
                            ], [
                                'AND',
                                ['menuRefID' => $salesMenuModel->menuRefID],
                                ['NOT IN', 'statusID', [19]]
                            ]);
                        }
                    }
                    
                    $salesMenuAll = SalesMenu::find()
                        ->select([
                            'tr_salesmenu.salesNum',
                            'qty' => new Expression('SUM(tr_salesmenu.qty)')
                            ])
                        ->innerJoinWith('menu.branchMenu')
                        ->where(['salesNum' => $this->salesNum])
                        ->andWhere(['=', 'menuGroupID', 0])
                        ->andWhere(['<>', 'stationID', 0])
                        ->andWhere(['<>', 'statusID', 19])
                        ->groupBy(['tr_salesmenu.salesNum']);
    
                    $salesMenuDone = SalesMenu::find()
                        ->select([
                            'tr_salesmenu.salesNum',
                            'qty' => new Expression('SUM(tr_salesmenu.qty)')
                            ])
                        ->innerJoinWith('menu.branchMenu')
                        ->where(['salesNum' => $this->salesNum])
                        ->andWhere(['=', 'menuGroupID', 0])
                        ->andWhere(['<>', 'stationID', 0])
                        ->andWhere(['=', 'tr_salesmenu.statusID', 14])
                        ->groupBy(['tr_salesmenu.salesNum']);
    
                    $finishedMenuOrder = (new Query)
                        ->select([
                                'salesNum' => new Expression('subQuery.salesNum')
                        ])
                        ->from(['subQuery' => $salesMenuAll])
                        ->join("INNER JOIN", ["queries" => $salesMenuDone],
                            "subQuery.salesNum = queries.salesNum AND subQuery.qty = queries.qty")
                        ->one();
                    if ($finishedMenuOrder) {
                        $iDs = SalesMenu::find()
                            ->select('tr_salesmenu.ID')
                            ->innerJoinWith('menu.branchMenu')
                            ->where(['salesNum' => $this->salesNum])
                            ->andWhere(['=', 'stationID', 0])
                            ->andWhere(['IN', 'menuRefID', [$salesMenuModel->menuRefID, 0]])
                            ->andWhere(['NOT IN', 'statusID', [19, 46]])
                            ->column();

                            if($iDs) {
                                SalesMenu::updateAll([
                                    'statusID' => 14,
                                    'editedDate' => date("Y-m-d H:i:s")
                                ], ['ID' => $iDs]);
                            }
                    }
                }
                
                SalesHead::updateAll([
                    'syncDate' => null,
                    'editedDate' => date("Y-m-d H:i:s")
                ], ['salesNum' => $this->salesNum]);

                $transaction->commit();

                $salesPayment = SalesPayment::find()
                ->where(['salesNum' => $this->salesNum])
                ->one();

                $salesHead = SalesHead::find()
                ->where(['salesNum' => $this->salesNum])
                ->one();

                if(($salesHead->transactionModeID == 2 && $salesPayment->selfOrderID) && $finishedMenuOrder) {
                    $selfOrderApi = Setting::getEsoQsApiUrl();
                    $branch = Branch::findOne(['branchID' => Setting::getCurrentBranch()]);
                    $companyCode = $branch->companyCode;
            
                    $authKey = Setting::getApiKey();
                    $client = new Client(['baseUrl' => $selfOrderApi]);
                    $response = $client->createRequest()
                        ->setUrl("pos-update-order-status")
                        ->setMethod('POST')
                        ->addHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Basic ' . base64_encode("$companyCode:$authKey"),
                            'data-branch' => $branch->branchCode,
                        ])
                        ->setData([
                            "orderID" => $salesPayment->selfOrderID,
                            "statusID" => 8
                        ])
                        ->setFormat(Client::FORMAT_JSON)
                        ->send();
                    if ($response->getIsOk()) {
                        $content = json_decode($response->getContent(), true);
                        if ($content && $content['status'] == '00') {
                            EsoPickupOrder::deleteAll(['orderID' => $salesPayment->selfOrderID]);
                            $attributes = [
                                'salesNum' => $this->salesNum,
                                'orderID' => $salesPayment->selfOrderID,
                                'finishedBy' => 'ODS'
                            ];
                            Logging::save($this->salesNum, Logging::FINISH_PICKUP_ORDER, $attributes);
                            return true;
                        } else {
                            throw new Exception(json_encode($content), 500);
                        }
                    } else {
                        throw new Exception('Cannot connect to ESO server', 500);
                    }
                }
                return true;
            }
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }
	
	private function processKitchenOrderFromChecker($startDate) {
        $completionCheckerModel = SalesMenuCompletion::find()
        ->select(['qty' => 'SUM(qty)'])
        ->where(['salesMenuID' => $this->salesMenuID])
        ->andWhere(['typeID' => 2])
        ->one();

        $completionCheckerQty = $completionCheckerModel ? $completionCheckerModel->qty : 0;
        $qtyProcess = $this->qty + $completionCheckerQty;

		$completionKitchenModel = SalesMenuCompletion::find()
                ->select(['qty' => 'SUM(qty)'])
                ->where(['salesMenuID' => $this->salesMenuID])
                ->andWhere(['typeID' => 1])
                ->one();

        if ($completionKitchenModel) {
            $qtyProcess = $qtyProcess - $completionKitchenModel->qty;
        }

        if ($qtyProcess > 0) {
            $model = new SalesMenuCompletion();
            $model->salesMenuID = $this->salesMenuID;
            $model->salesNum = $this->salesNum;
            $model->typeID = 1;
            $model->qty = $qtyProcess;
            $model->startDate = $startDate;
            $model->completedDate = isset($this->completedDate) ? $this->completedDate : new Expression("NOW()");
            $allPackageSetting = Setting::getValue1('POS', 'Finish All Packages');
            if ($allPackageSetting) {
                $salesMenuPackageModel = SalesMenu::find()
                    ->where(['menuRefID' => $this->salesMenuID])
                    ->andWhere(['<>', 'ID', $this->salesMenuID])
                    ->all();

                foreach ($salesMenuPackageModel as $data) {
                    $childMenuModel = new SalesMenuCompletion();
                    $childMenuModel->salesMenuID = $data->ID;
                    $childMenuModel->salesNum = $this->salesNum;
                    $childMenuModel->typeID = 1;
                    $childMenuModel->qty = $this->qty * $data->qty; // di kali $data->qty untuk mendapatkan hasil pengurangan
                    $childMenuModel->startDate = $startDate;
                    $childMenuModel->completedDate = isset($this->completedDate) ? $this->completedDate : new Expression("NOW()");
                    
                    if (!$childMenuModel->save()) {
                        throw new Exception('Failed to save child menu completion');
                    }
                }
            }

            /*if ($this->maxQty == $this->qty) {
                $salesMenu = SalesMenu::find()
                        ->where([
                            "salesNum" => $this->salesNum,
                            "ID" => $this->salesMenuID
                        ])
                        ->one();

                $salesMenu->statusID = 14;
                $salesMenu->save();
            }*/

            if (!$model->save()) {
                throw new Exception('Failed to save menu completion');
            } else {
                $mode = \app\models\Setting::find()
                    ->where(['key1' => 'POS'])
                    ->andWhere(['key2' => 'ODS Mode'])
                    ->one()
                    ->value1;

                $status = 34;
                if ($mode) {
                    if ($mode == 2) {
                        $status = 14;
                    }
                }
                
                $completionModel = SalesMenuCompletion::find()
                    ->select(['qty' => 'SUM(qty)'])
                    ->where(['salesMenuID' => $this->salesMenuID])
                    ->andWhere(['typeID' => 1])
                    ->one();
                
                $salesMenuModel = SalesMenu::find()
                    ->where(['ID' => $this->salesMenuID])
                    ->one();
                
                $currentSalesMenuQty = $salesMenuModel->qty;  
                
                if ($salesMenuModel->menuRefID != 0 && $salesMenuModel->menuGroupID != 0) {
                    $refModel = SalesMenu::find()
                        ->where(['ID' => $salesMenuModel->menuRefID])
                        ->one();
                    
                    if ($refModel) {
                        $salesMenuModel->qty = $salesMenuModel->qty * $refModel->qty;
                    }
                }

                if ($completionModel->qty == $salesMenuModel->qty) {
                    SalesMenu::updateAll([
                        'statusID' => $status,
                        'editedDate' => date("Y-m-d H:i:s")
                    ], [
                        'AND',
                        ['ID' => $salesMenuModel->ID],
                        ['NOT IN', 'statusID', [19]]
                    ]);

                    if ($salesMenuModel->menuRefID != 0) {
                        if ($salesMenuModel->menuRefID == $salesMenuModel->ID) {
                            $salesMenuPackageModel = SalesMenu::find()
                                ->where(['menuRefID' => $salesMenuModel->ID])
                                ->andWhere(['<>', 'menuGroupID', 0])
                                ->all();
                            
                            if ($salesMenuPackageModel) {
                                foreach ($salesMenuPackageModel as $salesMenu) {
                                    SalesMenu::updateAll([
                                        'statusID' => $status,
                                        'editedDate' => date("Y-m-d H:i:s")
                                    ], [
                                        'AND',
                                        ['ID' => $salesMenu->ID],
                                        ['NOT IN', 'statusID', [19]]
                                    ]);
                                }
                            }
                        } else {
                            $salesMenuPackageModel = SalesMenu::find()
                            ->where(['menuRefID' => $salesMenuModel->menuRefID])
                            ->andWhere(['<>', 'menuGroupID', 0])
                            ->count();
                    
                            $currentSalesMenuPackageModel = SalesMenu::find()
                                ->where(['menuRefID' => $salesMenuModel->menuRefID])
                                ->andWhere(['<>', 'menuGroupID', 0])
                                ->andWhere(['statusID' => $status])
                                ->count();

                            if ($salesMenuPackageModel == $currentSalesMenuPackageModel) {
                                $headSalesMenuModel = SalesMenu::find()
                                    ->where(['menuRefID' => $salesMenuModel->menuRefID])
                                    ->andWhere(['menuGroupID' => 0])
                                    ->one();
                                SalesMenu::updateAll([
                                    'statusID' => $status,
                                    'editedDate' => date("Y-m-d H:i:s")
                                ], [
                                    'AND',
                                    ['ID' => $headSalesMenuModel->ID],
                                    ['NOT IN', 'statusID', [19]]
                                ]);
                            }
                        }
                    }             
                }
                
                return true;
            }
        } else {
            return true;
        }
    }
    
    public static function getMenuCategoryDetail($menuID) {
        $menu = Menu::find()
            ->innerJoinWith('menuCategoryDetail')
            ->andWhere(['menuID' => $menuID])
            ->one();

        return $menu->menuCategoryDetail->menuCategoryID;
    }

    public static function getCountOutstandingOrder() {
        $branchID = Setting::getCurrentBranch();
        $station = Station::find()
            ->andWhere(['branchID' => $branchID])
            ->all();
        $stationArrId = [];
        for ($i=0; $i < count($station); $i++) { 
            $stationArrId[$i] = $station[$i]['stationID'];
        }

        $data = [];
        $rawData = OrderCompletion::getOutstandingOrder('1', implode(",",$stationArrId));
        $i = 0;
        foreach ($rawData as $value) {
            foreach ($value['order'] as $menu) {
                $data[$i]['menuID'] = $menu['menuID'];
                $data[$i]['qty'] = $menu['qty'];
                $data[$i]['menuCategoryID'] = OrderCompletion::getMenuCategoryDetail($menu['menuID']);
                $i++;
            }
        }

        return $data;
    }

    public static function getOutstandingOrder($viewMode, $stationID, $visitPurposeID = null) {
        $connection = Yii::$app->getDb();

        $printingSettings = $connection->createCommand("SELECT value1, key2 FROM ms_setting WHERE key1='POS'")->queryAll();

        $assigValueByKeyFunction = function ($defaultValue, $key) use ($printingSettings) {
          $var = $defaultValue;
          foreach ($printingSettings as $setting) {
            if ($setting['key2'] == $key) {
              $var = $setting['value1'];
            }
          }
          return $var;
        };

        $printingAfterPayment = $assigValueByKeyFunction(0, 'Print Take Away Order After Payment');
        $odsMode = $assigValueByKeyFunction(1, 'ODS Mode');
        $stationList = explode(",", $stationID);

        if ($visitPurposeID) {
            $visitPurposeList = explode(",", $visitPurposeID);
        } else {
            $visitPurpose = $connection->createCommand("SELECT ms_visitpurpose.* FROM ms_visitpurpose")->queryAll();
            $visitPurposeList = [];
            for ($i=0; $i < count($visitPurpose); $i++) { 
                $visitPurposeList[$i] = $visitPurpose[$i]['visitPurposeID'];
            }
        }
          
        if ($viewMode == 1) {
          $statusFilter = "((tr_salesmenu.statusID = 13) OR (tr_salesmenu.statusID = 19))";
        } else {
          $statusFilter = "((tr_salesmenu.statusID = 13) OR (tr_salesmenu.statusID = 19) OR (tr_salesmenu.statusID = 34))";
        }
          
        $stationCondition = [];
        $checkerStationCondition = [];

        $stationCondition = '(';
        $checkerStationCondition = '(';
        $i = 0;

        foreach ($stationList as $station) {
          $i++; 

          $addConditionOR = $i < count($stationList) ? ' OR ' : '';

          $stationCondition .= "(CONCAT(',', ms_branchmenu.stationID, ',') LIKE '%,$station,%')$addConditionOR";

          $checkerStationCondition .= "(CONCAT(',', ms_branchmenu.checkerStationID, ',') LIKE '%,$station,%')$addConditionOR";
        }

        $stationCondition .= ')';
        $checkerStationCondition .= ')';

        $stationFilter = $viewMode == 1 ? $stationCondition : $checkerStationCondition;

        if ($viewMode == 1) {
          $andWhereStationFilter = "ms_branchmenu.stationID <> 0";
        } else {
          $andWhereStationFilter = "ms_branchmenu.checkerStationID <> 0";
        }

        $shiftInDate = ShiftLog::getShiftInDate();
        $visitPurposeListStringArray = implode(', ', $visitPurposeList);

        $querySelect = "SELECT
          tr_salesmenu.ID,
          tr_salesmenu.localID,
          tr_saleshead.salesNum,
          tr_salesmenu.customMenuName,
          tr_salesmenu.menuGroupID,
          tr_salesmenu.menuRefID,
          tr_salesmenu.batchID,
          tr_salesmenu.createdDate,
          tr_salesmenu.notes,
          tr_salesmenu.statusID,
          ms_menu.menuID,
          ms_menu.menuName,
          ms_menu.menuShortName,
          lk_status.statusName,
          tr_saleshead.tableID,
          tr_saleshead.editedDate,
          tr_saleshead.queueNum,
          tr_saleshead.additionalInfo,
          ms_branchmenu.stationID,
          ms_table.tableName,
          ms_visitpurpose.visitPurposeName,
          tr_salesprocessmenu.fireTime,
          creator.fullName AS creatorFullName,
          customer.fullName AS customerFullName,
          esoTable.value AS esoTableValue,
          salesPickUp.value AS salesPickUpValue";

        $queryJoin = "
          FROM
            tr_salesmenu
          LEFT JOIN
            tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
          LEFT JOIN
            ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
          LEFT JOIN
            ms_branchmenu ON tr_salesmenu.menuID = ms_branchmenu.menuID
          LEFT JOIN
            ms_posuser creator ON tr_salesmenu.createdBy = creator.username
          LEFT JOIN 
            ms_posuser editor ON tr_salesmenu.editedBy = editor.username
          LEFT JOIN 
            lk_status ON tr_salesmenu.statusID = lk_status.statusID
          LEFT JOIN 
            ms_table ON tr_saleshead.tableID = ms_table.tableID
          LEFT JOIN 
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
          LEFT JOIN 
            ms_branch ON tr_saleshead.branchID = ms_branch.branchID
          LEFT JOIN 
            tr_salesprocessmenu ON tr_salesmenu.ID = tr_salesprocessmenu.salesMenuID AND tr_salesmenu.salesNum = tr_salesprocessmenu.salesNum
          LEFT JOIN
            tr_salesinfo esoTable ON tr_saleshead.salesNum = esoTable.salesNum AND esoTable.key = 'Table Name'
          LEFT JOIN
            tr_salesinfo salesPickUp ON tr_saleshead.salesNum = salesPickUp.salesNum AND salesPickUp.key IN ('Pickup Time', 'Delivery Time')
          LEFT JOIN
            tr_customertransaction customer ON tr_saleshead.salesNum = customer.salesNum";

        // Start Query Sales Menu
        $salesMenuQuery = $querySelect . ",
          tr_salesmenu.qty
          " . $queryJoin . "
          WHERE
            tr_saleshead.salesDate = '$shiftInDate'
            AND $statusFilter
            AND (tr_saleshead.statusID <> 24)
            AND tr_saleshead.visitPurposeID in ($visitPurposeListStringArray)
            AND $stationFilter
            AND $andWhereStationFilter
            AND (tr_salesmenu.menuRefID = tr_salesmenu.ID OR tr_salesmenu.menuRefID = 0)";

        if ($printingAfterPayment) {
          $salesMenuQuery .= "AND (ms_branch.posModeID = 2 AND tr_saleshead.salesDateOut IS NOT NULL)
            OR (ms_branch.posModeID <> 2 
                AND tr_saleshead.tableID > 0 
                AND $statusFilter 
                AND tr_saleshead.salesDate = '$shiftInDate'
                AND (tr_salesmenu.menuRefID = tr_salesmenu.ID OR tr_salesmenu.menuRefID = 0)
                AND $stationFilter
            )
            OR (ms_branch.posModeID <> 2 
                AND tr_saleshead.tableID = 0 
                AND tr_saleshead.salesDateOut IS NOT NULL 
                AND $statusFilter 
                AND tr_saleshead.salesDate = '$shiftInDate'
                AND (tr_salesmenu.menuRefID = tr_salesmenu.ID OR tr_salesmenu.menuRefID = 0)
                AND $stationFilter
            )";
        }

        $salesMenuQuery .= "ORDER BY tr_salesmenu.createdDate, tr_saleshead.tableID, ms_menu.menuShortName";

        // Start Query Sales Menu Package
        $salesMenuPackageQuery = $querySelect . ",
          tr_salesmenu.qty * headPackage.qty AS qty
          " . $queryJoin . "
          RIGHT JOIN
            tr_salesmenu headPackage ON tr_salesmenu.menuRefID = headPackage.ID AND tr_salesmenu.menuGroupID > 0 AND headPackage.statusID IN (13, 19)
          LEFT JOIN
            ms_menu headMenu ON headPackage.menuID = headMenu.menuID
          LEFT JOIN
            ms_branchmenu headBranchMenu ON headMenu.menuID = headBranchMenu.menuID
          WHERE
            tr_saleshead.salesDate = '$shiftInDate'
            AND $statusFilter
            AND (tr_saleshead.statusID <> 24)
            AND tr_saleshead.visitPurposeID in ($visitPurposeListStringArray)
            AND $stationFilter
            AND $andWhereStationFilter
            AND (tr_salesmenu.menuRefID > 0)
            AND (ms_branchmenu.stationID <> headBranchMenu.stationID)";

        if ($printingAfterPayment) {
          $salesMenuPackageQuery .= "AND (ms_branch.posModeID = 2 AND tr_saleshead.salesDateOut IS NOT NULL)
            OR (ms_branch.posModeID <> 2 
                AND tr_saleshead.tableID > 0 
                AND $statusFilter 
                AND tr_saleshead.salesDate = '$shiftInDate'
                AND (ms_branchmenu.stationID <> headBranchMenu.stationID)
                AND $stationFilter
            )
            OR (ms_branch.posModeID <> 2 
                AND tr_saleshead.tableID = 0 
                AND tr_saleshead.salesDateOut IS NOT NULL 
                AND $statusFilter 
                AND tr_saleshead.salesDate = '$shiftInDate'
                AND (ms_branchmenu.stationID <> headBranchMenu.stationID)
                AND $stationFilter
            )";
        }

        // Start Query Sales Menu Child
        $salesMenuChildQuery = "SELECT
            childSalesMenu.ID,
            childSalesMenu.salesNum,
            childSalesMenu.menuRefID,
            childSalesMenu.qty,
            childSalesMenu.customMenuName,
            childSalesMenu.statusID,
            childSalesMenu.notes,
            ms_menu.menuID,
            ms_menu.menuName,
            ms_menu.menuShortName,
            lk_status.statusName,
            ms_branchmenu.stationID
          FROM
            tr_salesmenu
          LEFT JOIN
            tr_salesmenu childSalesMenu ON tr_salesmenu.ID = childSalesMenu.menuRefID AND childSalesMenu.ID <> childSalesMenu.menuRefID AND childSalesMenu.menuRefID <> 0
          LEFT JOIN
            tr_saleshead ON childSalesMenu.salesNum = tr_saleshead.salesNum
          LEFT JOIN
            ms_menu ON childSalesMenu.menuID = ms_menu.menuID
          LEFT JOIN
            lk_status ON childSalesMenu.statusID = lk_status.statusID
          LEFT JOIN
            ms_branchmenu ON childSalesMenu.menuID = ms_branchmenu.menuID
          WHERE
            tr_saleshead.salesDate = '$shiftInDate'
              AND $statusFilter
              AND (tr_saleshead.statusID <> 24)
              AND tr_saleshead.visitPurposeID in ($visitPurposeListStringArray)
              AND $stationFilter
              AND $andWhereStationFilter
              AND childSalesMenu.salesNum IS NOT NULL";

        $salesMenuChildModel = $connection->createCommand($salesMenuChildQuery)->queryAll();

        if($viewMode == 2){
            $salesMenuArrays = $connection->createCommand($salesMenuQuery)->queryAll();
        } else {
            $salesMenuQuery = "(" . $salesMenuQuery . ")  UNION (" . $salesMenuPackageQuery . ")";
            $salesMenuArrays = $connection->createCommand($salesMenuQuery)->queryAll();
        }

        $newSalesNumList = "'" . implode("', '", array_unique(ArrayHelper::getColumn($salesMenuArrays, "salesNum"))) . "'";

        // Start Query Sales Menu Extra
        $newSalesMenuExtraQuery = "SELECT
            tr_salesmenuextra.salesNum,
            tr_salesmenuextra.menuDetailID,
            tr_salesmenuextra.qty,
            ms_menuextra.menuExtraShortName
          FROM
            tr_salesmenuextra
          LEFT JOIN
            tr_salesmenu ON tr_salesmenuextra.salesNum = tr_salesmenu.salesNum AND tr_salesmenuextra.menuDetailID = tr_salesmenu.ID
          LEFT JOIN
            tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
          LEFT JOIN
            ms_menuextra ON tr_salesmenuextra.menuExtraID = ms_menuextra.menuExtraID
          LEFT JOIN
            ms_branchmenu ON tr_salesmenu.menuID = ms_branchmenu.menuID
          WHERE
            (tr_saleshead.salesDate = '$shiftInDate'
              AND $statusFilter
              AND (tr_saleshead.statusID <> 24)
              AND tr_saleshead.visitPurposeID in ($visitPurposeListStringArray)
              AND $stationFilter
              AND $andWhereStationFilter)
            OR
              tr_salesmenuextra.salesNum in ($newSalesNumList)";

        $salesMenuExtraModel = $connection->createCommand($newSalesMenuExtraQuery)->queryAll();

        $newSalesNumList = "'" . implode("', '", array_unique(ArrayHelper::getColumn($salesMenuArrays, "salesNum"))) . "'";

        // Start Query Sales Menu Completion
        $newOrderCompletionQuery = "SELECT
            tr_salesmenucompletion.*,
            ms_menu.menuCategoryDetailID,
            ms_menucategorydetail.menuCategoryID,
            ms_menu.menuID,
            COALESCE(ms_menugroup.menuID, 0) AS headMenuID,
            tr_salesmenu.menuGroupID,
            tr_salesmenu.menuRefID,
            ms_menu.menuName,
            COALESCE(headMenuGroup.menuName, '') AS headMenuName,
            ms_menu.menuShortName,
            ms_menu.menuCode,
            tr_salesmenu.customMenuName,
            COALESCE(parentSalesMenu.qty, 0) AS parentQty,
            (CASE WHEN tr_saleshead.tableID = 0 THEN 'Quick Service' ELSE ms_table.tableName END) AS tableName,
            ms_visitpurpose.visitPurposeName,
            lk_status.statusName,
            tr_saleshead.queueNum AS queue
          FROM
            tr_salesmenucompletion
          LEFT JOIN
            tr_salesmenu ON tr_salesmenucompletion.salesMenuID = tr_salesmenu.ID
          LEFT JOIN
            ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
          LEFT JOIN 
            tr_salesmenu parentSalesMenu ON tr_salesmenu.menuRefID = parentSalesMenu.ID
          LEFT JOIN
            ms_menucategorydetail ON ms_menu.menuCategoryDetailID = ms_menucategorydetail.ID
          LEFT JOIN
            ms_menugroup ON tr_salesmenu.menuGroupID = ms_menugroup.menuGroupID
          LEFT JOIN
            ms_menu headMenuGroup ON ms_menugroup.menuID = headMenuGroup.menuID
          LEFT JOIN
            tr_saleshead ON tr_salesmenu.salesNum = tr_saleshead.salesNum
          LEFT JOIN
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
          LEFT JOIN
            ms_table ON tr_saleshead.tableID = ms_table.tableID
          LEFT JOIN
            lk_status ON tr_salesmenu.statusID = lk_status.statusID
          WHERE
            tr_salesmenucompletion.salesNum IN ($newSalesNumList)
          ORDER BY tr_salesmenucompletion.completedDate DESC";

        $newOrderCompletionModel = $connection->createCommand($newOrderCompletionQuery)->queryAll();
        $newOrderCompletionArray = [];
        foreach ($newOrderCompletionModel as $orderComplete) {
          $newOrderComplete = [];
          foreach ($orderComplete as $key => $value) {
            if (strpos(strtolower($key), 'id') !== false) {
              $newOrderComplete[$key] = (float) $value;
            } else if (strpos(strtolower($key), 'qty') !== false) {
              $newOrderComplete[$key] = (int) $value;
            } else if ($key == 'completedDate') {
              $newOrderComplete[$key] = $value;
              $newOrderComplete['completedTime'] = date("H:i:s", strtotime($value));
            } else {
              $newOrderComplete[$key] = $value;
            }
          }
          $newOrderCompletionArray[$orderComplete['salesNum']][] = $newOrderComplete;
        }

        // Variable function for check menu has complete
        $checkSalesMenuHasComplete = function ($field, $salesNum, $value, $typeID) use ($newOrderCompletionArray) {
          $salesMenuHasComplete = [];
          if (isset($newOrderCompletionArray[$salesNum])) {
            foreach ($newOrderCompletionArray[$salesNum] as $hasComplete) {
              if ($hasComplete[$field] == $value && $hasComplete['typeID'] == $typeID) {
                $salesMenuHasComplete[] = $hasComplete;
              }
            }
          }
          return $salesMenuHasComplete;
        };

        // Variable function for grouping menu package
        $groupingSalesPackages = function ($salesNum, $ID) use ($salesMenuChildModel) {
          $salesMenuPackages = [];
          foreach ($salesMenuChildModel as $salesPackages) {
            if ($salesPackages['salesNum'] == $salesNum && $salesPackages['menuRefID'] == $ID) {
              $salesMenuPackages[] = $salesPackages;
            }
          }
          return $salesMenuPackages;
        };

        $salesMenuExtraModelArray = [];
        foreach ($salesMenuExtraModel as $extra) {
          $salesMenuExtraModelArray[$extra['menuDetailID']][] = $extra;
        }

        $salesMenuChildModelArray = [];
        foreach ($salesMenuChildModel as $package) {
          $tempPackages = [];
          foreach ($package as $key => $value) {
              $tempPackages[$key] = $value;
          }

          $salesMenuPackageHasCompleteKitchen = $checkSalesMenuHasComplete('salesMenuID', $package['salesNum'], $package['ID'], 1);
          $salesMenuPackageHasCompleteChecker = $checkSalesMenuHasComplete('salesMenuID', $package['salesNum'], $package['ID'], 2);

          $tempPackages['menuID'] = $package['menuID'];
          $tempPackages['menuName'] = $package['menuName'];
          $tempPackages['menuShortName'] = $package['menuShortName'];
          $tempPackages['customMenuName'] = $package['customMenuName'];
          $tempPackages['qty'] = (float) $package['qty'];
          $tempPackages['statusID'] = (int) $package['statusID'];
          $tempPackages['statusName'] = $package['statusName'];
          $packageCompletions = 0;
          foreach ($salesMenuPackageHasCompleteKitchen as $packageComplete) {
            $packageCompletions += $packageComplete['qty'];
          }
          $tempPackages['packageCompletions'] = $packageCompletions;
          $tempPackages['salesMenuCompletionKitchen'] = $salesMenuPackageHasCompleteKitchen;
          $tempPackages['salesMenuCompletionChecker'] = $salesMenuPackageHasCompleteChecker;
          $tempPackages['packages'] = $groupingSalesPackages($package['salesNum'], $package['ID']);
          $tempPackages['extras'] = isset($salesMenuExtraModelArray[$package['ID']]) ? $salesMenuExtraModelArray[$package['ID']] : [];
          $tempPackages['stationID'] = $package['stationID'] != null ? $package['stationID'] : 0;

          $salesMenuChildModelArray[$package['menuRefID']][] = $tempPackages;
        }

        $salesMenuParentModelArray = [];
        foreach ($salesMenuArrays as $parentSalesMenu) {
          $tempOrder = $parentSalesMenu;
          $tempNewOrder = [];
          foreach ($tempOrder as $key => $value) {
              $tempNewOrder[$key] = $value;
          }

          $salesParentHasCompleteKitchen = $checkSalesMenuHasComplete('salesMenuID', $tempOrder['salesNum'], $tempOrder['ID'], 1);
          $salesParentHasCompleteChecker = $checkSalesMenuHasComplete('salesMenuID', $tempOrder['salesNum'], $tempOrder['ID'], 2);

          $tempNewOrder['menuID'] = $tempOrder['menuID'];
          $tempNewOrder['menuName'] = $tempOrder['menuName'];
          $tempNewOrder['menuShortName'] = $tempOrder['menuShortName'];
          $tempNewOrder['customMenuName'] = $tempOrder['customMenuName'];
          $tempNewOrder['qty'] = (float) $tempOrder['qty'];
          $tempNewOrder['statusID'] = (int) $tempOrder['statusID'];
          $tempNewOrder['statusName'] = $tempOrder['statusName'];
          $tempNewOrder['salesMenuCompletionKitchen'] = $salesParentHasCompleteKitchen;
          $tempNewOrder['salesMenuCompletionChecker'] = $salesParentHasCompleteChecker;
          $packages = isset($salesMenuChildModelArray[$tempOrder['ID']]) ? $salesMenuChildModelArray[$tempOrder['ID']] : [];
          $packageCompletions = array_column($packages, 'packageCompletions');
          $tempNewOrder['packages'] = $packages;
          $tempNewOrder['packageCompletions'] = $packageCompletions;
          $tempNewOrder['extras'] = isset($salesMenuExtraModelArray[$tempOrder['ID']]) ? $salesMenuExtraModelArray[$tempOrder['ID']] : [];
          $tempNewOrder['stationID'] = $tempOrder['stationID'] != null ? $tempOrder['stationID'] : 0;
          
          $salesMenuParentModelArray[$parentSalesMenu['ID']] = $tempNewOrder;
        }

        $orderResult = [];
        $i = 1;
        foreach ($salesMenuArrays as $newOrderModel) {
          $newTempOrder = [];
          foreach ($newOrderModel as $key => $value) {
            $newTempOrder[$key] = $value;
          }

          $salesMenuHasCompleteKitchen = $checkSalesMenuHasComplete('salesMenuID', $newOrderModel['salesNum'], $newOrderModel['ID'], 1);
          $salesMenuHasCompleteChecker = $checkSalesMenuHasComplete('salesMenuID', $newOrderModel['salesNum'], $newOrderModel['ID'], 2);

          $newTempOrder['menuID'] = $newOrderModel['menuID'];
          $newTempOrder['menuName'] = $newOrderModel['menuName'];
          $newTempOrder['menuShortName'] = $newOrderModel['menuShortName'];
          $newTempOrder['customMenuName'] = $newOrderModel['customMenuName'];
          $newTempOrder['qty'] = (float) $newOrderModel['qty'];
          $newTempOrder['statusID'] = (int) $newOrderModel['statusID'];
          $newTempOrder['statusName'] = $newOrderModel['statusName'];
          $newTempOrder['salesMenuCompletionKitchen'] = $salesMenuHasCompleteKitchen;
          $newTempOrder['salesMenuCompletionChecker'] = $salesMenuHasCompleteChecker;
          $packages = isset($salesMenuChildModelArray[$newOrderModel['ID']]) ? $salesMenuChildModelArray[$newOrderModel['ID']] : [];
          $packageCompletions = array_column($packages, 'packageCompletions');
          $newTempOrder['packages'] = $packages;
          $newTempOrder['packageCompletions'] = $packageCompletions;
          $newTempOrder['extras'] = isset($salesMenuExtraModelArray[$newOrderModel['ID']]) ? $salesMenuExtraModelArray[$newOrderModel['ID']] : [];
          $newTempOrder['stationID'] = $newOrderModel['stationID'] != null ? $newOrderModel['stationID'] : 0;

          $createDateOrder = $newOrderModel['fireTime'] ? $newOrderModel['fireTime'] : $newOrderModel['createdDate'];
          $editedDateOrder = self::getEditedDateOrder($odsMode, $newOrderModel, $salesMenuHasCompleteKitchen);
          $createdDate = date_create($viewMode == 1 ? $createDateOrder : $editedDateOrder);
          $currentDate = date_create(date('Y-m-d H:i:s'));
          if ($newOrderModel['tableID'] == 0 && $printingAfterPayment == 1) {
              $diff = date_diff(date_create($newOrderModel['editedDate']), $currentDate);
          } else {
              $diff = date_diff($createdDate, $currentDate);
          }
          $minutes = $diff->days * 24 * 60;
          $minutes += $diff->h * 60;
          $minutes += $diff->i;
          
          $orderNew = $newTempOrder;
          $currentStationID = $orderNew['stationID'];

          if (isset($salesMenuParentModelArray[$newOrderModel['menuRefID']]) && $newOrderModel['menuGroupID'] > 0) {
            $orderNew = $salesMenuParentModelArray[$newOrderModel['menuRefID']];
            $orderNew['stationID'] = $currentStationID; 
          }

          $keyGroupBy = $newOrderModel['salesNum'].$newOrderModel['batchID'];
          $orderResult[$keyGroupBy]['timeProcess'] = $minutes;
          if ($newOrderModel['tableID'] == 0 && $printingAfterPayment == 1) {
              $orderResult[$keyGroupBy]['createdDate'] = $newOrderModel['editedDate'];
          } else {
              $orderResult[$keyGroupBy]['createdDate'] = $createDateOrder;
          }
          $orderResult[$keyGroupBy]['createdTime'] = date("H:i:s", strtotime($createDateOrder));
          $orderResult[$keyGroupBy]['pickupTime'] = $newOrderModel['salesPickUpValue'] ? date("H:i", strtotime($newOrderModel['salesPickUpValue'])) : null;
          $orderResult[$keyGroupBy]['originalCreatedDate'] = $createDateOrder;

          $orderResult[$keyGroupBy]['tableName'] = $newOrderModel['tableID'] == 0 ? 
              ($newOrderModel['esoTableValue'] ? $newOrderModel['esoTableValue'] : 'Quick Service') : $newOrderModel['tableName'];
          $orderResult[$keyGroupBy]['queueNumber'] = 'Queue : ' . $newOrderModel['queueNum'];          
          $orderResult[$keyGroupBy]['salesNum'] = $newOrderModel['salesNum'];
          $orderResult[$keyGroupBy]['visitPurposeName'] = $newOrderModel['visitPurposeName'];
          $orderResult[$keyGroupBy]['additionalInfo'] = $newOrderModel['customerFullName'] ? $newOrderModel['customerFullName'] : ($newOrderModel['additionalInfo'] ? $newOrderModel['additionalInfo'] : '-');
          $orderResult[$keyGroupBy]['sender'] = $newOrderModel['creatorFullName'] ? $newOrderModel['creatorFullName'] : 'SELF ORDER';
          $orderResult[$keyGroupBy]['order'][] = $orderNew;
          $orderResult[$keyGroupBy]['tableID'][] = $newOrderModel['tableID'];

          $salesHeadHasCompleteKitchen = $checkSalesMenuHasComplete('salesNum', $newOrderModel['salesNum'], $newOrderModel['salesNum'], 1);
          $salesHeadHasCompleteChecker = $checkSalesMenuHasComplete('salesNum', $newOrderModel['salesNum'], $newOrderModel['salesNum'], 2);

          $orderResult[$keyGroupBy]['salesMenuCompletionKitchen'] = $salesHeadHasCompleteKitchen;
          $orderResult[$keyGroupBy]['salesMenuCompletionChecker'] = $salesHeadHasCompleteChecker;
          $orderResult[$keyGroupBy]['historyKitchen'] = OrderCompletion::salesMenuCompletionCustom($salesHeadHasCompleteKitchen, $salesMenuExtraModel);
          $orderResult[$keyGroupBy]['historyChecker'] = OrderCompletion::salesMenuCompletionCustom($salesHeadHasCompleteChecker, $salesMenuExtraModel);
          $orderResult[$keyGroupBy]['isShowArrowDown'] = false;

          $i++;
        }

        $orderResult = array_values($orderResult);
        $deletedPackage = [];
        $deletedOrderPackage = [];
        $deletedOrder = [];
        $unsetArrayIndex = [];
        for ($i = 0; $i < count($orderResult); $i++) {
            $preparingExist = false; // $viewMode == 1 ? false : true;
            $sumQty = 0;
            for ($j = 0; $j < count($orderResult[$i]['order']); $j ++) {
                if ($orderResult[$i]['order'][$j]['statusID'] == 13 || $orderResult[$i]['order'][$j]['statusID'] == 34) {
                    $preparingExist = true;
                }
                
                if ($orderResult[$i]['order'][$j]['statusID'] != 19) {
                    $sumQty += $orderResult[$i]['order'][$j]['qty'];
                }
                
                $orderResult[$i]['order'][$j]['statusPackages'] = 1;
                $countStation = 0;
                $currentStationID = 0;
                $hasPackages = false;
                for ($l = 0; $l < count($orderResult[$i]['order'][$j]['packages']); $l ++) {
                    $hasPackages = true;
                    if ($currentStationID != $orderResult[$i]['order'][$j]['packages'][$l]['stationID']) {
                        $countStation += 1;
                        
                        $currentStationID = $orderResult[$i]['order'][$j]['packages'][$l]['stationID'];
                    }

                    if($viewMode != 2){
                        if ($orderResult[$i]['order'][$j]['stationID'] != $orderResult[$i]['order'][$j]['packages'][$l]['stationID']) {
                            $deletedOrderPackage[] = ["i" => $i, "j" => $j, "l" => $l];
                        }
                    }
                    
                    $orderResult[$i]['order'][$j]['packages'][$l]['qty'] = $orderResult[$i]['order'][$j]['packages'][$l]['qty'] * $orderResult[$i]['order'][$j]['qty'];
                }
                
                if ($hasPackages) {
                    $orderResult[$i]['order'][$j]['hasPackages'] = $hasPackages;
                }
                
                if ($countStation > 1) {
                    $orderResult[$i]['order'][$j]['statusPackages'] = 0;
                }

                $salesNum = $orderResult[$i]['order'][$j]['salesNum'];
                if (isset($newOrderCompletionArray[$salesNum])) {
                  foreach ($newOrderCompletionArray[$salesNum] as $completion) {
                    if ($completion['typeID'] == $viewMode) {
                      if ($orderResult[$i]['order'][$j]['ID'] == $completion['salesMenuID']) {
                          $orderResult[$i]['order'][$j]['qty'] -= $completion['qty'];
                      }
                      for ($k = 0; $k < count($orderResult[$i]['order'][$j]['packages']); $k ++) {
                          if ($orderResult[$i]['order'][$j]['packages'][$k]['ID'] == $completion['salesMenuID']) {
                              $orderResult[$i]['order'][$j]['packages'][$k]['qty'] -= $completion['qty'];
                          }
                          if ($orderResult[$i]['order'][$j]['packages'][$k]['qty'] <= 0) {
                              $deletedPackage[] = ["i" => $i, "j" => $j, "k" => $k];
                          }
                      }
                    }
                  }
                }

                if ($orderResult[$i]['order'][$j]['qty'] == 0) {
                    $deletedOrder[] = ["i" => $i, "j" => $j];
                }
            }

            $orderResult[$i]['order'] = array_values($orderResult[$i]['order']);
            $item = 'Item';
            if ($sumQty > 1) {
                $item = 'Items';
            }
            //$orderResult[$i]['tableName'] = $orderResult[$i]['tableName'] . ' (' . $sumQty . ' ' . $item . ')';
            
            if (!$preparingExist) {
                $unsetArrayIndex[] = $i;
            }
        }

        $deletedPackage = array_unique($deletedPackage, SORT_REGULAR);
        foreach ($deletedPackage as $value) {
            $i = $value['i'];
            $j = $value['j'];
            $k = $value['k'];
            unset($orderResult[$i]['order'][$j]['packages'][$k]);
            if (empty($orderResult[$i]['order'][$j]['packages'])) {
                $deletedOrder[] = ["i" => $i, "j" => $j];
            }
        }

        $deletedOrderPackage = array_unique($deletedOrderPackage, SORT_REGULAR);
        foreach ($deletedOrderPackage as $value) {
            $i = $value['i'];
            $j = $value['j'];
            $l = $value['l'];
            unset($orderResult[$i]['order'][$j]['packages'][$l]);
            if (empty($orderResult[$i]['order'][$j]['packages'])) {
                $deletedOrder[] = ["i" => $i, "j" => $j];
            }
        }


        $deletedOrder = array_unique($deletedOrder, SORT_REGULAR);
        foreach ($deletedOrder as $value) {
            $i = $value['i'];
            $j = $value['j'];
            unset($orderResult[$i]['order'][$j]);
            if (empty($orderResult[$i]['order'])) {
                unset($orderResult[$i]);
            }
        }

        if ($unsetArrayIndex) {
            foreach ($unsetArrayIndex as $index) {
                unset($orderResult[$index]);
            }
        }

        $orderResult = array_values($orderResult);

        $allQuickServ = true;
        for ($i = 0; $i < count($orderResult); $i++) {
            if ($orderResult[$i]['tableID'][0] > 0) {
                $allQuickServ = false;
            }

            $orderResult[$i]['order'] = array_values($orderResult[$i]['order']);

            $isOdd = true;
            for ($j = 0; $j < count($orderResult[$i]['order']); $j ++) {
                if (isset($orderResult[$i]['order'][$j])) {
                    $rowClass = $isOdd ? 'bg-grey' : 'bg-white';
                    $orderResult[$i]['order'][$j]['class'] = $rowClass;
                    $orderResult[$i]['order'][$j]['packages'] = array_values($orderResult[$i]['order'][$j]['packages']);
                    $isOdd = !$isOdd;
                }
            }
        }

        $orderResult = array_values($orderResult);
        usort($orderResult, function($a, $b) {return strcmp($a['originalCreatedDate'], $b['originalCreatedDate']);});

        return $orderResult;
    }
            
    private static function getEditedDateOrder($odsMode, $salesMenu, $salesMenuCompletionKitchen) {
      $editedDate = $salesMenu['editedDate'];
      if ($odsMode == 1) {
          $editedDate = $salesMenu['createdDate'];
          if ($salesMenuCompletionKitchen) {
              foreach ($salesMenuCompletionKitchen as $salesMenuComplete) {
                  $editedDate = $salesMenuComplete['completedDate'];
              }
          } else {
              if ($salesMenu['fireTime'])
                $editedDate = $salesMenu['fireTime'];
          }
      } else if ($odsMode == 3) {
          $editedDate = $salesMenu['createdDate'];
          if ($salesMenu['fireTime'])
            $editedDate = $salesMenu['fireTime']; 
      }
      return $editedDate;
    }

    public static function salesMenuCompletionCustom($data, $salesExtraModel)
    {   
        $package = [];
        foreach ($data as $dt) {
          if ($dt['menuGroupID'] > 0 && $dt['menuRefID'] != 0) {
            $package[$dt['headMenuID']][] = $dt;
          }
        }

        $tempData = [];
        $result = [];
        foreach ($data as $item) {
          if ($item['menuGroupID'] > 0) {
            if (!in_array($item['headMenuID'], array_column($result, 'menuID'))) {
              $tempData['menuID'] = $item['headMenuID'];
              $tempData['menuName'] =  $item['headMenuName'];
              $tempData['customMenuName'] =  $item['customMenuName'];
              $tempData['qty'] = (int) $item['parentQty'];
              $tempData['packages'] = $package[$item['headMenuID']];
              $tempData['isPackage'] = true;
              $tempData['extras'] = [];
              $tempData['completedTime'] = date('H:i:s', strtotime($item['completedDate']));
              $result[] = $tempData;
            }
          } 
          else if ($item['menuGroupID'] == 0 && $item['menuRefID'] == 0) {
            $tempData['menuID'] = $item['menuID'];
            $tempData['menuName'] = $item['menuName'];
            $tempData['customMenuName'] =  $item['customMenuName'];
            $tempData['qty'] = (int) $item['qty'];
            $tempData['packages'] = [];
            $tempData['isPackage'] = false;
            $tempData['extras'] = self::groupingSalesExtras($item['salesNum'], $item['salesMenuID'], $salesExtraModel);
            $tempData['completedTime'] = date('H:i:s', strtotime($item['completedDate']));
            $result[] = $tempData;
          }
        }
        return $result;
    }

    private static function groupingSalesExtras($salesNum, $ID, $salesExtraModel) {
      $salesMenuExtras = [];
      foreach ($salesExtraModel as $salesExtra) {
        if ($salesExtra['salesNum'] == $salesNum && $salesExtra['menuDetailID'] == $ID) {
          $newSalesExtra = [];
          foreach ($salesExtra as $key => $value) {
            if (strpos($key, 'ID') !== false ||
                strpos($key, 'price') !== false ||
                strpos($key, 'total') !== false ||
                strpos($key, 'qty') !== false
              )
            {
              $newSalesExtra[$key] = (float) $value;
            } else {
              $newSalesExtra[$key] = $value;
            }
          }
          $salesMenuExtras[] = $newSalesExtra;
        }
      }
      return $salesMenuExtras;
    }
    
    public static function getOutstandingCheckerOrder() {
        $orderModel = SalesMenu::find()
                ->joinWith(['salesHead.table', 'menu.branchMenu'])
                ->with('creator')
                ->with('editor')
                ->with('menu.menuCategoryDetail')
                ->with('status')
                ->with('childSalesMenus.menu.menuCategoryDetail')
                ->with('childSalesMenus.status')
                ->with('childSalesMenus.editor')
                ->with('childSalesMenus.creator')
                ->with('salesExtras.menuExtra')
                ->with('salesExtras.salesMenu')
                ->with('parentSalesMenu')
                ->with('promotion')
                ->andWhere(['tr_saleshead.salesDate' => ShiftLog::getShiftInDate()])
                ->andWhere(['tr_salesmenu.statusID' => 34])
                ->andWhere(['<>', 'tr_saleshead.statusID', 24])
                ->andWhere([
                    'OR',
                    'tr_salesmenu.menuRefID = tr_salesmenu.ID',
                    'tr_salesmenu.menuRefID = 0'
                ])
                ->orderBy('tr_salesmenu.createdDate, tr_saleshead.tableID, ms_menu.menuShortName')
                ->all();

        $salesNumList = array_unique(ArrayHelper::getColumn($orderModel, "salesNum"));

        $orderCompletion = SalesMenuCompletion::find()
                ->with('salesMenu.menu')
                ->with('salesMenu.status')
                ->with('salesMenu.salesHead')
                ->where(["IN", "salesNum", $salesNumList])
                ->andWhere(['typeID' => 2])
                ->all();


        $orderResult = [];

        foreach ($orderModel as $order) {
            $editedDate = date_create($order->editedDate);
            $currentDate = date_create(date('Y-m-d H:i:s'));
            $diff = date_diff($editedDate, $currentDate);
            $minutes = $diff->days * 24 * 60;
            $minutes += $diff->h * 60;
            $minutes += $diff->i;
            
            $orderResult[$order->createdDate]['timeProcess'] = $minutes;
            $orderResult[$order->createdDate]['createdDate'] = $order->createdDate;
            $orderResult[$order->createdDate]['createdTime'] = date("H:i:s", strtotime($order->createdDate));
            $orderResult[$order->createdDate]['tableName'] = $order->salesHead->tableID == 0 ? 'Quick Service (' . $order->salesHead->salesNum . ')' : $order->salesHead->table->tableName;
            $orderResult[$order->createdDate]['salesNum'] = $order->salesHead->salesNum;
            $orderResult[$order->createdDate]['additionalInfo'] = $order->salesHead->additionalInfo;
            $orderResult[$order->createdDate]['visitPurposeName'] = $order->salesHead->visitPurpose->visitPurposeName;
            $orderResult[$order->createdDate]['rowClass'] = 'text-dark';
            $orderResult[$order->createdDate]['sender'] = $order->createdBy;
            $orderResult[$order->createdDate]['order'][] = ArrayHelper::toArray($order);
        }

        $orderResult = array_values($orderResult);

        $deletedPackage = [];
        $deletedOrder = [];

        for ($i = 0; $i < count($orderResult); $i++) {
            $sumQty = 0;
            for ($j = 0; $j < count($orderResult[$i]['order']); $j ++) {
                if ($orderResult[$i]['order'][$j]['statusID'] != 19) {
                    $sumQty += $orderResult[$i]['order'][$j]['qty'];
                }
                
                $orderResult[$i]['order'][$j]['statusPackages'] = 1;
                $countStation = 0;
                $currentStationID = 0;
                for ($l = 0; $l < count($orderResult[$i]['order'][$j]['packages']); $l ++) { 
                    if ($currentStationID != $orderResult[$i]['order'][$j]['packages'][$l]['stationID']) {
                        $countStation += 1;
                        
                        $currentStationID = $orderResult[$i]['order'][$j]['packages'][$l]['stationID'];
                    }
                    
                    $orderResult[$i]['order'][$j]['packages'][$l]['qty'] = $orderResult[$i]['order'][$j]['packages'][$l]['qty'] * $orderResult[$i]['order'][$j]['qty'];
                }
                
                if ($countStation > 1) {
                    $orderResult[$i]['order'][$j]['statusPackages'] = 0;
                }
                
                foreach ($orderCompletion as $completion) {
                    if ($orderResult[$i]['order'][$j]['ID'] == $completion->salesMenuID) {
                        $orderResult[$i]['order'][$j]['qty'] -= $completion->qty;
                    }

                    for ($k = 0; $k < count($orderResult[$i]['order'][$j]['packages']); $k ++) {
                        if ($orderResult[$i]['order'][$j]['packages'][$k]['ID'] == $completion->salesMenuID) {
                            $orderResult[$i]['order'][$j]['packages'][$k]['qty'] -= $completion->qty;
                        }
                        if ($orderResult[$i]['order'][$j]['packages'][$k]['qty'] == 0) {
                            $deletedPackage[] = ["i" => $i, "j" => $j, "k" => $k];
                        }
                    }
                }

                if ($orderResult[$i]['order'][$j]['qty'] == 0) {
                    $deletedOrder[] = ["i" => $i, "j" => $j];
                }
            }

            $orderResult[$i]['order'] = array_values($orderResult[$i]['order']);
            // $orderResult[$i]['tableName'] = $sumQty . ' - ' . $orderResult[$i]['tableName'];
        }

        $deletedPackage = array_unique($deletedPackage, SORT_REGULAR);
        foreach ($deletedPackage as $value) {
            $i = $value['i'];
            $j = $value['j'];
            $k = $value['k'];
            unset($orderResult[$i]['order'][$j]['packages'][$k]);
            if (empty($orderResult[$i]['order'][$j]['packages'])) {
                $deletedOrder[] = ["i" => $i, "j" => $j];
            }
        }

        $deletedOrder = array_unique($deletedOrder, SORT_REGULAR);
        foreach ($deletedOrder as $value) {
            $i = $value['i'];
            $j = $value['j'];
            unset($orderResult[$i]['order'][$j]);
            if (empty($orderResult[$i]['order'])) {
                unset($orderResult[$i]);
            }
        }

        $orderResult = array_values($orderResult);

        for ($i = 0; $i < count($orderResult); $i++) {
            $orderResult[$i]['order'] = array_values($orderResult[$i]['order']);

            for ($j = 0; $j < count($orderResult[$i]['order']); $j ++) {
                if (isset($orderResult[$i]['order'][$j])) {
                    $orderResult[$i]['order'][$j]['packages'] = array_values($orderResult[$i]['order'][$j]['packages']);
                }
            }
        }

        return $orderResult;
    }

    private static function getTableName(array $detail): ?string {
        $tableName = SalesInfo::findBySalesNumKey($detail['salesNum'], 'Table Name');
        return $tableName ?? $detail['tableName'] ?? '-';
    }

    public static function getAllDataQueue($stationID = null, $visitPurposeID = null) {
        $shiftIn = ShiftLog::getShiftInDate();

        $connection = Yii::$app->getDb();

        $salesHeadNumQuery = "SELECT
            tr_saleshead.salesNum
          FROM
            tr_saleshead
          WHERE
            tr_saleshead.salesDate = '$shiftIn'
            AND tr_saleshead.salesDateOut IS NOT NULL";
          
        $station = $stationID !== 0 ? $stationID : null;
        $stationFilter = $stationID !== 0 ? "AND (ms_branchmenu.stationID = $station)" : '';

        $visitPurposeList = [];
        if ($visitPurposeID !== null) {
            $visitPurposeList = explode(",", $visitPurposeID);
        }

        $visitPurposeListStringArray = implode(', ', $visitPurposeList);

        $salesHeadNum = "'" . implode("', '", $connection->createCommand($salesHeadNumQuery)->queryColumn()) . "'";

        $outStandingQuery = "(SELECT
            tr_salesmenu.salesNum,
            SUM(tr_salesmenu.qty) AS qty
          FROM
            tr_salesmenu 
          LEFT JOIN
            ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
          LEFT JOIN
            ms_branchmenu ON ms_menu.menuID = ms_branchmenu.menuID
          WHERE
            (tr_salesmenu.statusID NOT IN (12, 19))
            AND tr_salesmenu.salesNum IN ($salesHeadNum)
            $stationFilter
          GROUP BY tr_salesmenu.salesNum) outstanding";
        
        $finishedOrderQuery = "(SELECT
            tr_salesmenu.salesNum,
            SUM(tr_salesmenu.qty) AS qty
          FROM
            tr_salesmenu 
          LEFT JOIN
            ms_menu ON tr_salesmenu.menuID = ms_menu.menuID
          LEFT JOIN
            ms_branchmenu ON ms_menu.menuID = ms_branchmenu.menuID
          WHERE
            (tr_salesmenu.statusID = 14)
            AND tr_salesmenu.salesNum IN ($salesHeadNum)
            $stationFilter
          GROUP BY tr_salesmenu.salesNum) finished";

        $finishedOrderQuery = "SELECT
            outstanding.salesNum
          FROM
            " . $outStandingQuery . "
          INNER JOIN
            " . $finishedOrderQuery . " ON outstanding.salesNum = finished.salesNum AND outstanding.qty = finished.qty";
        
        $finishedOrder = $connection->createCommand($finishedOrderQuery)->queryColumn();
        $finishedOrder = "'" . implode("', '", $finishedOrder) . "'";

        $salesStationQuery = "SELECT tr_salesmenu.salesNum FROM tr_salesmenu
            LEFT JOIN ms_branchmenu ON ms_branchmenu.menuID = tr_salesmenu.menuID
            WHERE tr_salesmenu.salesNum IN ($salesHeadNum)
            $stationFilter
            GROUP BY tr_salesmenu.salesNum
        ";
        $salesStation = "'" . implode("', '", $connection->createCommand($salesStationQuery)->queryColumn()) . "'";

        $filterSalesStation = $stationID !== 0 ? "AND (tr_saleshead.salesNum IN ($salesStation))" : '';
        
        $outstandingQueueOrderQuery = "SELECT
            tr_saleshead.queueNum,
            tr_saleshead.salesNum,
            IFNULL(tr_customertransaction.fullName,tr_saleshead.additionalInfo) AS additionalInfo,
            ms_visitpurpose.visitPurposeName,
            ms_table.tableName
          FROM
            tr_saleshead
          LEFT JOIN
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
          LEFT JOIN
            tr_customertransaction ON tr_saleshead.salesNum = tr_customertransaction.salesNum
          LEFT JOIN
            ms_table ON tr_saleshead.tableID = ms_table.tableID
          WHERE
            salesDate = '$shiftIn'
            $filterSalesStation
            AND tr_saleshead.salesNum NOT IN ($finishedOrder)
            AND tr_saleshead.salesDateOut IS NOT NULL
            AND tr_saleshead.statusID NOT IN (12, 24)
            AND tr_saleshead.visitPurposeID IN ($visitPurposeListStringArray)
          ORDER BY tr_saleshead.queueNum ASC";

        $outstandingQueueOrderModel = $connection->createCommand($outstandingQueueOrderQuery)->queryAll();

        $finishedQueueOrderQuery = "SELECT
            tr_saleshead.queueNum,
            tr_saleshead.salesNum,
            IFNULL(tr_customertransaction.fullName,tr_saleshead.additionalInfo) AS additionalInfo,
            ms_visitpurpose.visitPurposeName,
            ms_table.tableName
          FROM
            tr_saleshead
          LEFT JOIN
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
          LEFT JOIN
            tr_customertransaction ON tr_saleshead.salesNum = tr_customertransaction.salesNum
          LEFT JOIN
            ms_table ON tr_saleshead.tableID = ms_table.tableID
          INNER JOIN
            tr_salesmenucompletion ON tr_saleshead.salesNum = tr_salesmenucompletion.salesNum
          WHERE
            salesDate = '$shiftIn'
            $filterSalesStation
            AND ((tr_saleshead.salesNum IN ($finishedOrder)) OR (tr_saleshead.statusID IN (12, 24)))
            AND tr_saleshead.visitPurposeID IN ($visitPurposeListStringArray)
        GROUP BY tr_saleshead.salesNum, tr_saleshead.queueNum, tr_saleshead.salesDate,
                tr_saleshead.additionalInfo, tr_salesmenucompletion.completedDate
        ORDER BY tr_salesmenucompletion.completedDate DESC
        LIMIT 8";

        $finishedQueueOrderModel = $connection->createCommand($finishedQueueOrderQuery)->queryAll();

        $readyQueueOrderQuery = "SELECT
            tr_saleshead.salesNum,
            tr_saleshead.queueNum,
            tr_saleshead.salesDate,
            IFNULL(tr_customertransaction.fullName, tr_saleshead.additionalInfo) AS additionalInfo,
            MAX(tr_salesmenucompletion.completedDate) AS completedDate,
            ms_visitpurpose.visitPurposeName,
            ms_table.tableName
          FROM
            tr_saleshead
          LEFT JOIN
            ms_visitpurpose ON tr_saleshead.visitPurposeID = ms_visitpurpose.visitPurposeID
          LEFT JOIN
            tr_customertransaction ON tr_saleshead.salesNum = tr_customertransaction.salesNum
          LEFT JOIN
            ms_table ON tr_saleshead.tableID = ms_table.tableID
          INNER JOIN
            tr_salesmenucompletion ON tr_saleshead.salesNum = tr_salesmenucompletion.salesNum
          WHERE
            salesDate = '$shiftIn'
            $filterSalesStation
            AND tr_saleshead.salesNum IN ($finishedOrder)
            AND tr_saleshead.visitPurposeID IN ($visitPurposeListStringArray)
          GROUP BY tr_saleshead.salesNum, tr_saleshead.queueNum, tr_saleshead.salesDate,
                   tr_saleshead.additionalInfo, tr_salesmenucompletion.completedDate
          ORDER BY tr_salesmenucompletion.completedDate DESC
          LIMIT 1";

        $readyQueueOrderModel = $connection->createCommand($readyQueueOrderQuery)->queryAll();
        
        $outstandingQueueOrderDetail = [];
        if($outstandingQueueOrderModel) {
            foreach($outstandingQueueOrderModel as $detail) {
                if(!isset($finishedQueueOrderDetail[$detail['queueNum']]['order'])){
                    $outstandingQueueOrderDetail[$detail['queueNum']]['order'] = $detail['queueNum'];
                    $outstandingQueueOrderDetail[$detail['queueNum']]['additionalInfo'] = $detail['additionalInfo'] ? $detail['additionalInfo'] : '-';
                    $outstandingQueueOrderDetail[$detail['queueNum']]['visitPurposeName'] = $detail['visitPurposeName'];
                    $outstandingQueueOrderDetail[$detail['queueNum']]['tableName'] = self::getTableName($detail);
                }
            }
        }
        $outstandingQueueOrderDetail = array_values($outstandingQueueOrderDetail);

        $finishedQueueOrderDetail = [];
        if ($finishedQueueOrderModel) {
            foreach ($finishedQueueOrderModel as $detail) {
                $finishedQueueOrderDetail[$detail['queueNum']]['order'] = $detail['queueNum'];
                $finishedQueueOrderDetail[$detail['queueNum']]['additionalInfo'] = $detail['additionalInfo'] ? $detail['additionalInfo'] : '-';
                $finishedQueueOrderDetail[$detail['queueNum']]['visitPurposeName'] = $detail['visitPurposeName'];
                $finishedQueueOrderDetail[$detail['queueNum']]['tableName'] = self::getTableName($detail);
            }
        }
        $finishedQueueOrderDetail = array_values($finishedQueueOrderDetail);

        $readyQueueOrderDetail = [];
        if($readyQueueOrderModel) {
            $readyQueueOrderDetail['order'] = $readyQueueOrderModel[0]['queueNum'];
            $readyQueueOrderDetail['additionalInfo'] = $readyQueueOrderModel[0]['additionalInfo'] ? $readyQueueOrderModel[0]['additionalInfo'] : '-';
            $readyQueueOrderDetail['visitPurposeName'] = $readyQueueOrderModel[0]['visitPurposeName'];
            $readyQueueOrderDetail['tableName'] = self::getTableName($readyQueueOrderModel[0]);
        }

        return [
            'outstanding' => $outstandingQueueOrderDetail,
            'finish' => $finishedQueueOrderDetail,
            'ready' => $readyQueueOrderDetail
        ];
    }

    public static function getOutstandingQueueOrder($stationID = null, $visitPurposeID = null) {
        $mode = Setting::getValue1('POS', 'ODS Mode') ? Setting::getValue1('POS', 'ODS Mode') : 0;
        $modePackage = Setting::getValue1('POS', 'Finish All Packages') ? Setting::getValue1('POS', 'Finish All Packages') : 0;
        
        $salesHeadNum = SalesHead::find()
        ->select(
            'tr_saleshead.salesNum'
        )
        ->where(['salesDate' => ShiftLog::getShiftInDate()])
        ->andWhere(['<>', 'salesDateOut', 'NULL'])
        ->column();
             
        $station = $stationID !== 0 ? $stationID : null;
        $stationFilter = ['=', 'ms_branchmenu.stationID', $station];

        $visitPurposeList = [];
        if ($visitPurposeID !== null) {
            $visitPurposeList = explode(",", $visitPurposeID);
        }
        
        $subQuery = SalesMenu::find()
            ->select([
                'tr_salesmenu.salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
                ])
            ->joinWith(['menu.branchMenu'])
            ->where(['NOT IN', 'tr_salesmenu.statusID', [12, 19]])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        
        $subQuery2 = SalesMenu::find()
            ->select([
                'tr_salesmenu.salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
            ])
            ->joinWith(['menu.branchMenu'])
            ->where(['tr_salesmenu.statusID' => 14])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        
        $salesStation = SalesMenu::find()
            ->select([
                'tr_salesmenu.salesNum'
            ])
            ->joinWith(['menu.branchMenu'])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['salesNum']);

        $filterSalesStation = $stationID !== 0 ? ['IN', 'salesNum', $salesStation] : [] ;
        
        $finishedOrder = (new Query)
            ->select([
                'subQuery.salesNum'
            ])
            ->from(['subQuery' => $subQuery])
            ->join("INNER JOIN", ["queries" => $subQuery2],
                "subQuery.salesNum = queries.salesNum AND subQuery.qty = queries.qty");
        $orderModel = SalesHead::find()
            ->select([
                'queueNum' => new Expression('tr_saleshead.queueNum'),
                'additionalInfo' => new Expression('tr_saleshead.additionalInfo'),
                'visitPurposeName' => new Expression('ms_visitpurpose.visitPurposeName')
            ])
            ->joinWith(['visitPurpose'])
            ->where(['salesDate' => ShiftLog::getShiftInDate()])
            ->andFilterWhere($filterSalesStation)
            ->andWhere(['NOT IN', 'salesNum', $finishedOrder])
            ->andWhere(['<>', 'salesDateOut', 'NULL'])
            ->andWhere(['NOT IN','statusID', [12, 24]])
            ->andWhere(['IN', 'tr_saleshead.visitPurposeID', $visitPurposeList])
            ->all();

        $i = 0;
        $orderDetail = [];
        if($orderModel) {
            foreach($orderModel as $detail) {
                $orderDetail[$i]['order'] = $detail['queueNum'];
                $orderDetail[$i]['additionalInfo'] = $detail['additionalInfo'] ? $detail['additionalInfo'] : '-';
                $orderDetail[$i]['visitPurposeName'] = $detail['visitPurposeName'];
                $i++;
            }
        }
        $orderDetail = array_values($orderDetail);
        return $orderDetail;
    }
    
    public static function getFinishedQueueOrder($stationID = null, $visitPurposeID = null) {

        $salesHeadNum = SalesHead::find()
        ->select([
            'tr_saleshead.salesNum',
        ])
        ->where(['salesDate' => ShiftLog::getShiftInDate()])
        ->andWhere(['<>', 'salesDateOut', 'NULL'])
        ->column();
        
        $station = $stationID !== 0 ? $stationID : null;
        $stationFilter = ['=', 'ms_branchmenu.stationID', $station];

        $visitPurposeList = [];
        if ($visitPurposeID !== null) {
            $visitPurposeList = explode(",", $visitPurposeID);
        }
        
        $subQuery = SalesMenu::find()
            ->select([
                'tr_salesmenu.salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
                ])
            ->joinWith(['menu.branchMenu'])
            ->where(['NOT IN', 'tr_salesmenu.statusID', [12, 19]])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        
        $subQuery2 = SalesMenu::find()
            ->select([
                'salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
            ])
            ->joinWith(['menu.branchMenu'])
            ->where(['tr_salesmenu.statusID' => 14])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        $salesStation = SalesMenu::find()
            ->select([
                'salesNum'
            ])
            ->joinWith(['menu.branchMenu'])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['salesNum']);
        $filterSalesStation = $stationID !== 0 ? ['IN', 'salesNum', $salesStation] : [] ;
        
        $finishedOrder = (new Query)
            ->select([
                'subQuery.salesNum'
            ])
            ->from(['subQuery' => $subQuery])
            ->join("INNER JOIN", ["queries" => $subQuery2],
                "subQuery.salesNum = queries.salesNum AND subQuery.qty = queries.qty");

        $orderModel = SalesHead::find()
            ->select([
                'tr_saleshead.queueNum',
                'tr_saleshead.additionalInfo',
                'visitPurposeName' => new Expression('ms_visitpurpose.visitPurposeName')
            ])
            ->joinWith(['visitPurpose'])
            ->where(['salesDate' => ShiftLog::getShiftInDate()])
            ->andFilterWhere($filterSalesStation)
            ->andWhere(['OR',
                    ['IN','salesNum', $finishedOrder],
                    ['IN','statusID', [12, 24]]
            ])
            ->andWhere(['IN', 'tr_saleshead.visitPurposeID', $visitPurposeList])
            ->orderBy('salesDateIn DESC')
            ->limit(6)
            ->all();
        $i = 0;
        $orderDetail = [];
        if($orderModel) {
            foreach($orderModel as $detail) {
                $orderDetail[$i]['order'] = $detail['queueNum'];
                $orderDetail[$i]['additionalInfo'] = $detail['additionalInfo'] ? $detail['additionalInfo'] : '-';
                $orderDetail[$i]['visitPurposeName'] = $detail['visitPurposeName'];
                $i++;
            }
        }
        $orderDetail = array_values($orderDetail);
        return $orderDetail;
    }
    
    public static function getReadyQueueOrder($stationID = null, $visitPurposeID = null) {

        $salesHeadNum = SalesHead::find()
        ->select([
            'tr_saleshead.salesNum',
        ])
        ->where(['salesDate' => ShiftLog::getShiftInDate()])
        ->andWhere(['<>', 'salesDateOut', 'NULL'])
        ->column();
        
        $station = $stationID !== 0 ? $stationID : null;
        $stationFilter = ['=', 'ms_branchmenu.stationID', $station];

        $visitPurposeList = [];
        if ($visitPurposeID !== null) {
            $visitPurposeList = explode(",", $visitPurposeID);
        }

        $subQuery = SalesMenu::find()
            ->select([
                'tr_salesmenu.salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
                ])
            ->joinWith(['menu.branchMenu'])
            ->where(['NOT IN', 'tr_salesmenu.statusID', [12, 19]])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        
        $subQuery2 = SalesMenu::find()
            ->select([
                'salesNum',
                'qty' => new Expression('SUM(tr_salesmenu.qty)')
            ])
            ->joinWith(['menu.branchMenu'])
            ->where(['tr_salesmenu.statusID' => 14])
            ->andWhere(['IN', 'tr_salesmenu.salesNum', $salesHeadNum])
            ->andFilterWhere($stationFilter)
            ->groupBy(['tr_salesmenu.salesNum']);
        
        $finishedOrder = (new Query)
            ->select([
                'subQuery.salesNum'
            ])
            ->from(['subQuery' => $subQuery])
            ->join("INNER JOIN", ["queries" => $subQuery2],
                "subQuery.salesNum = queries.salesNum AND subQuery.qty = queries.qty");
        
        $orderModel = SalesHead::find()
            ->select([
                'tr_saleshead.salesNum',
                'tr_saleshead.queueNum',
                'tr_saleshead.salesDate',
                'tr_saleshead.additionalInfo',
                'completedDate' => new Expression('MAX(tr_salesmenucompletion.completedDate)'),
                'visitPurposeName' => new Expression('ms_visitpurpose.visitPurposeName')
            ])
            ->joinWith(['visitPurpose'])
            ->join("INNER JOIN", SalesMenuCompletion::tableName(),
                "tr_saleshead.salesNum = tr_salesmenucompletion.salesNum")
            ->where(['salesDate' => ShiftLog::getShiftInDate()])
            ->andWhere(['IN','tr_saleshead.salesNum', $finishedOrder])
            ->andWhere(['IN', 'tr_saleshead.visitPurposeID', $visitPurposeList])
            ->groupBy([
                'tr_saleshead.salesNum',
                'tr_saleshead.queueNum',
                'tr_saleshead.salesDate',
                'tr_saleshead.additionalInfo',
                'tr_salesmenucompletion.completedDate'
            ])
            ->orderBy('tr_salesmenucompletion.completedDate DESC')
            ->limit(1)
            ->one();
        
        $i = 0;
        $orderDetail = [];
        if($orderModel) {
            $orderDetail['order'] = $orderModel['queueNum'];
            $orderDetail['additionalInfo'] = $orderModel['additionalInfo'] ? $orderModel['additionalInfo'] : '-';
            $orderDetail['visitPurposeName'] = $orderModel['visitPurposeName'];
        }
        return $orderDetail;
    }

    public static function getHistoryOrder($filterDate=null, $viewMode=null, $stationID=null) {
        if($filterDate == null){
            $filterDate = date("Y-m-d");
        }

        $stationList = explode(",", $stationID);

		$stationCondition = [];
		$checkerStationCondition = [];
		foreach ($stationList as $station) {
			$stationCondition[] = [
				"like", "concat(',',ms_branchmenu.stationID,',')", ",$station,"
			];
			
			$checkerStationCondition[] = [
				"like", "concat(',',ms_branchmenu.checkerStationID,',')", ",$station,"
			];
		}
        
		$stationFilter = $viewMode == 1 ? array_merge(['OR'], $stationCondition) : 
		array_merge(['OR'], $checkerStationCondition);
        
        $andWhereStationFilter = $viewMode == 1 ? [
            '<>', 'ms_branchmenu.stationID', 0
        ] : ['<>', 'ms_branchmenu.checkerStationID', 0];

        $salesMenuCompletionModel = SalesMenuCompletion::find()
            ->with('salesMenu.salesHead')
            ->joinWith('salesMenu.branchMenu')
            ->where([
                'DATE(completedDate)' => $filterDate
            ])
            ->andWhere(['=', 'menuGroupID', 0])
            ->andFilterWhere(['typeID' => $viewMode])
            ->andFilterWhere($stationFilter)
            ->andWhere($andWhereStationFilter)
            ->orderBy('completedDate DESC')
            ->all();
        
        $newSalesMenuCompletion = [];
        if ($salesMenuCompletionModel) {
            $i=0;
            $packageMenuIDs = [];
            foreach ($salesMenuCompletionModel as $salesMenuCompletion) {
                if (isset($salesMenuCompletion->salesMenu->childSalesMenus) && count($salesMenuCompletion->salesMenu->childSalesMenus) > 0) {
                    foreach ($salesMenuCompletion->salesMenu->childSalesMenus as $package) {
                        if (!in_array($package->menuID, $packageMenuIDs)) {
                            $packageMenuIDs[] = $package->menuID;
                        }
                    }
                }
            }
            $menus = [];
            if (count($packageMenuIDs) > 0) {
                $menuModel = Menu::find()
                    ->where(['flagActive' => 1])
                    ->andWhere(['IN', 'menuID', $packageMenuIDs])
                    ->all();
                
                if ($menuModel) {
                    foreach ($menuModel as $menu) {
                        if (in_array($menu->menuID, $packageMenuIDs)) {
                            $menus[] = $menu;
                        }
                    }
                }
            }

            foreach ($salesMenuCompletionModel as $salesMenuCompletion) {
                $newSalesMenuCompletion[$i]['ID'] = $salesMenuCompletion->ID;
                $newSalesMenuCompletion[$i]['localID'] = $salesMenuCompletion->localID;
                $newSalesMenuCompletion[$i]['salesNum'] = $salesMenuCompletion->salesNum;
                $newSalesMenuCompletion[$i]['salesMenuID'] = $salesMenuCompletion->salesMenuID;
                $newSalesMenuCompletion[$i]['qty'] = (float) $salesMenuCompletion->qty;
                $newSalesMenuCompletion[$i]['completedDate'] = $salesMenuCompletion->completedDate;
                $newSalesMenuCompletion[$i]['typeID'] = $salesMenuCompletion->typeID;
                $newSalesMenuCompletion[$i]['startDate'] = $salesMenuCompletion->startDate;
                $newSalesMenuCompletion[$i]['syncDate'] = $salesMenuCompletion->syncDate;
                $newSalesMenuCompletion[$i]['menuCategoryID'] = $salesMenuCompletion->salesMenu->menu->menuCategoryDetail->menuCategoryID;
                $newSalesMenuCompletion[$i]['menuCategoryDetailID'] = $salesMenuCompletion->salesMenu->menu->menuCategoryDetailID;
                $newSalesMenuCompletion[$i]['menuID'] = $salesMenuCompletion->salesMenu->menu->menuID;
                $newSalesMenuCompletion[$i]['menuName'] = $salesMenuCompletion->salesMenu->menu->menuName;
                $newSalesMenuCompletion[$i]['menuShortName'] = $salesMenuCompletion->salesMenu->menu->menuShortName;
                $newSalesMenuCompletion[$i]['customMenuName'] = $salesMenuCompletion->salesMenu->customMenuName;
                $newSalesMenuCompletion[$i]['menuCode'] = $salesMenuCompletion->salesMenu->menu->menuCode;
                $newSalesMenuCompletion[$i]['tableName'] = $salesMenuCompletion->salesMenu->salesHead->tableID == 0 ? 'Quick Service' : $salesMenuCompletion->salesMenu->salesHead->table->tableName;
                $newSalesMenuCompletion[$i]['visitPurposeName'] = $salesMenuCompletion->salesMenu->salesHead->visitPurpose->visitPurposeName;
                $newSalesMenuCompletion[$i]['statusName'] = $salesMenuCompletion->salesMenu->status->statusName;
                $newSalesMenuCompletion[$i]['completedTime'] = date("H:i:s", strtotime($salesMenuCompletion->completedDate));
                $newSalesMenuCompletion[$i]['packages'] = self::newPackages($salesMenuCompletion->salesMenu->childSalesMenus, $menus);
                $newSalesMenuCompletion[$i]['extras'] = $salesMenuCompletion->salesMenu->salesExtras;
                $newSalesMenuCompletion[$i]['queue'] = $salesMenuCompletion->salesMenu->salesHead->queueNum;
                $i++;
            }
        }
        return $newSalesMenuCompletion;
    }

    private static function newPackages($currentPackages, $menus) {
        $newPackages = [];
        if (count($currentPackages) > 0) {
            foreach ($currentPackages as $key => $package) {
                $newPackages[$key]['ID'] = $package->ID;
                $newPackages[$key]['batchID'] = $package->batchID;
                $newPackages[$key]['cancelNotes'] = $package->cancelNotes;
                $newPackages[$key]['createdBy'] = $package->createdBy;
                $newPackages[$key]['createdDate'] = $package->createdDate;
                $newPackages[$key]['customMenuName'] = $package->customMenuName;
                $newPackages[$key]['discount'] = $package->discount;
                $newPackages[$key]['discountValue'] = $package->discountValue;
                $newPackages[$key]['editedBy'] = $package->editedBy;
                $newPackages[$key]['editedDate'] = $package->editedDate;
                $newPackages[$key]['flagPending'] = $package->flagPending;
                $newPackages[$key]['inclusiveDiscountValue'] = $package->inclusiveDiscountValue;
                $newPackages[$key]['inclusivePrice'] = $package->inclusivePrice;
                $newPackages[$key]['localID'] = $package->localID;
                $newPackages[$key]['menuGroupID'] = $package->menuGroupID;
                $newPackages[$key]['menuID'] = $package->menuID;
                $newPackages[$key]['menuPromotionID'] = $package->menuPromotionID;
                $newPackages[$key]['menuRefID'] = $package->menuRefID;
                $newPackages[$key]['notes'] = $package->notes;
                $newPackages[$key]['originalPrice'] = $package->originalPrice;
                $newPackages[$key]['otherTax'] = $package->otherTax;
                $newPackages[$key]['otherTaxOnVat'] = $package->otherTaxOnVat;
                $newPackages[$key]['otherTaxValue'] = $package->otherTaxValue;
                $newPackages[$key]['price'] = $package->price;
                $newPackages[$key]['promotionDetailID'] = $package->promotionDetailID;
                $newPackages[$key]['promotionVoucherCode'] = $package->promotionVoucherCode;
                $newPackages[$key]['qty'] = $package->qty;
                $newPackages[$key]['salesNum'] = $package->salesNum;
                $newPackages[$key]['salesType'] = $package->salesType;
                $newPackages[$key]['statusID'] = $package->statusID;
                $newPackages[$key]['syncDate'] = $package->syncDate;
                $newPackages[$key]['total'] = $package->total;
                $newPackages[$key]['vat'] = $package->vat;
                $newPackages[$key]['vatValue'] = $package->vatValue;
                $newPackages[$key]['menuShortName'] = null;
                if ($menus) {
                    foreach ($menus as $menu) {
                        if ($package->menuID === $menu->menuID) {
                            $newPackages[$key]['menuShortName'] = $menu->menuShortName;
                        }
                    }
                }
            }
        }
        return $newPackages;
    }
}
