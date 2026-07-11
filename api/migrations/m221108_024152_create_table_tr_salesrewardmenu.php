<?php

use app\models\SalesRewardMenu;
use yii\db\Migration;

/**
 * Class m221108_024152_create_table_tr_salesrewardmenu
 */
class m221108_024152_create_table_tr_salesrewardmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesRewardMenu::tableName(), true) === null) {
            $this->createTable(SalesRewardMenu::tableName(), [
                'ID' => $this->integer()->append('PRIMARY KEY'),
                'localID' => $this->integer(),
                'salesNum' => $this->string(50)->notNull(),
                'rewardType' => $this->string(50)->notNull()
            ]);

            $this->createIndex('idx_rewardmenu_salesNum', SalesRewardMenu::tableName(), 'salesNum');
        }
    }

    public function down()
    {
        try {
            $this->dropIndex('idx_rewardmenu_salesNum', SalesRewardMenu::tableName());
        } catch (Exception $ex) {
            echo 'Index idx_rewardmenu_salesNum does not exist.\n';
        }

        if ($this->db->getTableSchema(SalesRewardMenu::tableName(), true) !== null) {
            $this->dropTable(SalesRewardMenu::tableName());
        }
    }
}
