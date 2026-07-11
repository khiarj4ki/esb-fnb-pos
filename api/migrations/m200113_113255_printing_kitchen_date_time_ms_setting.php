<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200113_113255_printing_kitchen_date_time_ms_setting
 */
class m200113_113255_printing_kitchen_date_time_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Kitchen Date'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Kitchen Date', 'value1' => '1']);
        }
        
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Kitchen Time'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Kitchen Time', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Printing Kitchen Date"');
        $this->delete('ms_setting', 'key2 = "Show Printing Kitchen Time"');
    }
}
