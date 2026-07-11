<?php

use app\models\MsSelfOrderCampaignHead;
use yii\db\Migration;

/**
 * Class m201210_035513_add_column_max_usage_self_order_campaign
 */
class m201210_035513_add_column_max_usage_self_order_campaign extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('maxUsage') === null) {
            $this->addColumn(MsSelfOrderCampaignHead::tableName(), 'maxUsage',
                $this->integer()->after('menuID'));
        }        
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('maxUsage') !== null) {
            $this->dropColumn(MsSelfOrderCampaignHead::tableName(), 'maxUsage');
        }
    }
}
