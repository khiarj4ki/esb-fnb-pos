<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200217_050547_add_rounding_decimal_ms_setting
 */
class m200217_050547_add_rounding_decimal_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Sales Decimal Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Sales Decimal Mode', 'value1' => 'DOWN']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Sales Decimal Separator Setting'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Sales Decimal Separator Setting', 'value1' => ',']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Sales Decimal Setting'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Sales Decimal Setting', 'value1' => '0']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Rounding Mode'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Rounding Mode', 'value1' => 'DOWN']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Rounding Nearest Value'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Rounding Nearest Value', 'value1' => '500']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Sales Decimal Mode"');
        $this->delete('ms_setting', 'key2 = "Sales Decimal Separator Setting"');
        $this->delete('ms_setting', 'key2 = "Sales Decimal Setting"');
        $this->delete('ms_setting', 'key2 = "Rounding Mode"');
        $this->delete('ms_setting', 'key2 = "Rounding Nearest Value"');
    }
}
