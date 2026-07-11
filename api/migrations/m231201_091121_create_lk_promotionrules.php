<?php

use app\models\PromotionRules;
use yii\db\Migration;

/**
 * Class m231201_091121_create_lk_promotionrules
 */
class m231201_091121_create_lk_promotionrules extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {

        if ($this->db->getTableSchema(PromotionRules::tableName(), true) === null) {
            $this->createTable(
                PromotionRules::tableName(),
                [
                    'promotionRulesID' => $this->integer()->notNull(),
                    'promotionRulesName' => $this->string(50)->notNull(null)
                ]
            );

            $this->addPrimaryKey(
                'PRIMARYKEY',
                PromotionRules::tableName(),
                ['promotionRulesID']
            );

            $this->batchInsert(
                PromotionRules::tableName(),
                ['promotionRulesID', 'promotionRulesName'],
                [
                    [1, 'Best Deal for Merchant'],
                    [2, 'Best Deal for Customer'],
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionRules::tableName(), true) !== null) {
            $this->dropTable(PromotionRules::tableName());
        }
    }
}
