<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200217_022501_alter_column_voucherdiscounttotal_tr_saleshead
 */
class m200217_022501_alter_column_voucherdiscounttotal_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('voucherDiscountTotal') === null) {
            $this->addColumn(SalesHead::tableName(), 'voucherDiscountTotal',
                $this->decimal(20, 4)->after('promotionDiscount'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('voucherDiscountTotal') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'voucherDiscountTotal');
        }
    }
}
