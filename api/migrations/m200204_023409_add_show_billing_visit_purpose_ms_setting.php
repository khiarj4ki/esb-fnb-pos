<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200204_023409_add_show_billing_visit_purpose_ms_setting
 */
class m200204_023409_add_show_billing_visit_purpose_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Billing Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Billing Visit Purpose', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Billing Visit Purpose"');
    }
}
