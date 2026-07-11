<?php

use app\models\EmployeeGroup;
use yii\db\Migration;

/**
 * Class m200811_064557_create_table_ms_employeegroup
 */
class m200811_064557_create_table_ms_employeegroup extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(EmployeeGroup::tableName(), true) === null) {
            $this->createTable(EmployeeGroup::tableName(),
                [
                'employeeGroupID' => $this->primaryKey(),
                'employeeGroupName' => $this->string(100)->notNull(),
                'flagActive' => $this->tinyInteger(1)->notNull(),
                'createdBy' => $this->string(100)->notNull(),
                'createdDate' => $this->dateTime(),
                'editedBy' => $this->string(100)->notNull(),
                'editedDate' => $this->dateTime()
            ]);
        }

    }

    public function down()
    {
        if ($this->db->getTableSchema(EmployeeGroup::tableName(), true) !== null) {
            $this->dropTable(EmployeeGroup::tableName());
        }
    }
}
