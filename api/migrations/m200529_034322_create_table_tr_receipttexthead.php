<?php

use app\models\ReceiptTextHead;
use yii\db\Migration;

/**
 * Class m200529_034322_create_table_tr_receipttexthead
 */
class m200529_034322_create_table_tr_receipttexthead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(ReceiptTextHead::tableName(), true) === null) {
            $this->createTable(ReceiptTextHead::tableName(),
                [
                    'receiptTextID' => $this->primaryKey(),
                    'notes' => $this->string(100)->notNull(),
                    'minimumSalesTotal' => $this->decimal(20, 4)->notNull(),
                    'flagMultiplier' => $this->tinyInteger(1),
                    'createdBy' => $this->string(50),
                    'createdDate' => $this->dateTime(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(ReceiptTextHead::tableName(), true) !== null) {
            $this->dropTable(ReceiptTextHead::tableName());
        }
    }
}
