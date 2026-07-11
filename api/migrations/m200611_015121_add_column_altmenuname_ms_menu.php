<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m200611_015121_add_column_altmenuname_ms_menu
 */
class m200611_015121_add_column_altmenuname_ms_menu extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('altMenuName') === null) {
            $this->addColumn(Menu::tableName(),
                'altMenuName',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('VARCHAR(100)')->append('CHARACTER SET utf8 COLLATE utf8_unicode_ci')->after('menuShortName'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('altMenuName') !== null) {
            $this->dropColumn(Menu::tableName(),
                'altMenuName');
        }
    }
}
