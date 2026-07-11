<?php

use app\models\SalesHeadVat;
use yii\db\Migration;

/**
 * Class m250218_022932_create_table_tr_salesheadvat
 */
class m250218_022932_create_table_tr_salesheadvat extends Migration
{
    public function up()
    {
        if ($this->db->getTableSchema(SalesHeadVat::tableName(), true) === null) {
            $this->createTable(SalesHeadVat::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'salesNum' => $this->string(50)->notNull(),
                    'dppValue' => $this->decimal(20, 4)->notNull()
                ]
            );

            $this->createIndex(
                'salesNum_INDEX',
                SalesHeadVat::tableName(),
                'salesNum'
            );
        }
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesHeadVat::tableName(), true) !== null) {
            $this->dropTable(SalesHeadVat::tableName());
        }
    }
}
