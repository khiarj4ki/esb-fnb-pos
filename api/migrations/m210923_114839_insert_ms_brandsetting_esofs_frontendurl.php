<?php

use app\models\Brand;
use app\models\BrandSetting;
use yii\db\Migration;

/**
 * Class m210923_114839_insert_ms_brandsetting_esofs_frontendurl
 */
class m210923_114839_insert_ms_brandsetting_esofs_frontendurl extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if (!BrandSetting::find()->where(['brandSettingID' => '65'])->exists()) {
            $this->execute("INSERT INTO " . BrandSetting::tableName() . " (brandID, brandSettingID, value1, value2) " .
                    "SELECT brandID, 65, 'https://esborder.fs.esb.co.id', '' " .
                    "FROM " . Brand::tableName());
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if (BrandSetting::find()->where(['brandSettingID' => 65])->exists()) {
            BrandSetting::deleteAll(['brandSettingID' => 65]);
        }
    }

}
