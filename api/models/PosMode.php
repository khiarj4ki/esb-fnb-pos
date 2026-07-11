<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lk_posmode".
 *
 * @property int $posModeID
 * @property string $posModeName
 */
class PosMode extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'lk_posmode';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['posModeName'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'posModeID' => 'Pos Mode ID',
            'posModeName' => 'Pos Mode Name',
        ];
    }
}
