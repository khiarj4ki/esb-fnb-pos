<?php

use app\models\SalesPayment;
use yii\db\Migration;

/**
 * Class m220907_041634_add_culumn_edcterminalid_tr_salespayment
 */
class m220907_041634_add_column_edcterminalid_tr_salespayment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('edcTerminalID') === null) {
            $this->addColumn(
                SalesPayment::tableName(),
                'edcTerminalID',
                $this->string(50)->after('verificationCode')
            );
        };

    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
       if($this->db->getTableSchema(SalesPayment::tableName(), true)->getColumn('edcTerminalID') !== null) {
        $this->dropColumn(
            SalesPayment::tableName(), 
            'edcTerminalID'
        );
       }
    }
}
