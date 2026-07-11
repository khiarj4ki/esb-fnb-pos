<?php

use app\models\EsoPickupOrder;
use yii\db\Migration;

/**
 * Class m230911_021059_create_tr_esopickuporder
 */
class m230911_021059_create_tr_esopickuporder extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(EsoPickupOrder::tableName(), true) === null) {
            $this->createTable(EsoPickupOrder::tableName(), [
                'salesNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                'orderID' => $this->string(20)->notNull(),
            ]);
        }

        // create indexing for table eso pickup order on salesnum
        $checkIndexEsoPickupOrder = "SHOW INDEX FROM " . EsoPickupOrder::tableName() . " WHERE Key_name = 'idx_tr_esopickuporder_salesNum'";
        if (!$this->db->createCommand($checkIndexEsoPickupOrder)->queryScalar())
        {
            $this->createIndex('idx_tr_esopickuporder_salesNum', EsoPickupOrder::tableName(), 'salesNum');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(EsoPickupOrder::tableName(), true) !== null) {
            $this->dropTable(EsoPickupOrder::tableName());
        }
    }
}