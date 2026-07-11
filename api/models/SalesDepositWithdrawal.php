<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesdepositwithdrawal".
 *
 * @property int $ID
 * @property int $localID
 * @property string $salesNum
 * @property string $memberDepositNum
 * @property string $paymentTotal
 * @property string $syncDate
 */
class SalesDepositWithdrawal extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesdepositwithdrawal';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['localID'], 'integer'],
            [['salesNum', 'memberDepositNum', 'paymentTotal'], 'required'],
            [['paymentTotal'], 'number'],
            [['syncDate'], 'safe'],
            [['salesNum', 'memberDepositNum'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'localID' => 'Local ID',
            'salesNum' => 'Sales Num',
            'memberDepositNum' => 'Member Deposit Num',
            'paymentTotal' => 'Payment Total',
            'syncDate' => 'Sync Date'
        ];
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->syncDate = null;

        return true;
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $this->localID = $this->ID;
            $this->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }

}
