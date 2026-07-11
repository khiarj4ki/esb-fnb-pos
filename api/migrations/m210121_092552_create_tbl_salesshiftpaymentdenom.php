<?php

use app\models\SalesShiftPaymentDenom;
use yii\db\Migration;

/**
 * Class m210121_092552_create_tbl_salesshiftpaymentdenom
 */
class m210121_092552_create_tbl_salesshiftpaymentdenom extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesShiftPaymentDenom::tableName(),
                true) === null) {
            $this->createTable(SalesShiftPaymentDenom::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(11)->null(),
                    'salesShiftPaymentHeadID' => $this->integer(11)->notNull(),
                    'denomAmount' => $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')->notNull()->defaultValue('0'),
                    'denomQty' => $this->getDb()->getSchema()->createColumnSchemaBuilder('int(11)')->notNull()->defaultValue('0'),
                    'denomTotal' => $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20, 4)')->notNull()->defaultValue('0')
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesShiftPaymentDenom::tableName(),
                true) !== null) {
            $this->dropTable(SalesShiftPaymentDenom::tableName());
        }
    }
}
