<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m240108_010713_add_indexing_tr_saleshead_sales_date
 */
class m240108_010713_add_indexing_tr_saleshead_sales_date extends Migration
{
    public function up()
    {
        $checkSalesDate = "SHOW INDEX FROM " . SalesHead::tableName() . " WHERE Key_name = 'idx_tr_saleshead_salesDate'";
        if (!$this->db->createCommand($checkSalesDate)->queryScalar()) {
            $this->createIndex('idx_tr_saleshead_salesDate', SalesHead::tableName(), 'salesDate');
        }
    }

    public function down()
    {
        $checkSalesDate = "SHOW INDEX FROM " . SalesHead::tableName() . " WHERE Key_name = 'idx_tr_saleshead_salesDate'";
        if ($this->db->createCommand($checkSalesDate)->queryScalar()) {
            $this->dropIndex('idx_tr_saleshead_salesDate', SalesHead::tableName());
        }
    }
}
