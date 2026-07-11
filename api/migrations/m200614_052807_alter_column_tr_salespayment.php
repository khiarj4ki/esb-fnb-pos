<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m200614_052807_alter_column_tr_salespayment
 */
class m200614_052807_alter_column_tr_salespayment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('traceNumber') === null) {
            $this->addColumn(SalesPayment::tableName(), 'traceNumber',
                $this->string(50)->after('verificationCode'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('traceNumber') !== null) {
            $this->dropColumn(SalesPayment::tableName(),
                'traceNumber');
        }
    }
}
