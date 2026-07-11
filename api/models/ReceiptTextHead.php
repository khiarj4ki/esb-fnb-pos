<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_receipttexthead".
 *
 * @property int $genderID
 * @property string $genderName
 */
class ReceiptTextHead extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_receipttexthead';
    }

    public function rules() {
        return [
            [['receiptTextID'], 'required'],
            [['notes'], 'string', 'max' => 100],
            [['minimumSalesTotal', 'flagMultiplier', 'createdBy', 'createdDate'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'receiptTextID' => 'Receipt Text ID',
            'notes' => 'Notes',
            'minimumSalesTotal' => 'Minimum Sales Total',
            'flagMultiplier' => 'Flag Multiplier',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date'
        ];
    }

    public static function getReceiptText($billingTotal) {
        $randNumber = 0;
        $selectedReceiptText = '';
        $receiptModel = ReceiptTextDetail::find()
            ->select([
                'tr_receipttextdetail.ID',
                'tr_receipttextdetail.receiptTextID',
                'tr_receipttextdetail.receiptTextDesc',
                'tr_receipttexthead.notes',
                'tr_receipttexthead.minimumSalesTotal',
                'tr_receipttexthead.flagMultiplier',
                'tr_receipttexthead.createdBy',
                'tr_receipttexthead.createdDate'
            ])
            ->joinWith('receiptTextHead')
            ->where(['<', 'minimumSalesTotal', $billingTotal])
            ->all();
        if ($receiptModel) {
            $deleteDetailID = 0;
            $randNumber = rand(0, count($receiptModel) - 1);
            foreach ($receiptModel as $key => $dataReceipt) {
                if ($key == $randNumber) {
                    $selectedReceiptText = $dataReceipt->receiptTextDesc;
                    $deleteDetailID = $dataReceipt->ID;
                    break;
                }
            }
            ReceiptTextDetail::deleteAll(['ID' => $deleteDetailID]);
        }
        return $selectedReceiptText;
    }

}
