<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200727_085756_insert_dinein_print_checker
 */
class m200727_085756_insert_dinein_print_checker extends Migration
{
   
    
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Dine In Print Checker'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Dine In Print Checker', 'value1' => '1']);
        }

    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Dine In Print Checker"');
    }
   
}
