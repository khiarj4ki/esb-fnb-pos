<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200614_052757_alter_column_ms_paymentmethod
 */
class m200614_052757_alter_column_ms_paymentmethod extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('edcWssUrl') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'edcWssUrl',
                $this->string(200)->after('posExternalPaymentID'));
        }
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('edcPort') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'edcPort',
                $this->string(200)->after('edcWssUrl'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('edcWssUrl') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'edcWssUrl');
        }
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('edcPort') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'edcPort');
        }
    }
}
