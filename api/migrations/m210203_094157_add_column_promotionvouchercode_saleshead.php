<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m210203_094157_add_column_promotionvouchercode_saleshead
 */
class m210203_094157_add_column_promotionvouchercode_saleshead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('promotionVoucherCode') === null) {
            $this->addColumn(SalesHead::tableName(), 'promotionVoucherCode',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('varchar(50)')->null()->after('promotionID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('promotionVoucherCode') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'promotionVoucherCode');
        }
    }
}
