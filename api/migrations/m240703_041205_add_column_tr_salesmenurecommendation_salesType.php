<?php

use app\models\SalesMenuRecommendation;
use yii\db\Migration;

/**
 * Class m240703_041205_add_column_tr_salesmenurecommendation_salesType
 */
class m240703_041205_add_column_tr_salesmenurecommendation_salesType extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuRecommendation::tableName(), true)->getColumn('salesType') === null) {
            $this->addColumn(SalesMenuRecommendation::tableName(), 'salesType',
                $this->string(50)->null()->after('salesMenuID'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuRecommendation::tableName(), true)->getColumn('salesType') !== null) {
            $this->dropColumn(SalesMenuRecommendation::tableName(), 'salesType');
        }
    }
}
