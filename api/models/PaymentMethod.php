<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use Exception;
use yii\db\Expression;

/**
 * This is the model class for table "ms_paymentmethod".
 *
 * @property int $paymentMethodID
 * @property int $paymentMethodTypeID
 * @property int $voucherSourceID
 * @property int $voucherCategoryID
 * @property string $paymentMethodName
 * @property string $posExternalPaymentID
 * @property int $cardNumberValidationTypeID
 * @property string $edcWssUrl
 * @property string $edcPort
 * @property int $branchID
 * @property string $coaNo
 * @property int $flagMandatoryCardNumber
 * @property int $flagMandatoryVerificationCode
 * @property int $flagAuthorization
 * @property int $flagUseEmployeeLimit
 * @property int $flagEdcActive
 * @property int $depositSourceID
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $syncDate
 * 
 * @property PaymentMethodType $paymentMethodType
 */
class PaymentMethod extends ActiveRecord {

    public $cardNumberValidationName;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_paymentmethod';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['paymentMethodTypeID', 'paymentMethodName', 'branchID', 'coaNo', 'flagAuthorization', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['paymentMethodTypeID', 'branchID', 'flagAuthorization', 'flagActive', 'parentID', 'flagUseEmployeeLimit'], 'integer'],
            [[
                'paymentMethodID', 'paymentMethodCode', 'flagOpenCashdrawer', 'voucherTypeID', 'voucherSourceID', 'printedCount', 
                'fixedAmount', 'createdDate', 'editedDate', 'posExternalPaymentID', 'flagUseEmployeeLimit', 'edcWssUrl', 
                'edcPort', 'syncDate', 'flagEdcActive', 'voucherCategoryID',
                'cardNumberValidationTypeID', 'flagMandatoryCardNumber', 'flagMandatoryVerificationCode','buttonColor', 'flagIncludeTotalSpent', 'depositSourceID'], 'safe'],
            [['paymentMethodName'], 'string', 'max' => 50],
            [['coaNo'], 'string', 'max' => 20],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'paymentMethodID' => 'Payment Method ID',
            'paymentMethodTypeID' => 'Payment Method Type ID',
            'paymentMethodName' => 'Payment Method Name',
            'branchID' => 'Branch ID',
            'voucherTypeID' => 'Voucher Type ID',
            'coaNo' => 'Coa No',
            'flagAuthorization' => 'Flag Authorization',
            'flagUseEmployeeLimit' => 'Flag Use Employee Limit',
            'flagActive' => 'Flag Active',
            'flagIncludeTotalSpent' => 'Flag Include Total Spent',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'fixedAmount' => 'Fixed Amount',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['paymentMethodTypeName'] = function ($model) {
            return isset($model->paymentMethodType->paymentMethodTypeName) ? $model->paymentMethodType->paymentMethodTypeName : null;
        };
        $fields['posExternalPaymentName'] = function ($model) {
            return isset($model->posExternalPayment->posExternalPaymentName) ? $model->posExternalPayment->posExternalPaymentName : null;
        };

        return $fields;
    }

    public function getPaymentMethodType() {
        return $this->hasOne(PaymentMethodType::class,
                ['paymentMethodTypeID' => 'paymentMethodTypeID']);
    }
    
    public function getPosExternalPayment() {
        return $this->hasOne(PosExternalPayment::class,
                ['posExternalPaymentID' => 'posExternalPaymentID']);
    }

    public function getSelfOrderPayment() {
        return $this->hasOne(MapSelfOrderPaymentMethod::class,
                ['paymentMethodID' => 'paymentMethodID']);
    }
    
    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public static function findActive() {
        return PaymentMethod::find()->andWhere([PaymentMethod::tableName() . '.flagActive' => 1]);
    }

    public static function findActiveAsArray($visitPurposeID) {
        $branchID = Setting::getCurrentBranch();
        $paymentDisplayMode = Setting::getSetting('POS','Payment Display Mode');
        $parentID = ($paymentDisplayMode->value1 == 'No Group')? null : 0;

        $paymentMethodChildModel = PaymentMethod::find()
            ->with('selfOrderPayment')
            ->joinWith('posExternalPayment')
            ->leftJoin(
                'map_visitpurposepaymentmethod',
                'ms_paymentmethod.paymentMethodID = map_visitpurposepaymentmethod.paymentMethodID'
            )
            ->leftJoin(
                'lk_cardnumbervalidationtype',
                'ms_paymentmethod.cardNumberValidationTypeID = lk_cardnumbervalidationtype.cardNumberValidationTypeID'
            )
            ->andWhere(['not', ['map_visitpurposepaymentmethod.visitPurposeID' => null]])
            ->andFilterWhere(['map_visitpurposepaymentmethod.visitPurposeID' => $visitPurposeID])
            ->andWhere(['flagActive' => 1])
            ->all();

        foreach ($paymentMethodChildModel as $paymentDetail) {
            $paymentChildData[$paymentDetail->parentID][] = $paymentDetail;
        }

        // @notes: payment method parent yang memiliki child berdasarkan vp yang dipilih
        $paymentMethodHasChildByVpModel = PaymentMethod::find()
        ->select([
            'paymentMethodID' => PaymentMethod::tableName() . '.paymentMethodID',
            'childCount' => new Expression("COUNT('paymentmethodchildVp.paymentMethodID')"),
        ])
        ->leftJoin(
            ['paymentmethodchildVp' => PaymentMethod::tableName()],
            'ms_paymentmethod.paymentMethodID = paymentmethodchildVp.parentID'
        )
        ->leftJoin(
            'map_visitpurposepaymentmethod',
            'paymentmethodchildVp.paymentMethodID = map_visitpurposepaymentmethod.paymentMethodID'
        )
        ->where([PaymentMethod::tableName() . '.branchID' => $branchID])
        ->andWhere([PaymentMethod::tableName() . '.flagActive' => 1])
        ->andWhere(['paymentmethodchildVp.flagActive' => 1])
        ->andWhere(['not', ['map_visitpurposepaymentmethod.visitPurposeID' => null]])
        ->andFilterWhere(['map_visitpurposepaymentmethod.visitPurposeID' => $visitPurposeID])
        ->groupBy(PaymentMethod::tableName() . '.paymentMethodID');
    
    $paymentMethodHasChildModel = PaymentMethod::find()
        ->select([
            'paymentMethodID' => PaymentMethod::tableName() . '.paymentMethodID',
            'childCount' => new Expression("COUNT('paymentMethodChild.paymentMethodID')"),
        ])
        ->leftJoin(
            ['paymentmethodchild' => PaymentMethod::tableName()],
            'ms_paymentmethod.paymentMethodID = paymentmethodchild.parentID'
        )
        ->leftJoin(
            'map_visitpurposepaymentmethod',
            'paymentmethodchild.paymentMethodID = map_visitpurposepaymentmethod.paymentMethodID'
        )
        ->where([PaymentMethod::tableName() . '.branchID' => $branchID])
        ->andWhere([PaymentMethod::tableName() . '.flagActive' => 1])
        ->andWhere(['paymentmethodchild.flagActive' => 1])
        ->andWhere(['not', ['map_visitpurposepaymentmethod.visitPurposeID' => null]])
        ->groupBy(PaymentMethod::tableName() . '.paymentMethodID');

    $paymentMethodModel = PaymentMethod::find()
        ->select([
            'ms_paymentmethod.*',
            'lk_cardnumbervalidationtype.cardNumberValidationName'
        ])
        ->with('selfOrderPayment')
        ->joinWith('posExternalPayment')
        ->leftJoin(
            'map_visitpurposepaymentmethod',
            'ms_paymentmethod.paymentMethodID = map_visitpurposepaymentmethod.paymentMethodID'
        )
        ->leftJoin(
            ['paymentMethodChild' => $paymentMethodHasChildModel], 
            'ms_paymentmethod.paymentMethodID = paymentMethodChild.paymentMethodID'
        )
        ->leftJoin(
            ['paymentMethodChildVp' => $paymentMethodHasChildByVpModel], 
            'ms_paymentmethod.paymentMethodID = paymentMethodChildVp.paymentMethodID'
        )
        ->leftJoin(
            'lk_cardnumbervalidationtype',
            'ms_paymentmethod.cardNumberValidationTypeID = lk_cardnumbervalidationtype.cardNumberValidationTypeID'
        )
        ->where(['branchID' => $branchID])
        ->andWhere(['flagActive' => 1])
        ->andFilterWhere(['parentID' => $parentID])
        ->andWhere(['not', ['map_visitpurposepaymentmethod.visitPurposeID' => null]])
        ->andFilterWhere(['map_visitpurposepaymentmethod.visitPurposeID' => $visitPurposeID])
        ->andWhere(['or', 
            ['and',
                ['or',
                    ['paymentMethodChild.childCount' => NULL],
                    ['paymentMethodChild.childCount' => 0]
                ]
            ],
            ['>', 'paymentMethodChildVp.childCount', 0]
        ])
        ->orderBy('paymentMethodTypeID ASC')
        ->all();

        $paymentMethodArray = [];
        $j = 0;
        $currentPaymentMethodTypeID = -1;
        foreach ($paymentMethodModel as $paymentDetail) {
            if ($currentPaymentMethodTypeID != $paymentDetail->paymentMethodTypeID) {
                $j = 0;
            }

            $currentPaymentMethodTypeID = $paymentDetail->paymentMethodTypeID;
            $minimumPaymentAmount = 0;
            // detect minimum payment amount
            if ($paymentDetail->posExternalPayment) {
                $minimumPaymentAmount = $paymentDetail->posExternalPayment->minimumPaymentAmount;
            }
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodID'] = $paymentDetail->paymentMethodID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodTypeID'] = $paymentDetail->paymentMethodTypeID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodName'] = $paymentDetail->paymentMethodName;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodCode'] = $paymentDetail->paymentMethodCode;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['coaNo'] = $paymentDetail->coaNo;
            if($paymentDisplayMode->value1 == 'No Group'){
                $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodChild'] = '';
            }else{
                $paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodChild'] = PaymentMethod::findPaymentMethodChild($paymentChildData, $paymentDetail->paymentMethodID);
            }
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagAuthorization'] = $paymentDetail->flagAuthorization;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['voucherTypeID'] = $paymentDetail->voucherTypeID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['voucherSourceID'] = $paymentDetail->voucherSourceID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['voucherCategoryID'] = $paymentDetail->voucherCategoryID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['fixedAmount'] = $paymentDetail->fixedAmount;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['minimumPaymentAmount'] = $minimumPaymentAmount;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['posExternalPaymentID'] = $paymentDetail->posExternalPaymentID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['cardNumberValidationTypeID'] = $paymentDetail->cardNumberValidationTypeID;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['cardNumberValidationName'] = $paymentDetail->cardNumberValidationName;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['edcWssUrl'] = $paymentDetail->edcWssUrl;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['edcPort'] = $paymentDetail->edcPort;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagMandatoryCardNumber'] = $paymentDetail->flagMandatoryCardNumber;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagMandatoryVerificationCode'] = $paymentDetail->flagMandatoryVerificationCode;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagUseEmployeeLimit'] = $paymentDetail->flagUseEmployeeLimit;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagEdcActive'] = $paymentDetail->flagEdcActive;
            $paymentMethodButtonColor = $paymentDetail->buttonColor ? $paymentDetail->buttonColor : ($paymentMethodArray[$currentPaymentMethodTypeID][$j]['paymentMethodChild'] ? "#3c8dbc" : "#F39C12");
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['buttonColor'] = $paymentMethodButtonColor;
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['buttonTextColor'] = self::defineButtonTextColor($paymentMethodButtonColor);
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['flagSelfOrderPayment'] = !($paymentDetail->selfOrderPayment == null);
            $paymentMethodArray[$currentPaymentMethodTypeID][$j]['depositSourceID'] = $paymentDetail->depositSourceID;
            $j++;
        }

        $paymentMethodTypeArray = [];
        $i = 0;
        $paymentMethodTypeModel = PaymentMethodType::find()
            ->orderBy(PaymentMethodType::tableName() . '.paymentMethodTypeID')
            ->all();
        
        foreach ($paymentMethodTypeModel as $detail) {
            
            //@Notes: Display only payment types that have at least one payment method.
            if(isset($paymentMethodArray[$detail->paymentMethodTypeID])){
                $paymentMethodTypeArray[$i]['paymentMethodTypeID'] = $detail->paymentMethodTypeID;
                $paymentMethodTypeArray[$i]['paymentMethodTypeName'] = $detail->paymentMethodTypeName;
                $paymentMethodTypeArray[$i]['paymentMethod'] = isset($paymentMethodArray[$detail->paymentMethodTypeID]) ? $paymentMethodArray[$detail->paymentMethodTypeID] : [];
                $i++;
            }
        }
        
        return $paymentMethodTypeArray;
    }

    public static function findActiveQrisAsArray() {
        $branchID = Setting::getCurrentBranch();
        $paymentMethodHasChildModel = PaymentMethod::find()
                ->select([
                    'paymentMethodID' => PaymentMethod::tableName() . '.paymentMethodID',
                    'childCount' => new Expression("COUNT('paymentMethodChild.paymentMethodID')"),
                ])
                ->leftJoin(
                    ['paymentmethodchild' => PaymentMethod::tableName()],
                    'ms_paymentmethod.paymentMethodID = paymentmethodchild.parentID'
                )
                ->leftJoin(
                    'map_visitpurposepaymentmethod',
                    'paymentmethodchild.paymentMethodID = map_visitpurposepaymentmethod.paymentMethodID'
                )
                ->where([PaymentMethod::tableName() . '.branchID' => $branchID])
                ->andWhere([PaymentMethod::tableName() . '.flagActive' => 1])
                ->andWhere(['paymentmethodchild.flagActive' => 1])
                ->andWhere(['not', ['map_visitpurposepaymentmethod.visitPurposeID' => null]])
                ->groupBy(PaymentMethod::tableName() . '.paymentMethodID');

        $paymentMethodModel = PaymentMethod::find()
            ->select([
                self::tableName().'.posExternalPaymentID',
                PosExternalPayment::tableName().'.posExternalPaymentName',
                PosExternalPayment::tableName().'.minimumPaymentAmount'
            ])
            ->innerJoin(PosExternalPayment::tableName(),
                PosExternalPayment::tableName().'.posExternalPaymentID = ' . self::tableName() . '.posExternalPaymentID')
            ->leftJoin(
                    ['paymentMethodChild' => $paymentMethodHasChildModel], 
                    'ms_paymentmethod.paymentMethodID = paymentMethodChild.paymentMethodID'
            )
            ->where(['branchID' => $branchID])
            ->andWhere(['flagActive' => 1])
            ->andWhere([ self::tableName().'.paymentMethodTypeID' => 2])
            ->andWhere(['IN', self::tableName().'.posExternalPaymentID', ['qris', 'qrisyukk', 'qrisshopee', 'qrisnobu', 'qrisesb', 'qrisbri', 'qrisdki']])
            ->andWhere(['OR', ['paymentMethodChild.childCount' => NULL], ['paymentMethodChild.childCount' => 0]])
            ->groupBy([
                'posExternalPaymentID',
                PosExternalPayment::tableName().'.posExternalPaymentName'
            ])->asArray()->all(); 
        
        $externalPaymentArray = [];
        foreach ($paymentMethodModel as $paymentDetail) {
            $isHaveSetting = true;
            if (in_array($paymentDetail['posExternalPaymentID'], ['qrisnobu', 'qrisotopay', 'qrisgpay', 'qrisbri'])) {
                $externalPaymentSetting = Setting::getExternalPaymentSetting($paymentDetail['posExternalPaymentID']);
                $isHaveSetting = $externalPaymentSetting['status'];
            }
            $externalPaymentArray[$paymentDetail['posExternalPaymentID']] = $paymentDetail;
            $externalPaymentArray[$paymentDetail['posExternalPaymentID']]['isHaveSetting'] = $isHaveSetting;
        }
        $paymentMethodModel = array_values($externalPaymentArray);
        return $paymentMethodModel;
    }

    public static function findActiveEdcPaymentAsArray() {
        $branchID = Setting::getCurrentBranch();
        $paymentMethodModel = PaymentMethod::find()
                ->select([PaymentMethod::tableName() . '.*',
                PosExternalPayment::tableName().'.posExternalPaymentName',
                PosExternalPayment::tableName() . '.minimumPaymentAmount'
                ])
                ->innerJoin(PosExternalPayment::tableName(), PosExternalPayment::tableName().'.posExternalPaymentID = ' . self::tableName() . '.posExternalPaymentID')
                ->where(['branchID' => $branchID])
                ->andWhere([PaymentMethod::tableName() . '.flagActive' => 1])
                ->andWhere(['IN', 'ms_paymentmethod.posExternalPaymentID', 
                    ['edcbca', 'wirecard', 'edccimb', 'edcbni', 'ecrbcaqris', 'ecrbcaflaz', 'ecrcimbqr', 
                        'edcmdryoke', 'edcbri', 'edccimband', 'ecrbriqr', 'emoney']])
                ->asArray()->all();

        return $paymentMethodModel;
    }

    public static function findPaymentMethodChild($childPaymentMethods, $parentID) {
        $childData = [];

        $j = 0;
        if (isset($childPaymentMethods[$parentID])) {
            foreach ($childPaymentMethods[$parentID] as $paymentDetail) {
                $minimumPaymentAmount = 0;
                // detect minimum payment amount
                if ($paymentDetail->posExternalPayment) {
                    $minimumPaymentAmount = $paymentDetail->posExternalPayment->minimumPaymentAmount;
                }
                $childData[$j]['paymentMethodID'] = $paymentDetail->paymentMethodID;
                $childData[$j]['paymentMethodTypeID'] = $paymentDetail->paymentMethodTypeID;
                $childData[$j]['paymentMethodName'] = $paymentDetail->paymentMethodName;
                $childData[$j]['paymentMethodCode'] = $paymentDetail->paymentMethodCode;
                $childData[$j]['coaNo'] = $paymentDetail->coaNo;
                $childData[$j]['paymentMethodChild'] = PaymentMethod::findPaymentMethodChild($childPaymentMethods, $paymentDetail->paymentMethodID);
                $childData[$j]['flagAuthorization'] = $paymentDetail->flagAuthorization;
                $childData[$j]['voucherTypeID'] = $paymentDetail->voucherTypeID;
                $childData[$j]['voucherSourceID'] = $paymentDetail->voucherSourceID;
                $childData[$j]['voucherCategoryID'] = $paymentDetail->voucherCategoryID;
                $childData[$j]['fixedAmount'] = $paymentDetail->fixedAmount;
                $childData[$j]['minimumPaymentAmount'] = $minimumPaymentAmount;
                $childData[$j]['posExternalPaymentID'] = $paymentDetail->posExternalPaymentID;
                $childData[$j]['cardNumberValidationTypeID'] = $paymentDetail->cardNumberValidationTypeID;
                $childData[$j]['cardNumberValidationName'] = $paymentDetail->cardNumberValidationName;
                $childData[$j]['edcWssUrl'] = $paymentDetail->edcWssUrl;
                $childData[$j]['edcPort'] = $paymentDetail->edcPort;
                $childData[$j]['flagUseEmployeeLimit'] = $paymentDetail->flagUseEmployeeLimit;
                $childData[$j]['flagMandatoryCardNumber'] = $paymentDetail->flagMandatoryCardNumber;
                $childData[$j]['flagMandatoryVerificationCode'] = $paymentDetail->flagMandatoryVerificationCode;
                $childData[$j]['flagEdcActive'] = $paymentDetail->flagEdcActive;
                $paymentChildButtonColor = $paymentDetail->buttonColor ? $paymentDetail->buttonColor : ($childData[$j]['paymentMethodChild'] ? "#3c8dbc" : "#F39C12");
                $childData[$j]['buttonColor'] = $paymentChildButtonColor;
                $childData[$j]['buttonTextColor'] = self::defineButtonTextColor($paymentChildButtonColor);
                $childData[$j]['flagSelfOrderPayment'] = !($paymentDetail->selfOrderPayment == null);
                $childData[$j]['depositSourceID'] = $paymentDetail->depositSourceID;
                $j++;
            }
        }

        return !empty($childData) ? $childData : '';
    }

    public static function findPaymentMethodEmployeeLimit() {
        $paymentMethodModel = PaymentMethod::find()
            ->where(['paymentMethodTypeID' => 7])
            ->andWhere(['flagUseEmployeeLimit' => 1])
            ->andWhere(['flagActive' => 1])
            ->all();

        return $paymentMethodModel;
    }
    
    public static function syncUpdate($paymentMethodID, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            PaymentMethod::updateAll(['syncDate' => $syncDate],
                ['AND', ['branchID' => $branchID], ['paymentMethodID' => $paymentMethodID]]
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

    private static function defineButtonTextColor($hexa) {
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

    public static function checkPaymentMethodEsbVoucher($paymentMethodID) {
        $paymentMethodModel = PaymentMethod::find()
            ->where(['paymentMethodID' => $paymentMethodID])
            ->andWhere(['voucherSourceID' => 1])
            ->andWhere(['flagActive' => 1])
            ->one();

        return $paymentMethodModel ? true : false;
    }

    public static function checkPaymentMethodEsbVoucherKioskOnly() {
        
        $paymentMethodModel = PaymentMethod::find()
            ->where(['paymentMethodTypeID' => 4])
            ->andWhere(['voucherSourceID' => 1])
            ->andWhere(['flagActive' => 1])
            ->orderBy([
                'paymentMethodID' => SORT_DESC
            ])
            ->one();

        return $paymentMethodModel;
    }

}
