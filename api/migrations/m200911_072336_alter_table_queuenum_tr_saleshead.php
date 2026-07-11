<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200911_072336_alter_table_queuenum_tr_saleshead
 */
class m200911_072336_alter_table_queuenum_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('queueNum') !== null) {
            $this->alterColumn(
                SalesHead::tableName(),
                'queueNum',
                $this->string(10)
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('queueNum') !== null) {
            $this->alterColumn(
                SalesHead::tableName(),
                'queueNum',
                $this->integer()
            );
        }
    }
}
