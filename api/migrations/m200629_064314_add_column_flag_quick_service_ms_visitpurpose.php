<?php

use yii\db\Migration;
use app\models\VisitPurpose;

/**
 * Class m200629_064314_add_column_flag_quick_service_ms_visitpurpose
 */
class m200629_064314_add_column_flag_quick_service_ms_visitpurpose extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagQuickService') === null) {
            $this->addColumn(VisitPurpose::tableName(), 'flagQuickService',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('flagDineIn'));

            VisitPurpose::updateAll(['flagQuickService' => 1]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagQuickService') !== null) {
            $this->dropColumn(VisitPurpose::tableName(),
                'flagQuickService');
        }
    }
}
