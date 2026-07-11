<?php

use app\models\SalesShiftPaymentDenom;
use yii\db\Migration;

/**
 * Class m210309_080646_alter_tbl_salesshiftdenom
 */
class m210309_080646_alter_tbl_salesshiftdenom extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesShiftPaymentDenom::tableName(), true)->getColumn('syncDate') === null) {
            $this->addColumn(SalesShiftPaymentDenom::tableName(), 'syncDate',
                $this->dateTime()->after('denomTotal'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesShiftPaymentDenom::tableName(), true)->getColumn('syncDate') !== null) {
            $this->dropColumn(SalesShiftPaymentDenom::tableName(), 'syncDate');
        }
    }
}
