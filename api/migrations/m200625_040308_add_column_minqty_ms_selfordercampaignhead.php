<?php

use app\models\MsSelfOrderCampaignHead;
use yii\db\Migration;

/**
 * Class m200625_040308_add_column_minqty_ms_selfordercampaignhead
 */
class m200625_040308_add_column_minqty_ms_selfordercampaignhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('maxUsageQty') === null) {
            $this->addColumn(MsSelfOrderCampaignHead::tableName(), 'minQty',
                $this->decimal(20,4)->after('postAmountMsg'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignHead::tableName(), true)->getColumn('minQty') !== null) {
            $this->dropColumn(MsSelfOrderCampaignHead::tableName(), 'minQty');
        }
    }
}
