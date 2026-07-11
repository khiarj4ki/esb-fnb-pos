<?php

use app\models\VisitorType;
use yii\db\Migration;

/**
 * Class m210609_143751_add_column_ms_visitor_type_flagDineIn_and_flagQuickService
 */
class m210609_143751_add_column_ms_visitor_type_flagDineIn_and_flagQuickService extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(VisitorType::tableName(), true)->getColumn('flagDineIn') === null) {
            $this->addColumn(
                VisitorType::tableName(),
                'flagDineIn',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('TINYINT(1)')->defaultValue(1)->after('visitorTypeName')
            );
        }
        if ($this->db->getTableSchema(VisitorType::tableName(), true)->getColumn('flagQuickService') === null) {
            $this->addColumn(
                VisitorType::tableName(),
                'flagQuickService',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('TINYINT(1)')->defaultValue(1)->after('flagDineIn')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(VisitorType::tableName(), true)->getColumn('flagDineIn') !== null) {
            $this->dropColumn(VisitorType::tableName(), 'flagDineIn');
        }
        if ($this->db->getTableSchema(VisitorType::tableName(), true)->getColumn('flagQuickService') !== null) {
            $this->dropColumn(VisitorType::tableName(), 'flagQuickService');
        }
    }
}
