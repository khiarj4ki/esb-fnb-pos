<?php

use app\models\VoucherTemplate;
use yii\db\Migration;

/**
 * Class m201111_030034_create_table_ms_vouchertemplate
 */
class m201111_030034_create_table_ms_vouchertemplate extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(VoucherTemplate::tableName(), true) === null) {
            $this->createTable(VoucherTemplate::tableName(),
                [
                    'voucherTemplateID' => $this->primaryKey(),
                    'voucherTemplateName' => $this->string(50)->notNull(),
                    'voucherLength' => $this->integer()->notNull(),
                    'startDate' => $this->dateTime()->notNull(),
                    'endDate' => $this->dateTime()->notNull(),
                    'voucherTypeID' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull(),
                    'voucherUseTypeID' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull(),
                    'minSalesPrice' => $this->decimal(20, 4)->notNull(),
                    'minSalesUsagePrice' => $this->decimal(20, 4)->notNull(),
                    'maxVoucherAmount' => $this->decimal(20, 4)->notNull(),
                    'voucherAmount' => $this->decimal(20, 4)->notNull(),
                    'voucherPercentage' => $this->decimal(20, 4)->notNull(),
                    'additionalInfo' => $this->string(200)->notNull(),
                    'flagActive' => $this->getDb()->getSchema()->createColumnSchemaBuilder('tinyint(1)')->notNull()
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(VoucherTemplate::tableName(), true) !== null) {
            $this->dropTable(VoucherTemplate::tableName());
        }
    }
}
