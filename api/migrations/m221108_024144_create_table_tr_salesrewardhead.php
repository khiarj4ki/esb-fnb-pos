<?php

use app\models\SalesRewardHead;
use yii\db\Migration;

/**
 * Class m221108_024144_create_table_tr_salesrewardhead
 */
class m221108_024144_create_table_tr_salesrewardhead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesRewardHead::tableName(), true) === null) {
            $this->createTable(SalesRewardHead::tableName(), [
                'salesNum' => $this->string(50)->notNull()->append('PRIMARY KEY'),
                'rewardType' => $this->string(50)->notNull()
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesRewardHead::tableName(), true) !== null) {
            $this->dropTable(SalesRewardHead::tableName());
        }
    }
}
