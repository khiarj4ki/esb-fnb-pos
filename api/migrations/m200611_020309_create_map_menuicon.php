<?php

use app\models\MapMenuIcon;
use yii\db\Migration;

/**
 * Class m200611_020309_create_map_menuicon
 */
class m200611_020309_create_map_menuicon extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapMenuIcon::tableName(), true) === null) {
            $this->createTable(MapMenuIcon::tableName(),
                [
                'menuIconID' => $this->integer()->notNull(),
                'menuID' => $this->integer()->notNull(),
            ]);
            $this->addPrimaryKey('PRIMARYKEY', MapMenuIcon::tableName(),
                ['menuIconID', 'menuID']);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapMenuIcon::tableName(), true) !== null) {
            $this->dropTable(MapMenuIcon::tableName());
        }
    }

}
