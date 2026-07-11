<?php

use app\models\MsSelfOrderCampaignHead;
use yii\db\Migration;

/**
 * Class m200902_082751_update_preamountmessage_ms_selfordercampaignhead
 */
class m200902_082751_update_preamountmessage_ms_selfordercampaignhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountMsg',
                $this->string(200)
            );
        }
        
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('postAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'postAmountMsg',
                $this->string(200)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountMsg',
                $this->string(100)
            );
        }
        
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('postAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'postAmountMsg',
                $this->string(100)
            );
        }
    }
}
