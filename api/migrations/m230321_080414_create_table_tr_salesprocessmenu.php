<?php

use app\models\SalesProcessMenu;
use yii\db\Migration;

/**
 * Class m230321_080414_create_table_tr_salesprocessmenu
 */
class m230321_080414_create_table_tr_salesprocessmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesProcessMenu::tableName(), true) === null) {
            $this->createTable(SalesProcessMenu::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'localID' => $this->integer(50),
                    'salesNum' => $this->string(50)->notNull(),
                    'salesMenuID' => $this->integer(11)->notNull(),
                    'holdTime' => $this->dateTime(),
                    'fireTime' => $this->dateTime()
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesProcessMenu::tableName(), true) !== null) {
            $this->dropTable(SalesProcessMenu::tableName());
        }
    }
}
