<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m200515_081732_add_column_flag_separate_print_package_ms_menu
 */
class m200515_081732_add_column_flag_separate_print_package_ms_menu extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagSeparatePrintPackage') === null) {
            $this->addColumn(Menu::tableName(), 'flagSeparatePrintPackage',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue('0')->after('flagCustomerPrint'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagSeparatePrintPackage') !== null) {
            $this->dropColumn(Menu::tableName(), 'flagSeparatePrintPackage');
        }
    }
}
