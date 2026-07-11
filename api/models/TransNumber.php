<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_transnumber".
 *
 * @property int $transNumberID
 * @property string $transType
 * @property string $transAbbreviation
 */
class TransNumber extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_transnumber';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['transType', 'transAbbreviation'], 'required'],
            [['transType'], 'string', 'max' => 50],
            [['transAbbreviation'], 'string', 'max' => 3],
            [['transNumberID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'transNumberID' => 'Trans Number ID',
            'transType' => 'Trans Type',
            'transAbbreviation' => 'Trans Abbreviation'
        ];
    }

}
