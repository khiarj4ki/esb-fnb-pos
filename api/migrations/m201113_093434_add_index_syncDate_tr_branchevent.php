<?php
use app\components\AppHelper;
use app\models\BranchEvent;
use yii\db\Migration;

/**
 * Class m201113_093434_add_index_syncDate_tr_branchevent
 */
class m201113_093434_add_index_syncDate_tr_branchevent extends Migration {
    /**
     * @inheritdoc 
     */
    public function up() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexSyncDate = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            BranchEvent::tableName() . "' " .
            "AND INDEX_NAME = 'idx_syncDate_tr_branchevent' ";
        if (!$this->db->createCommand($checkIndexSyncDate)->queryScalar()) {
            $this->createIndex(
                'idx_syncDate_tr_branchevent', BranchEvent::tableName(),
                'syncDate'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexSyncDate = "SELECT * " .
            "FROM INFORMATION_SCHEMA.STATISTICS " .
            "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
            BranchEvent::tableName() . "' " .
            "AND INDEX_NAME = 'idx_syncDate_tr_branchevent' ";
        if ($this->db->createCommand($checkIndexSyncDate)->queryScalar()) {
            $this->dropIndex(
                'idx_syncDate_tr_branchevent', BranchEvent::tableName()
            );
        }
    }

}
