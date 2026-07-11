<?php
use app\models\Branch;
use yii\db\Migration;

/**
 * Class m191112_055954_add_company_code_ms_branch
 */
class m191112_055954_add_company_code_ms_branch extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('companyCode') === null) {
            $this->addColumn(Branch::tableName(), 'companyCode',
                $this->string(5)->after('branchID'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('companyCode') !== null) {
            $this->dropColumn(Branch::tableName(), 'companyCode');
        }
    }

}
