<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_printingmode".
 *
 * @property int $printingModeID
 * @property string $printingModeName
 */
class PrintingMode extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_printingmode';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['printingModeName'], 'string', 'max' => 50],
            [['printingModeID'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'printingModeID' => 'Printing Mode',
            'printingModeName' => 'Printing Mode'
        ];
    }

}