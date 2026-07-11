<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m200918_070854_alter_column_notes_tr_salesmenu
 */
class m200918_070854_alter_column_notes_tr_salesmenu extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(
                SalesMenu::tableName(),
                'notes',
                $this->string(200)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(
                SalesMenu::tableName(),
                'notes',
                $this->string(100)
            );
        }
    }
}
