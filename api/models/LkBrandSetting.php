<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "lk_brandsetting".
 *
 * @property int $brandSettingID
 * @property string $key1
 * @property string $key2
 */
class LkBrandSetting extends ActiveRecord {
    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'lk_brandsetting';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['brandSettingID', 'key1', 'key2'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'brandSettingID' => 'Brand Setting',
            'key1' => 'Key 1',
            'key2' => 'Key 2',
        ];
    }

    public function getBrandSetting() {
        return $this->hasOne(BrandSetting::class,
                ['brandSettingID' => 'brandSettingID']);
    }

}
