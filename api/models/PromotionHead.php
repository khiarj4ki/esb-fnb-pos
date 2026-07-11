<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "ms_promotionhead".
 *
 * @property int $promotionID
 * @property string $startDate
 * @property string $endDate
 * @property int $branchID
 * @property int $promotionTypeID
 * @property int $voucherSourceID
 * @property string $minSalesPrice
 * @property int $flagMultiplication
 * @property string $maxSalesPrice
 * @property int $paymentMethodTypeID
 * @property int $paymentMethodID
 * @property string $discount
 * @property string $notes
 * @property int $flagMemberOnly
 * @property int $promotionMemberTypeID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * 
 * @property PromotionDay[] $promotionDays
 * @property PromotionCategory[] $promotionCategories
 * @property PromotionDetail[] $promotionDetails
 */
class PromotionHead extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotionhead';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['startDate', 'endDate', 'branchID', 'promotionTypeID', 'minSalesPrice', 'flagMultiplication', 'maxSalesPrice', 'discount', 'flagAuthorization', 'notes', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['promotionID', 'startDate', 'endDate', 'createdDate', 'editedDate', 'promotionMasterCode', 'flagLoyalty', 'promotionRulesID'], 'safe'],
            [['branchID', 'promotionTypeID', 'flagMultiplication', 'paymentMethodTypeID', 'paymentMethodID'
                , 'flagActive', 'flagMemberOnly', 'flagPackageContent', 'flagMenuExtra', 'promotionMemberTypeID', 'flagAuthorization'
                , 'voucherSourceID', 'flagBinRequired'], 'integer'],
            [['minSalesPrice', 'maxSalesPrice', 'discount'], 'number'],
            [['notes', 'createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'promotionID' => 'Promotion ID',
            'startDate' => 'Start Date',
            'endDate' => 'End Date',
            'branchID' => 'Branch ID',
            'promotionTypeID' => 'Promotion Type ID',
            'minSalesPrice' => 'Min Sales Price',
            'flagMultiplication' => 'Flag Multiplication',
            'maxSalesPrice' => 'Max Sales Price',
            'paymentMethodTypeID' => 'Payment Method Type ID',
            'paymentMethodID' => 'Payment Method',
            'discount' => 'Discount',
            'notes' => 'Notes',
            'flagMemberOnly' => 'Flag Member Only',
            'flagAuthorization' => 'Flag Authorization',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['minSalesPrice'] = function ($model) {
            return (float) $model->minSalesPrice;
        };
        $fields['maxSalesPrice'] = function ($model) {
            return (float) $model->maxSalesPrice;
        };
        $fields['discount'] = function ($model) {
            return (float) $model->discount;
        };
        $fields['discountDisplay'] = function ($model) {
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
            $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
            // @Notes: 1 = Discount(%), 3 = Discount(Rp)
            if ($model->promotionTypeID == 1) {
                $maxDiscount = '-';
                if (!$model->promotionCategories) {
                    $maxDiscount = number_format($model->maxSalesPrice,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator");
                }

                return number_format($model->discount, $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator") . '%; Max. ' . $maxDiscount;
            } else if ($model->promotionTypeID == 3) {
                return number_format($model->discount, $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            } else if ($model->promotionTypeID == 9) {
                return 'Amount ' . number_format($model->discount, $salesDecimalSetting,
                    "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
            } else if ($model->promotionTypeID == 10) {
                $maxDiscount = number_format($model->maxSalesPrice,
                        $salesDecimalSetting, "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator");

                return number_format($model->discount, $salesDecimalSetting,
                        "$salesDecimalSeparatorSetting",
                        "$reverseDecimalSeparator") . '%; Max. ' . $maxDiscount;
            }
        };
        $fields['menuCategory'] = function ($model) {
            return $model->promotionCategories;
        };
        $fields['startDate'] = function ($model) {
            return str_replace("-", "/", $model->startDate);
        };
        $fields['endDate'] = function ($model) {
            return str_replace("-", "/", $model->endDate);
        };
        $fields['paymentMethodID'] = function ($model) {
            return (int) $model->paymentMethodID;
        };
        $fields['paymentMethodName'] = function ($model) {
            return $model->paymentMethod ? $model->paymentMethod->paymentMethodName : '';
        };
        $fields['promotionTypeName'] = function ($model) {
            return $model->promotionType ? $model->promotionType->promotionTypeDesc : '';
        };
        $fields['PromotionVisitPurpose'] = function ($model) {
            return $model->promotionVisitPurpose;
        };
        $fields['requirements'] = function ($model) {
            return $model->promotionRequirements;
        };
        $fields['reward'] = function ($model) {
            return $model->promotionReward;
        };
        $fields['bankIdentificationNumber'] = function ($model) {
            return $model->promotionBin;
        };

        return $fields;
    }

    public function getPromotionDays() {
        return $this->hasMany(PromotionDay::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionTimes() {
        return $this->hasMany(PromotionTime::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionCategories() {
        return $this->hasMany(PromotionCategory::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionDetails() {
        return $this->hasMany(PromotionDetail::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPaymentMethod() {
        return $this->hasOne(PaymentMethod::class,
                ['paymentMethodID' => 'paymentMethodID']);
    }

    public function getPromotionRequirements() {
        return $this->hasMany(PromotionRequirement::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionReward() {
        return $this->hasOne(PromotionReward::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionVisitPurpose() {
        return $this->hasMany(MapPromotionVisitPurpose::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionBin() {
        return $this->hasMany(PromotionBin::class,
                ['promotionID' => 'promotionID']);
    }

    public function getPromotionType() {
        return $this->hasOne(PromotionType::class,
                ['promotionTypeID' => 'promotionTypeID']);
    }

    public static function findActive() {
        $branchID = Setting::getCurrentBranch();

        $query = PromotionHead::find()
            ->innerJoinWith('promotionDays')
            ->innerJoinWith('promotionTimes')
            ->andWhere('TIME(NOW()) BETWEEN startTime AND endTime')
            ->andWhere(['IN', 'branchID', [0, $branchID]])
            ->andWhere('NOW() BETWEEN startDate AND endDate')
            ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
            ->andWhere([PromotionHead::tableName() . '.flagActive' => 1]);


        $query2 = PromotionHead::find()
        ->innerJoinWith('promotionDays')
        ->andWhere(['IN', 'branchID', [0, $branchID]])
        ->andWhere('NOW() BETWEEN startDate AND endDate')
        ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
        ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
        ->andWhere('ms_promotionhead.promotionID NOT IN (SELECT distinct promotionID FROM ms_promotiontime)');

        $PromotionModel = PromotionHead::find()
                ->from(['ms_promotionhead' => $query->union($query2,true)]);

        return $PromotionModel;
    }

    public static function findActiveInSalesTime($salesDate) {
        $branchID = Setting::getCurrentBranch();
        $date = date('Y-m-d', strtotime($salesDate));

        $query = PromotionHead::find()
            ->andWhere(['IN', 'branchID', [0, $branchID]])
            ->andWhere("'" . $salesDate . "' BETWEEN startDate AND endDate")
            ->andWhere([PromotionHead::tableName() . '.flagActive' => 1]);

        $query2 = PromotionHead::find()
            ->andWhere(['IN', 'branchID', [0, $branchID]])
            ->andWhere("'" . $salesDate . "' BETWEEN startDate AND endDate")
            ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
            ->andWhere('ms_promotionhead.promotionID NOT IN (SELECT distinct promotionID FROM ms_promotiontime)');

        $PromotionModel = PromotionHead::find()
                ->from(['ms_promotionhead' => $query->union($query2,true)]);

        return $PromotionModel;
    }

    public static function findIncomingPromotion() {
        $branchID = Setting::getCurrentBranch();
        $maxExpiredTime = date('Y-m-d', strtotime('-30 days'));
        
        return PromotionHead::find()
            ->where(['IN', 'branchID', [0, $branchID]])
            ->andWhere(['flagActive' => 1])
            ->andWhere(['>=','endDate', $maxExpiredTime])
            ->orderBy('createdDate DESC');
    }

    public static function findActiveForBill($memberID = 0, $employeeCode = null, $modePromotion = 0, $externalMemberID = null, $salesDateIn = null) {
        // @Notes: Check if membership type member.id is currently active
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $externalMember = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $isMemberID = ($externalMember == 1 && $membershipType == 'memberid' && $externalMemberID) ? TRUE : FALSE;
        $isLoyalty = ($externalMember == 1 && $membershipType == 'esbloyalty' && $externalMemberID) ? TRUE : FALSE;
        $isTada = ($externalMember == 1 && $membershipType == 'tada' && $externalMemberID) ? TRUE : FALSE;
        $isMap = ($externalMember == 1 && $membershipType == 'general' && $externalMemberID) ? TRUE : FALSE;
        $isLoopLite = ($externalMember == 1 && $membershipType == 'looplite' && $externalMemberID) ? TRUE : FALSE;
        $isCapillary = ($externalMember == 1 && $membershipType == 'capillary' && $externalMemberID) ? TRUE : FALSE;
        $isCapillaryV2 = ($externalMember == 1 && $membershipType == 'capillaryV2' && $externalMemberID) ? TRUE : FALSE;
        $isStamps = ($externalMember == 1 && $membershipType == 'stamps' && $externalMemberID) ? TRUE : FALSE;
        // @Notes: 1 = Discount(%), 3 = Bill Discount(Rp), 9 = Menu Discount (Rp)
        if ($modePromotion == 1) {
            $paramMemberTypeID = [1];
        } else {
            $employeeCode = $employeeCode !== '' ? $employeeCode : null;
            $isExternalMember = ( $isMemberID || $isTada || $isLoyalty || $isMap || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps);
            if (($memberID > 0 || $isExternalMember) && $employeeCode == null) {
                $paramMemberTypeID = [0, 1, 3];
            } elseif ($memberID == 0 && !$isExternalMember && $employeeCode !== null) {
                $paramMemberTypeID = [0, 1, 2];
            } elseif (($memberID > 0 || $isExternalMember) && $employeeCode !== null) {
                $paramMemberTypeID = [0, 1, 2, 3];
            } elseif ($memberID == 0 && $employeeCode == null && (!$isMemberID || !$isTada || !$isLoyalty || !$isMap || !$isLoopLite || !$isCapillary || $isCapillaryV2 || !$isStamps)) {
                $paramMemberTypeID = [0];
            }
        }
        $dataPromotionID = null;
        if ($employeeCode) {
            $validEmployeePromotionIDs = EmployeeGroup::find()
                ->select(['promoEmployee.promotionID'])
                ->distinct()
                ->innerJoin(EmployeeGroupDetail::tableName() . ' employeeGroupDetail',
                    EmployeeGroup::tableName() . '.employeeGroupID = employeeGroupDetail.employeeGroupID')
                ->innerJoin(PromotionEmployeeGroup::tableName() . ' promoEmployee',
                    EmployeeGroup::tableName() . '.employeeGroupID = promoEmployee.employeeGroupID')
                ->where([EmployeeGroup::tableName() . '.flagActive' => true])
                ->andWhere(['employeeGroupDetail.employeeCode' => $employeeCode]);

            $dataPromotionID = PromotionHead::findActive()
                ->select([PromotionHead::tableName() .'.promotionID'])
                ->innerJoin(PromotionEmployeeGroup::tableName() . ' promoEmployee',
                    PromotionHead::tableName() . '.promotionID = promoEmployee.promotionID')
                ->where(['IN', 'promotionMemberTypeID', [1, 2]])
                ->andWhere(['=',PromotionHead::tableName() .'.flagActive','1'])
                ->andWhere(['NOT IN', PromotionHead::tableName() .'.promotionID', $validEmployeePromotionIDs])
                ->groupBy(PromotionHead::tableName() .'.promotionID');
        }

        if (!$salesDateIn) {
            return PromotionHead::findActive()
                ->andWhere(['IN', 'promotionTypeID', [1, 3, 9, 10, 11, 12, 18, 19]])
                ->andWhere(['IN', 'promotionMemberTypeID', $paramMemberTypeID])
                ->andFilterWhere(['NOT IN', PromotionHead::tableName() .'.promotionID', $dataPromotionID]);
        } else {
            return PromotionHead::findActiveInSalesTime($salesDateIn)
                ->andWhere(['IN', 'promotionTypeID', [1, 3, 9, 10, 11, 12, 18, 19]])
                ->andWhere(['IN', 'promotionMemberTypeID', $paramMemberTypeID])
                ->andFilterWhere(['NOT IN', PromotionHead::tableName() .'.promotionID', $dataPromotionID]);
        }

    }

    public static function findActiveForMenu($menuID, $memberID, $employeeCode = null, $menuSubsID = null, $externalMemberID = null, $spesificPromotionID = null, $menuPackageMenuIDs = null) {
        // @Notes: Check if membership type member.id is currently active
        $externalMemberSetting = BrandSetting::getExternalMemberSetting();
        $externalMember = array_key_exists('External Member', $externalMemberSetting) ? (int) $externalMemberSetting['External Member'] : 0;
        $membershipType = array_key_exists('Membership Type', $externalMemberSetting) ? $externalMemberSetting['Membership Type'] : 'general';
        $isMemberID = ($externalMember == 1 && $membershipType == 'memberid' && $externalMemberID) ? TRUE : FALSE;
        $isLoyalty = ($externalMember == 1 && $membershipType == 'esbloyalty' && $externalMemberID) ? TRUE : FALSE;
        $isTada = ($externalMember == 1 && $membershipType == 'tada' && $externalMemberID) ? TRUE : FALSE;
        $isMap = ($externalMember == 1 && $membershipType == 'general' && $externalMemberID) ? TRUE : FALSE;
        $isLoopLite = ($externalMember == 1 && $membershipType == 'looplite' && $externalMemberID) ? TRUE : FALSE;
        $isCapillary = ($externalMember == 1 && $membershipType == 'capillary' && $externalMemberID) ? TRUE : FALSE;
        $isCapillaryV2 = ($externalMember == 1 && $membershipType == 'capillaryV2' && $externalMemberID) ? TRUE : FALSE;
        $isStamps = ($externalMember == 1 && $membershipType == 'stamps' && $externalMemberID) ? TRUE : FALSE;

        $paramMemberID = $memberID ? [0, 1] : [0];
        $employeeCode = $employeeCode !== '' ? $employeeCode : null;
        $isExternalMember = ( $isMemberID || $isTada || $isLoyalty || $isMap || $isLoopLite || $isCapillary || $isCapillaryV2 || $isStamps);
        if (($memberID > 0 || $isExternalMember) && $employeeCode == null) {
            $paramMemberTypeID = [0, 1, 3];
            $paramMemberID = [0, 1];
        } elseif ($memberID == 0 && !$isExternalMember && $employeeCode !== null) {
            $paramMemberTypeID = [0, 1, 2];
            $paramMemberID = [0, 1];
        } elseif (($memberID > 0 || $isExternalMember) && $employeeCode !== null) {
            $paramMemberTypeID = [0, 1, 2, 3];
            $paramMemberID = [0, 1];
        } elseif ($memberID == 0 && $employeeCode == null && (!$isMemberID || !$isTada || !$isLoyalty || !$isMap || !$isLoopLite || !$isCapillary || $isCapillaryV2 || !$isStamps)) {
            $paramMemberTypeID = [0];
        }

        $forMenuSubs = $menuID;
        if ($menuSubsID) {
            if ($menuSubsID && $menuSubsID != 0){
                $forMenuSubs = $menuSubsID;
            }
        }

        $menuModel = Menu::find()
            ->with('menuCategoryDetail.menuCategory')
            ->andWhere(['menuID' => $forMenuSubs])
            ->one();

        $menuPackage = MenuGroup::find()
            ->select([
                'ms_menupackage.menuID'
            ])
            ->joinWith('activeMenuPackages')
            ->where(['=','ms_menugroup.flagActive',true])
            ->andWhere(['=','ms_menugroup.menuID', $forMenuSubs])
            ->andFilterWhere(['IN','ms_menupackage.menuID', $menuPackageMenuIDs])
            ->all();

        $packageMenuList = [];

        if($menuPackage){
            foreach($menuPackage as $menu){
                $packageMenuList[] = $menu->menuID;
            }
        }
        
        $menuSubsModel = [];
        if ($menuSubsID) {
            $forMenuSubs = $menuID;
            if ($menuSubsID && $menuSubsID != 0){
                $forMenuSubs = $menuSubsID;
            }

            $menuSubsModel = PromotionHead::findActive()
                ->leftJoin(PromotionDetail::tableName() . ' detail',
                    PromotionHead::tableName() . '.promotionID = detail.promotionID')
                ->leftJoin(PromotionPackageSub::tableName() . ' packageSub',
                    PromotionHead::tableName() . '.promotionID = packageSub.promotionID')
                ->where(['=','promotionTypeID', '7'])
                ->andWhere("'".date('Y-m-d H:i:s')."'".' BETWEEN startDate AND endDate')
                ->andWhere(['=','flagActive','1'])
                ->andWhere(['OR',
                    ['OR',
                        ['=', 'detail.menuID', $forMenuSubs],
                        ['in','detail.menuID', $packageMenuList]
                    ],
                    ['OR',
                        ['=', 'packageSub.menuID', $forMenuSubs]
                    ]
                ])
                ->andWhere(['IN', PromotionHead::tableName() .'.flagMemberOnly', $paramMemberID])
                ->andWhere(['IN', PromotionHead::tableName() . '.promotionMemberTypeID', $paramMemberTypeID]);
        } else {
            $forMenuSubs = $menuID;
            $menuSubsModel = PromotionHead::findActive()
                ->leftJoin(PromotionDetail::tableName() . ' detail',
                    PromotionHead::tableName() . '.promotionID = detail.promotionID')
                ->leftJoin(PromotionPackageSub::tableName() . ' packageSub',
                    PromotionHead::tableName() . '.promotionID = packageSub.promotionID')
                ->where(['=','promotionTypeID', '7'])
                ->andWhere("'".date('Y-m-d H:i:s')."'".' BETWEEN startDate AND endDate')
                ->andWhere(['=','flagActive','1'])
                ->andWhere(['OR',
                    ['OR',
                        ['=', 'detail.menuID', $forMenuSubs],
                        ['in','detail.menuID', $packageMenuList]
                    ],
                    ['OR',
                        ['=', 'packageSub.menuID', $forMenuSubs]
                    ]
                ])
                ->andWhere(['IN', PromotionHead::tableName() .'.flagMemberOnly', $paramMemberID])
                ->andWhere(['IN', PromotionHead::tableName() . '.promotionMemberTypeID', $paramMemberTypeID]);
        }

        $dataPromotionID = null;
        if ($employeeCode) {
            $validEmployeePromotionIDs = EmployeeGroup::find()
                ->select(['promoEmployee.promotionID'])
                ->distinct()
                ->innerJoin(EmployeeGroupDetail::tableName() . ' employeeGroupDetail',
                    EmployeeGroup::tableName() . '.employeeGroupID = employeeGroupDetail.employeeGroupID')
                ->innerJoin(PromotionEmployeeGroup::tableName() . ' promoEmployee',
                    EmployeeGroup::tableName() . '.employeeGroupID = promoEmployee.employeeGroupID')
                ->where([EmployeeGroup::tableName() . '.flagActive' => true])
                ->andWhere(['employeeGroupDetail.employeeCode' => $employeeCode]);

            $dataPromotionID = PromotionHead::findActive()
                ->select([PromotionHead::tableName() .'.promotionID'])
                ->innerJoin(PromotionEmployeeGroup::tableName() . ' promoEmployee',
                    PromotionHead::tableName() . '.promotionID = promoEmployee.promotionID')
                ->where(['IN', 'promotionMemberTypeID', [1, 2]])
                ->andWhere(['=',PromotionHead::tableName() .'.flagActive','1'])
                ->andWhere(['NOT IN', PromotionHead::tableName() .'.promotionID', $validEmployeePromotionIDs])
                ->groupBy(PromotionHead::tableName() .'.promotionID');
        }
        

        // @Notes: 1 = Discount(%), 4 = Free Item, 9 = MENU DISCOUNT(RP), 18 = Buy X Get Y
        $mainModel = PromotionHead::findActive()
                ->leftJoin(PromotionCategory::tableName() . ' b',
                    PromotionHead::tableName() . '.promotionID = b.promotionID')
                ->andFilterWhere(['NOT IN', PromotionHead::tableName() .'.promotionID', $dataPromotionID])
                ->andWhere(['IN', PromotionHead::tableName() .'.flagMemberOnly', $paramMemberID])
                ->andWhere(['IN', PromotionHead::tableName() . '.promotionMemberTypeID', $paramMemberTypeID])
                ->andWhere(
                    ['AND',
                        ['IN', PromotionHead::tableName() . '.promotionTypeID', [1, 4, 9, 18, 19]],
                        ['OR',
                            ['AND',
                                ['OR',
                                    ['IS', 'b.menuCategoryID', null],
                                    ['=', 'b.menuCategoryID', 0]
                                ],
                                ['OR',
                                    ['IS', 'b.menuCategoryDetailID', null],
                                    ['=', 'b.menuCategoryDetailID', 0]
                                ],
                                ['IS', 'b.menuID', null]
                            ],
                            ['OR',
                                ['AND',
                                    ['b.menuCategoryID' => $menuModel->menuCategoryDetail->menuCategoryID],
                                    ['OR',
                                        ['IS', 'b.menuCategoryDetailID', null],
                                        ['=', 'b.menuCategoryDetailID', 0]
                                    ],
                                    ['IS', 'b.menuID', null]
                                ],
                                ['AND',
                                    ['b.menuCategoryDetailID' => $menuModel->menuCategoryDetailID],
                                    ['OR',
                                        ['IS', 'b.menuCategoryID', null],
                                        ['=', 'b.menuCategoryID', 0]
                                    ],
                                    ['IS', 'b.menuID', null]
                                ],
                                ['AND',
                                    ['b.menuID' => $menuModel->menuID],
                                    ['OR',
                                        ['IS', 'b.menuCategoryID', null],
                                        ['=', 'b.menuCategoryID', 0]
                                    ],
                                    ['OR',
                                        ['IS', 'b.menuCategoryDetailID', null],
                                        ['=', 'b.menuCategoryDetailID', 0]
                                    ]
                                ],
                            ]
                        ]
                    ]);

        if($spesificPromotionID){
            $mainModel = $mainModel->where(['=','ms_promotionhead.promotionID',$spesificPromotionID]);
            $menuSubsModel = $menuSubsModel->where(['=','ms_promotionhead.promotionID',$spesificPromotionID]);
        } 


        $returnModel = $menuSubsModel ? $mainModel->union($menuSubsModel) : $mainModel;
        return $returnModel;
    }

    public static function findActiveArrayValue() {
        return PromotionHead::find()
                ->select([
                    'ms_promotionhead.promotionID',
                    'ms_promotionhead.promotionTypeID',
                    'ms_promotionhead.discount',
                    'ms_promotionhead.flagPackageContent',
                    'ms_promotionhead.flagMenuExtra'
                ])
                ->innerJoinWith('promotionDays')
                ->andWhere('NOW() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->asArray()
                ->indexBy('promotionID')
                ->all();
    }

    public static function checkPromoActive($promotionID, $salesDateIn = null) {
        $branchID = Setting::getCurrentBranch();

        if ($salesDateIn) {
            $query = PromotionHead::find()
                ->andWhere(['IN', 'branchID', [0, $branchID]])
                ->andWhere("'" . $salesDateIn . "' BETWEEN startDate AND endDate")
                ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
                ->andWhere(['=', 'ms_promotionhead.promotionID', $promotionID]);
    
            return PromotionHead::find()
                ->andWhere(['IN', 'branchID', [0, $branchID]])
                ->andWhere("'" . $salesDateIn . "' BETWEEN startDate AND endDate")
                ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
                ->andWhere('ms_promotionhead.promotionID NOT IN (SELECT distinct promotionID FROM ms_promotiontime)')
                ->andWhere(['=', 'ms_promotionhead.promotionID', $promotionID])
                ->union($query)
                ->all();
        } else {
            $query = PromotionHead::find()
                ->innerJoinWith('promotionDays')
                ->innerJoinWith('promotionTimes')
                ->andWhere('TIME(NOW()) BETWEEN startTime AND endTime')
                ->andWhere(['IN', 'branchID', [0, $branchID]])
                ->andWhere('NOW() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
                ->andWhere(['=', 'ms_promotionhead.promotionID', $promotionID]);
    
            return PromotionHead::find()
                ->innerJoinWith('promotionDays')
                ->andWhere(['IN', 'branchID', [0, $branchID]])
                ->andWhere('NOW() BETWEEN startDate AND endDate')
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->andWhere([PromotionHead::tableName() . '.flagActive' => 1])
                ->andWhere('ms_promotionhead.promotionID NOT IN (SELECT distinct promotionID FROM ms_promotiontime)')
                ->andWhere(['=', 'ms_promotionhead.promotionID', $promotionID])
                ->union($query)
                ->all();
        }
    }

    public static function findActiveForLoyalty() {
        return PromotionHead::find()
            ->select([
                'promotionID',
                'promotionTypeID',
                'promotionMasterCode',
                'minSalesPrice',
                'discount',
                'notes'
            ])
            ->where('NOW() BETWEEN startDate AND endDate')
            ->andWhere('flagLoyalty = 1')
            ->andWhere('promotionTypeID IN (1, 3, 4)')
            ->andWhere('flagActive = 1')
            ->asArray()
            ->indexBy('promotionID')
            ->all();
    }
    
    public static function findActiveTodayPromotion() {
        $branchID = Setting::getCurrentBranch();
        
        return PromotionHead::find()
                ->innerJoinWith('promotionDays')
                ->andWhere(['IN', 'branchID', [0, $branchID]])
                ->andWhere(['OR','DATE(startDate) = CURDATE()', 'NOW() BETWEEN startDate AND endDate'])
                ->andWhere('dayID = CASE WHEN (DAYOFWEEK(NOW()) - 1) = 0 THEN 7 ELSE (DAYOFWEEK(NOW()) - 1) END')
                ->andWhere(['flagActive' => 1])
                ->orderBy('createdDate DESC');
    }
}
