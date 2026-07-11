<?php

use app\models\MenuCategory;
use yii\db\Migration;

/**
 * Class m200324_043143_add_column_orderid_ms_menucategory
 */
class m200324_043143_add_column_orderid_ms_menucategory extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuCategory::tableName(), 'orderID',
                $this->integer(11)->after('notes'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuCategory::tableName(),
                'orderID');
        }
    }
}
