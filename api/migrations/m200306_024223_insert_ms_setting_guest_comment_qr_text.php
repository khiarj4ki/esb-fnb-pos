<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m200306_024223_insert_ms_setting_guest_comment_qr_text
 */
class m200306_024223_insert_ms_setting_guest_comment_qr_text extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Guest Comment QR Text'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Guest Comment QR Text', 'value1' => 'Please scan here to submit your feedback']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Guest Comment QR Text"');
    }
}
