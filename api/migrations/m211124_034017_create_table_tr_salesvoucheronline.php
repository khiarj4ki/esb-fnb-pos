<?php

use yii\db\Migration;

/**
 * Class m211124_034017_create_table_tr_salesvoucheronline
 */
class m211124_034017_create_table_tr_salesvoucheronline extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema('{{%tr_salesvoucheronline}}', true) === null) {
            $this->createTable('{{%tr_salesvoucheronline}}', [
                'ID' => $this->primaryKey(),
                'localID' => $this->integer(),
                'salesNum' => $this->string(50)->notNull(),
                'voucherID' => $this->string(20)->notNull(),
                'voucherAmount' => $this->decimal(20, 4)->notNull(),
                'voucherSalesPrice' => $this->decimal(20, 4)->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema('{{%tr_salesvoucheronline}}', true) !== null) {
            $this->dropTable('{{%tr_salesvoucheronline}}');
        }
    }
}
