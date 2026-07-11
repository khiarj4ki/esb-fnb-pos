<?php
namespace app\models;

use DateTime;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "ms_table".
 *
 * @property int $tableID
 * @property int $tableTypeID
 * @property string $tableName
 * @property string $tableSeat
 * @property int $tableSectionID
 * @property string $tableMinimumBilling
 * @property string $tableChargeFee
 * @property string $notes
 * @property string $posX
 * @property string $posY
 * @property int $widthRes
 * @property int $heightRes
 * @property int $stationID
 * @property int $flagActive
 * @property int $flagAvailableForBooking
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property TableType $tableType
 * @property TableSection $tableSection
 * @property TableUsage $tableUsage
 */
class Table extends ActiveRecord {
    public $tableTypeName;
    public $salesNum;
    public $billingPrintCount;
    public $orderCount;
    public $mergeTableID;
    public $mergeTableName;
    public $parentTableID;
    public $parentTableName;
    public $visitPurposeID;
    public $flagInclusive;
    public $additionalInfo;
    public $promotionID;

    //@optimaze get statusID hold
    public $statusID;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_table';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableTypeID', 'tableName', 'tableSeat', 'tableSectionID', 'tableMinimumBilling', 'tableChargeFee', 'posX', 'posY', 'widthRes', 'heightRes', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['tableTypeID', 'tableSectionID', 'widthRes', 'heightRes', 'flagActive'], 'integer'],
            [['tableMinimumBilling', 'tableChargeFee', 'posX', 'posY'], 'number'],
            [['tableID', 'createdDate', 'editedDate', 'stationID', 'flagAvailableForBooking', 'additionalInfo','statusID'], 'safe'],
            [['tableName', 'tableSeat'], 'string', 'max' => 50],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'tableID' => 'Table ID',
            'tableTypeID' => 'Table Type ID',
            'tableName' => 'Table Name',
            'tableSeat' => 'Table Seat',
            'tableSectionID' => 'Table Section ID',
            'tableMinimumBilling' => 'Table Minimum Billing',
            'tableChargeFee' => 'Table Charge Fee',
            'notes' => 'Notes',
            'posX' => 'Pos X',
            'posY' => 'Pos Y',
            'widthRes' => 'Width Res',
            'heightRes' => 'Height Res',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'flagAvailableForBooking' => 'Available For Book',
        ];
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['tableID' => 'tableID'])
            ->andOnCondition(['IS', SalesHead::tableName() . '.salesDateOut', null]);
    }

    public function getTableType() {
        return $this->hasOne(TableType::class, ['tableTypeID' => 'tableTypeID']);
    }

    public function getTableSection() {
        return $this->hasOne(TableSection::class,
                ['tableSectionID' => 'tableSectionID']);
    }

    public function getTableUsage() {
        return $this->hasOne(TableUsage::class, ['referenceID' => 'tableID']);
    }

    public static function findActive() {
        return Table::find()->andWhere([Table::tableName() . '.flagActive' => 1])
                ->orderBy(Table::tableName() . '.tableName');
    }
    
    public static function checkActive($tableID) {
        $tableModel = Table::find()->where(['tableID' => $tableID])->one();
        if($tableModel){
            return true;
        } else return false;
    }

    public static function findAllAsArray($token, $flagAvailableForBooking = 0, $terminalCode = null, $activatedDate = null) {
        $branchID = Setting::getCurrentBranch();

        $userModel = null;
        if ($token) {
            $userModel = PosUser::find()
                ->andWhere(['posAuthKey' => $token])
                ->one();
        }

        $flagAvailableForBookingFilter = 'IN (0, 1)';
        if ($flagAvailableForBooking > 0) {
            $flagAvailableForBookingFilter = '= 1';
        }

        $connection = Yii::$app->getDb();
        $queryTableModel = " SELECT
                    ms_table.tableID,
                    ms_table.tableName,
                    ms_table.posX,
                    ms_table.posY,
                    ms_table.widthRes,
                    ms_table.heightRes,
                    ms_table.tableTypeID,
                    ms_table.tableSectionID,
                    lk_tabletype.tableTypeName,
                    tr_saleshead.salesNum,
                    tr_saleshead.billingPrintCount,
                    tr_saleshead.visitPurposeID,
                    tr_saleshead.flagInclusive,
                    ms_table.flagAvailableForBooking,
                    COALESCE(menu.orderCount, 0) AS orderCount,
                    mergeparent.tableID AS mergeTableID,
                    mergeparent.tableName AS mergeTableName,
                    link.tableID AS parentTableID,
                    link.tableName AS parentTableName,
                    tr_saleshead.additionalInfo,
                    tr_saleshead.promotionID,
                    tr_salesmenu.statusID,
                    ms_tablesection.tableSectionName,
                    ms_tablesection.image,
                    tr_tableusage.referenceID,
                    tr_tableusage.expiredTime,
                    tr_tableusage.username,
                    ms_posuser.fullName,
                    ms_paymentmethod.voucherSourceID,
                    ms_paymentmethod.posExternalPaymentID
                FROM
                    ms_table
                LEFT JOIN lk_tabletype
                    ON ms_table.tableTypeID = lk_tabletype.tableTypeID
                LEFT JOIN ms_tablesection
                    ON ms_table.tableSectionID = ms_tablesection.tableSectionID
                LEFT JOIN (
                        SELECT
                            tr_saleshead.salesNum,
                            tr_salesmergetable.tableID
                        FROM
                            tr_salesmergetable
                        LEFT JOIN tr_saleshead
                            ON tr_salesmergetable.salesNum = tr_saleshead.salesNum
                        WHERE (branchID = $branchID)
                        AND (salesDateOut IS NULL)
                ) merge
                    ON ms_table.tableID = merge.tableID
                LEFT JOIN tr_saleshead
                    ON ( ms_table.tableID = tr_saleshead.tableID OR merge.salesNum = tr_saleshead.salesNum ) AND tr_saleshead.salesDateOut IS NULL
                LEFT JOIN (
                    SELECT
                        tr_salesmenu.salesNum,
                        COUNT(*) AS orderCount
                    FROM
                        tr_salesmenu
                    LEFT JOIN tr_saleshead
                    ON tr_salesmenu.salesNum = tr_saleshead.salesNum
                    WHERE (branchID = $branchID)
                    AND (salesDateOut IS NULL)
                    GROUP BY salesNum
                ) menu
                    ON tr_saleshead.salesNum = menu.salesNum
                LEFT JOIN ms_table mergeparent
                    ON tr_saleshead.tableID = mergeparent.tableID
                    AND EXISTS ( SELECT salesNum FROM tr_salesmergetable WHERE salesNum = tr_saleshead.salesNum)
                LEFT JOIN (
                        SELECT
                            tr_saleslink.salesNum,
                            tr_saleslink.linkSalesNum,
                            tr_saleshead.tableID,
                            tableName
                        FROM
                            tr_saleslink
                        LEFT JOIN tr_saleshead
                            ON tr_saleslink.salesNum = tr_saleshead.salesNum
                        LEFT JOIN ms_table
                            ON tr_saleshead.tableID = ms_table.tableID
                        WHERE (branchID = $branchID)
                        AND (salesDateOut IS NULL)
                ) link
                    ON tr_saleshead.salesNum = link.linkSalesNum OR tr_saleshead.salesNum = link.salesNum
                LEFT JOIN tr_salesmenu
                    ON tr_salesmenu.salesNum = tr_saleshead.salesNum AND tr_salesmenu.statusID = 46
                LEFT JOIN tr_tableusage
                    ON tr_tableusage.referenceID = tr_saleshead.salesNum
                LEFT JOIN ms_posuser
                    ON tr_tableusage.username = ms_posuser.username
                LEFT JOIN tr_salespayment
                    ON tr_salespayment.salesNum = tr_saleshead.salesNum
                LEFT JOIN ms_paymentmethod
                    ON ms_paymentmethod.paymentMethodID = tr_salespayment.paymentMethodID
                WHERE (ms_table.flagActive = 1)
                    AND (ms_tablesection.branchID = $branchID)
                    AND ( ms_tablesection.flagActive = 1)
                    AND (ms_table.flagAvailableForBooking $flagAvailableForBookingFilter)
                GROUP BY
                    ms_table.tableID,
                    ms_table.tableName,
                    ms_table.posX,
                    ms_table.posY,
                    ms_table.widthRes,
                    ms_table.heightRes,
                    ms_table.tableTypeID,
                    ms_table.tableSectionID,
                    lk_tabletype.tableTypeName,
                    tr_saleshead.salesNum,
                    tr_saleshead.billingPrintCount,
                    tr_saleshead.visitPurposeID,
                    tr_saleshead.flagInclusive,
                    ms_table.flagAvailableForBooking,
                    orderCount,
                    mergeparent.tableID,
                    mergeparent.tableName,
                    link.tableID,
                    link.tableName,
                    tr_saleshead.additionalInfo,
                    tr_saleshead.promotionID,
                    tr_salesmenu.statusID,
                    ms_tablesection.tableSectionName,
                    ms_tablesection.image,
                    tr_tableusage.referenceID,
                    tr_tableusage.expiredTime,
                    tr_tableusage.username,
                    ms_posuser.fullName,
                    ms_paymentmethod.voucherSourceID,
                    ms_paymentmethod.posExternalPaymentID
                ORDER BY
                    ms_tablesection.tableSectionName,
                    ms_table.tableID,
                    tr_saleshead.additionalInfo;";
        
        $tableModel = $connection->createCommand($queryTableModel)->queryAll();
        
        $lockTerminal = false;
        if ($terminalCode && $activatedDate) {
            $terminalModel = Terminal::findOne([
                'terminalCode' => $terminalCode,
            ]);
            $tempActivatedDate = date('Y-m-d H:i:s', $activatedDate);
            if ($terminalModel) {
                if ($terminalModel->activatedDate == $tempActivatedDate) $lockTerminal = true;
            }
        }

        $salesNumArray = [];
        foreach ($tableModel as $tables) {
            if ($tables['salesNum']) {
                if (strpos($tables['salesNum'], '-') !== false) {
                    $subsSalesNum = substr($tables['salesNum'], 0, strpos($tables['salesNum'], '-'));
                    $salesNumArray[] = "'".$subsSalesNum."'";
                }
                $salesNumArray[] = "'".$tables['salesNum']."'";
            }
        }

        $currentShift = ShiftLog::getShiftInDate();
        $currentDate = date('Y-m-d');
        $salesNumArray = array_unique($salesNumArray);

        $salesNumArrays = implode(", ", $salesNumArray);
        $whereSalesNum = $salesNumArrays ? "(salesNum IN ($salesNumArrays))": "(0=1)";
        $salesModelQuery = "
            SELECT * FROM tr_saleshead
            WHERE ($whereSalesNum OR (salesNum LIKE '%-%'))
            AND (salesDate BETWEEN '$currentShift' AND '$currentDate')";
            
        $salesModelArray = $connection->createCommand($salesModelQuery)->queryAll();
            
        $salesNumPromoArrayMenu = [];
        foreach ($salesModelArray as $data) {
                $salesNumPromoArrayMenu[] = "'".$data['salesNum']."'";
        }

        $salesNumConditonalPromoArrays = implode(", ", $salesNumPromoArrayMenu);
        $whereSalesNumConditional = $salesNumConditonalPromoArrays ? "salesNum IN ($salesNumConditonalPromoArrays)": "(0=1)";

        $salesConditonalPromoQuery = "
            SELECT
            tr_salesmenu.*, ms_promotionhead.promotionTypeID
            FROM tr_salesmenu
            LEFT JOIN ms_promotionhead
            ON ms_promotionhead.promotionID = tr_salesmenu.promotionDetailID
            WHERE promotiondetailID > 0
            AND statusID <> 19
            AND $whereSalesNumConditional";

        $salesConditonalPromoModelArray = $connection->createCommand($salesConditonalPromoQuery)->queryAll();

        $salesMenuArrayCondtionalPromo= [];
        if($salesConditonalPromoModelArray) {
            foreach ($salesConditonalPromoModelArray as $data) {
                    $salesMenuArrayCondtionalPromo[$data['salesNum']]['promotionDetailID'] = $data['promotionDetailID'];
                    $salesMenuArrayCondtionalPromo[$data['salesNum']]['statusID'] = $data['statusID'];
                    $salesMenuArrayCondtionalPromo[$data['salesNum']]['promotionTypeID'] = $data['promotionTypeID'];
            }
        }
      
        $trialMode = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => 'Trial Mode'])
            ->one();

        $warningSetting = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => 'Timer Warning'])
            ->one();
        
        $dangerSetting = Setting::find()
            ->andWhere(['key1' => 'Local Setting'])
            ->andWhere(['key2' => 'Timer Danger'])
            ->one();

        $i = 0;
        $tableData = [];
        foreach ($tableModel as $table) {
            $tableData[$table['tableSectionID']]['tableSectionID'] = $table['tableSectionID'];
            $tableData[$table['tableSectionID']]['tableSectionName'] = $table['tableSectionName'];
            $tableData[$table['tableSectionID']]['image'] = $table['image'];
            $tableStatusID = 0;
            $tableStatusName = 'Available';
            $tableStatusClass = 'primary';
            if (isset($table['salesNum']) && $table['orderCount'] == 0) {
                $tableStatusID = 1;
                $tableStatusName = 'Booked';
                $tableStatusClass = 'warning';
            }
            if (isset($table['salesNum']) && $table['orderCount'] > 0) {
                $tableStatusID = 2;
                $tableStatusName = 'Occupied';
                $tableStatusClass = 'danger';
            }
            if ($table['billingPrintCount'] > 0) {
                $tableStatusID = 3;
                $tableStatusName = 'Billed';
                $tableStatusClass = 'success';
            }

            $salesModel = null;
            $isExternalMembershipTypeIDLoyalty = false;
            $isHavingAssignMember = false;
            $isHavingSplitBill = false;
            $isHavingConditionalPromo = false;
            $isHavingUltraVoucherPayment = false;
            $isHavingUvlPayment = false;
            if ($table['salesNum']) {
                $tempSalesNums = $table['salesNum'];
                if (strpos($table['salesNum'], '-') !== false) {
                    $tempSalesNums = substr($table['salesNum'], 0, strpos($table['salesNum'], '-'));
                }

                foreach ($salesModelArray as $sales) {
                    if (strpos($sales['salesNum'], $tempSalesNums.'-') !== false) {
                        $splitBillParentSales[$tempSalesNums] = $tempSalesNums;
                    }
                }

                foreach ($salesModelArray as $sales) {
                    if ($tempSalesNums == $sales['salesNum']) {
                        $salesModel = $sales;
                    }

                    if (isset($splitBillParentSales[$sales['salesNum']])) {
                        $splitBillParentSales[$sales['salesNum']] = $sales;
                    }

                    if (strpos($sales['salesNum'], $tempSalesNums.'-') !== false) {
                        $childSalesNum = substr($sales['salesNum'], 0, strpos($sales['salesNum'], '-'));
                        if (isset($splitBillParentSales[$childSalesNum])) {
                            $parentSales = $splitBillParentSales[$childSalesNum];
                            if ($parentSales['tableID'] == $sales['tableID'] && $parentSales['tableID'] == $table['tableID']) {
                                $isHavingSplitBill = true;
                            }
                        }
                    }
                }
                if ($salesModel) {
                    $isExternalMembershipTypeIDLoyalty = $salesModel['externalMembershipTypeID'] && ($salesModel['externalMembershipTypeID'] == 'esbloyalty' || $salesModel['externalMembershipTypeID'] == 'memberid') ? true : false;
                    $isHavingAssignMember = $salesModel['externalMembershipTypeID'] || ($salesModel['memberID'] && $salesModel['memberID'] > 0) ? true : false;
                    
                    if (isset($salesMenuArrayCondtionalPromo[$table['salesNum']])) {
                        if ($salesMenuArrayCondtionalPromo[$table['salesNum']]['promotionDetailID'] > 0 && $salesMenuArrayCondtionalPromo[$table['salesNum']]['statusID'] != 19) {
                            $promotionTypeID = $salesMenuArrayCondtionalPromo[$table['salesNum']]['promotionTypeID'];
                            if ($promotionTypeID == 18 || $promotionTypeID == 19) {
                                $isHavingConditionalPromo = true;
                            }
                        }
                    }
                }
                // @having ultra voucher payment
                if ($table['voucherSourceID'] == 14) {
                    $isHavingUltraVoucherPayment = true;
                }
                if($table['posExternalPaymentID'] === 'uvlpoint'){
                    $isHavingUvlPayment = true;
                }
            }

            $newSalesNum = $table['salesNum'];
            if ($isHavingSplitBill) {
                if (strpos($newSalesNum, '-') !== false) {
                    $newSalesNum = substr($table['salesNum'], 0, strpos($table['salesNum'], '-'));
                }
            }

            if (isset($tableData[$table['tableSectionID']]['tables'])) {
                $continueLoop = true;
                foreach ($tableData[$table['tableSectionID']]['tables'] as $currentTable) {
                    if ($currentTable['tableID'] == (int) $table['tableID'] && $currentTable['salesNum'] == $newSalesNum) {
                        $continueLoop = false;
                        break;
                    }
                }
                if (!$continueLoop)
                {
                 continue;
                }
            }

            $tableData[$table['tableSectionID']]['tables'][] = [
                'tableID' => (int) $table['tableID'],
                'tableName' => $table['tableName'],
                'salesNum' => $newSalesNum,
                'outstandingSalesMenu' => $table['orderCount'] > 0 ? true : false,
                'visitPurposeID' => $table['visitPurposeID'],
                'flagInclusive' => (int) $table['flagInclusive'],
                'posX' => (float) $table['posX'],
                'posY' => (float) $table['posY'],
                'widthRes' => $table['widthRes'],
                'heightRes' => $table['heightRes'],
                'tableTypeID' => (int) $table['tableTypeID'],
                'tableTypeName' => $table['tableTypeName'],
                'tableTypeClass' => strtolower(str_replace(' ', '-',
                        trim($table['tableTypeName']))),
                'tableStatusID' => (int) $tableStatusID,
                'tableStatusName' => $tableStatusName,
                'tableStatusClass' => $tableStatusClass,
                'mergeTableID' => (int) $table['mergeTableID'],
                'mergeTableName' => $table['mergeTableName'],
                'parentTableID' => (int) $table['parentTableID'],
                'parentTableName' => $table['parentTableName'],
                'occupiedTime' => $salesModel ? Table::getOccupiedTime(date_create($salesModel['salesDateIn']),
                    new DateTime()) : null,
                'timerClass' => $salesModel ? Table::getTimerClassColor(date_create($salesModel['salesDateIn']),
                    new DateTime(), $warningSetting, $dangerSetting) : '',
                'lockStatus' => Table::getLockStatus($table, $userModel ),
                'lockUser' => Table::getLockUser($table, $userModel),
                'lockUserFullName' => Table::getLockUser($table, $userModel, 'Full Name'),
                'isExternalMemberLoyalty' => $salesModel ? $isExternalMembershipTypeIDLoyalty : false,
                'isAnyHoldStatus' => $table['statusID'] ? true : false,
                'flagAvailableForBooking' => (int) $table['flagAvailableForBooking'],
                'isHavingSplitBill' => $isHavingSplitBill,
                'isHavingAssignMember' => $salesModel ? $isHavingAssignMember : false,
                'additionalInfo' => $table['additionalInfo'],
                'promotionID' => (int) $table['promotionID'],
                'lockTerminal' => $lockTerminal,
                'trialMode' => $trialMode ? (float) $trialMode->value1 : 1,
                'isHavingConditionalPromo' => $isHavingConditionalPromo,
                'isHavingUltraVoucherPayment' => $isHavingUltraVoucherPayment,
                'isHavingUvlPayment' => $isHavingUvlPayment
            ];

            $i++;
        }

        return array_values($tableData);
    }
    
    public static function findDropdownTableData() {
        $tableData = [];
        $tableModel = Table::findActive()
            ->select([
                Table::tableName() . '.tableID',
                Table::tableName() . '.tableName',
                TableSection::tableName() . '.tableSectionID',
                TableSection::tableName() . '.tableSectionName'
            ])
            ->joinWith('tableSection')
            ->orderBy(TableSection::tableName() . '.tableSectionName, ' . Table::tableName() . '.tableID')
            ->all();
        
        $tableData[0]['name'] = '';
        $tableData[0]['table'][] = [
            'value' => 0,
            'viewValue' => '- No Table -'
        ];
        foreach ($tableModel as $table) {
            $tableData[$table->tableSectionID]['name'] = $table->tableSection->tableSectionName;
            $tableData[$table->tableSectionID]['table'][] = [
                'value' => $table->tableID,
                'viewValue' => $table->tableName
            ];
        }
        
        return array_values($tableData);
    }

    public static function getOccupiedTime($startDate, $endDate) {
        // @Notes: Return in seconds
        $dateDiff = strtotime($endDate->format('Y-m-d H:i:s')) - strtotime($startDate->format('Y-m-d H:i:s'));

        $hours = floor($dateDiff / 60 / 60);
        $minutes = floor((($dateDiff / 60 / 60) - $hours) * 60);

        return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes,
                2, '0', STR_PAD_LEFT);
    }

    private static function getTimerClassColor($startDate, $endDate, $warningSetting, $dangerSetting) {
        $warningTime = 0;
        $dangerTime = 0;
        $warningClass = '';

        if ($warningSetting) {
            $warningTime = $warningSetting->value1;
        }
        if ($dangerSetting) {
            $dangerTime = $dangerSetting->value1;
        }

        if ($warningTime == 0 && $dangerTime == 0) {
            return $warningClass;
        }

        // @Notes: Return in seconds
        $dateDiff = strtotime($endDate->format('Y-m-d H:i:s')) - strtotime($startDate->format('Y-m-d H:i:s'));

        $minutes = $dateDiff / 60;
        if ($warningTime != 0 && $minutes > $warningTime) {
            $warningClass = 'timer-warning';
        }
        if ($dangerTime != 0 && $minutes > $dangerTime) {
            $warningClass = 'timer-danger';
        }

        return $warningClass;
    }

    private static function getLockStatus($table, $userModel) {
        if (!$table['salesNum']) {
            return false;
        }

        $now = new DateTime();
        $timeDiff = strtotime($table['referenceID'] ? $table['expiredTime'] : $now->format('Y-m-d H:i:s')) - strtotime($now->format('Y-m-d H:i:s'));
        if (!$userModel) {
            return $timeDiff > 0;
        } else {
            return $timeDiff > 0 && ($table['referenceID'] ? $table['username'] : '') != $userModel->username;
        }
    }

    private static function getLockUser($table, $userModel, $user = '') {
        if (Table::getLockStatus($table, $userModel)) {
            if ($user == 'Full Name') {
                return $table['salesNum'] ? $table['fullName'] : '';
            }
            return $table['salesNum'] ? $table['username'] : '';
        }

        return null;
    }
    
    public static function checkBookActive($tableID) {
        $salesNumExists = self::onCheckTableOrder($tableID);

        if($salesNumExists){
            $salesMenuCountQuery = SalesMenu::find()
                ->select([
                    SalesMenu::tableName() . '.salesNum',
                    'COUNT(*) AS orderCount'
                ])
                ->where([SalesMenu::tableName() . '.salesNum' => $salesNumExists])
                ->groupBy(['salesNum']);

            $salesModel = (new Query())
                ->select([
                    SalesHead::tableName() . '.salesNum',
                    SalesHead::tableName() . '.visitPurposeID',
                    SalesHead::tableName() . '.billingPrintCount',
                    SalesHead::tableName() . '.paxTotal',
                    'menu.orderCount'
                ])
                ->from(SalesHead::tableName())
                ->leftJoin(['menu' => $salesMenuCountQuery],
                    SalesHead::tableName() . '.salesNum = menu.salesNum')
                ->where([SalesHead::tableName() . '.salesNum' => $salesNumExists])
                ->one();

            if ($salesModel) {
                $salesNum = $salesModel['salesNum'];
                $tableStatusID = 0;
                if (isset($salesNum) && $salesModel['orderCount'] == 0) {
                    $tableStatusID = 1;
                }
                if (isset($salesNum) && $salesModel['orderCount'] > 0) {
                    $tableStatusID = 2;
                }
                if (isset($salesNum) && $salesModel['billingPrintCount'] > 0) {
                    $tableStatusID = 3;
                }
                $branchID = Setting::getCurrentBranch();
                $visitPurpose = (new Query())
                    ->select([
                        MapBranchVisitPurpose::tableName() . '.*',
                        'tableStatusID' => new Expression($tableStatusID),
                        'paxTotal' => new Expression($salesModel['paxTotal']),
                        'salesNum' => new Expression('"'.$salesNum.'"')
                    ])
                    ->from(MapBranchVisitPurpose::tableName())
                    ->where(['visitPurposeID' => $salesModel['visitPurposeID']])
                    ->andWhere(['branchID' => $branchID])
                    ->one();
                return $visitPurpose;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
    
    public static function checkBookLocked($tableID) {
        $salesModel = SalesHead::find()
            ->where(['tableID' => $tableID])
            ->andWhere(['IS', 'salesDateOut', NULL])
            ->andWhere(['<>', 'statusID', 12])
            ->andWhere(['=', 'lockTable', 1])
            ->one();
        
        if($salesModel){
            return true;
        } else {
            return false;
        }
    }
    
    public static function getBillTime($tableID) {
        $salesModel = (new Query())
            ->select([
                'a.salesDateIn',
                'b.createdDate'
            ])
            ->from(SalesHead::tableName() . ' a')
            ->innerJoin(Notification::tableName() . ' b', 'a.tableID = b.tableID')
            ->andWhere(['IS', 'salesDateOut', NULL])
            ->andWhere(['<>', 'statusID', 12])
            ->andWhere(['=', 'lockTable', 1])
            ->andWhere(['=', 'b.action', 'BILL'])
            ->one();
        $occupiedTime = Table::getIntervalTime(date_create($salesModel['salesDateIn']),
                    date_create($salesModel['createdDate']));
        if($salesModel){
            return $occupiedTime;
        } else {
            return false;
        }
    }
    
    public static function getIntervalTime($startDate, $endDate) {
        // @Notes: Return in seconds
        $dateDiff = strtotime($endDate->format('Y-m-d H:i:s')) - strtotime($startDate->format('Y-m-d H:i:s'));        
        
        $hours = floor($dateDiff / 60 / 60);
        $minutes = floor((($dateDiff / 60 / 60) - $hours) * 60);
        $seconds = floor((((($dateDiff / 60 / 60) - $hours) * 60) - $minutes) * 60) ;

        return str_pad($hours, 2, '0', STR_PAD_LEFT) 
                . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT) 
                . ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
    }

    private static function checkHoldStatus($salesNum) {
        // $salesMenuOnHold = SalesMenu::findSalesOnHold($salesNum);
        $salesMenuOnHold = SalesMenu::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['statusID' => 46])
            ->one();
        return $salesMenuOnHold ? true : false;
    }

    public static function onCheckTableOrder($tableID = 0) {
        $shiftInDate = ShiftLog::getShiftInDate();
        $salesNumExists = SalesHead::find()
            ->select('salesNum')
            ->where(['tableID' => $tableID])
            ->andWhere(['IS', SalesHead::tableName() . '.salesDateOut', NULL])
            ->andWhere(['<>', SalesHead::tableName() . '.statusID', 12])
            ->andWhere(['>=', SalesHead::tableName() . '.salesDateIn', $shiftInDate])
            ->scalar();

        return $salesNumExists;
    }

    public static function onCheckFetchTableValidate($responTable) {
        $isValid = false;
        $tables = '';
        $tableIDs = '';
        foreach ($responTable as $key => $value) {

            $tableID = $value['tableID'];
            $tableName = $value['tableName'];
            // @check active transaction table
            $model = Table::find()
                ->select([
                    Table::tableName() . '.flagActive'
                ])
                ->innerJoin(SalesHead::tableName() . ' b', Table::tableName() .'.tableID = b.tableID')
                ->where(['b.statusID' => 1 ])
                ->andWhere(['b.tableID' => $tableID])
                ->one();

            if($model) {
                // @validate tabel unactive from core only
                if ($model->flagActive == 1 && $value['flagActive'] === "0" ){
                    $isValid = true;
                    $tables .= $tableName . ',';
                    $tableIDs .= $tableID . ',';
                }
            }
        }
        
        if ($isValid)
            return json_encode(['status' => false, 'message' => rtrim($tables, ",") . '|'. rtrim($tableIDs, ",")]);

        return json_encode(['status' => true, 'message' => 'Success']);
    }
}
