<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m230828_093746_add_column_ms_paymentmethod
 */
class m230828_093746_add_column_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('depositSourceID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'depositSourceID',
                $this->integer()->after('flagIncludeTotalSpent'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('depositSourceID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'flagIncludeTotalSpent');
        }
    }
}
