<?php

use app\models\SalesMenuVat;
use yii\db\Migration;

/**
 * Class m250218_022937_create_table_tr_salesmenuvat
 */
class m250218_022937_create_table_tr_salesmenuvat extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesMenuVat::tableName(), true) === null) {
            $this->createTable(SalesMenuVat::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'salesMenuID' => $this->integer(11)->notNull(),
                    'localID' => $this->integer(50),
                    'salesNum' => $this->string(50)->notNull(),
                    'dpp' => $this->string(10)->notNull(),
                    'dppValue' => $this->decimal(20, 4)->notNull()
                ]
            );

            $this->createIndex(
                'salesNum_INDEX',
                SalesMenuVat::tableName(),
                'salesNum'
            );
            
            $this->createIndex(
                'salesMenuID_INDEX',
                SalesMenuVat::tableName(),
                'salesMenuID'
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesMenuVat::tableName(), true) !== null) {
            $this->dropTable(SalesMenuVat::tableName());
        }
    }
}
