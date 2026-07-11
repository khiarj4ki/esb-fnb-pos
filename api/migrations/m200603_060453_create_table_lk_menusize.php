<?php

use app\models\LkMenuSize;
use yii\db\Migration;

/**
 * Class m200603_060453_create_table_lk_menusize
 */
class m200603_060453_create_table_lk_menusize extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(LkMenuSize::tableName(), true) === null) {
            $this->createTable(LkMenuSize::tableName(),
                [
                    'menuSizeID' => $this->integer()->notNull(),
                    'menuSizeName' => $this->string(50)->notNull(),
                    'width' => $this->decimal(2,1)->notNull(),
                    'height' => $this->decimal(2,1)->notNull(),
            ]);
            
            $this->addPrimaryKey('PRIMARY KEY', 
                LkMenuSize::tableName(), 
                ['menuSizeID']);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(LkMenuSize::tableName(), true) !== null) {
            $this->dropTable(LkMenuSize::tableName());
        }
    }
}
