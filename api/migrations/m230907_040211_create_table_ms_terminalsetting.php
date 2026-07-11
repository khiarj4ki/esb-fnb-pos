<?php

use app\models\TerminalSetting;
use yii\db\cubrid\Schema;
use yii\db\Migration;

/**
 * Class m230907_040211_create_table_ms_terminalsetting
 */
class m230907_040211_create_table_ms_terminalsetting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(TerminalSetting::tableName(), true) === null) {
            $this->createTable(
                TerminalSetting::tableName(),
                [
                    'ID' => $this->primaryKey()->unsigned()->notNull(),
                    'terminalID' => $this->string(20)->notNull(),
                    'key' => $this->string(50),
                    'value' => $this->string(20),
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropTable(TerminalSetting::tableName());
    }
}
