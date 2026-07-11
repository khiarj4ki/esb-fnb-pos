<?php

use app\models\TableUsage;
use yii\db\Migration;

/**
 * Class m240814_061900_add_indexing_tr_tableusage_referenceID
 */
class m240814_061900_add_indexing_tr_tableusage_referenceID extends Migration
{
    public function up()
    {
        $checkSalesDate = "SHOW INDEX FROM " . TableUsage::tableName() . " WHERE Key_name = 'idx_tr_tableusage_referenceID'";
        if (!$this->db->createCommand($checkSalesDate)->queryScalar()) {
            $this->createIndex('idx_tr_tableusage_referenceID', TableUsage::tableName(), 'referenceID');
        }
    }

    public function down()
    {
        $checkSalesDate = "SHOW INDEX FROM " . TableUsage::tableName() . " WHERE Key_name = 'idx_tr_tableusage_referenceID'";
        if ($this->db->createCommand($checkSalesDate)->queryScalar()) {
            $this->dropIndex('idx_tr_tableusage_referenceID', TableUsage::tableName());
        }
    }

}
