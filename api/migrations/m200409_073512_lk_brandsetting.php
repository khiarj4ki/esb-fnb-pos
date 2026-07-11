<?php

use app\models\LkBrandSetting;
use yii\db\Migration;

/**
 * Class m200409_073512_lk_brandsetting
 */
class m200409_073512_lk_brandsetting extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(LkBrandSetting::tableName(), true) === null) {
            $this->createTable(LkBrandSetting::tableName(),
                [
                    
                    'brandSettingID' => $this->primaryKey(),
                    'key1' => $this->string(100)->null(),
                    'key2' => $this->string(100)->null()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(LkBrandSetting::tableName(), true) !== null) {
            $this->dropTable(LkBrandSetting::tableName());
        }
    }
}
