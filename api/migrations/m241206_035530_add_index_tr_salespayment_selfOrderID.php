<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m241206_035530_add_index_tr_salespayment_selfOrderID
 */
class m241206_035530_add_index_tr_salespayment_selfOrderID extends Migration
{
    public function up()
    {
        $checkRefNum = "SHOW INDEX FROM " . SalesPayment::tableName() . " WHERE Key_name = 'idx_tr_salespayment_selfOrderID'";
        if (!$this->db->createCommand($checkRefNum)->queryScalar()) {
            $this->createIndex('idx_tr_salespayment_selfOrderID', SalesPayment::tableName(), 'selfOrderID');
        }
    }

    public function down()
    {
        $checkRefNum = "SHOW INDEX FROM " . SalesPayment::tableName() . " WHERE Key_name = 'idx_tr_salespayment_selfOrderID'";
        if ($this->db->createCommand($checkRefNum)->queryScalar()) {
            $this->dropIndex('idx_tr_salespayment_selfOrderID', SalesPayment::tableName());
        }
    }

}
