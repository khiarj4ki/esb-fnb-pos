<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m200318_044000_add_column_sales_type_tr_salesmenu
 */
class m200318_044000_add_column_sales_type_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('salesType') === null) {
            $this->addColumn(SalesMenu::tableName(), 'salesType',
                $this->string(50)->after('cancelNotes'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('salesType') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'salesType');
        }
    }
}
