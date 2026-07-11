<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200827_025903_insert_show_menu_promotion_on_receipt_ms_setting
 */
class m200827_025903_insert_show_menu_promotion_on_receipt_ms_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Show Menu Promotion Text'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Show Menu Promotion Text', 'value1' => '1']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Show Menu Promotion Text"');
    }
}
