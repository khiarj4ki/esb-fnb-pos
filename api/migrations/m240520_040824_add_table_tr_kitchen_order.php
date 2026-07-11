<?php

use app\models\KitchenOrder;
use yii\db\Migration;

/**
 * Class m240520_040824_add_table_tr_kitchen_order
 */
class m240520_040824_add_table_tr_kitchen_order extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(KitchenOrder::tableName(), true) === null) {
            $this->createTable(KitchenOrder::tableName(), [
                'code' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'salesNum' => $this->string(50)->notNull(),
                'data' => $this->text()->notNull(),
                'status' => $this->string(15)->notNull(),
                'createdDate' => $this->dateTime()->notNull(),
                'editedDate' => $this->dateTime(),
            ]);

            $this->createIndex('idx_tr_kitchen_order_salesNum', KitchenOrder::tableName(), 'salesNum');
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(KitchenOrder::tableName(), true) !== null) {
            $this->dropTable(KitchenOrder::tableName());
        }
    }
}
