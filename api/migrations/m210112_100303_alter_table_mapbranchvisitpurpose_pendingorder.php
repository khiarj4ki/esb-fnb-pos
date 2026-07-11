<?php

use app\models\MapBranchVisitPurpose;
use yii\db\Migration;

/**
 * Class m210112_100303_alter_table_mapbranchvisitpurpose_pendingorder
 */
class m210112_100303_alter_table_mapbranchvisitpurpose_pendingorder extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('pendingOrder') === null) {
            $this->addColumn(MapBranchVisitPurpose::tableName(), 'pendingOrder',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(0)->after('flagSelfOrder'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('pendingOrder') !== null) {
            $this->dropColumn(MapBranchVisitPurpose::tableName(), 'pendingOrder');
        }
    }
}
