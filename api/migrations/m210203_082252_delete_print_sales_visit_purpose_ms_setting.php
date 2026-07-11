<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m210203_082252_delete_print_sales_visit_purpose_ms_setting
 */
class m210203_082252_delete_print_sales_visit_purpose_ms_setting extends Migration
{
   
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose'])->exists()) {
            $this->delete('ms_setting', 'key2 = "Print Sales By Visit Purpose"');
        }
    }

    public function down()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose', 'value1' => '1']);
        }
    }
    
}
