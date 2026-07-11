<?php

use app\models\VisitPurpose;
use yii\db\Migration;

/**
 * Class m211108_111020_add_column_flag_show_queue_ms_visitpurpose
 */
class m211108_111020_add_column_flag_show_queue_ms_visitpurpose extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagShowQueue') === null) {
            $this->addColumn(VisitPurpose::tableName(), 'flagShowQueue',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('flagQuickService'));

            VisitPurpose::updateAll(['flagShowQueue' => 1]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagShowQueue') !== null) {
            $this->dropColumn(VisitPurpose::tableName(),
                'flagShowQueue');
        }
    }
}
