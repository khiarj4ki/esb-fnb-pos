<?php

use yii\db\Migration;
use app\models\MenuTemplateCategory;

/**
 * Class m200616_060815_create_table_ms_menutemplatecategory
 */
class m200616_060815_create_table_ms_menutemplatecategory extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(MenuTemplateCategory::tableName(),
                true) === null) {
            $this->createTable(MenuTemplateCategory::tableName(),
                [
                'menuTemplateID' => $this->integer()->notNull(),
                'menuCategoryID' => $this->integer()->notNull(),
                'orderID' => $this->integer()->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', MenuTemplateCategory::tableName(),
                ['menuTemplateID', 'menuCategoryID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuTemplateCategory::tableName(),
                true) !== null) {
            $this->dropTable(MenuTemplateCategory::tableName());
        }
    }
}
