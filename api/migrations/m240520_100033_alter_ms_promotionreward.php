<?php

use app\models\PromotionReward;
use yii\db\Migration;

/**
 * Class m240520_100033_alter_ms_promotionreward
 */
class m240520_100033_alter_ms_promotionreward extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PromotionReward::tableName(), true)->getColumn('menuCategoryDetailID') === null) {
            $this->addColumn(
                PromotionReward::tableName(),
                'menuCategoryDetailID',
                $this->integer(11)->defaultValue(null)->after('promotionID')
            );
        }

        if ($this->db->getTableSchema(PromotionReward::tableName(), true)->getColumn('menuCategoryID') === null) {
            $this->addColumn(
                PromotionReward::tableName(),
                'menuCategoryID',
                $this->integer(11)->defaultValue(null)->after('promotionID')
            );
        }

        if ($this->db->getTableSchema(PromotionReward::tableName(), true)->getColumn('menuID') !== null) {
            $this->alterColumn(PromotionReward::tableName(), 'menuID', $this->integer(11)->null()->defaultValue(null));
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionReward::tableName(), true)->getColumn('menuCategoryDetailID') !== null) {
            $this->dropColumn(
                PromotionReward::tableName(),
                'menuCategoryDetailID'
            );
        }

        if ($this->db->getTableSchema(PromotionReward::tableName(), true)->getColumn('menuCategoryID') !== null) {
            $this->dropColumn(
                PromotionReward::tableName(),
                'menuCategoryID'
            );
        }
    }
}
