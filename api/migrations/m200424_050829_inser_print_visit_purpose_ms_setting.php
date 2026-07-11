<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200424_050829_inser_print_visit_purpose_ms_setting
 */
class m200424_050829_inser_print_visit_purpose_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Sales By Visit Purpose"');
    }
}
