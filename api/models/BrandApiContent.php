<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_brandapicontent".
 *
 * @property int $brandID
 * @property int $brandSettingID
 * @property string $keyAttribute
 * @property string $valueAttribute
 */
class BrandApiContent extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_brandapicontent';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['brandID', 'brandSettingID', 'keyAttribute', 'valueAttribute'], 'required']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'brandID' => 'Brand',
            'brandSettingID' => 'Brand Setting',
            'keyAttribute' => 'Key Attribute',
            'valueAttribute' => 'Value Attribute',
        ];
    }

    public static function findApiContent($brandID, $key2) {
        return BrandApiContent::find()
            ->innerJoinWith('lkBrandSetting')
            ->where(['brandID' => $brandID])
            ->andWhere([LkBrandSetting::tableName() . '.key2' => $key2]);
    }

    public function getLkBrandSetting() {
        return $this->hasOne(LkBrandSetting::class,
                ['brandSettingID' => 'brandSettingID']);
    }

}
