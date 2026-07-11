<?php
namespace app\models;

use app\models\forms\Deposit;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "tr_memberdeposit".
 *
 * @property string $memberDepositNum
 * @property string $memberDepositDate
 * @property int $branchID
 * @property int $memberID
 * @property int $paymentMethodID
 * @property int $currencyID
 * @property string $rate
 * @property string $depositTotal
 * @property string $usedDepositTotal
 * @property string $additionalInfo
 * @property int $statusID
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property string $authorizedBy
 * @property string $authorizedDate
 * @property string $syncDate
 * 
 * @property Branch $branch
 * @property Member $member
 * @property PaymentMethod $paymentMethod
 * @property Status $status
 * @property PosUser $creator
 */
class MemberDeposit extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_memberdeposit';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate'],
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
            [['memberDepositNum', 'memberDepositDate', 'branchID', 'memberID', 'memberCode', 'paymentMethodID', 'depositTotal'], 'required'],
            [['memberDepositDate', 'createdDate', 'editedDate', 'authorizedDate', 'syncDate'], 'safe'],
            [['branchID', 'memberID', 'paymentMethodID', 'currencyID', 'statusID'], 'integer'],
            [['rate', 'depositTotal', 'usedDepositTotal'], 'number'],
            [['currencyID', 'rate'], 'default', 'value' => 1],
            [['usedDepositTotal'], 'default', 'value' => 0],
            [['statusID'], 'default', 'value' => 1],
            [['memberDepositNum'], 'string', 'max' => 20],
            [['additionalInfo', 'createdBy', 'editedBy', 'authorizedBy'], 'string', 'max' => 100],
            [['additionalInfo'], 'default', 'value' => ''],
            [['memberDepositNum'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'memberDepositNum' => 'Member Deposit Num',
            'memberDepositDate' => 'Member Deposit Date',
            'branchID' => 'Branch ID',
            'memberID' => 'Member ID',
            'paymentMethodID' => 'Payment Method ID',
            'currencyID' => 'Currency ID',
            'rate' => 'Rate',
            'depositTotal' => 'Deposit Total',
            'usedDepositTotal' => 'Used Deposit Total',
            'additionalInfo' => 'Additional Info',
            'statusID' => 'Status ID',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date',
            'authorizedBy' => 'Authorized By',
            'authorizedDate' => 'Authorized Date',
            'syncDate' => 'Sync Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['memberName'] = function ($model) {
            return $model->member ? $model->member->memberName : '';
        };
        $fields['memberPhone'] = function ($model) {
            return $model->member ? $model->member->memberPhone : '';
        };
        $fields['memberEmail'] = function ($model) {
            return $model->member ? $model->member->memberEmail : '';
        };
        $fields['depositTotal'] = function ($model) {
            return (float) $model->depositTotal;
        };
        $fields['statusName'] = function ($model) {
            return $model->status->statusName;
        };

        return $fields;
    }

    public function getBranch() {
        return $this->hasOne(Branch::class, ['branchID' => 'branchID']);
    }

    public function getMember() {
        return $this->hasOne(Member::class, ['memberCode' => 'memberCode']);
    }

    public function getPaymentMethod() {
        return $this->hasOne(PaymentMethod::class,
                ['paymentMethodID' => 'paymentMethodID']);
    }

    public function getStatus() {
        return $this->hasOne(Status::class, ['statusID' => 'statusID']);
    }

    public function getCreator() {
        return $this->hasOne(PosUser::class, ['username' => 'createdBy']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $memberMode = Setting::getMemberMode();
        if (!($memberMode && $memberMode == 'online')) {
            $this->syncDate = null;
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->statusID = 3;
            $this->authorizedBy = $this->createdBy;
            $this->authorizedDate = $this->createdDate;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public static function getOutstandingDeposit($memberCode) {
        $model = MemberDeposit::find()
            ->select([
                'depositTotal' => 'COALESCE(SUM(depositTotal - usedDepositTotal), 0)'
            ])
            ->andWhere(['memberCode' => $memberCode])
            ->one();

        return $model->depositTotal;
    }

    public static function substractDeposit($memberCode, $substractionTotal, $paymentMethodID = null) {
        $outstanding = $substractionTotal;
        // @Notes: 3 = Authorized
        $depositModel = MemberDeposit::find()
            ->andWhere(['memberCode' => $memberCode])
            ->andWhere(['statusID' => 3])
            ->andWhere('usedDepositTotal < depositTotal')
            ->orderBy('memberDepositDate, memberDepositNum')
            ->all();
        $substractionDetail = [];
        foreach ($depositModel as $deposit) {
            $substractionAmount = 0;
            if ($deposit->depositTotal - $deposit->usedDepositTotal <= $outstanding) {
                $substractionAmount = $deposit->depositTotal - $deposit->usedDepositTotal;
            } else {
                $substractionAmount = $outstanding;
            }

            $substractionDetail[] = [
                'memberDepositNum' => $deposit->memberDepositNum,
                'substractionAmount' => $substractionAmount
            ];

            $deposit->usedDepositTotal += $substractionAmount;
            if (!$deposit->save()) {
                Yii::error($deposit->errors);
                throw new Exception('Failed to update deposit');
            }

            $outstanding -= $substractionAmount;
            if ($outstanding <= 0) {
                break;
            }
        }

        if ($outstanding > 0) {
            $memberModel = Member::findOne(['memberCode' => $memberCode]);
            if ($memberModel) {
                $depositModel = new Deposit();
                $depositModel->memberCode = $memberModel->memberCode;
                $depositModel->memberID = $memberModel->memberID;
                $depositModel->paymentMethodID = $paymentMethodID;
                $depositModel->depositTotal = $outstanding * -1;
                $depositModel->usedDepositTotal = 0;
                $depositModel->additionalInfo = 'Deposit Receivable';
                if (!$depositModel->save()) {
                    Yii::error($depositModel->getErrors());
                }

                $substractionDetail[] = [
                    'memberDepositNum' => $depositModel->memberDepositNum,
                    'substractionAmount' => $outstanding
                ];
            }
        }

        return $substractionDetail;
    }

    public static function syncUpdate($memberDepositNum, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            MemberDeposit::updateAll([
                'syncDate' => $syncDate
                ],
                ['memberDepositNum' => $memberDepositNum]);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

}
