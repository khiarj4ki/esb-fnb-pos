<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200622_034906_add_column_ms_paymentmethod
 */
class m200622_034906_add_column_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('syncDate') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'syncDate',
                $this->dateTime()->after('editedDate'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('syncDate') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'syncDate');
        }
    }
}
