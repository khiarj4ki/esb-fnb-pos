<?php

use yii\db\Migration;
use app\models\SalesMenuExtra;

/**
 * Class m200804_015429_add_column_inclusive_price_tr_salesmenuextra
 */
class m200804_015429_add_column_inclusive_price_tr_salesmenuextra extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('inclusivePrice') === null) {
            $this->addColumn(SalesMenuExtra::tableName(), 'inclusivePrice',
                $this->decimal(20, 4)->after('price')->defaultValue('0.000'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('inclusivePrice') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(), 'inclusivePrice');
        }
    }
}
