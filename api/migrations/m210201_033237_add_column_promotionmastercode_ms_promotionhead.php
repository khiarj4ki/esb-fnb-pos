<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m210201_033237_add_column_promotionmastercode_ms_promotionhead
 */
class m210201_033237_add_column_promotionmastercode_ms_promotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionMasterCode') === null) {
            $this->addColumn(PromotionHead::tableName(), 'promotionMasterCode',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->null()->after('promotionID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('promotionMasterCode') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'promotionMasterCode');
        }
    }
}
