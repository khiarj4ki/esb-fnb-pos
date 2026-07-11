<?php

use app\components\AppHelper;
use app\models\SalesMenuCompletion;
use yii\db\Migration;

/**
 * Class m220222_081534_add_index_tr_salesmenucompletion
 */
class m220222_081534_add_index_tr_salesmenucompletion extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexCompletedDate = "SELECT * " .
                "FROM INFORMATION_SCHEMA.STATISTICS " .
                "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
                SalesMenuCompletion::tableName() . "' " .
                "AND INDEX_NAME = 'idx_tr_salesmenucompletion_completedDate' ";
        if (!$this->db->createCommand($checkIndexCompletedDate)->queryScalar()) {
            $this->createIndex(
                    'idx_tr_salesmenucompletion_completedDate',
                    SalesMenuCompletion::tableName(),
                    'completedDate'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexCompletedDate = "SELECT * " .
                "FROM INFORMATION_SCHEMA.STATISTICS " .
                "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
                SalesMenuCompletion::tableName() . "' " .
                "AND INDEX_NAME = 'idx_tr_salesmenucompletion_completedDate' ";
        if ($this->db->createCommand($checkIndexCompletedDate)->queryScalar()) {
            $this->dropIndex(
                    'idx_tr_salesmenucompletion_completedDate',
                    SalesMenuCompletion::tableName()
            );
        }
    }
}
