<?php

use app\models\SalesShiftPaymentHead;
use yii\db\Migration;

/**
 * Class m210308_024329_alter_tbl_tr_salesshiftpaymenthead_createdBy_submittedBy
 */
class m210308_024329_alter_tbl_tr_salesshiftpaymenthead_createdBy_submittedBy extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('createdBy') === null) {
            $this->addColumn(SalesShiftPaymentHead::tableName(), 'createdBy',
                $this->string(100)->after('description'));
        }

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('submittedBy') === null) {
            $this->addColumn(SalesShiftPaymentHead::tableName(), 'submittedBy',
                $this->string(100)->after('createdBy'));
        }

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('shiftID') === null) {
            $this->addColumn(SalesShiftPaymentHead::tableName(), 'shiftID',
                $this->integer(20)->after('salesShiftPaymentHeadID'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('createdBy') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(), 'createdBy');
        }

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('submittedBy') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(), 'submittedBy');
        }

        if ($this->db->getTableSchema(SalesShiftPaymentHead::tableName(), true)->getColumn('shiftID') !== null) {
            $this->dropColumn(SalesShiftPaymentHead::tableName(), 'shiftID');
        }
    }
}
