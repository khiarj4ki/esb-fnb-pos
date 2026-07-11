<?php

use app\models\Menu;
use app\models\MenuExtra;
use yii\db\Migration;

/**
 * Class m220110_031604_add_menu_btn_color
 */
class m220110_031604_add_menu_btn_color extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(Menu::tableName(), 'buttonColor', 
                $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->defaultValue('#f39c12')->after('openPrice'));
        }

        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('buttonColor') === null) {
            $this->addColumn(MenuExtra::tableName(), 'buttonColor',
                $this->string(50)->after('notes')->defaultValue(NULL));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(Menu::tableName(),
                'buttonColor');
        }

        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('buttonColor') !== null) {
            $this->dropColumn(MenuExtra::tableName(), 'buttonColor');
        }
    }
}
