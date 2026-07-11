<?php

use yii\db\Migration;
use app\models\MapStationPosCustomerDisplay;

/**
 * Class m200909_032216_create_mapstationposcustomerdisplay
 */
class m200909_032216_create_mapstationposcustomerdisplay extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MapStationPosCustomerDisplay::tableName(),
                true) === null) {
            $this->createTable(MapStationPosCustomerDisplay::tableName(),
                [
                    'posCustomerDetailID' => $this->integer(11)->notNull(),
                    'stationID' => $this->integer(11)->notNull()
                ]);

            $this->addPrimaryKey('PRIMARYKEY',
                MapStationPosCustomerDisplay::tableName(),['posCustomerDetailID', 'stationID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapStationPosCustomerDisplay::tableName(),
                true) !== null) {
            $this->dropTable(MapStationPosCustomerDisplay::tableName());
        }
    }
}
