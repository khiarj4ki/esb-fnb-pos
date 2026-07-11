<?php

use app\models\SalesVoucherUsage;
use yii\db\Migration;
use yii\db\mysql\Schema;

/**
 * Class m200217_022550_create_table_tr_salesvoucherusage
 */
class m200217_022550_create_table_tr_salesvoucherusage extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesVoucherUsage::tableName(), true) === null) {
            $this->createTable(SalesVoucherUsage::tableName(),
                [
                'ID' => Schema::TYPE_PK.' NOT NULL AUTO_INCREMENT',
                'localID' => $this->integer()->defaultValue(NULL),
                'salesNum' => $this->string(50)->notNull(),
                'paymentMethodID' => $this->integer()->notNull(),
                'voucherCode' => $this->string(50)->notNull(),
                'notes' => $this->string(100),
                'coaNo' => $this->string(20)->notNull(),
                'voucherAmount' => $this->decimal(20, 4)->notNull(),
                'fullVoucherAmount' => $this->decimal(20, 4)->notNull(),
                'syncDate' => $this->dateTime(),
            ]);
            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesVoucherUsage::tableName(), true) !== null) {
            $this->dropTable(SalesVoucherUsage::tableName());
        }
    }
}
