<?php

use yii\db\Migration;
use app\models\VisitPurpose;

/**
 * Class m200629_063833_add_column_flag_dine_in_ms_visitpurpose
 */
class m200629_063833_add_column_flag_dine_in_ms_visitpurpose extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagDineIn') === null) {
            $this->addColumn(VisitPurpose::tableName(), 'flagDineIn',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('visitPurposeName'));

            VisitPurpose::updateAll(['flagDineIn' => 1]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('flagDineIn') !== null) {
            $this->dropColumn(VisitPurpose::tableName(),
                'flagDineIn');
        }
    }
}
