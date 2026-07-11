<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200221_022028_alter_column_fixedamount_ms_paymentmethod
 */
class m200221_022028_alter_column_fixedamount_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('fixedAmount') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'fixedAmount',
                $this->decimal(20,4)->null()->after('printedCount'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('fixedAmount') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'fixedAmount');
        }
    }
}
