<?php
use app\models\Menu;
use app\models\MenuCategoryDetail;
use app\models\MenuTemplateCategoryDetail;
use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m200720_054622_insert_initial_menu_template_category_detail
 */
class m200720_054622_insert_initial_menu_template_category_detail extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!MenuTemplateCategoryDetail::find()->exists()) {
            $this->execute("INSERT INTO " . MenuTemplateCategoryDetail::tableName() . " " .
                "SELECT DISTINCT a.menuTemplateID, b.menuCategoryDetailID, COALESCE(c.orderID, 0) " .
                "FROM " . MenuTemplateDetail::tableName() . " a " .
                "JOIN " . Menu::tableName() . " b ON a.menuID = b.menuID " .
                "JOIN " . MenuCategoryDetail::tableName() . " c ON b.menuCategoryDetailID = c.ID ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
