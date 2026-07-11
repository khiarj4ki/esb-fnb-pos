<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m220308_051150_alter_tr_salesmenu_otherVat_otherVatValue
 */
class m220308_051150_alter_tr_salesmenu_otherVat_otherVatValue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherVat') === null) {
            $this->addColumn(SalesMenu::tableName(), 'otherVat',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20,4)')->defaultValue('0')->after('vatValue'));
        }

        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherVatValue') === null) {
            $this->addColumn(SalesMenu::tableName(), 'otherVatValue',
            $this->getDb()->getSchema()->createColumnSchemaBuilder('decimal(20,4)')->defaultValue('0')->after('otherVat'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherVat') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'otherVat');
        }

        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('otherVatValue') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'otherVatValue');
        }
    }
}
