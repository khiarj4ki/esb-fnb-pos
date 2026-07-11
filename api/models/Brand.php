<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_brand".
 *
 * @property int $brandID
 * @property string $brandName
 * @property bool $flagActive
 * @property string $posXenditApiKey
 * @property string $posXenditVerificationToken
 * @property string $posMidtransServerKey
 * @property string $ezoXenditApiKey
 * @property string $ezoXenditVerificationToken
 * @property string $ezoMidtransServerKey
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 * @property Branch $branch
 */
class Brand extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_brand';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['brandID', 'brandName'], 'required'],
            [['brandName', 'membershipType'], 'string', 'max' => 50],
            [['posXenditApiKey','posXenditVerificationToken', 'posMidtransServerKey',
                'ezoXenditApiKey','ezoXenditVerificationToken', 'ezoMidtransServerKey'], 'string', 'max' => 100],
            [['flagActive', 'createdBy', 'createdDate', 'editedBy', 'editedDate'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'brandID' => 'Brand',
            'brandName' => 'Brand Name',
            'posXenditApiKey' => 'Xendit Api Key',
            'posXenditVerificationToken' => 'Xendit Verification Token',
            'posMidtransServerKey' => 'Midtrans Server Key',
            'ezoXenditApiKey' => 'Xendit Api Key',
            'ezoXenditVerificationToken' => 'Xendit Verification Token',
            'ezoMidtransServerKey' => 'Midtrans Server Key',
            'flagActive' => 'Status',
        ];
    }

    public function getBranch() {
        return $this->hasOne(Branch::class,
                    ['brandID' => 'brandID']);
    }

}
