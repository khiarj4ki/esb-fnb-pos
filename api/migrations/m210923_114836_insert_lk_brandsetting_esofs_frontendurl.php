<?php

use app\models\LkBrandSetting;
use yii\db\Migration;

/**
 * Class m210923_114836_insert_lk_brandsetting_esofs_frontendurl
 */
class m210923_114836_insert_lk_brandsetting_esofs_frontendurl extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!LkBrandSetting::find()
            ->where(['brandSettingID' => 65, 'key1' => 'EZO', 'key2' => 'Frontend Url'])
            ->exists()) {
                $this->insert(LkBrandSetting::tableName(),
                    ['brandSettingID' => 65, 'key1' => 'EZO', 'key2' => 'Frontend Url']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if (LkBrandSetting::find()
            ->where(['brandSettingID' => 65, 'key1' => 'EZO', 'key2' => 'Frontend Url'])
            ->exists()) {
                LkBrandSetting::deleteAll(['brandSettingID' => 65, 'key1' => 'EZO', 'key2' => 'Frontend Url']);
        }
    }

}
