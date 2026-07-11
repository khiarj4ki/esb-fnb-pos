<?php

use yii\db\Migration;
use app\models\forms\SyncOptimize;
use yii\db\mysql\Schema;

/**
 * Class m201123_030014_create_sync_optimize
 */
class m201123_030014_create_sync_optimize extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SyncOptimize::tableName(), true) === null) {
            $this->createTable(SyncOptimize::tableName(),
                [
                    'syncType' => $this->string(50)->notNull(),
                    'pushDateTime' => $this->dateTime()->notNull(),
                    'pullDateTime' => $this->dateTime()->null(),
            ]);

            $this->addPrimaryKey('PRIMARYKEY', SyncOptimize::tableName(),
                ['syncType']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SyncOptimize::tableName(), true) !== null) {
            $this->dropTable(SyncOptimize::tableName());
        }
    }

}
