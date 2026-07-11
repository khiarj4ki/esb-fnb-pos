<?php

use app\models\SalesPaymentStiReader;
use yii\db\Migration;

/**
 * Class m240925_064950_create_tr_salespaymentstireader
 */
class m240925_064950_create_tr_salespaymentstireader extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesPaymentStiReader::tableName(), true) === null) {
            $this->createTable(SalesPaymentStiReader::tableName(), [
                'ID' => $this->primaryKey(),
                'TID' => $this->string(10)->null(),
                'MID' => $this->string(50)->null(),
                'salesNum' => $this->string(20)->null(),
                'remainBalance' => $this->decimal(20, 4)->notNull(),
                'branchID' => $this->integer(11)->null(),
                'createdBy' => $this->string(50)->null(),
                'createdDate' => $this->dateTime()->null()
            ]);

            $this->createIndex(
                'idx_tr_salespaymentstireader',
                SalesPaymentStiReader::tableName(),
                ['ID','MID','TID','branchID']
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if ($this->db->getTableSchema(SalesPaymentStiReader::tableName(), true) !== null) {
            $this->dropTable(SalesPaymentStiReader::tableName());
        }
    }
}
