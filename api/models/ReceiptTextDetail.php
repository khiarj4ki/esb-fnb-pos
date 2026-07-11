<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_receipttextdetail".
 *
 * @property int $genderID
 * @property string $genderName
 */
class ReceiptTextDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_receipttextdetail';
    }

    public function rules() {
        return [
            [['ID', 'receiptTextID'], 'required'],
            [['receiptTextDesc'], 'string', 'max' => 100],
            [['ID', 'receiptTextID', 'receiptTextDesc'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'receiptTextID' => 'Receipt Text ID',
            'receiptTextDesc' => 'Receipt Text Desc'
        ];
    }
    
    public function getReceiptTextHead() {
        return $this->hasOne(ReceiptTextHead::class,
                        ['receiptTextID' => 'receiptTextID']);
    }

}
