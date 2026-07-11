<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_depositwithdrawaldetail".
 *
 * @property int $ID
 * @property string $depositWithdrawalNum
 * @property string $memberDepositNum
 * @property string $withdrawalTotal
 * @property string $syncDate
 */
class DepositWithdrawalDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_depositwithdrawaldetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['depositWithdrawalNum', 'memberDepositNum', 'withdrawalTotal'], 'required'],
            [['withdrawalTotal'], 'number'],
            [['syncDate'], 'safe'],
            [['depositWithdrawalNum', 'memberDepositNum'], 'string', 'max' => 20]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'depositWithdrawalNum' => 'Deposit Withdrawal Num',
            'memberDepositNum' => 'Member Deposit Num',
            'withdrawalTotal' => 'Withdrawal Total',
            'syncDate' => 'Sync Date'
        ];
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

}
