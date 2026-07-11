<?php

use app\models\PaymentMethod;
use yii\db\Migration;

/**
 * Class m200529_073111_add_column_flaguseemployeelimit_ms_paymentmethod
 */
class m200529_073111_add_column_flaguseemployeelimit_ms_paymentmethod extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagUseEmployeeLimit') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'flagUseEmployeeLimit',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->after('flagAuthorization'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('flagUseEmployeeLimit') !== null) {
            $this->dropColumn(PaymentMethod::tableName(),
                'flagUseEmployeeLimit');
        }
    }
}
