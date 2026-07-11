<?php

use app\components\AppHelper;
use app\models\BranchMenu;
use yii\db\Migration;

/**
 * Class m200913_023345_add_index_ms_branchmenu_menuID
 */
class m200913_023345_add_index_ms_branchmenu_menuID extends Migration {

    /**
     * @inheritdoc 
     */
    public function up() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexMenuID = "SELECT * " .
                "FROM INFORMATION_SCHEMA.STATISTICS " .
                "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
                BranchMenu::tableName() . "' " .
                "AND INDEX_NAME = 'idx_branchMenu_menuID' ";
        if (!$this->db->createCommand($checkIndexMenuID)->queryScalar()) {
            $this->createIndex(
                    'idx_branchMenu_menuID',
                    BranchMenu::tableName(),
                    'menuID'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $mainDbName = AppHelper::getDsnAttribute('dbname', $this->db->dsn);

        $checkIndexMenuID = "SELECT * " .
                "FROM INFORMATION_SCHEMA.STATISTICS " .
                "WHERE TABLE_SCHEMA = '$mainDbName' AND TABLE_NAME = '" .
                BranchMenu::tableName() . "' " .
                "AND INDEX_NAME = 'idx_branchMenu_menuID' ";
        if ($this->db->createCommand($checkIndexMenuID)->queryScalar()) {
            $this->dropIndex(
                    'idx_branchMenu_menuID',
                    BranchMenu::tableName()
            );
        }
    }

}
