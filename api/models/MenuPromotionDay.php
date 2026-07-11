<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_menupromotionday".
 *
 * @property int $ID
 * @property int $headID
 * @property int $dayID
 */
class MenuPromotionDay extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_menupromotionday';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['headID', 'dayID'], 'required'],
            [['headID', 'dayID'], 'integer'],
            [['ID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'headID' => 'Head ID',
            'dayID' => 'Day ID'
        ];
    }

}
