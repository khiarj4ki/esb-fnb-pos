<?php

use app\models\PromotionHead;
use yii\db\Migration;

/**
 * Class m241125_050951_add_flag_required_bin_ms_promotionhead
 */
class m241125_050951_add_flag_required_bin_ms_promotionhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagBinRequired') === null) {
            $this->addColumn(PromotionHead::tableName(), 'flagBinRequired',
                $this->tinyInteger(1)->defaultValue(0)->after('flagAuthorization'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('flagBinRequired') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'flagBinRequired');
        }
    }
}
