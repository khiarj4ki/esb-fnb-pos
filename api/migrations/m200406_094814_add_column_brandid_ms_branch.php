<?php

use app\models\Branch;
use yii\db\Migration;

/**
 * Class m200406_094814_add_column_brandid_ms_branch
 */
class m200406_094814_add_column_brandid_ms_branch extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('brandID') === null) {
            $this->addColumn(Branch::tableName(), 'brandID',
                $this->integer(11)->after('posOtherTaxCalculationID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(Branch::tableName(), true)->getColumn('brandID') !== null) {
            $this->dropColumn(Branch::tableName(),
                'brandID');
        }
    }
}
