<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200424_071428_add_receipt_qr_code_encryption_ms_setting
 */
class m200424_071428_add_receipt_qr_code_encryption_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Receipt QR Code Encryption'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'Local Setting', 'key2' => 'Receipt QR Code Encryption', 'value1' => 'PC1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Receipt QR Code Encryption"');
    }
}
