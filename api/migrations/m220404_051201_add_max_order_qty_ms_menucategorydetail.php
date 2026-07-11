<?php

use app\models\MenuCategoryDetail;
use yii\db\Migration;

/**
 * Class m220404_051201_add_max_order_qty_ms_menucategorydetail
 */
class m220404_051201_add_max_order_qty_ms_menucategorydetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('maxOrderQty') === null) {
            $this->addColumn(MenuCategoryDetail::tableName(), 'maxOrderQty',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')->defaultValue(0)->after('imageUrl'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('maxOrderQty') !== null) {
            $this->dropColumn(MenuCategoryDetail::tableName(), 'maxOrderQty');
        }
    }
}
