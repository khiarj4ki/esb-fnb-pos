<?php
use app\models\Branch;
use yii\db\Migration;

/**
 * Class m200212_043741_alter_column_posmodeid_ms_branch
 */
class m200212_043741_alter_column_posmodeid_ms_branch extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posModeID') === null) {
            $this->addColumn(Branch::tableName(), 'posModeID',
                $this->integer()->defaultValue(0)->after('menuTemplateID'));

            $this->execute("UPDATE " . Branch::tableName() . " " .
                "SET posModeID = 1 " .
                "WHERE posModeID IS NULL OR posModeID = 0;");
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('posModeID') !== null) {
            $this->dropColumn(Branch::tableName(), 'posModeID');
        }
    }

}
