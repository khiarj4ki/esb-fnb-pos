<?php

use app\models\Station;
use yii\db\Migration;

/**
 * Class m210624_074301_add_column_ms_station
 */
class m210624_074301_add_column_ms_station extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('flagAutocut') === null) {
            $this->addColumn(Station::tableName(), 'flagAutocut',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(1)->after('printingModeID'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Station::tableName(), true)->getColumn('flagAutocut') !== null) {
            $this->dropColumn(Station::tableName(), 'flagAutocut');
        }
    }
}
