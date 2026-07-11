<?php

use app\models\Terminal;
use yii\db\Migration;

/**
 * Class m230523_060733_create_table_ms_terminal
 */
class m230523_060733_create_table_ms_terminal extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Terminal::tableName(), true) === null) {
            $this->createTable(Terminal::tableName(), [
                'terminalID' => $this->primaryKey(),
                'posType' => $this->tinyInteger()->notNull()->defaultValue(0),
                'terminalCode' => $this->string(10)->notNull(),
                'branchID' => $this->integer(11)->notNull(),
                'deviceType' => $this->string(20)->defaultValue(null),
                'caption' => $this->string(100)->defaultValue(null),
                'statusID' => $this->tinyInteger()->notNull()->defaultValue(48),
                'activatedDate' => $this->dateTime(),
                'createdBy' => $this->string(100),
                'createdDate' => $this->dateTime(),
                'editedBy' => $this->string(100),
                'editedDate' => $this->dateTime(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(Terminal::tableName(), true) !== null) {
            $this->dropTable(Terminal::tableName());
        }
    }
}
