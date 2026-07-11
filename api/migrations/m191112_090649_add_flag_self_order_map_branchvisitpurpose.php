<?php
use app\models\MapBranchVisitPurpose;
use yii\db\Migration;

/**
 * Class m191112_090649_add_flag_self_order_map_branchvisitpurpose
 */
class m191112_090649_add_flag_self_order_map_branchvisitpurpose extends Migration {
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('flagSelfOrder') === null) {
            $this->addColumn(MapBranchVisitPurpose::tableName(),
                'flagSelfOrder', $this->tinyInteger(1)->after('menuTemplateID'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(MapBranchVisitPurpose::tableName(), true)->getColumn('flagSelfOrder') !== null) {
            $this->dropColumn(MapBranchVisitPurpose::tableName(),
                'flagSelfOrder');
        }
    }

}
