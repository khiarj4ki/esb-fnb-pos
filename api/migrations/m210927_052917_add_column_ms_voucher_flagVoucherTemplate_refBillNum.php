<?php

use app\models\Voucher;
use yii\db\Migration;

/**
 * Class m210927_052917_add_column_ms_voucher_flagVoucherTemplate_refBillNum
 */
class m210927_052917_add_column_ms_voucher_flagVoucherTemplate_refBillNum extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(Voucher::tableName(), true)->getColumn('flagVoucherTemplate') === null) {
            $this->addColumn(Voucher::tableName(), 'flagVoucherTemplate',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->after('syncDate')->null()->defaultValue('0')
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(Voucher::tableName(), true)->getColumn('flagVoucherTemplate') !== null) {
            $this->dropColumn(Voucher::tableName(), 'flagVoucherTemplate');
        }
    }
}
