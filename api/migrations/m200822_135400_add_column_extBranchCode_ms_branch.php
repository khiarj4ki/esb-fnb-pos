<?php

use app\models\Branch;
use yii\db\Migration;

/**
 * Class m200822_135400_add_column_extBranchCode_ms_branch
 */
class m200822_135400_add_column_extBranchCode_ms_branch extends Migration {

    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('extBranchCode') === null) {
            $this->addColumn(Branch::tableName(), 'extBranchCode',
                    $this->string(20)->after('branchCode'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('extBranchCode') !== null) {
            $this->dropColumn(Branch::tableName(),
                    'extBranchCode');
        }
    }

}
