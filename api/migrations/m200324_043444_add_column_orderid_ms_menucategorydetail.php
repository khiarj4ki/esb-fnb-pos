<?php

use app\models\MenuCategoryDetail;
use yii\db\Migration;

/**
 * Class m200324_043444_add_column_orderid_ms_menucategorydetail
 */
class m200324_043444_add_column_orderid_ms_menucategorydetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('orderID') === null) {
            $this->addColumn(MenuCategoryDetail::tableName(), 'orderID',
                $this->integer(11)->after('imageUrl'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('orderID') !== null) {
            $this->dropColumn(MenuCategoryDetail::tableName(),
                'orderID');
        }
    }
}
