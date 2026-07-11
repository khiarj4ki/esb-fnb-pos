<?php

use app\models\MenuIcon;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m200611_020256_create_ms_menuicon
 */
class m200611_020256_create_ms_menuicon extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(MenuIcon::tableName(), true) === null) {
            $this->createTable(MenuIcon::tableName(),
                [
                    'menuIconID' =>  Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                    'menuIconName' => $this->string(100)->notNull(),
                    'menuIconUrl' => $this->getDb()->getSchema()->createColumnSchemaBuilder('text'),
            ]);
            
            $this->batchInsert(MenuIcon::tableName(),
                ['menuIconID', 'menuIconName','menuIconUrl'],
                [
                    [1, 'Recommended','https://esb.sgp1.digitaloceanspaces.com/general/recommended.png'],
                    [2, 'New','https://esb.sgp1.digitaloceanspaces.com/general/new.png'],
                    [3, 'Promoted','https://esb.sgp1.digitaloceanspaces.com/general/promoted.png'],
                    [4, 'Vegetarian','https://esb.sgp1.digitaloceanspaces.com/general/vegetarian.png'],
                    [5, 'Spicy','https://esb.sgp1.digitaloceanspaces.com/general/spicy.png'],
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MenuIcon::tableName(), true) !== null) {
            $this->dropTable(MenuIcon::tableName());
        }
    }
}
