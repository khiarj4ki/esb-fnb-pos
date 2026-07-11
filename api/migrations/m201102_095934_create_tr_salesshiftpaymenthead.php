<?php

use app\models\SalesShiftPaymentHead;
use yii\db\Migration;

/**
 * Class m201102_095934_create_tr_salesshiftpaymenthead
 */
class m201102_095934_create_tr_salesshiftpaymenthead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(),
                true) === null) {
            $this->createTable(SalesShiftPaymentHead::tableName(),
                [
                    'salesShiftPaymentHeadID' => $this->primaryKey(),
                    'shiftLogDetailID' => $this->integer(20)->notNull(),
                    'actualTotalPaymentNonCash' => $this->decimal(20, 4)->null(),
                    'actualTotalPaymentCash' => $this->decimal(20, 4)->null(),
                    'description' => $this->string(100)->null(),
                    'syncDate' => $this->dateTime()->null()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(),
                true) !== null) {
            $this->dropTable(SalesShiftPaymentHead::tableName());
        }
    }
}
