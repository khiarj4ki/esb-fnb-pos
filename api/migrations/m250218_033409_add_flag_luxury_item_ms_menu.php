<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m250218_033409_add_flag_luxury_item_ms_menu
 */
class m250218_033409_add_flag_luxury_item_ms_menu extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagLuxuryItem') === null) {
            $this->addColumn(
                Menu::tableName(),
                'flagLuxuryItem',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->defaultValue(0)->after('flagSeparateTaxCalculation')
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagLuxuryItem') !== null) {
            $this->dropColumn(Menu::tableName(), 'flagLuxuryItem');
        }
    }
}
