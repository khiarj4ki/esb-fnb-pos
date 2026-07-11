<?php

use yii\db\Migration;
use app\models\Setting;

/**
 * Class m240116_023408_alter_table_ms_setting_add_token_for_qr_parking_lippo
 */
class m240116_023408_alter_table_ms_setting_add_token_for_qr_parking_lippo extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Lippo Parking Voucher Token'])->exists()) {
            $this->insert(Setting::tableName(), [
                'key1' => 'Local Setting', 
                'key2' => 'Lippo Parking Voucher Token', 
                'value1' => null
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Lippo Parking Voucher Token'])->exists()) {
            $this->delete(Setting::tableName(), ['key2' => 'Lippo Parking Voucher Token']);
        }
    }
}
