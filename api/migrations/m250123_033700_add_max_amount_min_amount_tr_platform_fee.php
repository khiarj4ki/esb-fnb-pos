<?php

use app\models\SalesPlatformFee;
use yii\db\Migration;

/**
 * Class m250123_033700_add_max_amount_min_amount_tr_platform_fee
 */
class m250123_033700_add_max_amount_min_amount_tr_platform_fee extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true)->getColumn('maxAmount') === null) {
            $this->addColumn(SalesPlatformFee::tableName(), 'maxAmount',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')->defaultValue(0)->after('amount'));
        }

        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true)->getColumn('minAmount') === null) {
            $this->addColumn(SalesPlatformFee::tableName(), 'minAmount',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')->defaultValue(0)->after('amount'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true)->getColumn('maxAmount') !== null) {
            $this->dropColumn(SalesPlatformFee::tableName(), 'maxAmount');
        }

        if ($this->db->getTableSchema(SalesPlatformFee::tableName(), true)->getColumn('minAmount') !== null) {
            $this->dropColumn(SalesPlatformFee::tableName(), 'minAmount');
        }
    }
}
