<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m210222_034733_add_flag_edc_active_ms_paymentmethod
 */
class m210222_034733_add_flag_edc_active_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagEdcActive') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'flagEdcActive',
                $this->tinyInteger(1)->notNull()->defaultValue(1)->after('flagUseEmployeeLimit'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagEdcActive') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'flagEdcActive');
        }
    }
}
