<?php

use app\models\EsoProcessQueue;
use yii\db\Migration;

/**
 * Class m230419_033640_create_tr_esoprocessqueue
 */
class m230419_033640_create_tr_esoprocessqueue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true) === null) {
            $this->createTable(EsoProcessQueue::tableName(), [
                'orderID' => $this->string(20)->notNull()->append('PRIMARY KEY')
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true) !== null) {
            $this->dropTable(EsoProcessQueue::tableName());
        }
    }
}
