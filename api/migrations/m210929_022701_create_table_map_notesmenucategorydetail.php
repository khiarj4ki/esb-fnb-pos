<?php

use app\models\MapNotesMenuCategoryDetail;
use yii\db\Migration;

/**
 * Class m210929_022701_create_table_map_notesmenucategorydetail
 */
class m210929_022701_create_table_map_notesmenucategorydetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapNotesMenuCategoryDetail::tableName(), true) === null) {
            $this->createTable(
                MapNotesMenuCategoryDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'notesCategoryID' => $this->integer(),
                    'menuCategoryDetailID' => $this->integer()
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if ($this->db->getTableSchema(MapNotesMenuCategoryDetail::tableName(), true) !== null) {
            $this->dropTable(MapNotesMenuCategoryDetail::tableName());
        }
    }
}
