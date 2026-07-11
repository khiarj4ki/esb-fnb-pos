<?php

use app\models\MaxOrder;
use yii\db\Migration;

/**
 * Class m220711_063213_create_table_ms_maxorder
 */
class m220711_063213_create_table_ms_maxorder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MaxOrder::tableName(), true) === null) {
            $this->createTable(MaxOrder::tableName(), [
                'maxOrderID' => $this->primaryKey(),
                'visitPurposeID' => $this->integer()->notNull(),
                'maxOrder' => $this->integer()->notNull(),
                'notes' => $this->text(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MaxOrder::tableName(), true) !== null) {
            $this->dropTable(MaxOrder::tableName());
        }
    }
}
