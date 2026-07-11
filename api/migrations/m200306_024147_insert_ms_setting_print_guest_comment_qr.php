<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200306_024147_insert_ms_setting_print_guest_comment_qr
 */
class m200306_024147_insert_ms_setting_print_guest_comment_qr extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Print Guest Comment QR'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Print Guest Comment QR', 'value1' => '0']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Print Guest Comment QR"');
    }
}
