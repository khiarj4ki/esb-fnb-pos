<?php

use app\models\PromotionReward;
use yii\db\Migration;

/**
 * Class m231204_024354_create_ms_promotionreward
 */
class m231204_024354_create_ms_promotionreward extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionReward::tableName(), true) === null) {
            $this->createTable(
                PromotionReward::tableName(),
                [
                    'ID' => $this->integer(11)->notNull()->append('AUTO_INCREMENT PRIMARY KEY'),
                    'promotionID' => $this->integer(11)->notNull(),
                    'menuID' => $this->integer(11)->notNull(),
                    'rewardQty' => $this->decimal(20, 4)->notNull(),
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionReward::tableName(), true) !== null) {
            $this->dropTable(PromotionReward::tableName());
        }
    }
}
