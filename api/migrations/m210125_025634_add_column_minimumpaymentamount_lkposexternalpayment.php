<?php

use app\models\PosExternalPayment;
use yii\db\Migration;

/**
 * Class m210125_025634_add_column_minimumpaymentamount_lkposexternalpayment
 */
class m210125_025634_add_column_minimumpaymentamount_lkposexternalpayment extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        if ($this->db->getTableSchema(PosExternalPayment::tableName(), true)->getColumn('minimumPaymentAmount') === null) {
            $this->addColumn(PosExternalPayment::tableName(), 'minimumPaymentAmount',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')
                ->defaultValue(0)
                ->after('posExternalPaymentType'));
            PosExternalPayment::updateAll(['minimumPaymentAmount' => 1500],
                ['posExternalPaymentID' => 'qris']);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PosExternalPayment::tableName(), true)->getColumn('minimumPaymentAmount') !== null) {
            $this->dropColumn(PosExternalPayment::tableName(), 'minimumPaymentAmount');
        }
    }
}
