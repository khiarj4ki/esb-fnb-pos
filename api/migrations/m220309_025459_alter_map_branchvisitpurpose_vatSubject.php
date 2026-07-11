<?php

use app\models\MapBranchVisitPurpose;
use yii\db\Migration;

/**
 * Class m220309_025459_alter_map_branchvisitpurpose_vatSubject
 */
class m220309_025459_alter_map_branchvisitpurpose_vatSubject extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('vatSubject') === null) {
            $this->addColumn(MapBranchVisitPurpose::tableName(), 'vatSubject',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('taxValue'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('vatSubject') !== null) {
            $this->dropColumn(MapBranchVisitPurpose::tableName(),
                'vatSubject');
        }
    }
}
