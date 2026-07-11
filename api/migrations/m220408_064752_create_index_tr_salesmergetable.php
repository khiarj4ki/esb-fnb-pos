<?php

use app\models\SalesMergeTable;
use yii\db\Migration;

/**
 * Class m220408_064752_create_index_tr_salesmergetable
 */
class m220408_064752_create_index_tr_salesmergetable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $checkQuery = "SHOW INDEX FROM " . SalesMergeTable::tableName() . " WHERE Key_name = 'idx_tr_salesmergetable_salesNum'";
        if (!$this->db->createCommand($checkQuery)->queryScalar()) {
            $this->createIndex('idx_tr_salesmergetable_salesNum', SalesMergeTable::tableName(), 'salesNum');
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $checkQuery = "SHOW INDEX FROM " . SalesMergeTable::tableName() . " WHERE Key_name = 'idx_tr_salesmergetable_salesNum'";
        if ($this->db->createCommand($checkQuery)->queryScalar()) {
            $this->dropIndex('idx_tr_salesmergetable_salesNum', SalesMergeTable::tableName());
        }
    }
}
