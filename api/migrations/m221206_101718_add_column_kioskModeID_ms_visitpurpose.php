<?php

use app\models\VisitPurpose;
use yii\db\Migration;

/**
 * Class m221206_101718_add_column_kioskModeID_ms_visitpurpose
 */
class m221206_101718_add_column_kioskModeID_ms_visitpurpose extends Migration
{
    /**
     * {@inheritdoc}
     */
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('kioskModeID') === null) {
            $this->addColumn(VisitPurpose::tableName(),
            'kioskModeID',
            $this->integer(11)->after('flagDineIn')->defaultValue(0));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(VisitPurpose::tableName(), true)->getColumn('kioskModeID') !== null) {
            $this->dropColumn(VisitPurpose::tableName(), 'kioskModeID');
        }
    }
}
