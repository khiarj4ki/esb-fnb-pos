<?php

use app\models\CustomNumber;
use yii\db\Migration;

/**
 * Class m231215_024924_create_table_tr_customnumber
 */
class m231215_024924_create_table_tr_customnumber extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        if ($this->db->getTableSchema(CustomNumber::tableName(), true) === null) {
            $this->createTable(CustomNumber::tableName(),
                [
                    'salesNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                    'customNum' => $this->string(23)->notNull()
            ]);
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(CustomNumber::tableName(), true) !== null) {
            $this->dropTable(CustomNumber::tableName());
        }
    }
}
