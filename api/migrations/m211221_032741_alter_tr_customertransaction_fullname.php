<?php

use app\models\CustomerTransaction;
use yii\db\Migration;

/**
 * Class m211221_032741_alter_tr_customertransaction_fullname
 */
class m211221_032741_alter_tr_customertransaction_fullname extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(CustomerTransaction::tableName(), true)->getColumn('fullName') !== null) {
            $this->alterColumn(CustomerTransaction::tableName(), 'fullName', $this->string(100)->append('CHARACTER SET utf8 COLLATE utf8_unicode_ci'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(CustomerTransaction::tableName(), true)->getColumn('fullName') !== null) {
            $this->alterColumn(CustomerTransaction::tableName(), 'fullName', $this->string(100));
        }
    }
}
