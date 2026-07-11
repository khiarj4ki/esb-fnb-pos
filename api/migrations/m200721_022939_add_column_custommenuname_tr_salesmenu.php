<?php

use app\models\SalesMenu;
use yii\db\Migration;

/**
 * Class m200721_022939_add_column_custommenuname_tr_salesmenu
 */
class m200721_022939_add_column_custommenuname_tr_salesmenu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('customMenuName') === null) {
            $this->addColumn(SalesMenu::tableName(), 'customMenuName',
                $this->string(100)->defaultValue(NULL)->after('menuID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(SalesMenu::tableName(), true)->getColumn('customMenuName') !== null) {
            $this->dropColumn(SalesMenu::tableName(),
                'customMenuName');
        }
    }
}
