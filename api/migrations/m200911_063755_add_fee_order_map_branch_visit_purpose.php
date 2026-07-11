<?php

use app\models\MapBranchVisitPurpose;
use yii\db\Migration;

/**
 * Class m200911_063755_add_fee_order_map_branch_visit_purpose
 */
class m200911_063755_add_fee_order_map_branch_visit_purpose extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('orderFee') === null) {
            $this->addColumn(MapBranchVisitPurpose::tableName(), 'orderFee',
                $this->decimal(20, 4)->after('taxValue'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('orderFee') !== null) {
            $this->dropColumn(MapBranchVisitPurpose::tableName(), 'orderFee');
        }
    }
}
