<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_tabletype".
 *
 * @property int $tableTypeID
 * @property string $tableTypeName
 */
class TableType extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_tabletype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['tableTypeName'], 'string', 'max' => 50],
            [['tableTypeID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'tableTypeID' => 'Table Type ID',
            'tableTypeName' => 'Table Type Name'
        ];
    }

}
