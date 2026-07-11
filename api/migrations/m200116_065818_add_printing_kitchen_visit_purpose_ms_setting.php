<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200116_065818_add_printing_kitchen_visit_purpose_ms_setting
 */
class m200116_065818_add_printing_kitchen_visit_purpose_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Printing Kitchen Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Printing Kitchen Visit Purpose', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Printing Kitchen Visit Purpose"');
    }
}
