<?php

use app\models\SalesPaymentGateway;
use yii\db\Migration;

/**
 * Class m240731_071047_add_tbl_tr_salespaymentgateway
 */
class m240731_071047_add_tbl_tr_salespaymentgateway extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesPaymentGateway::tableName(), true) === null) {
            $this->createTable(SalesPaymentGateway::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'salesNum' => $this->string(20)->notNull(),
                    'selfOrderIdKiosk' => $this->string(50)->notNull()
            ]);

            $this->createIndex('idx_tr_salespaymentgateway_salesNum', SalesPaymentGateway::tableName(), 'salesNum');
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesPaymentGateway::tableName(), true) !== null) {
            $this->dropTable(SalesPaymentGateway::tableName());
        }
    }
}
