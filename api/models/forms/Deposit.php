<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\Member;
use app\models\MemberDeposit;
use app\models\PaymentMethod;
use app\models\Setting;
use Yii;
use yii\base\Model;
use yii\db\Exception;
use yii\web\HttpException;

/**
 * @property int $memberID
 * @property int $paymentMethodID
 * @property string $depositTotal
 * @property string $additionalInfo
 * @property string $memberDepositNum
 * @property string $authUserName
 * 
 * PRIVATE
 * @property Member $memberModel
 * @property PaymentMethod $paymentMethodModel
 */
class Deposit extends Model {
    public $memberID;
    public $memberCode;
    public $paymentMethodID;
    public $depositTotal;
    public $additionalInfo;
    public $memberDepositNum;
    public $memberModel;
    public $paymentMethodModel;
    public $usedDepositTotal;
    public $username;
    public $memberMode;
    public $authUserName;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['memberCode', 'paymentMethodID', 'depositTotal'], 'required'],
            [['memberID', 'paymentMethodID'], 'integer'],
            [['depositTotal', 'usedDepositTotal'], 'number'],
            [['additionalInfo'], 'string', 'max' => 100],
            [['username', 'memberDepositNum', 'authUserName'], 'safe'],
            [['memberCode'], 'validateMember'],
            [['paymentMethodID'], 'validatePaymentMethod'],
        ];
    }

    public function validateMember($attribute) {
        $this->memberModel = Member::findActive()
            ->andWhere(['memberCode' => $this->memberCode])
            ->one();
        if (!$this->memberModel) {
            $this->addError($attribute, 'Invalid member');
        } else {
            $this->memberID = $this->memberModel->memberID;
        }
    }

    public function validatePaymentMethod($attribute) {
        $this->paymentMethodModel = PaymentMethod::findActive()
            ->andWhere(['paymentMethodID' => $this->paymentMethodID])
            ->one();
        if (!$this->paymentMethodModel) {
            $this->addError($attribute, 'Invalid payment method');
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $branchID = Setting::getCurrentBranch();
            $depositModel = new MemberDeposit([
                'attributes' => $this->getAttributes()
            ]);

            $outstandingDepositTotal = $this->depositTotal;
            $usedDepositTotal = 0;
            if ($this->depositTotal > 0) {
                // @notes: Pengecekan hutang deposit
                $outstandingDeposit = MemberDeposit::getOutstandingDeposit($this->memberCode);
                if ($outstandingDeposit < 0) {
                    $memberDepositModel = MemberDeposit::find()
                        ->where(['memberCode' => $this->memberCode])
                        ->andWhere(['<', 'depositTotal', 0])
                        ->andWhere(['<>', 'depositTotal', 'usedDepositTotal'])
                        ->orderBy('memberDepositDate, memberDepositNum')
                        ->all();
                    foreach($memberDepositModel as $memberDeposit) {
                        if ($memberDeposit) {
                            if (($memberDeposit->depositTotal - $memberDeposit->usedDepositTotal) + $outstandingDepositTotal >= 0) {
                                $substractionAmount = $memberDeposit->depositTotal - $memberDeposit->usedDepositTotal;
                            } else {
                                $substractionAmount = $outstandingDepositTotal * -1;
                            }

                            $outstandingDepositTotal += $substractionAmount;
                            $memberDeposit->usedDepositTotal = $memberDeposit->usedDepositTotal + $substractionAmount;
                            $usedDepositTotal += abs($substractionAmount);

                            if (!$memberDeposit->save()) {
                                throw new Exception('Failed to save deposit');
                            }
                        }
                    }
                }
            }
            
            $depositModel->depositTotal = $this->depositTotal;
            $depositModel->usedDepositTotal = $usedDepositTotal;
            $depositModel->memberID = $this->memberID;
            $depositModel->memberDepositDate = date('Y-m-d');
            $depositModel->memberDepositNum = AppHelper::createNewTransactionNumber('Member Deposit',
                    $depositModel->memberDepositDate, $branchID);
            $depositModel->branchID = $branchID;
            $depositModel->additionalInfo = AppHelper::checkSpecialChar($depositModel->additionalInfo);

            if (!$depositModel->save()) {
                throw new Exception('Failed to save deposit');
            }
            $this->memberDepositNum = $depositModel->memberDepositNum;

            Logging::save($this->memberDepositNum, Logging::CREATE_DEPOSIT, $this->getAttributes());

            if($this->authUserName && $this->authUserName != '') {
                Logging::save($this->memberDepositNum, Logging::MEMBER_DEPOSIT_WITH_PIN, $this->getAttributes());
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollback();
            Yii::error($ex->getMessage());
            $this->addError('memberCode', $ex->getMessage());
            return true;
        }
    }

    public function saveOnline() {
        if (!$this->validate()) {
            return MemberDepositWithdrawalOnline::apiError(400, $this->errors[0]);
        }

        $sendDeposit = false;
        $branchID = null;

        //send deposit online
        try {
            $branchID = Setting::getCurrentBranch();
            if(strlen($this->memberDepositNum) == 0){
                $this->memberDepositNum = AppHelper::createNewTransactionNumber(
                    'Member Deposit',
                    date('Y-m-d'),
                    $branchID
                );
            }
            
            $depositWithdrawalOnlineModel = new MemberDepositWithdrawalOnline([
                'attributes' => $this->getAttributes()
            ]);
            
            // c h e c k  s p e c i a l  c h a r a c t e r
            $depositWithdrawalOnlineModel->additionalInfo = AppHelper::checkSpecialChar($depositWithdrawalOnlineModel->additionalInfo);

            $sendDeposit = $depositWithdrawalOnlineModel->sendDeposit($branchID);

            if($sendDeposit == false){
                $errorMsg = ($depositWithdrawalOnlineModel->responseData)
                    ? json_decode($depositWithdrawalOnlineModel->responseData["message"])
                    : 'Server Error';
                return MemberDepositWithdrawalOnline::apiError(400, $errorMsg);
            }
        } catch (Exception $ex) {
            return MemberDepositWithdrawalOnline::apiError(400, 'Failed to send deposit online');
        }

        //save deposit to local db
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $depositModel = new MemberDeposit([
                'attributes' => $this->getAttributes()
            ]);

            // c h e c k  s p e c i a l  c h a r a c t e r
            $depositModel->additionalInfo = AppHelper::checkSpecialChar($depositModel->additionalInfo);

            $outstandingDepositTotal = $this->depositTotal;
            $usedDepositTotal = 0;
            if ($this->depositTotal > 0) {
                // @notes: Pengecekan hutang deposit
                $outstandingDeposit = MemberDeposit::getOutstandingDeposit($this->memberCode);
                if ($outstandingDeposit < 0) {
                    $memberDepositModel = MemberDeposit::find()
                        ->where(['memberCode' => $this->memberCode])
                        ->andWhere(['<', 'depositTotal', 0])
                        ->andWhere(['<>', 'depositTotal', 'usedDepositTotal'])
                        ->orderBy('memberDepositDate, memberDepositNum')
                        ->all();
                    foreach($memberDepositModel as $memberDeposit) {
                        if ($memberDeposit) {
                            if (($memberDeposit->depositTotal - $memberDeposit->usedDepositTotal) + $outstandingDepositTotal >= 0) {
                                $substractionAmount = $memberDeposit->depositTotal - $memberDeposit->usedDepositTotal;
                            } else {
                                $substractionAmount = $outstandingDepositTotal * -1;
                            }

                            $outstandingDepositTotal += $substractionAmount;
                            $memberDeposit->usedDepositTotal = $memberDeposit->usedDepositTotal + $substractionAmount;
                            $usedDepositTotal += abs($substractionAmount);

                            if (!$memberDeposit->save()) {
                                throw new Exception('Failed to save deposit');
                            }
                        }
                    }
                }
            }
            
            $depositModel->depositTotal = $this->depositTotal;
            $depositModel->usedDepositTotal = $usedDepositTotal;
            $depositModel->memberID = $this->memberID;
            $depositModel->memberDepositDate = date('Y-m-d');
            $depositModel->memberDepositNum = $this->memberDepositNum;
            $depositModel->branchID = $branchID;
            $depositModel->syncDate = date('Y-m-d H:i:s');
            if (!$depositModel->save()) {
                throw new Exception('Failed to save deposit');
            }

            Logging::save($this->memberDepositNum, Logging::CREATE_DEPOSIT, $this->getAttributes());

            if($this->authUserName && $this->authUserName != '') {
                Logging::save($this->memberDepositNum, Logging::MEMBER_DEPOSIT_WITH_PIN, $this->getAttributes());
            }

            $transaction->commit();
            return [
                "memberCode" => $this->memberMode,
                "memberDepositNum" => $this->memberDepositNum,
                "balance" => (float) $depositWithdrawalOnlineModel->responseData["balance"],
                "activeBalance" => (float) $depositWithdrawalOnlineModel->responseData["activeBalance"]
            ];
        } catch (Exception $ex) {
            $transaction->rollback();
            Yii::error($ex->getMessage());
            $errorData = ["memberDepositNum" => $this->memberDepositNum];
            return MemberDepositWithdrawalOnline::apiError(500, $ex->getMessage(), $errorData);
        }
    }
}
