<?php

use app\models\EsoWebSocketQueue;
use yii\db\Migration;

/**
 * Class m240524_151846_add_tbl_tr_esowebsocketqueue
 */
class m240524_151846_add_tbl_tr_esowebsocketqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(EsoWebSocketQueue::tableName(), true) === null) {
            $this->createTable(EsoWebSocketQueue::tableName(), [
                'webSocketID' => $this->bigInteger(20)->notNull()->append('PRIMARY KEY')
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(EsoWebSocketQueue::tableName(), true) !== null) {
            $this->dropTable(EsoWebSocketQueue::tableName());
        }
    }
}
