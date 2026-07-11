<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_posexternalpayment".
 *
 * @property string $posExternalPaymentID
 * @property string $posExternalPaymentName
 * @property string $posExternalPaymentType
 * @property string $minimumPaymentAmount
 */
class PosExternalPayment extends ActiveRecord {
    
   public static function tableName() {
        return 'lk_posexternalpayment';
    }

    public function rules() {
        return [
            [['posExternalPaymentID'], 'string', 'max' => 10],
            [['posExternalPaymentName'], 'string', 'max' => 50],
            [['posExternalPaymentType'], 'string', 'max' => 20],
            [['minimumPaymentAmount'], 'safe'],
        ];
    }

    public function attributeLabels() {
        return [
            'posExternalPaymentID' => Yii::t('app', 'Pos External Payment ID'),
            'posExternalPaymentName' => Yii::t('app', 'Pos External Payment Name'),
            'posExternalPaymentType' => Yii::t('app', 'Pos External Payment Type'),
            'minimumPaymentAmount' => Yii::t('app', 'Minimum Payment Amount'),
        ];
    }

}
