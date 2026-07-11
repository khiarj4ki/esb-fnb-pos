<?php

use yii\db\Migration;
use app\models\PaymentMethod;

/**
 * Class m210309_034557_add_column_voucherCategoryID_ms_paymentmethod
 */
class m210309_034557_add_column_voucherCategoryID_ms_paymentmethod extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherCategoryID') === null) {
            $this->addColumn(PaymentMethod::tableName(), 'voucherCategoryID',
                $this->integer()->after('voucherSourceID'));
        }

        $conditionOtherVoucher = ['AND',
                ['paymentMethodTypeID' => 5],
                ['IS', 'voucherCategoryID', NULL],
            ];
        PaymentMethod::updateAll(['voucherCategoryID' => 1], $conditionOtherVoucher);

        $conditionInternalVoucher = ['AND',
            ['paymentMethodTypeID' => 4],
            ['IS', 'voucherCategoryID', NULL],
        ];
        PaymentMethod::updateAll(['voucherCategoryID' => 2], $conditionInternalVoucher);
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(PaymentMethod::tableName(), true)->getColumn('voucherCategoryID') !== null) {
            $this->dropColumn(PaymentMethod::tableName(), 'voucherCategoryID');
        }
    }
}
