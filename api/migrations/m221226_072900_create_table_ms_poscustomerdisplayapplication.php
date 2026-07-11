<?php

use app\models\PosCustomerDisplayApplication;
use yii\db\Migration;

/**
 * Class m221226_072900_create_table_ms_poscustomerdisplayapplication
 */
class m221226_072900_create_table_ms_poscustomerdisplayapplication extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(PosCustomerDisplayApplication::tableName(), true) === null) {
            $this->createTable(PosCustomerDisplayApplication::tableName(), [
                'posCustomerDetailID' => $this->integer(11)->notNull(),
                'applicationID' => $this->string(15)->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', PosCustomerDisplayApplication::tableName(), ['posCustomerDetailID', 'applicationID']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PosCustomerDisplayApplication::tableName(), true) !== null) {
            $this->dropTable(PosCustomerDisplayApplication::tableName());
        }
    }
}
