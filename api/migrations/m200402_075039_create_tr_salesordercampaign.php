<?php

use app\models\SalesOrderCampaign;
use yii\db\Migration;

/**
 * Class m200402_075039_create_tr_salesordercampaign
 */
class m200402_075039_create_tr_salesordercampaign extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesOrderCampaign::tableName(), true) === null) {
            $this->createTable(SalesOrderCampaign::tableName(),
                [
                    'salesNum' => $this->string(20)->notNull(),
                    'selfOrderCampaignID' => $this->integer()->notNull(),
                    'count' => $this->integer()->notNull()->defaultValue('0'),
            ]);
            $this->addPrimaryKey('PRIMARYKEY', SalesOrderCampaign::tableName(),
                ['salesNum', 'selfOrderCampaignID']);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesOrderCampaign::tableName(), true) !== null) {
            $this->dropTable(SalesOrderCampaign::tableName());
        }
    }
}
