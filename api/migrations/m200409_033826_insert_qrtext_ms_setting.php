<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200409_033826_insert_qrtext_ms_setting
 */
class m200409_033826_insert_qrtext_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'EZO', 'key2' => 'QR Footer Text'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                    'key1' => 'EZO', 
                    'key2' => 'QR Footer Text', 
                    'value1' => "This QR is linked to your table orders, please do not share this QR to external parties. Browse to esb.co.id for online QR Scanner"
                    ]);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "QR Footer Text"');
    }
}
