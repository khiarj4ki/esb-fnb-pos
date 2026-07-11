<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_printerconnection".
 *
 * @property int $printerConnectionID
 * @property string $printerConnectionName
 */
class PrinterConnection extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_printerconnection';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['printerConnectionName'], 'string', 'max' => 50],
            [['printerConnectionID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'printerConnectionID' => 'Printer Connection ID',
            'printerConnectionName' => 'Printer Connection Name'
        ];
    }

}
