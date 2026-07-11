<?php
use app\models\MenuCategoryDetail;
use yii\db\Migration;

/**
 * Class m191114_094907_add_imageurl_ms_menu_category_detail
 */
class m191114_094907_add_imageurl_ms_menu_category_detail extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('imageUrl') === null) {
            $this->addColumn(MenuCategoryDetail::tableName(), 'imageUrl',
                $this->text()->defaultValue(NULL)->after('menuCategoryDetailDesc'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MenuCategoryDetail::tableName(), true)->getColumn('imageUrl') !== null) {
            $this->dropColumn(MenuCategoryDetail::tableName(), 'imageUrl');
        }
    }

}
