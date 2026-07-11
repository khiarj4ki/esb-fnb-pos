<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m200921_073740_add_column_external_membership_type_id_tr_saleshead
 */
class m200921_073740_add_column_external_membership_type_id_tr_saleshead extends Migration
{
    /**
     * @inheritdoc
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMembershipTypeID') === null) {
            $this->addColumn(SalesHead::tableName(), 'externalMembershipTypeID',
                $this->getDb()->getSchema()->createColumnSchemaBuilder('string(20)')->defaultValue(null)->after('transactionModeID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('externalMembershipTypeID') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'externalMembershipTypeID');
        }
    }
}
