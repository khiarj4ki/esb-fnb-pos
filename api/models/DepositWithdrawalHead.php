<?php
namespace app\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "tr_depositwithdrawalhead".
 *
 * @property string $depositWithdrawalNum
 * @property string $depositWithdrawalDate
 * @property int $branchID
 * @property int $memberID
 * @property int $currencyID
 * @property string $rate
 * @property int $paymentMethodID
 * @property string $withdrawalTotal
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
 * @property Member $member
 * @property PaymentMethod $paymentMethod
 * @property Status $status
 * @property PosUser $creator
 */
class DepositWithdrawalHead extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_depositwithdrawalhead';
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
            [['depositWithdrawalNum', 'depositWithdrawalDate', 'branchID', 'memberID', 'memberCode', 'paymentMethodID', 'withdrawalTotal'], 'required'],
            [['depositWithdrawalDate', 'createdDate', 'editedDate', 'authorizedDate', 'syncDate'], 'safe'],
            [['branchID', 'memberID', 'paymentMethodID', 'currencyID', 'statusID'], 'integer'],
            [['rate', 'withdrawalTotal'], 'number'],
            [['currencyID', 'rate'], 'default', 'value' => 1],
            [['statusID'], 'default', 'value' => 1],
            [['depositWithdrawalNum'], 'string', 'max' => 20],
            [['additionalInfo', 'createdBy', 'editedBy', 'authorizedBy'], 'string', 'max' => 100],
            [['additionalInfo'], 'default', 'value' => ''],
            [['depositWithdrawalNum'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'depositWithdrawalNum' => 'Deposit Withdrawal Num',
            'depositWithdrawalDate' => 'Deposit Withdrawal Date',
            'branchID' => 'Branch ID',
            'memberID' => 'Member ID',
            'currencyID' => 'Currency ID',
            'rate' => 'Rate',
            'paymentMethodID' => 'Payment Method ID',
            'withdrawalTotal' => 'Withdrawal Total',
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
        $fields['withdrawalTotal'] = function ($model) {
            return (float) $model->withdrawalTotal;
        };
        $fields['memberCode'] = function ($model) {
            return $model->member ? $model->member->memberCode : '';
        };
        $fields['memberName'] = function ($model) {
            return $model->member ? $model->member->memberName : '';
        };
        $fields['statusName'] = function ($model) {
            return $model->status->statusName;
        };

        return $fields;
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

    public static function syncUpdate($depositWithdrawalNum, $syncDate) {
        $branchID = Setting::getCurrentBranch();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            DepositWithdrawalHead::updateAll([
                'syncDate' => $syncDate
                ],
                ['depositWithdrawalNum' => $depositWithdrawalNum]);

            DepositWithdrawalDetail::updateAll([
                'syncDate' => $syncDate
                ], ['depositWithdrawalNum' => $depositWithdrawalNum]
            );

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex);
            return false;
        }
    }

}
