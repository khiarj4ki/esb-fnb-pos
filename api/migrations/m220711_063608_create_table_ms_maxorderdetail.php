<?php

use app\models\MaxOrderDetail;
use yii\db\Migration;

/**
 * Class m220711_063608_create_table_ms_maxorderdetail
 */
class m220711_063608_create_table_ms_maxorderdetail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(MaxOrderDetail::tableName(), true) === null) {
            $this->createTable(MaxOrderDetail::tableName(), [
                'maxOrderDetailID' => $this->primaryKey(),
                'maxOrderID' => $this->integer()->notNull(),
                'menuCategoryDetailID' => $this->integer()->notNull(),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(MaxOrderDetail::tableName(), true) !== null) {
            $this->dropTable(MaxOrderDetail::tableName());
        }
    }
}
