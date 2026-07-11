<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m200316_074228_alter_column_vouchercode_tr_salespayment
 */
class m200316_074228_alter_column_vouchercode_tr_salespayment extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCode') !== null) {
            $this->alterColumn(
                SalesPayment::tableName(),
                'voucherCode',
                $this->string(50)->notNull()
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('voucherCode') !== null) {
            $this->alterColumn(
                SalesPayment::tableName(),
                'voucherCode',
                $this->string(20)->notNull()
            );
        }
    }
}
