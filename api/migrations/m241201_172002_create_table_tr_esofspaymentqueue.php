<?php

use app\models\EsoFSPaymentQueue;
use yii\db\Migration;

/**
 * Class m241201_172002_create_table_tr_esofspaymentqueue
 */
class m241201_172002_create_table_tr_esofspaymentqueue extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(EsoFSPaymentQueue::tableName(), true) === null) {
            $this->createTable(EsoFSPaymentQueue::tableName(), [
                'orderID' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                'salesNum' => $this->string(20),
                'status' => $this->string(10)->notNull(),
                'paymentMethod' => $this->string(20)->notNull(),
                'paymentTotal' => $this->string(20)->notNull()
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(EsoFSPaymentQueue::tableName(), true) !== null) {
            $this->dropTable(EsoFSPaymentQueue::tableName());
        }
    }
}