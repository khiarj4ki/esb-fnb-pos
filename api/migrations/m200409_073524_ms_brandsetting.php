<?php

use app\models\BrandSetting;
use yii\db\Migration;

/**
 * Class m200409_073524_ms_brandsetting
 */
class m200409_073524_ms_brandsetting extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(BrandSetting::tableName(), true) === null) {
            $this->createTable(BrandSetting::tableName(),
                [
                    'brandID' => $this->integer()->notNull(),
                    'brandSettingID' => $this->integer()->notNull(),
                    'value1' => $this->text()->null(),
                    'value2' => $this->text()->null()
            ]);
            $this->addPrimaryKey('PRIMARYKEY', 
                BrandSetting::tableName(), ['brandID', 'brandSettingID']);

        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(BrandSetting::tableName(), true) !== null) {
            $this->dropTable(BrandSetting::tableName());
        }
    }
}
