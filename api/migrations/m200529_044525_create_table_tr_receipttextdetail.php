<?php

use app\models\ReceiptTextDetail;
use yii\db\Migration;

/**
 * Class m200529_044525_create_table_tr_receipttextdetail
 */
class m200529_044525_create_table_tr_receipttextdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(ReceiptTextDetail::tableName(), true) === null) {
            $this->createTable(ReceiptTextDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'receiptTextID' => $this->integer()->notNull(),
                    'receiptTextDesc' => $this->text()->notNull()
                ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(ReceiptTextDetail::tableName(), true) !== null) {
            $this->dropTable(ReceiptTextDetail::tableName());
        }
    }
}
