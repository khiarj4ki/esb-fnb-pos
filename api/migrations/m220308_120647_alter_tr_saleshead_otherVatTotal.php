<?php

use app\models\SalesHead;
use yii\db\Migration;

/**
 * Class m220308_120647_alter_tr_saleshead_otherVatTotal
 */
class m220308_120647_alter_tr_saleshead_otherVatTotal extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('otherVatTotal') === null) {
            $this->addColumn(SalesHead::tableName(), 'otherVatTotal',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20,4)')->defaultValue('0')->after('vatTotal'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesHead::tableName(), true)->getColumn('otherVatTotal') !== null) {
            $this->dropColumn(SalesHead::tableName(),
                'otherVatTotal');
        }
    }
}
