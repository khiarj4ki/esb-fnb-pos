<?php

use app\models\Branch;
use yii\db\Migration;

/**
 * Class m220309_034108_alter_ms_branch_vatSubject_vatName
 */
class m220309_034108_alter_ms_branch_vatSubject_vatName extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('vatName') === null) {
            $this->addColumn(Branch::tableName(), 'vatName',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->after('posModeID'));
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('vatSubject') === null) {
            $this->addColumn(Branch::tableName(), 'vatSubject',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('vatName'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('vatName') !== null) {
            $this->dropColumn(Branch::tableName(),
                'vatName');
        }

        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('vatSubject') !== null) {
            $this->dropColumn(Branch::tableName(),
                'vatSubject');
        }
    }
}
