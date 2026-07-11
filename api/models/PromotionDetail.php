<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_promotiondetail".
 *
 * @property int $ID
 * @property int $promotionID
 * @property int $menuID
 * @property string $qty
 */
class PromotionDetail extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_promotiondetail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['promotionID', 'menuID', 'qty'], 'required'],
            [['promotionID', 'menuID'], 'integer'],
            [['qty'], 'number'],
            [['ID','menuSubsID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'promotionID' => 'Promotion ID',
            'menuID' => 'Menu ID',
            'qty' => 'Qty'
        ];
    }

}
