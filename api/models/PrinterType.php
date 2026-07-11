<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_printertype".
 *
 * @property int $printerTypeID
 * @property string $printerTypeName
 */
class PrinterType extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_printertype';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['printerTypeName'], 'string', 'max' => 50],
            [['printerTypeID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'printerTypeID' => 'Printer Type I D',
            'printerTypeName' => 'Printer Type Name'
        ];
    }

}
