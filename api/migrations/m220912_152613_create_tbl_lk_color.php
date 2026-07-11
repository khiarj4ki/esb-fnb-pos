<?php

use app\models\LkColor;
use yii\db\Migration;

/**
 * Class m220912_152613_create_tbl_lk_color
 */
class m220912_152613_create_tbl_lk_color extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(LkColor::tableName(), true) === null) {
            $this->createTable(LkColor::tableName(), [
                'colorID' => $this->integer()->notNull()->append('PRIMARY KEY'),
                'colorCode' => $this->string(7)->notNull(),
                'colorName' => $this->string(20)->notNull(),
            ]);
        }     
    }

    public function down()
    {
        if ($this->db->getTableSchema(LkColor::tableName(), true) !== null) {
            $this->dropTable(LkColor::tableName());
        }
    }
}
