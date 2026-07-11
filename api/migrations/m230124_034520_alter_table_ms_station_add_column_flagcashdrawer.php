<?php

use app\models\Station;
use yii\db\Migration;

/**
 * Class m230124_034520_alter_table_ms_station_add_column_flagcashdrawer
 */
class m230124_034520_alter_table_ms_station_add_column_flagcashdrawer extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('flagCashDrawer') === null) {
            $this->addColumn(Station::tableName(), 'flagCashDrawer', $this->tinyInteger(1)->notNull()->defaultValue(0)->after('printerName'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('flagCashDrawer') !== null) {
            $this->dropColumn(Station::tableName(), 'flagCashDrawer');
        }
    }
}
