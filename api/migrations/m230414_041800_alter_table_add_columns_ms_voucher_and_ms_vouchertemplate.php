<?php

use app\models\Voucher;
use app\models\VoucherTemplate;
use yii\db\Migration;

/**
 * Class m230414_041800_alter_table_add_columns_ms_voucher_and_ms_vouchertemplate
 */
class m230414_041800_alter_table_add_columns_ms_voucher_and_ms_vouchertemplate extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(Voucher::tableName(), true)->getColumn('createdFrom') === null) {
            $this->addColumn(
                Voucher::tableName(), 'createdFrom', $this->string(20)->defaultValue(null)->after('flagVoucherTemplate')
            );
        }

        if ($this->db->getTableSchema(VoucherTemplate::tableName(), true)->getColumn('isOnlinePurchaseVoucher') === null) {
            $this->addColumn(
                VoucherTemplate::tableName(), 'isOnlinePurchaseVoucher', $this->tinyInteger(1)->notNull()->defaultValue(0)->after('flagActive')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(Voucher::tableName(), true)->getColumn('createdFrom') !== null) {
            $this->dropColumn(
                Voucher::tableName(), 'createdFrom'
            );
        }

        if ($this->db->getTableSchema(VoucherTemplate::tableName(), true)->getColumn('isOnlinePurchaseVoucher') !== null) {
            $this->dropColumn(
                VoucherTemplate::tableName(), 'isOnlinePurchaseVoucher'
            );
        }
    }
}
