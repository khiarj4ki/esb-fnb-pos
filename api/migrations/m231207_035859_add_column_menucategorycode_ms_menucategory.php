<?php

use app\models\MenuCategory;
use yii\db\Migration;

/**
 * Class m231207_035859_add_column_menucategorycode_ms_menucategory
 */
class m231207_035859_add_column_menucategorycode_ms_menucategory extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('menuCategoryCode') === null) {
            $this->addColumn(
                MenuCategory::tableName(),
                'menuCategoryCode',
                $this->string(50)->null()->after('menuCategoryDesc')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(MenuCategory::tableName(), true)->getColumn('menuCategoryCode') !== null) {
            $this->dropColumn(MenuCategory::tableName(), 'menuCategoryCode');
        }
    }
}
