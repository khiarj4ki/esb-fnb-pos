<?php

use app\models\TrCustomerTransaction;
use yii\db\Migration;

/**
 * Class m210719_024218_create_tr_customertransaction
 */
class m210719_024218_create_tr_customertransaction extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(TrCustomerTransaction::tableName(), true) === null) {
            $this->createTable(TrCustomerTransaction::tableName(),
                [
                'salesNum' => $this->string(20)->notNull()->append('PRIMARY KEY'),
                'fullName' => $this->string(100)->null(),
                'email' => $this->string(100)->null(),
                'phoneNumber' => $this->string(100)->null(),
            ]);
        }

    }

    public function down()
    {
        if ($this->db->getTableSchema(TrCustomerTransaction::tableName(), true) !== null) {
            $this->dropTable(TrCustomerTransaction::tableName());
        }
    }
}
