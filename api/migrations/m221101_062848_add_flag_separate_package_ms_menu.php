<?php

use app\models\Menu;
use yii\db\Migration;

/**
 * Class m221101_062848_add_flag_separate_package_ms_menu
 */
class m221101_062848_add_flag_separate_package_ms_menu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagSeparateTaxCalculation') === null) {
            $this->addColumn(Menu::tableName(), 'flagSeparateTaxCalculation', $this->tinyInteger(1)->notNull()->defaultValue(0)->after('flagSeparatePrintPackage'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Menu::tableName(), true)->getColumn('flagSeparateTaxCalculation') !== null) {
            $this->dropColumn(Menu::tableName(), 'flagSeparateTaxCalculation');
        }
    }
}
