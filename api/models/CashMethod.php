<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_cashmethod".
 *
 * @property int $cashMethodID
 * @property string $cashMethodAmount
 * @property int $flagActive
 * @property string $createdBy
 * @property string $createdDate
 * @property string $editedBy
 * @property string $editedDate
 */
class CashMethod extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_cashmethod';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['cashMethodAmount', 'flagActive', 'createdBy', 'createdDate'], 'required'],
            [['cashMethodAmount'], 'number'],
            [['flagActive'], 'integer'],
            [['createdDate', 'editedDate'], 'safe'],
            [['createdBy', 'editedBy'], 'string', 'max' => 100]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'cashMethodID' => 'Cash Method ID',
            'cashMethodAmount' => 'Cash Method Amount',
            'flagActive' => 'Flag Active',
            'createdBy' => 'Created By',
            'createdDate' => 'Created Date',
            'editedBy' => 'Edited By',
            'editedDate' => 'Edited Date'
        ];
    }

    public function fields() {
        $fields = parent::fields();
        $fields['cashMethodAmount'] = function ($model) {
            return (float) $model->cashMethodAmount;
        };
        $fields['cashMethodDisplay'] = function ($model) {
            $settings = Setting::getPrintingSettings();
            $salesDecimalSetting = isset($settings['Sales Decimal Setting']) ? $settings['Sales Decimal Setting'] : 0;
            $salesDecimalSeparatorSetting = isset($settings['Sales Decimal Separator Setting']) ? $settings['Sales Decimal Separator Setting'] : ',';
            $reverseDecimalSeparator = $salesDecimalSeparatorSetting == '.' ? ',' : '.';
            return number_format($model->cashMethodAmount, $salesDecimalSetting, "$salesDecimalSeparatorSetting", "$reverseDecimalSeparator");
        };

        return $fields;
    }

    public static function findActive() {
        return CashMethod::find()->andWhere([CashMethod::tableName() . '.flagActive' => 1])
                ->orderBy(CashMethod::tableName() . '.cashMethodAmount DESC');
    }

}
