<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200624_041736_alter_ms_setting
 */
class m200624_041736_alter_ms_setting extends Migration
{
    public function up()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales by Menu Qty & Value'])->exists()) {
            $this->update(Setting::tableName(), ['key2' => 'Print Sales by Menu Qty Value'],
                "key2 = 'Print Sales by Menu Qty & Value' AND key1 = 'Local Setting'");
        }
    }

    public function down()
    {
        
    }
}
