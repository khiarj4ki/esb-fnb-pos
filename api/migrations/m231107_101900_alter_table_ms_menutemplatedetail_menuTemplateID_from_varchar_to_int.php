<?php

use app\models\MenuTemplateDetail;
use yii\db\Migration;

/**
 * Class m231107_101900_alter_table_ms_menutemplatedetail_menuTemplateID_from_varchar_to_int
 */
class m231107_101900_alter_table_ms_menutemplatedetail_menuTemplateID_from_varchar_to_int extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('menuTemplateID') !== null) {
            $this->alterColumn(MenuTemplateDetail::tableName(), 'menuTemplateID', $this->integer(11));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(MenuTemplateDetail::tableName(), true)->getColumn('menuTemplateID') !== null) {
            $this->alterColumn(MenuTemplateDetail::tableName(), 'menuTemplateID', $this->string(50));
        }
    }
}
