<?php

use app\models\MsSelfOrderCampaignHead;
use yii\db\Migration;

/**
 * Class m200616_043929_alter_column_ms_selfordercampaignhead
 */
class m200616_043929_alter_column_ms_selfordercampaignhead extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountVal') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountVal',
                $this->decimal(20,4)
            );
        }
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountMsg',
                $this->string(100)
            );
        }
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('minAmountVal') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'minAmountVal',
                $this->decimal(20,4)
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

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountVal') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountVal',
                $this->decimal(20,4)->notNull()
            );
        }

        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('preAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'preAmountMsg',
                $this->string(100)->notNull()
            );
        }
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('minAmountVal') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'minAmountVal',
                $this->decimal(20,4)->notNull()
            );
        }
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('postAmountMsg') !== null) {
            $this->alterColumn(
                MsSelfOrderCampaignHead::tableName(),
                'postAmountMsg',
                $this->string(100)->notNull()
            );
        }
    }
}
