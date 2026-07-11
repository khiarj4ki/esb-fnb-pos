<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m201127_094448_add_column_visitortypeid_tr_saleshead
 */
class m201127_094448_add_column_visitortypeid_tr_saleshead extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('visitorTypeID') === null) {
            $this->addColumn(SalesHead::tableName(), 'visitorTypeID',
                $this->integer()->after('visitPurposeID')->defaultValue(NULL));
        }
        
    }

    public function down()
    {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('visitorTypeID') !== null) {
            $this->dropColumn(SalesHead::tableName(), 'visitorTypeID');
        }
    }
}
