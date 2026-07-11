<?php

use app\models\MenuTemplateDetailDay;
use yii\db\Migration;

/**
 * Class m241029_045230_insert_ms_menutemplatedetailday
 */
class m241029_045230_insert_ms_menutemplatedetailday extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(MenuTemplateDetailDay::tableName(),true) === null) {

            $this->createTable(MenuTemplateDetailDay::tableName(),
            [
                'ID' => $this->integer(11)->notNull()->append('AUTO_INCREMENT PRIMARY KEY'),
                'menuTemplateID' => $this->string(50)->null(),
                'menuID'=> $this->integer(11)->null(),
                'dayID' => $this->integer(11)->null(),
            ]);

            $this->createIndex('idx_menuTemplateID_ms_menutemplatedetailday', MenuTemplateDetailDay::tableName(), 'menuTemplateID');
            $this->createIndex('idx_menuID_ms_menutemplatedetailday', MenuTemplateDetailDay::tableName(), 'menuID');
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MenuTemplateDetailDay::tableName(), true) !== null) {
            $this->dropTable(MenuTemplateDetailDay::tableName());
        }
    }
}
