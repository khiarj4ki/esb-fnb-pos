<?php

use app\models\MenuTemplateLayout;
use yii\db\Migration;

/**
 * Class m200603_054821_create_table_map_menutemplatelayout
 */
class m200603_054821_create_table_map_menutemplatelayout extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuTemplateLayout::tableName(), true) === null) {
            $this->createTable(MenuTemplateLayout::tableName(),
                [
                    'menuTemplateID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'menuSizeID' => $this->integer()->notNull(),
                    'posX' => $this->integer()->notNull(),
                    'posY' => $this->integer()->notNull(),
            ]);
            
            $this->addPrimaryKey('PRIMARY KEY', 
                MenuTemplateLayout::tableName(), 
                ['menuTemplateID', 'menuID']);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuTemplateLayout::tableName(), true) !== null) {
            $this->dropTable(MenuTemplateLayout::tableName());
        }
    }
}
