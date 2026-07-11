<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_status".
 *
 * @property int $statusID
 * @property string $statusName
 */
class Status extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_status';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['statusID'], 'required'],
            [['statusID'], 'integer'],
            [['statusName'], 'string', 'max' => 50],
            [['statusID'], 'unique']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'statusID' => 'Status ID',
            'statusName' => 'Status Name'
        ];
    }

}
