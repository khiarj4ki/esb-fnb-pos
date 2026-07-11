<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200218_041958_add_column_employee_code_tr_saleshead
 */
class m200218_041958_add_column_employee_code_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeCode') === null) {
            $this->addColumn(SalesHead::tableName(), 'employeeCode',
                $this->string(50)->after('memberID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('employeeCode') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'employeeCode');
        }
    }

}
