<?php

use yii\db\Migration;
use app\models\SalesMenu;

/**
 * Class m200804_015407_add_column_inclusive_price_tr_salesmenu
 */
class m200804_015407_add_column_inclusive_price_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('inclusivePrice') === null) {
            $this->addColumn(SalesMenu::tableName(), 'inclusivePrice',
                $this->decimal(20, 4)->after('price')->defaultValue('0.000'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('inclusivePrice') !== null) {
            $this->dropColumn(SalesMenu::tableName(), 'inclusivePrice');
        }
    }
}
