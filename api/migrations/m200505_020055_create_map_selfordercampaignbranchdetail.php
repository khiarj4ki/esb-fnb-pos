<?php

use app\models\MapSelfOrderCampaignBranchDetail;
use yii\db\Migration;

/**
 * Class m200505_020055_create_map_selfordercampaignbranchdetail
 */
class m200505_020055_create_map_selfordercampaignbranchdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MapSelfOrderCampaignBranchDetail::tableName(), true) === null) {
            $this->createTable(MapSelfOrderCampaignBranchDetail::tableName(),
                [
                    'selfOrderCampaignID' => $this->integer()->notNull(),
                    'branchID' => $this->integer()->notNull(),
                    'detailID' => $this->integer()->notNull(),
                    'usedQty' => $this->decimal(20,4)->notNull()
            ]);
            $this->addPrimaryKey('PRIMARYKEY', MapSelfOrderCampaignBranchDetail::tableName(),
                ['selfOrderCampaignID', 'branchID', 'detailID']);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(MapSelfOrderCampaignBranchDetail::tableName(), true) !== null) {
            $this->dropTable(MapSelfOrderCampaignBranchDetail::tableName());
        }
    }
}
