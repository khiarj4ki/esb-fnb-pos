<?php

use app\models\MapMenuTemplatePackage;
use yii\db\Migration;

/**
 * Class m220727_075648_create_table_map_menutemplatepackage
 */
class m220727_075648_create_table_map_menutemplatepackage extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapMenuTemplatePackage::tableName(), true) === null) {
            $this->createTable(MapMenuTemplatePackage::tableName(),
            [
                'ID' => $this->primaryKey(11),
                'menuTemplateID' => $this->integer(11)->notNull(),
                'menuGroupID' => $this->integer(11)->notNull(),
                'menuID' => $this->integer(11)->notNull(),
                'price' => $this->decimal(20, 4)->notNull(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MapMenuTemplatePackage::tableName(), true) !== null) {
            $this->dropTable(MapMenuTemplatePackage::tableName());
        }
    }
}
