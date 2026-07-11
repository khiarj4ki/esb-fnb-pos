<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m200518_034611_insert_ms_setting_guest_comment_url
 */
class m200518_034611_insert_ms_setting_guest_comment_url extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Guest Comment Url'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Guest Comment Url', 'value1' => 'https://survey.esb.co.id']);
        }
    }

    public function down()
    {
        $this->delete('ms_setting', 'key2 = "Guest Comment Url"');
    }
}
