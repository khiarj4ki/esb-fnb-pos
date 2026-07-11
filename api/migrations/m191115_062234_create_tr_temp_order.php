<?php

use app\models\TempOrder;
use yii\db\Migration;

/**
 * Class m191115_062234_create_tr_kiosk_order
 */
class m191115_062234_create_tr_temp_order extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(TempOrder::tableName(),
                true) === null) {

            $this->createTable(TempOrder::tableName(),
                [
                'orderID' => $this->string(13)->notNull(),
                'createdDate' => $this->dateTime()->notNull(),
                'orderData' => $this->text()->notNull()
            ]);

            $this->addPrimaryKey('PRIMARYKEY', TempOrder::tableName(),
                ['orderID']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(TempOrder::tableName(),
                true) !== null) {
            $this->dropTable(TempOrder::tableName());
        }
    }

}
