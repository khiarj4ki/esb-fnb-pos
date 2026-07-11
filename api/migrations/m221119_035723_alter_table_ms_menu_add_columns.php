<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m221119_035723_alter_table_ms_menu_add_columns
 */
class m221119_035723_alter_table_ms_menu_add_columns extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field1') === null) {
            $this->addColumn(Menu::tableName(), 'field1', $this->string(50)->after('notes'));
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field2') === null) {
            $this->addColumn(Menu::tableName(), 'field2', $this->string(50)->after('field1'));
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field3') === null) {
            $this->addColumn(Menu::tableName(), 'field3', $this->string(50)->after('field2'));
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field4') === null) {
            $this->addColumn(Menu::tableName(), 'field4', $this->string(50)->after('field3'));
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field5') === null) {
            $this->addColumn(Menu::tableName(), 'field5', $this->string(50)->after('field4'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field1') !== null) {
            $this->dropColumn(Menu::tableName(), 'field1');
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field2') !== null) {
            $this->dropColumn(Menu::tableName(), 'field2');
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field3') !== null) {
            $this->dropColumn(Menu::tableName(), 'field3');
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field4') !== null) {
            $this->dropColumn(Menu::tableName(), 'field4');
        }
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('field5') !== null) {
            $this->dropColumn(Menu::tableName(), 'field5');
        }
    }
}
