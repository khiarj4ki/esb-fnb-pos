<?php

use yii\db\Migration;
use app\models\SalesMenu;

/**
 * Class m200803_031946_add_column_inclusive_discount_value_tr_salesmenu
 */
class m200803_031946_add_column_inclusive_discount_value_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('inclusiveDiscountValue') === null) {
            $this->addColumn(SalesMenu::tableName(), 'inclusiveDiscountValue',
                $this->decimal(20, 4)->after('discountValue')->defaultValue('0.000'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('inclusiveDiscountValue') !== null) {
            $this->dropColumn(SalesMenu::tableName(), 'inclusiveDiscountValue');
        }
    }

}
