<?php

use app\models\EmployeeGroupDetail;
use yii\db\Migration;

/**
 * Class m200811_064607_create_table_ms_employeegroupdetail
 */
class m200811_064607_create_table_ms_employeegroupdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(EmployeeGroupDetail::tableName(), true) === null) {
            $this->createTable(EmployeeGroupDetail::tableName(),
                [
                'employeeGroupID' => $this->integer()->notNull(),
                'employeeCode' => $this->string(50)->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', EmployeeGroupDetail::tableName(),
                ['employeeGroupID', 'employeeCode']);
        }

    }

    public function down()
    {
        if ($this->db->getTableSchema(EmployeeGroupDetail::tableName(), true) !== null) {
            $this->dropTable(EmployeeGroupDetail::tableName());
        }
    }
}
