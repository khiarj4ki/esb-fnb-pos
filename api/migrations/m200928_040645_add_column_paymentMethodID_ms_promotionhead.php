<?php

use yii\db\Migration;
use app\models\PromotionHead;

/**
 * Class m200928_040645_add_column_paymentMethodID_ms_promotionhead
 */
class m200928_040645_add_column_paymentMethodID_ms_promotionhead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('paymentMethodID') === null) {
            $this->addColumn(PromotionHead::tableName(), 'paymentMethodID', $this->integer()->after('paymentMethodTypeID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PromotionHead::tableName(), true)->getColumn('paymentMethodID') !== null) {
            $this->dropColumn(PromotionHead::tableName(), 'paymentMethodID');
        }
    }
}
