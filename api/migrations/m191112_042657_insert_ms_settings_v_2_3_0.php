<?php
use app\models\Setting;
use yii\db\Migration;

/**
 * Class m191112_042657_insert_ms_settings_v_2_3_0
 */
class m191112_042657_insert_ms_settings_v_2_3_0 extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!Setting::find()->where(['key1' => 'POS', 'key2' => 'Print Take Away Order After Payment'])->exists()) {
            $this->insert(Setting::tableName(),
                ['key1' => 'POS', 'key2' => 'Print Take Away Order After Payment', 'value1' => '0']);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Basic Rest Username'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                'key1' => 'Local Setting',
                'key2' => 'Basic Rest Username',
                'value1' => 'hMQJO6Y/mPXU9ay9TE7qWjNjNzNiZDZlZWY3ZTUzMzNkZmEzYzFhZjhiMTdkZmMwYWIxYTVhOGI4NjQ5MmRkMjllMmE3MjBkNDk4ODhlM2SXva84ReKG3Pq89UNebc4sCezxH6cBguR4CGK1TCOS9g==',
                'value2' => 'Enc'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Basic Rest Password'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                'key1' => 'Local Setting',
                'key2' => 'Basic Rest Password',
                'value1' => 'suzBy3IZGXlP1X9toBC3IDRmNmY3NjY4M2Y3MGYyMzVlMDk0MjQ0MTc4MzZhYjg1YmViMTBiNjNiMjZiZTkyNTM3OTgxMWJlYzIxY2QzZjLympbIYPwQ99o/UFcrC2eeuZQy3R96nScufhHIt3yBy/BXcgUyCY10qQwe6sbXFiYyJDDwNpm7B7ezqJ+DdR9I',
                'value2' => 'Enc'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO TA API Url'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                'key1' => 'Local Setting',
                'key2' => 'EZO TA API Url',
                'value1' => '',
                'value2' => 'Enc'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO FS Url'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                'key1' => 'Local Setting',
                'key2' => 'EZO FS Url',
                'value1' => '',
                'value2' => 'Enc'
            ]);
        }

        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'EZO FS API Url'])->exists()) {
            $this->insert(Setting::tableName(),
                [
                'key1' => 'Local Setting',
                'key2' => 'EZO FS API Url',
                'value1' => '',
                'value2' => 'Enc'
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
