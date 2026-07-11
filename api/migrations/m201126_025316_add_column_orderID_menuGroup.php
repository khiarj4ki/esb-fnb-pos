<?php

use app\models\MenuGroup;
use yii\db\Migration;

/**
 * Class m201126_025316_add_column_orderID_menuGroup
 */
class m201126_025316_add_column_orderID_menuGroup extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuGroup::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuGroup::tableName(), 'orderID',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('integer(11)')->defaultValue(null)->after('notes'));
        }
        
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuGroup::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuGroup::tableName(), 'orderID');
        }
    }
}
