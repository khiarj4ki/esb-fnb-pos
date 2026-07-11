<?php

use app\models\MenuTemplateCategory;
use app\models\MenuTemplateCategoryDetail;
use app\models\MenuTemplateDetail;
use app\models\ProductDetailMenu;
use yii\db\Migration;

/**
 * Class m240219_021823_indexing_for_enhancement_load_branch_menu
 */
class m240219_021823_indexing_for_enhancement_load_branch_menu extends Migration
{
    public function up()
    {
        $checkIndex = "SHOW INDEX FROM " . ProductDetailMenu::tableName() . " WHERE Key_name = 'idx_ms_productdetailmenu_menuID'";
        if (!$this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->createIndex('idx_ms_productdetailmenu_menuID', ProductDetailMenu::tableName(), 'menuID');
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateCategory::tableName() . " WHERE Key_name = 'idx_ms_menutemplatecategory_menuCategoryID'";
        if (!$this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->createIndex('idx_ms_menutemplatecategory_menuCategoryID', MenuTemplateCategory::tableName(), 'menuCategoryID');
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateCategoryDetail::tableName() . " WHERE Key_name = 'idx_ms_menutemplatecategorydetail_menuCategoryDetailID'";
        if (!$this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->createIndex('idx_ms_menutemplatecategorydetail_menuCategoryDetailID', MenuTemplateCategoryDetail::tableName(), 'menuCategoryDetailID');
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateDetail::tableName() . " WHERE Key_name = 'idx_ms_menutemplatedetail_menuID'";
        if (!$this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->createIndex('idx_ms_menutemplatedetail_menuID', MenuTemplateDetail::tableName(), 'menuID');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $checkIndex = "SHOW INDEX FROM " . ProductDetailMenu::tableName() . " WHERE Key_name = 'idx_ms_productdetailmenu_menuID'";
        if ($this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->dropIndex('idx_ms_productdetailmenu_menuID', ProductDetailMenu::tableName());
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateCategory::tableName() . " WHERE Key_name = 'idx_ms_menutemplatecategory_menuCategoryID'";
        if ($this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->dropIndex('idx_ms_menutemplatecategory_menuCategoryID', MenuTemplateCategory::tableName());
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateCategoryDetail::tableName() . " WHERE Key_name = 'idx_ms_menutemplatecategorydetail_menuCategoryDetailID'";
        if ($this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->dropIndex('idx_ms_menutemplatecategorydetail_menuCategoryDetailID', MenuTemplateCategoryDetail::tableName());
        }

        $checkIndex = "SHOW INDEX FROM " . MenuTemplateDetail::tableName() . " WHERE Key_name = 'idx_ms_menutemplatedetail_menuID'";
        if ($this->db->createCommand($checkIndex)->queryScalar())
        {
            $this->dropIndex('idx_ms_menutemplatedetail_menuID', MenuTemplateDetail::tableName());
        }
    }
}
