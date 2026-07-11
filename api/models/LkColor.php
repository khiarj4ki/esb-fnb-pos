<?php

namespace app\models;
use Yii;
use yii\db\ActiveRecord;

class LkColor extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'lk_color';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['colorID', 'colorCode', 'colorName'], 'required'],
            [['colorID'], 'integer'],
            [['colorCode', 'colorName'], 'string']
        ];
    }

    public function getColorDetail() {
        return $this->hasOne(LkColorDetail::class, ['colorID' => 'colorID']);
    }

    public static function findColors() {
        $colorID = BrandSetting::getBrandSetting('KIOSK', 'Color ID');
        $kioskMode = BrandSetting::getBrandSetting('KIOSK', 'Kiosk Mode');

        $color = null;
        $rawColors = LkColor::find()
            ->innerJoinWith('colorDetail')
            ->where([LkColor::tableName() . '.colorID' => $colorID])
            ->andWhere([LkColorDetail::tableName() . '.kioskMode' => $kioskMode])
            ->one();

        if ($rawColors) {
            $color['btnPrimary'] = $rawColors->colorCode;
            $color['btnPrimaryLighter'] = $rawColors->colorCode;
            $color['btnCategoryColorCode'] = $rawColors->colorDetail->btnCategoryColorCode;
            $color['btnCancelColorCode'] = $rawColors->colorDetail->btnCancelColorCode;
            $color['btnSearchColorCode'] = $rawColors->colorDetail->btnSearchColorCode;
            $color['btnSearchColorCodeLighter'] = $rawColors->colorDetail->btnSearchColorCode;
            $color['btnBackColorCode'] = $rawColors->colorDetail->btnBackColorCode;
            $color['backgroundToolbar'] = $kioskMode == 1 ? $rawColors->colorCode : '#ffffff';
            $color['titleToolbar'] = $kioskMode == 1 ? '#ffffff' : '#333333';
            $color['btnCancel'] = $kioskMode == 0 ? '#dc92a8' : $rawColors->colorDetail->btnCategoryColorCode;
            $color['btnCancelText'] = $kioskMode == 0 ? '#cb2f40' : '#ffffff';
            $color['btnCheckout'] = $kioskMode == 0 ? $rawColors->colorCode : '#ffffff';
            $color['btnCheckoutText'] = $kioskMode == 0 ? '#ffffff' : $rawColors->colorCode;
            $color['priceTextToolbar'] = $kioskMode == 0 ? 'rgba(238, 27, 27, 0.6)' : '#ffffff';
            $color['indicatorDiscColorCode'] = $rawColors->colorDetail->indicatorDiscColorCode;
            $color['btnCart'] = $kioskMode == 0 ? $rawColors->colorCode : '#ffffff';
            $color['btnCartText'] = $kioskMode == 0 ? '#ffffff' : $rawColors->colorCode;
            $color['btnBackArrow'] = $kioskMode == 0 ? '#3c405f' : '#ffffff';
            $color['btnBackArrowText'] = $kioskMode == 0 ? '#ffffff' : $rawColors->colorCode;
            $color['priceTextToolbar'] = $kioskMode == 0 ? 'rgba(238, 27, 27, 0.6)' : '#ffffff';
        }

        return $color;
    }

    public static function findPosColors() {
        $colorID = BrandSetting::getBrandSetting('KIOSK', 'Color ID');

        $color = null;
        $rawColors = LkColor::find()
            ->innerJoinWith('colorDetail')
            ->where([LkColor::tableName() . '.colorID' => $colorID])
            ->one();

        if ($rawColors) {
            $color['btnPrimary'] = $rawColors->colorCode;
            $color['btnPrimaryLighter'] = $rawColors->colorCode;
            $color['btnCategoryColorCode'] = $rawColors->colorDetail->btnCategoryColorCode;
            $color['btnCancelColorCode'] = $rawColors->colorDetail->btnCancelColorCode;
            $color['btnSearchColorCode'] = $rawColors->colorDetail->btnSearchColorCode;
            $color['btnSearchColorCodeLighter'] = $rawColors->colorDetail->btnSearchColorCode;
            $color['btnBackColorCode'] = $rawColors->colorDetail->btnBackColorCode;
            $color['backgroundToolbar'] = $rawColors->colorCode;
            $color['titleToolbar'] = '#ffffff';
            $color['btnCancel'] = $rawColors->colorDetail->btnCategoryColorCode;
            $color['btnCancelText'] = '#ffffff';
            $color['btnCheckout'] = '#ffffff';
            $color['btnCheckoutText'] = $rawColors->colorCode;
            $color['priceTextToolbar'] = '#ffffff';
            $color['indicatorDiscColorCode'] = $rawColors->colorDetail->indicatorDiscColorCode;
            $color['btnCart'] = '#ffffff';
            $color['btnCartText'] = $rawColors->colorCode;
            $color['btnBackArrow'] = '#ffffff';
            $color['btnBackArrowText'] = $rawColors->colorCode;
            $color['priceTextToolbar'] = '#ffffff';
        }

        return $color;
    }
    
}
