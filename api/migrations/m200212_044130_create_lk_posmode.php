<?php

use app\models\PosMode;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m200212_044130_create_lk_posmode
 */
class m200212_044130_create_lk_posmode extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PosMode::tableName(), true) === null) {
            $this->createTable(PosMode::tableName(),
                [
                'posModeID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                'posModeName' => $this->string(50)->notNull()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PosMode::tableName(), true) !== null) {
            $this->dropTable(PosMode::tableName());
        }
    }
}
