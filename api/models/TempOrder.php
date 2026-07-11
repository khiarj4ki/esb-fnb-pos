<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_temporder".
 *
 * @property string $orderID
 * @property string $createdDate
 * @property int $orderData
 */
class TempOrder extends \yii\db\ActiveRecord
{

    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['createdDate'],
                ],
                'value' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tr_temporder';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['orderID', 'orderData'], 'required'],
            [['createdDate'], 'safe'],
            [['orderID'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'orderID' => 'Order ID',
            'createdDate' => 'Created Date',
            'orderData' => 'Order Data',
        ];
    }
}
