<?php

use app\models\MsSelfOrderCampaignItem;
use yii\db\Migration;

/**
 * Class m210414_085912_add_promotion_selfordercampaign
 */
class m210414_085912_add_promotion_selfordercampaign extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignItem::tableName(), true)->getColumn('itemPromotionID') === null) {
            $this->addColumn(
                MsSelfOrderCampaignItem::tableName(), 
                'itemPromotionID',
                $this->integer(11)->notNull()->defaultValue(0)->after('itemType'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MsSelfOrderCampaignItem::tableName(), true)->getColumn('itemPromotionID') !== null) {
            $this->dropColumn(MsSelfOrderCampaignItem::tableName(), 'itemPromotionID');
        }
    }
}
