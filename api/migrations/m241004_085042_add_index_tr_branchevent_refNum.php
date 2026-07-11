<?php

use app\models\BranchEvent;
use yii\db\Migration;

/**
 * Class m241004_085042_add_index_tr_branchevent_refNum
 */
class m241004_085042_add_index_tr_branchevent_refNum extends Migration
{
    public function up()
    {
        $checkRefNum = "SHOW INDEX FROM " . BranchEvent::tableName() . " WHERE Key_name = 'idx_tr_branchevent_refNum'";
        if (!$this->db->createCommand($checkRefNum)->queryScalar()) {
            $this->createIndex('idx_tr_branchevent_refNum', BranchEvent::tableName(), 'refNum');
        }
    }

    public function down()
    {
        $checkRefNum = "SHOW INDEX FROM " . BranchEvent::tableName() . " WHERE Key_name = 'idx_tr_branchevent_refNum'";
        if ($this->db->createCommand($checkRefNum)->queryScalar()) {
            $this->dropIndex('idx_tr_branchevent_refNum', BranchEvent::tableName());
        }
    }
}
