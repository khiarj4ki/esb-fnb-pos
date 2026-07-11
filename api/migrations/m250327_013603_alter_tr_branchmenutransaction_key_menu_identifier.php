<?php

use app\models\BranchMenuTransaction;
use yii\db\Migration;

/**
 * Class m250327_013603_alter_tr_branchmenutransaction_key_menu_identifier
 */
class m250327_013603_alter_tr_branchmenutransaction_key_menu_identifier extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(), true)->getColumn('salesMenuID') === null) {
            $this->addColumn(BranchMenuTransaction::tableName(), 'salesMenuID',
                $this->integer(11)->after('syncDate'));
        }
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(), true)->getColumn('category') === null) {
            $this->addColumn(BranchMenuTransaction::tableName(), 'category',
                $this->string(15)->after('salesMenuID'));
        }
        $this->createIndex(
            'idx-branchmenu-transaction',
            BranchMenuTransaction::tableName(),
            ['salesNum', 'salesMenuID', 'category'],
            true
        );
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(), true)->getColumn('salesMenuID') !== null) {
            $this->dropColumn(BranchMenuTransaction::tableName(),
                'salesMenuID');
        }
        if ($this->db->getTableSchema(BranchMenuTransaction::tableName(), true)->getColumn('category') !== null) {
            $this->dropColumn(BranchMenuTransaction::tableName(),
                'category');
        }
    }
}
