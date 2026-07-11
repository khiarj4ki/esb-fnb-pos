<?php

use app\models\BranchMenuDetail;
use yii\db\Migration;

/**
 * Class m241230_064502_create_table_ms_branchmenudetail
 */
class m241230_064502_create_table_ms_branchmenudetail extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(BranchMenuDetail::tableName(), true) === null) {
            $this->createTable(BranchMenuDetail::tableName(),
                [
                    'ID' => $this->primaryKey(),
                    'menuID' => $this->integer()->notNull()
            ]);

            $this->createIndex(
                'idx_ms_branchmenudetail',
                BranchMenuDetail::tableName(),
                ['ID','menuID']
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(BranchMenuDetail::tableName(), true) !== null) {
            $this->dropTable(BranchMenuDetail::tableName());
        }
        return false;
    }
}
