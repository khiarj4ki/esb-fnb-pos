<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class TrCustomerTransaction extends ActiveRecord {

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'tr_customertransaction';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['salesNum', 'fullName', 'email', 'phoneNumber'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Number',
            'fullName' => 'Full Name',
            'email' => 'Email',
            'phoneNumber' => 'Phone Number'
        ];
    }

    public static function findEmailBySalesNum($salesNum) {
        $result = '';
        if ($salesNum) {
            $dataCustomer = self::findOne(['salesNum' => $salesNum]);
            if ($dataCustomer) {
                $result = $dataCustomer->email;
            }
        }

        return $result;
    }

    public static function syncUpdate($salesNum, $syncDate) {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            TrCustomerTransaction::updateAll(
                [ 'syncDate' => $syncDate ],
                [ 'salesNum' => $salesNum ]
            );

            $transaction->commit();
            return true;
        } catch (\Exception $ex) {
            $transaction->rollBack();
            return false;
        }
    }

}