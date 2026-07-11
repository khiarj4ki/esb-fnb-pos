<?php

use app\models\MenuExtra;
use yii\db\Migration;

/**
 * Class m200514_131127_add_column_menurefid_menuextra
 */
class m200514_131127_add_column_menurefid_menuextra extends Migration
{
    public function up() {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('menuRefID') === null) {
            $this->addColumn(MenuExtra::tableName(), 
                'menuRefID',
                $this->integer()->after('menuID'));
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        if ($this->db->getTableSchema(MenuExtra::tableName(), true)->getColumn('menuRefID') !== null) {
            $this->dropColumn(MenuExtra::tableName(), 'menuRefID');
        }
    }
}
