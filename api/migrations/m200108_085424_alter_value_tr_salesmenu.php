<?php

use yii\db\Migration;
use app\models\SalesMenu;

/**
 * Class m200108_085424_alter_value_tr_salesmenu
 */
class m200108_085424_alter_value_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('discountValue') === null) {
            $this->addColumn(SalesMenu::tableName(),
                'discountValue',
                $this->decimal(20, 4)->defaultValue(0)->after('discount'));
        }
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherTaxValue') === null) {
            $this->addColumn(SalesMenu::tableName(),
                'otherTaxValue',
                $this->decimal(20, 4)->defaultValue(0)->after('otherTax'));
        }
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('vatValue') === null) {
            $this->addColumn(SalesMenu::tableName(),
                'vatValue',
                $this->decimal(20, 4)->defaultValue(0)->after('vat'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('discountValue') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'discountValue');
        }
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherTaxValue') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'otherTaxValue');
        }
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('vatValue') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'vatValue');
        }
    }
}
