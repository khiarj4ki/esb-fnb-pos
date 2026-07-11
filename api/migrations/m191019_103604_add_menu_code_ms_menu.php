<?php
use app\models\Menu;
use yii\db\Migration;

/**
 * Class m191019_103604_add_menu_code_ms_menu
 */
class m191019_103604_add_menu_code_ms_menu extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('menuCode') === null) {
            $this->addColumn(Menu::tableName(), 'menuCode',
                $this->string(50)->defaultValue('')->after('menuShortName'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('menuCode') !== null) {
            $this->dropColumn(Menu::tableName(), 'menuCode');
        }
    }

}
