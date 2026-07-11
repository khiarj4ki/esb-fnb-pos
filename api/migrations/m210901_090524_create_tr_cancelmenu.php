<?php

use app\models\CancelMenu;
use yii\db\Migration;

/**
 * Class m210901_090524_create_tr_cancelmenu
 */
class m210901_090524_create_tr_cancelmenu extends Migration
{
   /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(CancelMenu::tableName(), true) === null) {
            $this->createTable(CancelMenu::tableName(),
            [
                'ID' => $this->primaryKey(),
                'localID' => $this->integer(11)->null(),
                'salesNum' => $this->string(50)->null(),
                'branchID' => $this->integer(11)->null(),
                'menuRefID' => $this->string(50)->notNull(), 
                'menuGroupID' => $this->integer(11)->notNull(),
                'menuID' => $this->integer(11)->notNull(),
                'menuExtraID' => $this->integer(11)->notNull(),
                'customMenuName' => $this->string(100)->null(),
                'qty' => $this->decimal(20,4)->notNull(),
                'originalPrice' => $this->decimal(20,4)->notNull(),
                'price' => $this->decimal(20,4)->notNull(),
                'inclusivePrice' => $this->decimal(20,4)->defaultValue('0.000'),
                'createdBy' => $this->string(100)->null(),
                'createdDate' => $this->dateTime()->null(),
                'syncDate' => $this->dateTime()->null()
            ]);
        }

    }

    public function down()
    {
        if ($this->db->getTableSchema(CancelMenu::tableName(), true) !== null) {
            $this->dropTable(CancelMenu::tableName());
        }
    }

}
