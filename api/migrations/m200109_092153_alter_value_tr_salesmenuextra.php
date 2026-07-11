<?php

use app\models\SalesMenuExtra;
use yii\db\Migration;

/**
 * Class m200109_092153_alter_value_tr_salesmenuextra
 */
class m200109_092153_alter_value_tr_salesmenuextra extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('discountValue') === null) {
            $this->addColumn(SalesMenuExtra::tableName(),
                'discountValue',
                $this->decimal(20, 4)->defaultValue(0)->after('discount'));
        }
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherTaxValue') === null) {
            $this->addColumn(SalesMenuExtra::tableName(),
                'otherTaxValue',
                $this->decimal(20, 4)->defaultValue(0)->after('otherTax'));
        }
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('vatValue') === null) {
            $this->addColumn(SalesMenuExtra::tableName(),
                'vatValue',
                $this->decimal(20, 4)->defaultValue(0)->after('vat'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('discountValue') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(),
                'discountValue');
        }
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherTaxValue') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(),
                'otherTaxValue');
        }
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('vatValue') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(),
                'vatValue');
        }
    }
}
