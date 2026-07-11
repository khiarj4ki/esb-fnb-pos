<?php

use app\models\VisitPurposeGroup;
use yii\db\Migration;

/**
 * Class m210813_074016_create_ms_visitpurposegroup
 */
class m210813_074016_create_ms_visitpurposegroup extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(VisitPurposeGroup::tableName(), true) === null) {
            $this->createTable(VisitPurposeGroup::tableName(),
                [
                'visitPurposeGroupID' => $this->integer()->notNull(),
                'visitPurposeGroupName' => $this->string(100)->notNull(),
                'createdBy' => $this->string(100)->notNull(),
                'createdDate' => $this->dateTime()->notNull(),
                'editedBy' => $this->string(100),
                'editedDate' => $this->dateTime(),
            ]);

            $this->addPrimaryKey('PRIMARYKEY',
                VisitPurposeGroup::tableName(),
                ['visitPurposeGroupID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitPurposeGroup::tableName(), true) !== null) {
            $this->dropTable(VisitPurposeGroup::tableName());
        }
    }
}
