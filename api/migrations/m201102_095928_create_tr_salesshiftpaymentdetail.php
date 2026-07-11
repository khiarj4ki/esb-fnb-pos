<?php

use app\models\SalesShiftPaymentDetail;
use yii\db\Migration;

/**
 * Class m201102_095928_create_tr_salesshiftpaymentdetail
 */
class m201102_095928_create_tr_salesshiftpaymentdetail extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesShiftPaymentDetail::tableName(),
                true) === null) {
            $this->createTable(SalesShiftPaymentDetail::tableName(),
                [
                    'salesShiftDetailID' => $this->primaryKey(),
                    'salesShiftPaymentHeadID' => $this->integer(20)->notNull(),
                    'paymentMethodID' =>  $this->integer(20)->notNull(),
                    'actualPaymentAmount' =>  $this->decimal(20, 4)->null(),
                    'syncDate' => $this->dateTime()->null()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesShiftPaymentDetail::tableName(),
                true) !== null) {
            $this->dropTable(SalesShiftPaymentDetail::tableName());
        }
    }
}
