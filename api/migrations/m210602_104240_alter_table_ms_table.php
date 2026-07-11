<?php

use app\models\Table;
use yii\db\Migration;

/**
 * Class m210602_104240_alter_table_ms_table
 */
class m210602_104240_alter_table_ms_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Table::tableName(), true)->getColumn('flagAvailableForBooking') === null) {
            $this->addColumn(Table::tableName(), 'flagAvailableForBooking', $this->integer(11)->after('flagActive'));
        }

        Table::updateAll(['flagAvailableForBooking' => 0]);
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Table::tableName(), true)->getColumn('flagAvailableForBooking') !== null) {
            $this->dropColumn(Table::tableName(), 'flagAvailableForBooking');
        }
    }
}
