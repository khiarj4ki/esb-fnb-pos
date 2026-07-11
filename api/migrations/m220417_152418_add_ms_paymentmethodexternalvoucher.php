<?php

use app\models\PaymentMethodExternalVoucher;
use yii\db\Migration;

/**
 * Class m220417_152418_add_ms_paymentmethodexternalvoucher
 */
class m220417_152418_add_ms_paymentmethodexternalvoucher extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true) === null) {
            $this->createTable(
                PaymentMethodExternalVoucher::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'paymentMethodID' => $this->integer(11)->notNull(),
                    'prefix' => $this->string(20)->notNull(),
                    'amount' => $this->decimal(20, 4)->notNull()
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        if ($this->db->getTableSchema(PaymentMethodExternalVoucher::tableName(), true) !== null) {
            $this->dropTable(PaymentMethodExternalVoucher::tableName());
        }
    }
}
