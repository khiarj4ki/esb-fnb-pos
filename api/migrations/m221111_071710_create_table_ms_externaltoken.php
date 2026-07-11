<?php

use app\models\ExternalToken;
use yii\db\Migration;

/**
 * Class m221111_071710_create_table_ms_externaltoken
 */
class m221111_071710_create_table_ms_externaltoken extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(ExternalToken::tableName(), true) === null) {
            $this->createTable(ExternalToken::tableName(), [
                'terminalID' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'token' => $this->text()->notNull(),
                'transactionID' => $this->string(50)->notNull(),
                'batchID' => $this->string(50)->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(ExternalToken::tableName(), true) !== null) {
            $this->dropTable(ExternalToken::tableName());
        }
    }
}
