<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "ms_brandsetting".
 *
 * @property int $brandID
 * @property int $brandSettingID
 * @property string $key1
 * @property string $key2
 */
class BrandSetting extends ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'ms_brandsetting';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['brandID', 'brandSettingID'], 'required'],
            [['value1', 'value2'], 'safe']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'brandID' => 'Brand',
            'brandSettingID' => 'Brand Setting',
            'value1' => 'Value 1',
            'value2' => 'Value 2',
        ];
    }

    public function getLkBrandSetting() {
        return $this->hasOne(LkBrandSetting::class,
                ['brandSettingID' => 'brandSettingID']);
    }

    public function getBrand() {
        return $this->hasOne(Brand::class,
                ['brandID' => 'brandID']);
    }

    public static function getExternalMemberSetting() {
        $branchID = Setting::getCurrentBranch();
        $lkBrandSettingModel = LkBrandSetting::find()
            ->select(['brandSettingID', 'key2'])
            ->andWhere(['key1' => 'POS'])
            ->all();

        $result = [];
        $model = LkBrandSetting::find()
            ->select('value1')
            ->innerJoinWith('brandSetting')
            ->innerJoinWith('brandSetting.brand')
            ->innerJoinWith('brandSetting.brand.branch')
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['key1' => 'POS'])
            ->indexBy('key2')
            ->column();

        foreach($lkBrandSettingModel as $lkBrandSetting) {
            $result[$lkBrandSetting->key2] = array_key_exists($lkBrandSetting->key2, $model) ? $model[$lkBrandSetting->key2] : null;
        }
        return $result;
    }

    public static function getEzoBrandSetting() {
        $branchID = Setting::getCurrentBranch();
        $lkBrandSettingModel = LkBrandSetting::find()
            ->select(['brandSettingID', 'key2'])
            ->andWhere(['key1' => 'EZO'])
            ->all();

        $result = [];
        $model = LkBrandSetting::find()
            ->select('value1')
            ->innerJoinWith('brandSetting')
            ->innerJoinWith('brandSetting.brand')
            ->innerJoinWith('brandSetting.brand.branch')
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['key1' => 'EZO'])
            ->indexBy('key2')
            ->column();

        foreach($lkBrandSettingModel as $lkBrandSetting) {
            $result[$lkBrandSetting->key2] = array_key_exists($lkBrandSetting->key2, $model) ? $model[$lkBrandSetting->key2] : null;
        }
        return $result;
    }

    public static function getBrandPosSetting($key2 = null) {
        $branchID = Setting::getCurrentBranch();
        return LkBrandSetting::find()
                ->select('value1')
                ->innerJoinWith('brandSetting')
                ->innerJoinWith('brandSetting.brand')
                ->innerJoinWith('brandSetting.brand.branch')
                ->andWhere(['branchID' => $branchID])
                ->andWhere(['key1' => 'POS'])
                ->andFilterWhere(['in', 'key2', $key2])
                ->indexBy('key2')
                ->column();
    }

    public static function getBrandSetting($key1= null, $key2 = null) {
        $branchID = Setting::getCurrentBranch();
        return LkBrandSetting::find()
                ->select('value1')
                ->innerJoinWith('brandSetting')
                ->innerJoinWith('brandSetting.brand')
                ->innerJoinWith('brandSetting.brand.branch')
                ->andWhere(['branchID' => $branchID])
                ->andWhere(['key1' => $key1])
                ->andWhere(['key2' => $key2])
                ->scalar();
    }

    public static function getLoopSetting() {
        $branchID = Setting::getCurrentBranch();
        $lkBrandSettingModel = LkBrandSetting::find()
            ->select(['brandSettingID', 'key2'])
            ->andWhere(['key1' => 'LOOP'])
            ->all();

        $result = [];
        $model = LkBrandSetting::find()
            ->select('value1')
            ->innerJoinWith('brandSetting')
            ->innerJoinWith('brandSetting.brand')
            ->innerJoinWith('brandSetting.brand.branch')
            ->andWhere(['branchID' => $branchID])
            ->andWhere(['key1' => 'LOOP'])
            ->indexBy('key2')
            ->column();

        foreach($lkBrandSettingModel as $lkBrandSetting) {
            $result[$lkBrandSetting->key2] = array_key_exists($lkBrandSetting->key2, $model) ? $model[$lkBrandSetting->key2] : null;
        }
        return $result;
    }

}
