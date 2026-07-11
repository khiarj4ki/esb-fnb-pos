<?php

use app\models\EsoProcessQueue;
use yii\db\Migration;

/**
 * Class m241031_074001_alter_tbl_tr_esoprocessqueue
 */
class m241031_074001_alter_tbl_tr_esoprocessqueue extends Migration
{

    public function up()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('status') === null) {
            $this->addColumn(EsoProcessQueue::tableName(), 'status',
                $this->string(10)->notNull()->defaultValue(EsoProcessQueue::PENDING)->after('orderID'));
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(EsoProcessQueue::tableName(), true)->getColumn('status') !== null) {
            $this->dropColumn(EsoProcessQueue::tableName(), 'status');
        }
    }

}
