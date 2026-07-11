<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210729_084437_add_column_employeename_and_employeetype_tr_saleshead
 */
class m210729_084437_add_column_employeename_and_employeetype_tr_saleshead extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeName') === null) {
            $this->addColumn(SalesHead::tableName(),
                'employeeName',
                $this->string(100)->after('employeeCode')->null());
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeType') === null) {
            $this->addColumn(SalesHead::tableName(),
                'employeeType',
                $this->string(50)->after('employeeName')->null());
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeName') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'employeeName');
        }

        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeType') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'employeeType');
        }
    }
}
