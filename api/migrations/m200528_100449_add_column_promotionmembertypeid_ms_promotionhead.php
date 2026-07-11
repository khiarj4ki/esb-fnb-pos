<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m200528_100449_add_column_promotionmembertypeid_ms_promotionhead
 */
class m200528_100449_add_column_promotionmembertypeid_ms_promotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionMemberTypeID') === null) {
            $this->addColumn(PromotionHead::tableName(),
                'promotionMemberTypeID',
                $this->integer(11)->after('flagMenuExtra'));

            PromotionHead::updateAll(['promotionMemberTypeID' => 1],
                ['flagMemberOnly' => 1]);

            PromotionHead::updateAll(['promotionMemberTypeID' => 0],
                ['flagMemberOnly' => 0]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionMemberTypeID') !== null) {
            $this->dropColumn(PromotionHead::tableName(),
                'promotionMemberTypeID');
        }
    }
}
