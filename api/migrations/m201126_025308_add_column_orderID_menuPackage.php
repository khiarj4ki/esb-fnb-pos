<?php

use app\models\MenuPackage;
use yii\db\Migration;

/**
 * Class m201126_025308_add_column_orderID_menuPackage
 */
class m201126_025308_add_column_orderID_menuPackage extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuPackage::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuPackage::tableName(), 'orderID',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('integer(11)')->defaultValue(null)->after('price'));
        }
        
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuPackage::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuPackage::tableName(), 'orderID');
        }
    }
}
