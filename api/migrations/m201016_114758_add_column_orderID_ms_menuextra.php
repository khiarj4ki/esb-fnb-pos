<?php

use app\models\MenuExtra;
use yii\db\Migration;

/**
 * Class m201016_114758_add_column_orderID_ms_menuextra
 */
class m201016_114758_add_column_orderID_ms_menuextra extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuExtra::tableName(), 'orderID',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('integer(11)')->defaultValue(null)->after('notes'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuExtra::tableName(), 'orderID');
        }
    }
}
