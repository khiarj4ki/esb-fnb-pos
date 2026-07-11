<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "tr_salesinfo".
 *
 * @property integer $ID
 * @property string $salesNum
 * @property string $key
 * @property string $value
 * 
 */
class SalesInfo extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'tr_salesinfo';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['salesNum', 'key'], 'required'],
            [['value'], 'safe'],
            [['salesNum'], 'string', 'max' => 20],
            [['key'], 'string', 'max' => 100],
            [['value'], 'string', 'max' => 500]

        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'ID' => 'ID',
            'salesNum' => 'Sales Number',
            'key' => 'Key',
            'value' => 'Value'
        ];
    }

    public function getSalesHead() {
        return $this->hasOne(SalesHead::class, ['salesNum' => 'salesNum']);
    }

    public static function findBySalesNumKey($salesNum, $key) {
        $model = self::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['key' => $key])
            ->one();
        if ($model) {
            return $model->value;
        } else {
            return null;
        }
    }

    public static function findBySalesNum($salesNum) {
        $models = self::find()
            ->where(['salesNum' => $salesNum])
            ->all();
        if ($models) {
            return $models;
        } else {
            return [];
        }
    }

    public static function findBySalesNumPrinting($salesNum) {
        $model = self::find()
            ->where(['salesNum' => $salesNum])
            ->andWhere(['NOT IN', 'key', 'Full Name'])
            ->all();

        if ($model) {
            return $model;
        } else {
            return null;
        }
    }
}
