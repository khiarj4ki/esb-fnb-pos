<?php
use app\models\Station;
use yii\db\Migration;

/**
 * Class m200212_103632_alter_column_flag_single_menu_print_ms_station
 */
class m200212_103632_alter_column_flag_single_menu_print_ms_station extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('printingModeID') === null) {
            $this->addColumn(Station::tableName(), 'printingModeID',
                $this->tinyInteger(1)->defaultValue(0)->after('characterPerLine'));
        }

        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('flagSingleMenuPrint') !== null) {
            $this->execute("UPDATE " . Station::tableName() . " " .
                "SET printingModeID = CASE WHEN flagSingleMenuPrint = 1 THEN 2 ELSE 1 END  ");

            $this->dropColumn(Station::tableName(), 'flagSingleMenuPrint');
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('printingModeID') !== null) {
            $this->dropColumn(Station::tableName(), 'printingModeID');
        }
    }

}
