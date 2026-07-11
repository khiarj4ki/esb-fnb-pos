<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "lk_poscalculation".
 *
 * @property int $posCalculationID
 * @property string $posCalculationName
 */
class PosCalculation extends \yii\db\ActiveRecord
{
    
        
    public static function tableName()
    {
        return 'lk_poscalculation';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['posCalculationID','posCalculationName'], 'required'],
            [['posCalculationID'], 'integer'],
            [['posCalculationName'], 'string', 'max' => 50],
            [['posCalculationID'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'posCalculationID' => 'Pos Calculation ID',
            'posCalculationName' => 'Pos Calculation Name',
        ];
    }
}
