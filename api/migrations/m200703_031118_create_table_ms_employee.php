<?php

use app\models\MsEmployee;
use yii\db\Migration;

/**
 * Class m200703_031118_create_table_ms_employee
 */
class m200703_031118_create_table_ms_employee extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MsEmployee::tableName(), true) === null) {
            $this->createTable(MsEmployee::tableName(), [
                'employeeCode' => $this->string(50)->notNull(),
                'employeeName' => $this->string(100)->notNull(),
                'genderID' => $this->integer()->notNull(),
                'birthDate' => $this->date()->null(),
                'address' => $this->string(200)->defaultValue('NULL'),
                'phone' => $this->string(20)->defaultValue('NULL'),
                'email' => $this->string(50)->defaultValue('NULL'),
                'flagActive' => $this->boolean()->notNull(),
                'createdBy' => $this->string(100)->notNull(),
                'createdDate' => $this->dateTime()->notNull(),
                'editedBy' => $this->string(100)->defaultValue('NULL'),
                'editedDate' => $this->dateTime()->null()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', MsEmployee::tableName(), ['employeeCode']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MsEmployee::tableName(), true) !== null) {
            $this->dropTable(MsEmployee::tableName());
        }
    }
}
