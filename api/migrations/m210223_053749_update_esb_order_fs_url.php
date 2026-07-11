<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m210223_053749_update_esb_order_fs_url
 */
class m210223_053749_update_esb_order_fs_url extends Migration {

    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO FS Api Url'])->exists()) {
            $this->insert(Setting::tableName(),
                    [
                        'key1' => 'Local Setting',
                        'key2' => 'EZO FS Api Url',
                        'value1' => '9nDdlJ23kkqufiISQY2ZwjEwYjEyYjQ5ZDI5NTIwMTQyYjljYTQxMjMxZTI4OWYxN2ZjZWMyNDhmMGY3MDY4OTMzMDU3MzM5NmE3ZTE5NzFhw3pImOrjpI94ntQkfyyEk1dM3f3NEaHzvFseZDKBfNibvhNmykAJQ+FVDKchiYUnVSuVblrrA0yBZLCvQXK7',
                        'value2' => 'Enc'
            ]);
        } else {
            Setting::updateAll(['value1' => '9nDdlJ23kkqufiISQY2ZwjEwYjEyYjQ5ZDI5NTIwMTQyYjljYTQxMjMxZTI4OWYxN2ZjZWMyNDhmMGY3MDY4OTMzMDU3MzM5NmE3ZTE5NzFhw3pImOrjpI94ntQkfyyEk1dM3f3NEaHzvFseZDKBfNibvhNmykAJQ+FVDKchiYUnVSuVblrrA0yBZLCvQXK7'],
                    ['key1' => 'Local Setting', 'key2' => 'EZO FS Api Url']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO FS Url'])->exists()) {
            $this->insert(Setting::tableName(),
                    [
                        'key1' => 'Local Setting',
                        'key2' => 'EZO FS Url',
                        'value1' => '0Nq7gXRzvOXoOSihIKYVyWY0NDk3NWEwMjhhOTdhYmRmNzE4ZjA3ZmQyZDAzYjBhMmMwNmM1MzQxMTA3ZDVhMjc2ZmE4NjJjNDZiOTI4YjlCso2arrwFQf0jU/AUFTXQKzkCA3VAVUVNbWHgPS6de7wVHLMVPkP/I28OtIQJCG8=',
                        'value2' => 'Enc'
            ]);
        } else {
            Setting::updateAll(['value1' => '0Nq7gXRzvOXoOSihIKYVyWY0NDk3NWEwMjhhOTdhYmRmNzE4ZjA3ZmQyZDAzYjBhMmMwNmM1MzQxMTA3ZDVhMjc2ZmE4NjJjNDZiOTI4YjlCso2arrwFQf0jU/AUFTXQKzkCA3VAVUVNbWHgPS6de7wVHLMVPkP/I28OtIQJCG8='],
                    ['key1' => 'Local Setting', 'key2' => 'EZO FS Url']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}