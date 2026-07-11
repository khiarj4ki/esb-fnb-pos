<?php

use app\models\HubMenu;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m191230_060008_create_hub_menu
 */
class m191230_060008_create_hub_menu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(HubMenu::tableName(),
                true) === null) {
            $this->createTable(HubMenu::tableName(),
                [
                    'ID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                    'hubID' => $this->integer()->notNull(),
                    'menuID' => $this->integer()->notNull(),
                    'sourceMenuID' => $this->integer()->notNull()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(HubMenu::tableName(),
                true) !== null) {
            $this->dropTable(HubMenu::tableName());
        }
    }
}
