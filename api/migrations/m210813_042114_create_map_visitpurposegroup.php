<?php

use app\models\MapVisitPurposeGroup;
use yii\db\Migration;

/**
 * Class m210813_042114_create_map_visitpurposegroup
 */
class m210813_042114_create_map_visitpurposegroup extends Migration
{

    /**
     * @inheritdoc
     */
    public function up() {

        if ($this->db->getTableSchema(MapVisitPurposeGroup::tableName(), true) === null) {
            $this->createTable(MapVisitPurposeGroup::tableName(),
                [
                'visitPurposeGroupID' => $this->integer()->notNull(),
                'visitPurposeID' => $this->integer()->notNull(),
            ]);
            
            $this->addPrimaryKey('PRIMARYKEY',
                MapVisitPurposeGroup::tableName(),
                ['visitPurposeGroupID', 'visitPurposeID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapVisitPurposeGroup::tableName(), true) !== null) {
            $this->dropTable(MapVisitPurposeGroup::tableName());
        }
    }
}
