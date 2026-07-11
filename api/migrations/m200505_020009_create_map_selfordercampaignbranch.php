<?php

use app\models\MapSelfOrderCampaignBranch;
use yii\db\Migration;

/**
 * Class m200505_020009_create_map_selfordercampaignbranch
 */
class m200505_020009_create_map_selfordercampaignbranch extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapSelfOrderCampaignBranch::tableName(), true) === null) {
            $this->createTable(MapSelfOrderCampaignBranch::tableName(),
                [
                    'selfOrderCampaignID' => $this->integer()->notNull(),
                    'branchID' => $this->integer()->notNull(),
            ]);
            $this->addPrimaryKey('PRIMARYKEY', MapSelfOrderCampaignBranch::tableName(),
                ['selfOrderCampaignID', 'branchID']);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MapSelfOrderCampaignBranch::tableName(), true) !== null) {
            $this->dropTable(MapSelfOrderCampaignBranch::tableName());
        }
    }
}
