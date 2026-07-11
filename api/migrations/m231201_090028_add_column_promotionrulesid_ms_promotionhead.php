<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m231201_090028_add_column_promotionrulesid_ms_promotionhead
 */
class m231201_090028_add_column_promotionrulesid_ms_promotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionRulesID') === null) {
            $this->addColumn(
                PromotionHead::tableName(),
                'promotionRulesID',
                $this->getDb()->getSchema()
                    ->createColumnSchemaBuilder('integer(11)')->defaultValue(null)->after('discount')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionRulesID') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'promotionRulesID');
        }
    }
}
