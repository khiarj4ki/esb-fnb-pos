<?php

use yii\db\Migration;
use app\models\SalesMenuExtra;

/**
 * Class m200803_034555_add_column_inclusive_discount_value_tr_salesmenuextra
 */
class m200803_034555_add_column_inclusive_discount_value_tr_salesmenuextra extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('inclusiveDiscountValue') === null) {
            $this->addColumn(SalesMenuExtra::tableName(), 'inclusiveDiscountValue',
                $this->decimal(20, 4)->after('discountValue')->defaultValue('0.000'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('inclusiveDiscountValue') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(), 'inclusiveDiscountValue');
        }
    }
}
