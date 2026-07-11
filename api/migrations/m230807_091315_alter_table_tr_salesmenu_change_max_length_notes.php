<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m230807_091315_alter_table_tr_salesmenu_change_max_length_notes
 */
class m230807_091315_alter_table_tr_salesmenu_change_max_length_notes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(SalesMenu::tableName(), 'notes', $this->string(300));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('notes') !== null) {
            $this->alterColumn(SalesMenu::tableName(), 'notes', $this->string(200));
        }
    }
}
