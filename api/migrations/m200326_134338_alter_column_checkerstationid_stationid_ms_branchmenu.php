<?php

use app\models\BranchMenu;
use yii\db\Migration;

/**
 * Class m200326_134338_alter_column_checkerstationid_stationid_ms_branchmenu
 */
class m200326_134338_alter_column_checkerstationid_stationid_ms_branchmenu extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('checkerStationID') !== null) {
            $this->alterColumn(
                BranchMenu::tableName(),
                'checkerStationID',
                $this->string(50)
            );
        }
        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('stationID') !== null) {
            $this->alterColumn(
                BranchMenu::tableName(),
                'stationID',
                $this->string(50)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('checkerStationID') !== null) {
            $this->alterColumn(
                BranchMenu::tableName(),
                'checkerStationID',
                $this->integer()->notNull()
            );
        }

        if ($this->db->getTableSchema(BranchMenu::tableName(), true)->getColumn('stationID') !== null) {
            $this->alterColumn(
                BranchMenu::tableName(),
                'stationID',
                $this->integer()->notNull()
            );
        }
    }
}
