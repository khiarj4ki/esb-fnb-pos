<?php

use app\models\QueueSelfOrder;
use yii\db\Migration;

/**
 * Class m221102_115109_add_column_type_tr_esoqueue
 */
class m221102_115109_add_column_type_tr_esoqueue extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true)->getColumn('type') == null) {
            $this->addColumn(QueueSelfOrder::tableName(),
                'type',
                $this->string(10)->after('orderID')->notNull()->defaultValue('FINISH')
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(QueueSelfOrder::tableName(), true)->getColumn('type') !== null) {
            $this->dropColumn(QueueSelfOrder::tableName(), 'type');
        }
    }
}
