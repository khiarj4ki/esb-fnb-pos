<?php

use app\models\TableSectionStation;
use yii\db\Migration;

/**
 * Class m220517_031036_create_table_ms_tablesectionstation
 */
class m220517_031036_create_table_ms_tablesectionstation extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(TableSectionStation::tableName(), true) === null) {
            $this->createTable(TableSectionStation::tableName(),
                [
                    'ID' => $this->primaryKey(11),
                    'branchID' => $this->integer(11)->defaultValue(null),
                    'tableSectionID' => $this->integer(11)->defaultValue(null),
                    'menuCategoryDetailID' => $this->integer(11)->defaultValue(null),
                    'stationID' => $this->integer(11)->defaultValue(null)
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(TableSectionStation::tableName(), true) !== null) {
            $this->dropTable(TableSectionStation::tableName());
        }
    }
}
