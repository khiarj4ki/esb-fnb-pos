<?php

use app\models\Setting;
use yii\db\Migration;

/**
 * Class m210224_154512_insert_print_by_visit_purpose
 */
class m210224_154512_insert_print_by_visit_purpose extends Migration {

    public function up() {
        if (!Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose'])->exists()) {
            $this->insert(Setting::tableName(),
                    ['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose', 'value1' => '1']);
        }
    }

    public function down() {
        if (Setting::find()->where(['key1' => 'Local Setting', 'key2' => 'Print Sales By Visit Purpose'])->exists()) {
            $this->delete('ms_setting', 'key2 = "Print Sales By Visit Purpose"');
        }
    }

}
