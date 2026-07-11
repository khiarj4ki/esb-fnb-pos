<?php

use yii\db\Migration;
use app\models\Table;

/**
 * Class m200703_070040_add_column_station_id_ms_table
 */
class m200703_070040_add_column_station_id_ms_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(Table::tableName(), true)->getColumn('stationID') === null) {
            $this->addColumn(Table::tableName(), 'stationID',
                $this->integer()->after('heightRes'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Table::tableName(), true)->getColumn('stationID') !== null) {
            $this->dropColumn(Table::tableName(), 'stationID');
        }
    }
}
