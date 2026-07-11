<?php

use app\models\SalesMenuExtra;
use yii\db\Migration;

/**
 * Class m220308_061715_alter_tr_salesmenuextra_otherVat_otherVatValue
 */
class m220308_061715_alter_tr_salesmenuextra_otherVat_otherVatValue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherVat') === null) {
            $this->addColumn(SalesMenuExtra::tableName(), 'otherVat',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20,4)')->defaultValue('0')->after('vatValue'));
        }

        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherVatValue') === null) {
            $this->addColumn(SalesMenuExtra::tableName(), 'otherVatValue',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20,4)')->defaultValue('0')->after('otherVat'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherVat') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(),
                'otherVat');
        }

        if ($this->db->getTableSchema(SalesMenuExtra::tableName(), true)->getColumn('otherVatValue') !== null) {
            $this->dropColumn(SalesMenuExtra::tableName(),
                'otherVatValue');
        }
    }
}
