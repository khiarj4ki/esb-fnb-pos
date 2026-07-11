<?php

use app\models\VisitPurpose;
use yii\db\Migration;

/**
 * Class m220404_051142_add_flag_max_order_ms_visitpurpose
 */
class m220404_051142_add_flag_max_order_ms_visitpurpose extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagMaxOrder') === null) {
            $this->addColumn(VisitPurpose::tableName(), 'flagMaxOrder',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(0)->after('flagShowQueue'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagMaxOrder') !== null) {
            $this->dropColumn(VisitPurpose::tableName(), 'flagMaxOrder');
        }
    }
}
