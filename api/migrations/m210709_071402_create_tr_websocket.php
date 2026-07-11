<?php

use app\models\WebSocket;
use yii\db\Migration;

/**
 * Class m210709_071402_create_tr_websocket
 */
class m210709_071402_create_tr_websocket extends Migration
{
 
    public function up() {
        if ($this->db->getTableSchema(WebSocket::tableName(),
                true) === null) {

            $this->createTable(WebSocket::tableName(),
                ['timestamp' => $this->string(50)->notNull()]);

            $this->addPrimaryKey('tr_websocket', WebSocket::tableName(), ['timestamp']);

            $this->insert(WebSocket::tableName(),
            [
                'timestamp' => '1'
            ]);

        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(WebSocket::tableName(),
                true) !== null) {
            $this->dropTable(WebSocket::tableName());
        }
    }
}
