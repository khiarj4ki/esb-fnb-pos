<?php

use app\models\MapNotesMenuCategory;
use yii\db\Migration;

/**
 * Class m210929_022646_create_table_map_notesmenucategory
 */
class m210929_022646_create_table_map_notesmenucategory extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapNotesMenuCategory::tableName(), true) === null) {
            $this->createTable(
                MapNotesMenuCategory::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'notesCategoryID' => $this->integer(),
                    'menuCategoryID' => $this->integer()
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(MapNotesMenuCategory::tableName(), true) !== null) {
            $this->dropTable(MapNotesMenuCategory::tableName());
        }
    }
}
