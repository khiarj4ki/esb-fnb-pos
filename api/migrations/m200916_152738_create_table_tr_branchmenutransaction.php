<?php

use yii\db\Migration;
use app\models\BranchMenuTransaction;

/**
 * Class m200916_152738_create_table_tr_branchmenutransaction
 */
class m200916_152738_create_table_tr_branchmenutransaction extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(),
                true) === null) {
            $this->createTable(BranchMenuTransaction::tableName(),
                [
                'ID' => $this->primaryKey(),
                'transactionDate' => $this->dateTime()->notNull(),
                'branchID' => $this->integer()->notNull(),
                'salesNum' => $this->string(20)->notNull(),
                'menuID' => $this->integer()->notNull(),
                'qty' => $this->decimal(20, 4),
                'syncDate' => $this->dateTime(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(),
                true) !== null) {
            $this->dropTable(BranchMenuTransaction::tableName());
        }
    }
}
