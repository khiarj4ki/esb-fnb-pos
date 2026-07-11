<?php
namespace app\models;

use yii\db\ActiveRecord;
/**
 * This is the model class for table "tr_customertransaction".
 * @property string $salesNum
 * @property string $fullName
 * @property string $email
 * @property string $phoneNumber
 */
class CustomerTransaction extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_customertransaction';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'fullName', 'email', 'phoneNumber'], 'required'],
            [['salesNum', 'fullName', 'email', 'phoneNumber'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'salesNum' => 'Sales Number',
            'fullName' => 'Full Name',
            'email' => 'Email',
            'phoneNumber' => 'Phone Number'
        ];
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public function beforeSave($insert) {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        return true;
    }
}
