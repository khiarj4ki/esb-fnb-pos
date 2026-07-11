<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m200304_074328_add_column_openprice_ms_menu
 */
class m200304_074328_add_column_openprice_ms_menu extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('openPrice') === null) {
            $this->addColumn(Menu::tableName(),
                'openPrice',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(0)->after('description'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('openPrice') !== null) {
            $this->dropColumn(Menu::tableName(),
                'openPrice');
        }
    }
}
