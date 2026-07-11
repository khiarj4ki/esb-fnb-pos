<?php
namespace app\models\forms;

use app\components\AppHelper;
use app\models\DepositWithdrawalDetail;
use app\models\DepositWithdrawalHead;
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
 * @property string $withdrawalTotal
 * @property string $additionalInfo
 * @property string $depositWithdrawalNum
 * @property string $authUserName
 * 
 * PRIVATE
 * @property Member $memberModel
 * @property PaymentMethod $paymentMethodModel
 */
class Withdrawal extends Model {
    public $memberID;
    public $memberCode;
    public $paymentMethodID;
    public $withdrawalTotal;
    public $additionalInfo;
    public $depositWithdrawalNum;
    public $memberModel;
    public $paymentMethodModel;
    public $username;
    public $memberMode;
    public $authUserName;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['memberCode', 'paymentMethodID', 'withdrawalTotal'], 'required'],
            [['memberID', 'paymentMethodID'], 'integer'],
            [['withdrawalTotal'], 'number'],
            [['additionalInfo'], 'string', 'max' => 100],
            [['username', 'depositWithdrawalNum', 'authUserName'], 'safe'],
            [['memberCode'], 'validateMember'],
            [['paymentMethodID'], 'validatePaymentMethod'],
            [['withdrawalTotal'], 'validateWithdrawal'],
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

    public function validateWithdrawal($attribute) {
        if($this->memberMode != "online"){
            $availableDeposit = MemberDeposit::getOutstandingDeposit($this->memberCode);
            if ($this->withdrawalTotal > $availableDeposit) {
                $this->addError($attribute, 'Invalid withdrawal amount');
                Yii::error('Invalid withdrawal amount');
            }
        }
    }

    public function save() {
        if (!$this->validate()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $branchID = Setting::getCurrentBranch();

            $withdrawalHeadModel = new DepositWithdrawalHead([
                'attributes' => $this->getAttributes()
            ]);

            $withdrawalHeadModel->memberID = $this->memberID;
            $withdrawalHeadModel->depositWithdrawalDate = date('Y-m-d');
            $withdrawalHeadModel->depositWithdrawalNum = AppHelper::createNewTransactionNumber('Deposit Withdrawal',
                    $withdrawalHeadModel->depositWithdrawalDate, $branchID);
            $withdrawalHeadModel->branchID = $branchID;
            $withdrawalHeadModel->additionalInfo = AppHelper::checkSpecialChar($this->additionalInfo);
            
            if (!$withdrawalHeadModel->save()) {
                Yii::error($withdrawalHeadModel->errors);
                throw new Exception('Failed to save withdrawal head');
            }

            $withdrawalDetail = MemberDeposit::substractDeposit($this->memberCode,
                    $this->withdrawalTotal);
            foreach ($withdrawalDetail as $detail) {
                $withdrawalDetailModel = new DepositWithdrawalDetail();
                $withdrawalDetailModel->depositWithdrawalNum = $withdrawalHeadModel->depositWithdrawalNum;
                $withdrawalDetailModel->memberDepositNum = $detail['memberDepositNum'];
                $withdrawalDetailModel->withdrawalTotal = $detail['substractionAmount'];
                if (!$withdrawalDetailModel->save()) {
                    Yii::error($withdrawalDetailModel->errors);
                    throw new Exception('Failed to save withdrawal detail');
                }
            }
            $this->depositWithdrawalNum = $withdrawalHeadModel->depositWithdrawalNum;

            Logging::save($this->depositWithdrawalNum, Logging::CREATE_WITHDRAWAL, $this->getAttributes());
            
            if ($this->authUserName && $this->authUserName != '') {
                Logging::save($this->depositWithdrawalNum, Logging::MEMBER_WITHDRAWAL_WITH_PIN, $this->getAttributes());
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollback();
            $this->addError('memberID', $ex->getMessage());
            return false;
        }
    }

    public function saveOnline(){
        $this->memberMode = "online";
        if (!$this->validate()) {
            Yii::error($this->errors);
            return MemberDepositWithdrawalOnline::apiError(400, $this->errors[0]);
        }

        $sendWithdrawal = false;
        $branchID = null;

        //send withdraw online
        try {
            $branchID = Setting::getCurrentBranch();
            if (strlen($this->depositWithdrawalNum) == 0) {
                $this->depositWithdrawalNum = AppHelper::createNewTransactionNumber(
                    'Deposit Withdrawal',
                    date('Y-m-d'),
                    $branchID
                );
            }

            $depositWithdrawalOnlineModel = new MemberDepositWithdrawalOnline([
                'attributes' => $this->getAttributes()
            ]);
            $sendWithdrawal = $depositWithdrawalOnlineModel->sendWithdrawal($branchID);

            if($sendWithdrawal == false){
                $errorMsg = ($depositWithdrawalOnlineModel->responseData)
                    ? json_decode($depositWithdrawalOnlineModel->responseData["message"])
                    : 'Server Error';
                return MemberDepositWithdrawalOnline::apiError(400, $errorMsg);
            }
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return MemberDepositWithdrawalOnline::apiError(400, 'Internal Server Error');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $withdrawalHeadModel = new DepositWithdrawalHead([
                'attributes' => $this->getAttributes()
            ]);

            $withdrawalHeadModel->memberID = $this->memberID;
            $withdrawalHeadModel->depositWithdrawalDate = date('Y-m-d');
            $withdrawalHeadModel->depositWithdrawalNum = $this->depositWithdrawalNum;
            $withdrawalHeadModel->branchID = $branchID;
            $withdrawalHeadModel->additionalInfo = AppHelper::checkSpecialChar($withdrawalHeadModel->additionalInfo);
            $syncDate = date('Y-m-d H:i:s');
            $withdrawalHeadModel->syncDate = $syncDate;
            if (!$withdrawalHeadModel->save()) {
                Yii::error($withdrawalHeadModel->errors);
                throw new Exception('Failed to save withdrawal head');
            }
            
            if(isset($depositWithdrawalOnlineModel->responseData["withdrawalDetails"])){
                foreach ($depositWithdrawalOnlineModel->responseData["withdrawalDetails"] as $detail) {
                    $withdrawalDetailModel = new DepositWithdrawalDetail();
                    $withdrawalDetailModel->depositWithdrawalNum = $withdrawalHeadModel->depositWithdrawalNum;
                    $withdrawalDetailModel->memberDepositNum = $detail['memberDepositNum'];
                    $withdrawalDetailModel->withdrawalTotal = $detail['withdrawalTotal'];
                    $withdrawalDetailModel->syncDate = $syncDate;
                    if (!$withdrawalDetailModel->save()) {
                        Yii::error($withdrawalDetailModel->errors);
                        throw new Exception('Failed to save withdrawal detail');
                    } else {
                        //update usedDepositTotal
                        $depositModel = MemberDeposit::findOne(['memberDepositNum' => $detail['memberDepositNum']]);
                        if ($depositModel) {
                            $newUsedDepositTotal = $depositModel->usedDepositTotal + $detail['withdrawalTotal'];
                            $depositModel->usedDepositTotal = $newUsedDepositTotal;
                            $depositModel->syncDate = $syncDate;
                            if (!$depositModel->save()) {
                                Yii::error($depositModel->errors);
                                throw new Exception('Failed to update member deposit usage');
                            }
                        }
                    }
                }
            }

            Logging::save($this->depositWithdrawalNum, Logging::CREATE_WITHDRAWAL, $this->getAttributes());

            if ($this->authUserName && $this->authUserName != '') {
                Logging::save($this->depositWithdrawalNum, Logging::MEMBER_WITHDRAWAL_WITH_PIN, $this->getAttributes());
            }

            $transaction->commit();
            return [
                "memberCode" => $this->memberCode,
                "depositWithdrawalNum" => $this->depositWithdrawalNum,
                "balance" => (float) $depositWithdrawalOnlineModel->responseData["balance"],
                "activeBalance" => (float) $depositWithdrawalOnlineModel->responseData["activeBalance"]
            ];
        } catch (Exception $ex) {
            $transaction->rollback();
            Yii::error($ex->getMessage());
            $errorData = ["depositWithdrawalNum" => $this->depositWithdrawalNum];
            return MemberDepositWithdrawalOnline::apiError(500,  $ex->getMessage(), $errorData);
        }
    }

}
