<?php

use app\models\MenuCategoryDetail;
use yii\db\Migration;

/**
 * Class m210415_063124_add_column_menucategorydetailcode
 */
class m210415_063124_add_column_menucategorydetailcode extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('menuCategoryDetailCode') === null) {
            $this->addColumn(MenuCategoryDetail::tableName(), 'menuCategoryDetailCode',
                $this->string(50)->after('menuCategoryDetailDesc'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('menuCategoryDetailCode') !== null) {
            $this->dropColumn(MenuCategoryDetail::tableName(), 'menuCategoryDetailCode');
        }
    }
}
