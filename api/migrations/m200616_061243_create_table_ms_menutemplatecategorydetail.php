<?php

use yii\db\Migration;
use app\models\MenuTemplateCategoryDetail;

/**
 * Class m200616_061243_create_table_ms_menutemplatecategorydetail
 */
class m200616_061243_create_table_ms_menutemplatecategorydetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateCategoryDetail::tableName(),
                true) === null) {
            $this->createTable(MenuTemplateCategoryDetail::tableName(),
                [
                'menuTemplateID' => $this->integer()->notNull(),
                'menuCategoryDetailID' => $this->integer()->notNull(),
                'orderID' => $this->integer()->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', MenuTemplateCategoryDetail::tableName(),
                ['menuTemplateID', 'menuCategoryDetailID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateCategoryDetail::tableName(),
                true) !== null) {
            $this->dropTable(MenuTemplateCategoryDetail::tableName());
        }
    }
}
