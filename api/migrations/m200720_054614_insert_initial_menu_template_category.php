<?php
use app\models\Menu;
use app\models\MenuCategory;
use app\models\MenuCategoryDetail;
use app\models\MenuTemplateCategory;
use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m200720_054614_insert_initial_menu_template_category
 */
class m200720_054614_insert_initial_menu_template_category extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if (!MenuTemplateCategory::find()->exists()) {
            $this->execute("INSERT INTO " . MenuTemplateCategory::tableName() . " " .
                "SELECT DISTINCT a.menuTemplateID, d.menuCategoryID, COALESCE(d.orderID, 0) " .
                "FROM " . MenuTemplateDetail::tableName() . " a " .
                "JOIN " . Menu::tableName() . " b ON a.menuID = b.menuID " .
                "JOIN " . MenuCategoryDetail::tableName() . " c ON b.menuCategoryDetailID = c.ID " .
                "JOIN " . MenuCategory::tableName() . " d ON c.menuCategoryID = d.menuCategoryID ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        
    }

}
