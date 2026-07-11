<?php

use app\models\PaymentOnlineTrackingLog;
use yii\db\Migration;

/**
 * Class m240524_151848_create_tr_paymentonlinetrackinglog
 */
class m240524_151848_create_tr_paymentonlinetrackinglog extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PaymentOnlineTrackingLog::tableName(), true) === null) {
            $this->createTable(PaymentOnlineTrackingLog::tableName(), [
                'ID' => $this->primaryKey(),
                'salesNum' => $this->string(20),
                'billNum' => $this->string(20),
                'branchID' => $this->string(20),
                'productType' => $this->string(50),
                'externalPaymentCode' => $this->string(20),
                'paymentAmount' => $this->decimal(20,4),
                'syncDate' => $this->dateTime()
            ]);

            $this->createIndex(
                'salesNum_INDEX',
                PaymentOnlineTrackingLog::tableName(),
                'salesNum'
            );

        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PaymentOnlineTrackingLog::tableName(), true) !== null) {
            $this->dropTable(PaymentOnlineTrackingLog::tableName());
        }
    }
}
