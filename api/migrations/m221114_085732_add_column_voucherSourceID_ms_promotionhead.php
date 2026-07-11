<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m221114_085732_add_column_voucherSourceID_ms_promotionhead
 */
class m221114_085732_add_column_voucherSourceID_ms_promotionhead extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('voucherSourceID') === null) {
            $this->addColumn(PromotionHead::tableName(), 'voucherSourceID', 
                $this->tinyInteger(1)->notNull()->defaultValue(0)->after('promotionTypeID'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('voucherSourceID') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'voucherSourceID');
        }
    }
}
