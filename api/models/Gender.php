<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_gender".
 *
 * @property int $genderID
 * @property string $genderName
 */
class Gender extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_gender';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['genderName'], 'string', 'max' => 50],
            [['genderID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'genderID' => 'Gender ID',
            'genderName' => 'Gender Name'
        ];
    }

}
