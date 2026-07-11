<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_poscustomerdisplayapplication".
 *
 * @property int $posCustomerDetailID
 * @property string $applicationID
 */
class PosCustomerDisplayApplication extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_poscustomerdisplayapplication';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['posCustomerDetailID', 'applicationID'], 'required'],
            [['posCustomerDetailID'], 'number'],
            [['applicationID'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'posCustomerDetailID' => 'Pos Customer Detail ID',
            'applicationID' => 'Application ID'
        ];
    }
}
