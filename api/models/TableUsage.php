<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_tableusage".
 *
 * @property int $ID
 * @property string $referenceID
 * @property string $expiredTime
 * @property string $username
 * 
 * @property PosUser $posUser
 */
class TableUsage extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_tableusage';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['referenceID', 'expiredTime', 'username'], 'required'],
            [['expiredTime'], 'safe'],
            [['referenceID'], 'string', 'max' => 20],
            [['username'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'referenceID' => 'Reference ID',
            'expiredTime' => 'Expired Time',
            'username' => 'Username',
        ];
    }

    public function getPosUser() {
        return $this->hasOne(PosUser::class, ['username' => 'username']);
    }

}
