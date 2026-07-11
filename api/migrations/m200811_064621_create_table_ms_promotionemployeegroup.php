<?php

use app\models\PromotionEmployeeGroup;
use yii\db\Migration;

/**
 * Class m200811_064621_create_table_ms_promotionemployeegroup
 */
class m200811_064621_create_table_ms_promotionemployeegroup extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionEmployeeGroup::tableName(), true) === null) {
            $this->createTable(PromotionEmployeeGroup::tableName(),
                [
                'promotionID' => $this->integer()->notNull(),
                'employeeGroupID' => $this->integer()->notNull()
                
            ]);

            $this->addPrimaryKey('PRIMARYKEY', PromotionEmployeeGroup::tableName(),
                ['promotionID', 'employeeGroupID']);
        }

    }

    public function down()
    {
        if ($this->db->getTableSchema(PromotionEmployeeGroup::tableName(), true) !== null) {
            $this->dropTable(PromotionEmployeeGroup::tableName());
        }
    }
}
